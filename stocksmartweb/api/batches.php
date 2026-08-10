<?php
/**
 * ============================================================================
 *  StockSmart — Batch / Expiry API (api/batches.php)
 * ============================================================================
 *  GET    api/batches.php                    -> every batch + counts + warehouses
 *         ?status=expired|expiring_soon|valid|none|alerting
 *         ?product=<id>                      -> only that product's batches
 *         ?q=<text>                          -> product name / SKU / batch no.
 *  POST   api/batches.php                    -> create a batch
 *  PUT    api/batches.php?id=<batch_id>      -> update a batch (incl. expiry)
 *  DELETE api/batches.php?id=<batch_id>      -> delete a batch
 *
 *  Expiry is stored ONLY here, on product_batches.expiry_date. Two batches of
 *  the same product routinely carry different dates, so there is deliberately
 *  no expiry column on `products` to contradict this one. A NULL expiry_date
 *  means the batch is non-perishable and never appears in expiry alerts —
 *  migration 006 relaxed the column to allow it.
 *
 *  Classification (expired / expiring soon / valid / none) comes from
 *  helpers/stock_status.php, the same file the dashboard, sidebar badge and
 *  notification bell use, so this page can never disagree with them.
 *
 *  RELATIONSHIP TO `inventory` — stated plainly rather than implied:
 *  `inventory` (per product, per warehouse) remains the stock-of-record that
 *  checkout deducts from and that every stock figure in the app is computed
 *  from. `product_batches` is a lot-tracking overlay used for expiry. Editing
 *  a batch quantity here does NOT move sellable stock, and is not meant to —
 *  wiring batch-level depletion into checkout would change how every sale
 *  picks stock and is well outside this change. Use Inventory → Update Stock
 *  for sellable quantities; use this page to record what expires when.
 * ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/stock_status.php';
require_once __DIR__ . '/../helpers/notifications.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/** Unique, human-readable batch number. batch_number carries a UNIQUE index. */
function batches_generate_number(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $candidate = 'BT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $stmt = $pdo->prepare('SELECT batch_id FROM product_batches WHERE batch_number = :n LIMIT 1');
        $stmt->execute([':n' => $candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
    }
    throw new RuntimeException('Could not generate a unique batch number.');
}

/**
 * Shared validation for create and update.
 *
 * @return array{0:string[], 1:array<string,mixed>}
 */
function batches_validate(PDO $pdo, array $body, ?int $existingId = null): array
{
    $errors = [];

    $productId = (int) ($body['productId'] ?? 0);
    if ($productId <= 0) {
        $errors[] = 'Please choose a product.';
    } else {
        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = :id LIMIT 1');
        $stmt->execute([':id' => $productId]);
        if (!$stmt->fetch()) {
            $errors[] = 'That product no longer exists.';
        }
    }

    $warehouseId = (int) ($body['warehouseId'] ?? 0);
    if ($warehouseId <= 0) {
        $errors[] = 'Please choose a location.';
    } else {
        $stmt = $pdo->prepare('SELECT warehouse_id FROM warehouses WHERE warehouse_id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $warehouseId]);
        if (!$stmt->fetch()) {
            $errors[] = 'That location is not available.';
        }
    }

    $quantity = (int) ($body['quantity'] ?? 0);
    if ($quantity < 0) {
        $errors[] = 'Quantity cannot be negative.';
    }

    // Blank expiry is valid and means non-perishable — see file header.
    [$expiryDate, $expiryError] = expiry_parse_input($body['expiryDate'] ?? null);
    if ($expiryError !== null) {
        $errors[] = $expiryError;
    }

    [$manufactureDate, $manufactureError] = expiry_parse_input($body['manufactureDate'] ?? null);
    if ($manufactureError !== null) {
        $errors[] = 'Manufacture date must be a valid date in YYYY-MM-DD format.';
    }
    if ($expiryDate !== null && $manufactureDate !== null && $manufactureDate > $expiryDate) {
        $errors[] = 'Manufacture date cannot be after the expiry date.';
    }

    $batchNumber = trim((string) ($body['batchNumber'] ?? ''));
    if ($batchNumber !== '') {
        if (mb_strlen($batchNumber) > 40) {
            $errors[] = 'Batch number cannot exceed 40 characters.';
        }
        $sql = 'SELECT batch_id FROM product_batches WHERE batch_number = :n';
        $params = [':n' => $batchNumber];
        if ($existingId !== null) {
            $sql .= ' AND batch_id <> :id';
            $params[':id'] = $existingId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = "Batch number \"{$batchNumber}\" is already in use.";
        }
    }

    return [$errors, [
        'productId'       => $productId,
        'warehouseId'     => $warehouseId,
        'quantity'        => $quantity,
        'expiryDate'      => $expiryDate,
        'manufactureDate' => $manufactureDate,
        'batchNumber'     => $batchNumber,
    ]];
}

/* ============================================================================
 *  GET — batch list
 * ========================================================================== */
if ($method === 'GET') {
    api_require_permission('inventory.view');

    $statusFilter = (string) ($_GET['status'] ?? '');
    $productId    = (int) ($_GET['product'] ?? 0);
    $search       = trim((string) ($_GET['q'] ?? ''));

    $where = [];
    $params = [];
    if ($productId > 0) {
        $where[] = 'b.product_id = :pid';
        $params[':pid'] = $productId;
    }
    if ($search !== '') {
        // Three DISTINCT placeholders on purpose: db.php sets
        // PDO::ATTR_EMULATE_PREPARES => false, and native MySQL prepares reject
        // the same named placeholder used more than once (SQLSTATE[HY093]).
        // api/products.php's search does the same for the same reason.
        $where[] = '(p.product_name LIKE :q1 OR p.sku LIKE :q2 OR b.batch_number LIKE :q3)';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = '%' . $search . '%';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT b.batch_id, b.product_id, b.warehouse_id, b.batch_number, b.quantity,
               b.manufacture_date, b.expiry_date, b.received_at,
               p.product_name, p.sku, w.warehouse_name
        FROM product_batches b
        JOIN products p ON p.product_id = b.product_id
        LEFT JOIN warehouses w ON w.warehouse_id = b.warehouse_id
        {$whereSql}
        ORDER BY (b.expiry_date IS NULL), b.expiry_date ASC, p.product_name ASC
    ");
    $stmt->execute($params);

    $batches = [];
    $counts = ['expired' => 0, 'expiring_soon' => 0, 'valid' => 0, 'none' => 0];

    foreach ($stmt->fetchAll() as $r) {
        $state = expiry_state($r['expiry_date']);

        // Counts describe the whole set, before the status filter narrows it,
        // so the filter chips can show totals.
        if ($state['key'] === 'none' || (int) $r['quantity'] <= 0) {
            $counts[$state['key'] === 'none' ? 'none' : $state['key']]++;
        } else {
            $counts[$state['key']]++;
        }

        $row = [
            'batchId'         => (int) $r['batch_id'],
            'productId'       => (int) $r['product_id'],
            'productName'     => $r['product_name'],
            'sku'             => $r['sku'],
            'warehouseId'     => (int) $r['warehouse_id'],
            'warehouseName'   => $r['warehouse_name'] ?? 'Unassigned',
            'batchNumber'     => $r['batch_number'],
            'quantity'        => (int) $r['quantity'],
            'manufactureDate' => $r['manufacture_date'],
            'expiryDate'      => $r['expiry_date'],
            'status'          => $state['key'],
            'statusLabel'     => $state['label'],
            'daysLeft'        => $state['daysLeft'],
        ];

        if ($statusFilter !== '') {
            $isAlerting = expiry_is_alerting($r['expiry_date'], (int) $r['quantity']);
            if ($statusFilter === 'alerting' ? !$isAlerting : $state['key'] !== $statusFilter) {
                continue;
            }
        }

        $batches[] = $row;
    }

    $warehouses = $pdo->query('SELECT warehouse_id, warehouse_name FROM warehouses WHERE is_active = 1 ORDER BY warehouse_name')->fetchAll();
    $products = $pdo->query("SELECT product_id, product_name, sku FROM products WHERE status != 'discontinued' ORDER BY product_name")->fetchAll();

    echo json_encode([
        'batches'    => $batches,
        'counts'     => $counts,
        'warehouses' => array_map(static fn ($w) => ['id' => (int) $w['warehouse_id'], 'name' => $w['warehouse_name']], $warehouses),
        'products'   => array_map(static fn ($p) => ['id' => (int) $p['product_id'], 'name' => $p['product_name'], 'sku' => $p['sku']], $products),
    ]);
    exit;
}

/* ============================================================================
 *  POST — create a batch
 * ========================================================================== */
if ($method === 'POST') {
    $user = api_require_permission('inventory.manage');
    api_verify_csrf();

    $body = api_json_body();
    [$errors, $data] = batches_validate($pdo, $body);
    if ($errors) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    $batchNumber = $data['batchNumber'] !== '' ? $data['batchNumber'] : batches_generate_number($pdo);

    $pdo->prepare('
        INSERT INTO product_batches (product_id, warehouse_id, batch_number, quantity, manufacture_date, expiry_date)
        VALUES (:pid, :wid, :num, :qty, :mfg, :exp)
    ')->execute([
        ':pid' => $data['productId'],
        ':wid' => $data['warehouseId'],
        ':num' => $batchNumber,
        ':qty' => $data['quantity'],
        ':mfg' => $data['manufactureDate'],
        ':exp' => $data['expiryDate'],
    ]);

    $batchId = (int) $pdo->lastInsertId();
    api_log_activity($pdo, $user['id'], 'add', 'product_batches', $batchId, "Batch {$batchNumber} recorded");
    check_stock_alerts($pdo);

    http_response_code(201);
    echo json_encode(['ok' => true, 'batchId' => $batchId, 'batchNumber' => $batchNumber]);
    exit;
}

/* ============================================================================
 *  PUT — update a batch (this is where an expiry date gets corrected)
 * ========================================================================== */
if ($method === 'PUT') {
    $user = api_require_permission('inventory.manage');
    api_verify_csrf();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Batch id is required.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT batch_id FROM product_batches WHERE batch_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Batch not found.']);
        exit;
    }

    $body = api_json_body();
    [$errors, $data] = batches_validate($pdo, $body, $id);
    if ($errors) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    $params = [
        ':pid' => $data['productId'],
        ':wid' => $data['warehouseId'],
        ':qty' => $data['quantity'],
        ':mfg' => $data['manufactureDate'],
        ':exp' => $data['expiryDate'],
        ':id'  => $id,
    ];
    $numberSql = '';
    if ($data['batchNumber'] !== '') {
        $numberSql = ', batch_number = :num';
        $params[':num'] = $data['batchNumber'];
    }

    $pdo->prepare("
        UPDATE product_batches
        SET product_id = :pid, warehouse_id = :wid, quantity = :qty,
            manufacture_date = :mfg, expiry_date = :exp{$numberSql}
        WHERE batch_id = :id
    ")->execute($params);

    api_log_activity($pdo, $user['id'], 'update', 'product_batches', $id, "Batch #{$id} updated");
    check_stock_alerts($pdo); // re-evaluates: a corrected date can clear an alert

    echo json_encode(['ok' => true]);
    exit;
}

/* ============================================================================
 *  DELETE — remove a batch
 * ========================================================================== */
if ($method === 'DELETE') {
    $user = api_require_permission('inventory.manage');
    api_verify_csrf();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Batch id is required.']);
        exit;
    }

    // Alerts reference batches; clear this batch's alerts first so the sidebar
    // badge and the bell can't point at a row that no longer exists.
    $pdo->prepare('UPDATE alerts SET is_acknowledged = 1, acknowledged_at = NOW() WHERE batch_id = :id AND is_acknowledged = 0')
        ->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM product_batches WHERE batch_id = :id')->execute([':id' => $id]);

    api_log_activity($pdo, $user['id'], 'delete', 'product_batches', $id, "Batch #{$id} deleted");

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
