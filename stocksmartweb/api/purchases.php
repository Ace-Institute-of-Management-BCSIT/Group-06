<?php
/**
 * Purchase order CRUD + receiving (full or partial).
 * GET  api/purchases.php                    -> list POs with supplier + totals
 * GET  api/purchases.php?action=detail&id=X -> one PO with line items
 * POST api/purchases.php                    -> create a PO (draft or ordered)
 * POST api/purchases.php?action=receive&id=X -> receive stock against a PO (partial or full)
 * POST api/purchases.php?action=cancel&id=X  -> cancel a draft/ordered PO
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/notifications.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

function purchases_generate_number(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT po_number FROM purchase_orders ORDER BY purchase_order_id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/PO-(\d+)/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return 'PO-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
}

/* ============================================================================
 *  GET — list, or single PO detail
 * ========================================================================== */
if ($method === 'GET' && $action === 'detail') {
    api_require_permission('purchases.view');
    $id = (int) ($_GET['id'] ?? 0);
    $poStmt = $pdo->prepare("
        SELECT po.*, s.supplier_name, w.warehouse_name
        FROM purchase_orders po
        JOIN suppliers s ON s.supplier_id = po.supplier_id
        JOIN warehouses w ON w.warehouse_id = po.warehouse_id
        WHERE po.purchase_order_id = :id
    ");
    $poStmt->execute([':id' => $id]);
    $po = $poStmt->fetch();
    if (!$po) { http_response_code(404); echo json_encode(['error' => 'Purchase order not found.']); exit; }

    $itemStmt = $pdo->prepare("
        SELECT poi.*, p.product_name, p.sku
        FROM purchase_order_items poi
        JOIN products p ON p.product_id = poi.product_id
        WHERE poi.purchase_order_id = :id
    ");
    $itemStmt->execute([':id' => $id]);
    $po['items'] = $itemStmt->fetchAll();

    echo json_encode(['purchase_order' => $po]);
    exit;
}

if ($method === 'GET') {
    api_require_permission('purchases.view');
    $rows = $pdo->query("
        SELECT po.purchase_order_id, po.po_number, po.status, po.grand_total, po.ordered_at, po.received_at, po.created_at,
               s.supplier_name,
               (SELECT COUNT(*) FROM purchase_order_items i WHERE i.purchase_order_id = po.purchase_order_id) AS item_count
        FROM purchase_orders po
        JOIN suppliers s ON s.supplier_id = po.supplier_id
        ORDER BY po.created_at DESC
    ")->fetchAll();
    echo json_encode(['purchase_orders' => $rows]);
    exit;
}

/* ============================================================================
 *  POST ?action=receive — receive stock against a PO, partial or full
 * ========================================================================== */
if ($method === 'POST' && $action === 'receive') {
    $user = api_require_permission('purchases.manage');
    api_verify_csrf();

    $id = (int) ($_GET['id'] ?? 0);
    $body = api_json_body();
    $lines = is_array($body['items'] ?? null) ? $body['items'] : [];

    $poStmt = $pdo->prepare('SELECT * FROM purchase_orders WHERE purchase_order_id = :id');
    $poStmt->execute([':id' => $id]);
    $po = $poStmt->fetch();
    if (!$po) { http_response_code(404); echo json_encode(['error' => 'Purchase order not found.']); exit; }
    if (in_array($po['status'], ['received', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'This purchase order is already ' . $po['status'] . '.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $itemStmt = $pdo->prepare('SELECT * FROM purchase_order_items WHERE purchase_order_item_id = :id AND purchase_order_id = :po');
        $updateItem = $pdo->prepare('UPDATE purchase_order_items SET received_quantity = :rq WHERE purchase_order_item_id = :id');
        $updateInventory = $pdo->prepare('
            INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand, quantity_reserved)
            VALUES (:pid, :wid, :qty, 0)
            ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)
        ');
        $insertMovement = $pdo->prepare("
            INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, performed_by, notes)
            VALUES (:pid, :wid, 'purchase_in', :qty, 'purchase_orders', :ref, :uid, :notes)
        ");

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $qty = (float) ($line['receive_qty'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) continue;

            $itemStmt->execute([':id' => $itemId, ':po' => $id]);
            $item = $itemStmt->fetch();
            if (!$item) continue;

            $remaining = (float) $item['ordered_quantity'] - (float) $item['received_quantity'];
            $qty = min($qty, max(0, $remaining));
            if ($qty <= 0) continue;

            $newReceived = (float) $item['received_quantity'] + $qty;
            $updateItem->execute([':rq' => $newReceived, ':id' => $itemId]);
            $updateInventory->execute([':pid' => $item['product_id'], ':wid' => $po['warehouse_id'], ':qty' => (int) $qty]);
            $insertMovement->execute([
                ':pid' => $item['product_id'], ':wid' => $po['warehouse_id'], ':qty' => (int) $qty,
                ':ref' => $id, ':uid' => $user['id'], ':notes' => "Received against {$po['po_number']}",
            ]);
        }

        // Recompute status from the fully up-to-date item rows.
        $allItemsStmt = $pdo->prepare('SELECT ordered_quantity, received_quantity FROM purchase_order_items WHERE purchase_order_id = :id');
        $allItemsStmt->execute([':id' => $id]);
        $allItems = $allItemsStmt->fetchAll();
        $fullyReceived = true;
        $anyReceived = false;
        foreach ($allItems as $it) {
            if ((float) $it['received_quantity'] > 0) $anyReceived = true;
            if ((float) $it['received_quantity'] < (float) $it['ordered_quantity']) $fullyReceived = false;
        }
        $newStatus = $fullyReceived ? 'received' : ($anyReceived ? 'partial' : $po['status']);
        $pdo->prepare('UPDATE purchase_orders SET status = :status, received_at = ' . ($fullyReceived ? 'NOW()' : 'received_at') . ' WHERE purchase_order_id = :id')
            ->execute([':status' => $newStatus, ':id' => $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not receive stock: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'update', 'purchase_orders', $id, "Stock received against {$po['po_number']} (now {$newStatus})");
    check_stock_alerts($pdo);

    echo json_encode(['ok' => true, 'status' => $newStatus]);
    exit;
}

/* ============================================================================
 *  POST ?action=cancel — cancel a draft/ordered PO
 * ========================================================================== */
if ($method === 'POST' && $action === 'cancel') {
    $user = api_require_permission('purchases.manage');
    api_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT po_number, status FROM purchase_orders WHERE purchase_order_id = :id');
    $stmt->execute([':id' => $id]);
    $po = $stmt->fetch();
    if (!$po) { http_response_code(404); echo json_encode(['error' => 'Purchase order not found.']); exit; }
    if (in_array($po['status'], ['received', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'This purchase order can no longer be cancelled.']);
        exit;
    }

    $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE purchase_order_id = :id")->execute([':id' => $id]);
    api_log_activity($pdo, $user['id'], 'update', 'purchase_orders', $id, "{$po['po_number']} was cancelled");
    echo json_encode(['ok' => true]);
    exit;
}

/* ============================================================================
 *  POST — create a purchase order
 * ========================================================================== */
if ($method === 'POST') {
    $user = api_require_permission('purchases.manage');
    api_verify_csrf();

    $body = api_json_body();
    $supplierId = (int) ($body['supplier_id'] ?? 0);
    $warehouseId = (int) ($body['warehouse_id'] ?? 0);
    $dueDate = trim((string) ($body['due_date'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));
    $placeOrder = !empty($body['place_order']);
    $items = is_array($body['items'] ?? null) ? $body['items'] : [];

    if ($supplierId <= 0 || $warehouseId <= 0 || empty($items)) {
        http_response_code(422);
        echo json_encode(['error' => 'Supplier, warehouse, and at least one line item are required.']);
        exit;
    }

    $subtotal = 0.0;
    $taxTotal = 0.0;
    $cleanItems = [];
    foreach ($items as $line) {
        $productId = (int) ($line['product_id'] ?? 0);
        $qty = (float) ($line['ordered_quantity'] ?? 0);
        $cost = (float) ($line['unit_cost'] ?? 0);
        $taxRate = (float) ($line['tax_rate'] ?? 0);
        if ($productId <= 0 || $qty <= 0 || $cost < 0) continue;
        $lineTotal = $qty * $cost;
        $lineTax = $lineTotal * ($taxRate / 100);
        $subtotal += $lineTotal;
        $taxTotal += $lineTax;
        $cleanItems[] = [$productId, $qty, $cost, $taxRate];
    }
    if (empty($cleanItems)) {
        http_response_code(422);
        echo json_encode(['error' => 'At least one valid line item (product, quantity, cost) is required.']);
        exit;
    }
    $grandTotal = $subtotal + $taxTotal;

    try {
        $pdo->beginTransaction();
        $poNumber = purchases_generate_number($pdo);
        $status = $placeOrder ? 'ordered' : 'draft';

        $pdo->prepare("
            INSERT INTO purchase_orders (po_number, supplier_id, warehouse_id, status, subtotal, tax_amount, grand_total, due_date, notes, created_by, ordered_at)
            VALUES (:num, :sup, :wh, :status, :sub, :tax, :grand, :due, :notes, :uid, " . ($placeOrder ? 'NOW()' : 'NULL') . ")
        ")->execute([
            ':num' => $poNumber, ':sup' => $supplierId, ':wh' => $warehouseId, ':status' => $status,
            ':sub' => $subtotal, ':tax' => $taxTotal, ':grand' => $grandTotal,
            ':due' => $dueDate !== '' ? $dueDate : null, ':notes' => $notes !== '' ? $notes : null, ':uid' => $user['id'],
        ]);
        $poId = (int) $pdo->lastInsertId();

        $itemInsert = $pdo->prepare('
            INSERT INTO purchase_order_items (purchase_order_id, product_id, ordered_quantity, unit_cost, tax_rate)
            VALUES (:po, :pid, :qty, :cost, :tax)
        ');
        foreach ($cleanItems as [$productId, $qty, $cost, $taxRate]) {
            $itemInsert->execute([':po' => $poId, ':pid' => $productId, ':qty' => $qty, ':cost' => $cost, ':tax' => $taxRate]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not create purchase order: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'add', 'purchase_orders', $poId, "{$poNumber} created ({$status})");
    echo json_encode(['ok' => true, 'id' => $poId, 'po_number' => $poNumber]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
