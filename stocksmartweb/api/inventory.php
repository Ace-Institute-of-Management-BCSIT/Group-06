<?php
/**
 * ============================================================================
 *  StockSmart — Inventory API (api/inventory.php)
 * ============================================================================
 *  GET  api/inventory.php
 *      -> { stats, products[], warehouses[], activity[], expiringSoonCount }
 *      This is the exact shape inventory.html's renderStats/renderInventoryTable/
 *      renderCategoryBreakdown/renderLocationBreakdown/renderActivity already
 *      expect — only the data source changed, not the rendering code.
 *
 *  GET  api/inventory.php?action=product_inventory&id=5
 *      -> per-warehouse breakdown for one product, used to populate the
 *         warehouse dropdown in the Update Stock / Transfer Stock modals.
 *
 *  POST api/inventory.php?action=stock_adjust
 *      body: { productId, warehouseId, type: 'in'|'out'|'set', quantity, note? }
 *      Stock In / Stock Out / manual count correction for one warehouse.
 *
 *  POST api/inventory.php?action=stock_transfer
 *      body: { productId, fromWarehouseId, toWarehouseId, quantity }
 *      Moves stock between two warehouses for the same product.
 *
 *  Role rules: viewing (GET) is open to any logged-in role. Stock actions
 *  (POST) require Admin, Manager, or Staff — matches the `roles` table's own
 *  description of Staff as "Manage inventory counts and stock movements".
 *  Cashier is excluded.
 *
 *  Honesty note on "trend"/"dir": the schema has no historical inventory
 *  snapshot table, so a real week-over-week percentage isn't available yet.
 *  Rather than fabricate a number, every stat card's trend is reported as
 *  0% / 'up' until a snapshot table is added (planned alongside the Reports
 *  module, which needs the same historical data anyway).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/notifications.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  { $h = floor($diff / 3600); return $h . ' hour' . ($h == 1 ? '' : 's') . ' ago'; }
    if ($diff < 172800) return 'Yesterday';
    $d = floor($diff / 86400);
    return $d . ' days ago';
}

/**
 * Maps our activity_logs.activity_type enum onto the 4 icon buckets
 * inventory.html's renderActivity() knows how to draw.
 */
function map_activity_type(string $type): string
{
    switch ($type) {
        case 'add':      return 'add';
        case 'transfer': return 'transfer';
        case 'alert':    return 'alert';
        case 'update':
        case 'delete':
        case 'checkout':
        default:         return 'update';
    }
}

function get_or_create_inventory_row(PDO $pdo, int $productId, int $warehouseId): array
{
    $stmt = $pdo->prepare('SELECT inventory_id, quantity_on_hand, quantity_reserved FROM inventory WHERE product_id = :pid AND warehouse_id = :wid LIMIT 1');
    $stmt->execute([':pid' => $productId, ':wid' => $warehouseId]);
    $row = $stmt->fetch();
    if ($row) {
        return [
            'id'       => (int) $row['inventory_id'],
            'onHand'   => (int) $row['quantity_on_hand'],
            'reserved' => (int) $row['quantity_reserved'],
        ];
    }
    $pdo->prepare('INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand, quantity_reserved) VALUES (:pid, :wid, 0, 0)')
        ->execute([':pid' => $productId, ':wid' => $warehouseId]);
    return ['id' => (int) $pdo->lastInsertId(), 'onHand' => 0, 'reserved' => 0];
}

/* ============================================================================
 *  GET ?action=product_inventory&id=X — per-warehouse breakdown for one product
 * ========================================================================== */
if ($method === 'GET' && $action === 'product_inventory') {
    api_require_permission('inventory.view');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Product id is required.']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT w.warehouse_id, w.warehouse_name, COALESCE(i.quantity_on_hand,0) AS on_hand,
               COALESCE(i.quantity_reserved,0) AS reserved
        FROM warehouses w
        LEFT JOIN inventory i ON i.warehouse_id = w.warehouse_id AND i.product_id = :pid
        WHERE w.is_active = 1
        ORDER BY w.warehouse_name
    ');
    $stmt->execute([':pid' => $id]);

    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'warehouseId'   => (int) $r['warehouse_id'],
            'warehouseName' => $r['warehouse_name'],
            'onHand'        => (int) $r['on_hand'],
            'reserved'      => (int) $r['reserved'],
            'available'     => (int) $r['on_hand'] - (int) $r['reserved'],
        ];
    }

    echo json_encode($out);
    exit;
}

/* ============================================================================
 *  GET (no action) — full inventory page payload
 * ========================================================================== */
if ($method === 'GET') {
    api_require_permission('inventory.view');

    $rows = $pdo->query("
        SELECT p.product_id, p.product_name, p.sku, p.reorder_level, p.price,
               c.category_name,
               COALESCE(SUM(i.quantity_on_hand), 0)   AS in_stock,
               COALESCE(SUM(i.quantity_reserved), 0)  AS reserved,
               GROUP_CONCAT(DISTINCT w.warehouse_name ORDER BY w.warehouse_name SEPARATOR ', ') AS locations
        FROM   products p
        JOIN   categories c ON c.category_id = p.category_id
        LEFT JOIN inventory i  ON i.product_id = p.product_id
        LEFT JOIN warehouses w ON w.warehouse_id = i.warehouse_id
        WHERE  p.status != 'discontinued'
        GROUP BY p.product_id, p.product_name, p.sku, p.reorder_level, p.price, c.category_name
        ORDER BY p.product_name
    ")->fetchAll();

    $products = [];
    $totalValue = 0.0;
    $inStockCount = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;

    foreach ($rows as $r) {
        $onHand    = (int) $r['in_stock'];
        $reserved  = (int) $r['reserved'];
        $available = $onHand - $reserved;
        $reorder   = (int) $r['reorder_level'];

        if ($available <= 0) {
            $outOfStockCount++;
        } elseif ($available <= $reorder) {
            $lowStockCount++;
        } else {
            $inStockCount++;
        }

        $totalValue += $onHand * (float) $r['price'];

        $products[] = [
            'id'           => (int) $r['product_id'],
            'name'         => $r['product_name'],
            'sku'          => $r['sku'],
            'category'     => $r['category_name'],
            'inStock'      => $onHand,
            'reserved'     => $reserved,
            'reorderLevel' => $reorder,
            'location'     => $r['locations'] ?: 'Unassigned',
        ];
    }

    $neutral = ['trend' => 0, 'dir' => 'up']; // see honesty note above

    $stats = [
        'totalItems' => ['value' => count($products)]      + $neutral,
        'inStock'    => ['value' => $inStockCount]          + $neutral,
        'lowStock'   => ['value' => $lowStockCount]         + $neutral,
        'outOfStock' => ['value' => $outOfStockCount]       + $neutral,
        'totalValue' => ['value' => round($totalValue, 2)]  + $neutral,
    ];

    $warehouseRows = $pdo->query("SELECT warehouse_id, warehouse_name FROM warehouses WHERE is_active = 1 ORDER BY warehouse_name")->fetchAll();
    $warehouses = [];
    foreach ($warehouseRows as $w) {
        $warehouses[] = ['id' => (int) $w['warehouse_id'], 'name' => $w['warehouse_name']];
    }

    $expiringSoonCount = (int) $pdo->query("
        SELECT COUNT(*) AS c FROM product_batches
        WHERE quantity > 0 AND DATEDIFF(expiry_date, CURDATE()) BETWEEN 0 AND 7
    ")->fetch()['c'];

    $activityRows = $pdo->query("
        SELECT activity_type, description, created_at
        FROM activity_logs
        WHERE activity_type IN ('add','update','delete','checkout','alert','transfer')
        ORDER BY created_at DESC
        LIMIT 8
    ")->fetchAll();

    $activity = [];
    foreach ($activityRows as $a) {
        $activity[] = [
            'type' => map_activity_type($a['activity_type']),
            'text' => $a['description'],
            'time' => time_ago($a['created_at']),
        ];
    }

    echo json_encode([
        'stats'             => $stats,
        'products'          => $products,
        'warehouses'        => $warehouses,
        'activity'          => $activity,
        'expiringSoonCount' => $expiringSoonCount,
    ]);
    exit;
}

/* ============================================================================
 *  POST ?action=stock_adjust — Stock In / Stock Out / manual count correction
 * ========================================================================== */
if ($method === 'POST' && $action === 'stock_adjust') {
    $user = api_require_permission('inventory.manage');
    api_verify_csrf();

    $body        = api_json_body();
    $productId   = (int) ($body['productId']   ?? 0);
    $warehouseId = (int) ($body['warehouseId']  ?? DEFAULT_WAREHOUSE_ID);
    $type        = (string) ($body['type']      ?? '');
    $quantity    = $body['quantity'] ?? null;
    $note        = trim((string) ($body['note'] ?? ''));

    if ($productId <= 0) {
        http_response_code(422); echo json_encode(['error' => 'Please choose a product.']); exit;
    }
    if (!in_array($type, ['in', 'out', 'set'], true)) {
        http_response_code(422); echo json_encode(['error' => 'Invalid stock action type.']); exit;
    }
    if (!is_numeric($quantity) || (float) $quantity < 0) {
        http_response_code(422); echo json_encode(['error' => 'Quantity must be a non-negative number.']); exit;
    }
    $quantity = (int) $quantity;

    $stmt = $pdo->prepare('SELECT product_name FROM products WHERE product_id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        http_response_code(404); echo json_encode(['error' => 'Product not found']); exit;
    }

    try {
        $pdo->beginTransaction();

        $inv = get_or_create_inventory_row($pdo, $productId, $warehouseId);
        $movementType = null;
        $movementQty  = 0;
        $newOnHand    = $inv['onHand'];

        if ($type === 'in') {
            $newOnHand    = $inv['onHand'] + $quantity;
            $movementType = 'adjustment_in';
            $movementQty  = $quantity;
        } elseif ($type === 'out') {
            $removable = $inv['onHand'] - $inv['reserved']; // can't remove reserved stock
            if ($quantity > $removable) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['error' => "Only {$removable} unit(s) can be removed (the rest is reserved)."]);
                exit;
            }
            $newOnHand    = $inv['onHand'] - $quantity;
            $movementType = 'adjustment_out';
            $movementQty  = $quantity;
        } else { // 'set' — manual count correction
            if ($quantity < $inv['reserved']) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['error' => "Can't set stock below the {$inv['reserved']} unit(s) already reserved."]);
                exit;
            }
            $delta        = $quantity - $inv['onHand'];
            $newOnHand    = $quantity;
            $movementType = $delta >= 0 ? 'adjustment_in' : 'adjustment_out';
            $movementQty  = abs($delta);
        }

        $pdo->prepare('UPDATE inventory SET quantity_on_hand = :qty WHERE inventory_id = :iid')
            ->execute([':qty' => $newOnHand, ':iid' => $inv['id']]);

        if ($movementQty > 0) {
            $defaultNote = 'Manual stock ' . ($type === 'set' ? 'count correction' : $type);
            $pdo->prepare("
                INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, notes, performed_by)
                VALUES (:pid, :wid, :mtype, :qty, 'manual_adjustment', :notes, :uid)
            ")->execute([
                ':pid'   => $productId,
                ':wid'   => $warehouseId,
                ':mtype' => $movementType,
                ':qty'   => $movementQty,
                ':notes' => $note !== '' ? $note : $defaultNote,
                ':uid'   => $user['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not update stock: ' . $e->getMessage()]);
        exit;
    }

    $verb = $type === 'in' ? 'increased' : ($type === 'out' ? 'decreased' : 'count corrected');
    api_log_activity($pdo, $user['id'], 'update', 'inventory', $productId,
        "{$product['product_name']} stock {$verb} by {$movementQty} unit(s)");
    check_stock_alerts($pdo);

    echo json_encode(['ok' => true, 'newOnHand' => $newOnHand]);
    exit;
}

/* ============================================================================
 *  POST ?action=stock_transfer — move stock between two warehouses
 * ========================================================================== */
if ($method === 'POST' && $action === 'stock_transfer') {
    $user = api_require_permission('inventory.manage');
    api_verify_csrf();

    $body      = api_json_body();
    $productId = (int) ($body['productId']       ?? 0);
    $fromId    = (int) ($body['fromWarehouseId']  ?? 0);
    $toId      = (int) ($body['toWarehouseId']    ?? 0);
    $quantity  = $body['quantity'] ?? null;

    if ($productId <= 0 || $fromId <= 0 || $toId <= 0) {
        http_response_code(422); echo json_encode(['error' => 'Product and both warehouses are required.']); exit;
    }
    if ($fromId === $toId) {
        http_response_code(422); echo json_encode(['error' => 'Source and destination warehouses must be different.']); exit;
    }
    if (!is_numeric($quantity) || (float) $quantity <= 0) {
        http_response_code(422); echo json_encode(['error' => 'Quantity must be greater than zero.']); exit;
    }
    $quantity = (int) $quantity;

    $stmt = $pdo->prepare('SELECT product_name FROM products WHERE product_id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        http_response_code(404); echo json_encode(['error' => 'Product not found']); exit;
    }

    $transferId = null;

    try {
        $pdo->beginTransaction();

        $from = get_or_create_inventory_row($pdo, $productId, $fromId);
        $available = $from['onHand'] - $from['reserved'];
        if ($quantity > $available) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['error' => "Only {$available} unit(s) available to transfer from that warehouse."]);
            exit;
        }
        $to = get_or_create_inventory_row($pdo, $productId, $toId);

        $pdo->prepare('UPDATE inventory SET quantity_on_hand = :qty WHERE inventory_id = :iid')
            ->execute([':qty' => $from['onHand'] - $quantity, ':iid' => $from['id']]);
        $pdo->prepare('UPDATE inventory SET quantity_on_hand = :qty WHERE inventory_id = :iid')
            ->execute([':qty' => $to['onHand'] + $quantity, ':iid' => $to['id']]);

        $transferStmt = $pdo->prepare("
            INSERT INTO stock_transfers (product_id, from_warehouse_id, to_warehouse_id, quantity, status, requested_by)
            VALUES (:pid, :from, :to, :qty, 'completed', :uid)
        ");
        $transferStmt->execute([
            ':pid' => $productId, ':from' => $fromId, ':to' => $toId, ':qty' => $quantity, ':uid' => $user['id'],
        ]);
        $transferId = (int) $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, performed_by)
            VALUES (:pid, :wid, 'transfer_out', :qty, 'stock_transfers', :ref, :uid)
        ")->execute([':pid' => $productId, ':wid' => $fromId, ':qty' => $quantity, ':ref' => $transferId, ':uid' => $user['id']]);

        $pdo->prepare("
            INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, performed_by)
            VALUES (:pid, :wid, 'transfer_in', :qty, 'stock_transfers', :ref, :uid)
        ")->execute([':pid' => $productId, ':wid' => $toId, ':qty' => $quantity, ':ref' => $transferId, ':uid' => $user['id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not transfer stock: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'transfer', 'stock_transfers', $transferId,
        "{$quantity} unit(s) of {$product['product_name']} transferred between warehouses");
    check_stock_alerts($pdo);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
