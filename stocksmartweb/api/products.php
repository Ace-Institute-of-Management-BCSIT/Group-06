<?php
/**
 * ============================================================================
 *  StockSmart — Products API (api/products.php)
 * ============================================================================
 *  GET    api/products.php          -> list every non-discontinued product
 *  GET    api/products.php?id=5     -> single product
 *  POST   api/products.php          -> create product   {name,category,sku,stock,price}
 *  PUT    api/products.php?id=5     -> update product    {name,category,sku,stock,price}
 *  DELETE api/products.php?id=5     -> delete (or archive if it has sales history)
 *
 *  Response shape matches exactly what products.html's renderTable()/
 *  renderStats() expect:
 *    { id, name, sub, category, sku, stock, price, threshold, batchCount, expiryDate }
 *
 *  Field mapping notes:
 *    - "sub"       -> products.unit (e.g. "pcs", "kg", "pack") — shown as the
 *                     small subtitle under the product name in the table.
 *    - "category"  -> categories.category_name (a plain string). The Add/Edit
 *                     form is a free-text input, so on save we find-or-create
 *                     a matching category by name rather than requiring a
 *                     dropdown of existing category IDs.
 *    - "stock"     -> AVAILABLE stock across every warehouse:
 *                     SUM(quantity_on_hand) - SUM(quantity_reserved), the
 *                     definition in helpers/stock_status.php that Inventory,
 *                     the dashboard and alert scanning all use. Reserved units
 *                     are already promised to an order, so counting them as
 *                     sellable is what previously let this page report a
 *                     product as "In Stock" while the sidebar badge counted it
 *                     as low.
 *                     The current UI has no per-warehouse stock entry, so a
 *                     new product's stock is written to DEFAULT_WAREHOUSE_ID,
 *                     and an edit reconciles the total by adjusting whichever
 *                     existing warehouse row currently holds the most stock.
 *    - "threshold" -> products.reorder_level, the per-product low-stock
 *                     threshold. Editable from the Add/Edit form as "Reorder
 *                     Level" and validated server-side (whole number >= 0).
 *                     It was previously hard-coded to 10 on insert and never
 *                     updated, so every product shared one threshold that
 *                     could only be changed with SQL. Omitting the field on
 *                     create falls back to the column's own DEFAULT; omitting
 *                     it on update leaves the existing value alone.
 *                     This single column drives every low-stock decision in
 *                     the app via helpers/stock_status.php.
 *    - "expiryDate"/"batchCount" -> expiry is stored per BATCH on
 *                     product_batches, never on products. These two fields let
 *                     the Add/Edit form offer a single expiry date for the
 *                     simple case (a product with 0 or 1 batch) and defer to
 *                     expiry.php when several batches carry different dates.
 *                     A null expiry means non-perishable and never alerts.
 *
 *  Permission rules: GET requires "products.view"; POST/PUT/DELETE require
 *  "products.manage" (see database/production_upgrade.sql role_permissions
 *  seed data, editable per-role from roles.html).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/stock_status.php';

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Batch summary for one product, so the Add/Edit form knows whether it may
 * offer a single expiry date or has to defer to the Expiry page.
 *
 * @return array{count:int, batchId:?int, expiryDate:?string}
 */
function product_batch_summary(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare('SELECT batch_id, expiry_date FROM product_batches WHERE product_id = :id ORDER BY batch_id ASC');
    $stmt->execute([':id' => $productId]);
    $rows = $stmt->fetchAll();

    return [
        'count'      => count($rows),
        'batchId'    => count($rows) === 1 ? (int) $rows[0]['batch_id'] : null,
        'expiryDate' => count($rows) === 1 ? $rows[0]['expiry_date'] : null,
    ];
}

/**
 * Fetch one product in the exact shape the frontend expects.
 *
 * "stock" is available stock — on_hand MINUS reserved — matching
 * helpers/stock_status.php and every other screen. This function previously
 * summed quantity_on_hand alone while the list query on the same endpoint
 * subtracted reserved, so a single product could report two different stock
 * figures from two calls to this one file.
 */
function fetch_product_row(PDO $pdo, int $id): ?array
{
    $available = sql_available_stock('i');

    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.unit, p.sku, p.barcode, p.price, p.reorder_level,
               c.category_name,
               {$available} AS stock
        FROM   products p
        JOIN   categories c ON c.category_id = p.category_id
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE  p.product_id = :id
        GROUP BY p.product_id, p.product_name, p.unit, p.sku, p.barcode, p.price, p.reorder_level, c.category_name
    ");
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    if (!$r) {
        return null;
    }

    $batches = product_batch_summary($pdo, $id);

    return [
        'id'         => (int) $r['product_id'],
        'name'       => $r['product_name'],
        'sub'        => $r['unit'],
        'category'   => $r['category_name'],
        'sku'        => $r['sku'],
        'barcode'    => $r['barcode'],
        'stock'      => (int) $r['stock'],
        'price'      => (float) $r['price'],
        'threshold'  => (int) $r['reorder_level'],
        'batchCount' => $batches['count'],
        'expiryDate' => $batches['expiryDate'],
    ];
}

/**
 * Creates or updates the batch that carries a product's expiry date.
 *
 * Expiry lives on product_batches, never on products (see api/batches.php).
 * The Add/Edit Product form only ever manages the SIMPLE case — a product with
 * one batch, or none yet:
 *   - 0 batches + a date  -> create the product's first batch
 *   - 1 batch             -> update that batch's expiry (null clears it)
 *   - 2+ batches          -> do nothing; the form disables the field and sends
 *                            the user to expiry.php, because collapsing
 *                            several batches onto one date would silently
 *                            rewrite real, differing expiry dates.
 */
function sync_product_expiry(PDO $pdo, int $productId, ?string $expiryDate, int $quantity): void
{
    $batches = product_batch_summary($pdo, $productId);

    if ($batches['count'] > 1) {
        return;
    }

    if ($batches['count'] === 1) {
        $pdo->prepare('UPDATE product_batches SET expiry_date = :exp WHERE batch_id = :id')
            ->execute([':exp' => $expiryDate, ':id' => $batches['batchId']]);
        return;
    }

    if ($expiryDate === null) {
        return; // nothing to record — a non-perishable product needs no batch
    }

    // Unique batch number; batch_number carries a UNIQUE index.
    $batchNumber = null;
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $candidate = 'BT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $check = $pdo->prepare('SELECT batch_id FROM product_batches WHERE batch_number = :n LIMIT 1');
        $check->execute([':n' => $candidate]);
        if (!$check->fetch()) {
            $batchNumber = $candidate;
            break;
        }
    }
    if ($batchNumber === null) {
        return; // extraordinarily unlikely; never block the product save over it
    }

    $pdo->prepare('
        INSERT INTO product_batches (product_id, warehouse_id, batch_number, quantity, expiry_date)
        VALUES (:pid, :wid, :num, :qty, :exp)
    ')->execute([
        ':pid' => $productId,
        ':wid' => DEFAULT_WAREHOUSE_ID,
        ':num' => $batchNumber,
        ':qty' => max(0, $quantity),
        ':exp' => $expiryDate,
    ]);
}

/**
 * Validates the Add/Edit payload.
 * Returns [errors[], name, category, sku, barcode, stock, price, expiryDate].
 *
 * expiryDate is nullable on purpose: a blank field means the product does not
 * expire, which is a legitimate answer for non-perishable stock and must not
 * be turned into an invented date.
 */
function validate_product_payload(array $body): array
{
    $name     = trim((string) ($body['name']     ?? ''));
    $category = trim((string) ($body['category'] ?? ''));
    $sku      = trim((string) ($body['sku']      ?? ''));
    $barcode  = trim((string) ($body['barcode']  ?? ''));
    $stock    = $body['stock'] ?? 0;
    $price    = $body['price'] ?? 0;

    $errors = [];

    [$expiryDate, $expiryError] = expiry_parse_input($body['expiryDate'] ?? null);
    if ($expiryError !== null) {
        $errors[] = $expiryError;
    }

    // Reorder level is per-product and comes from the form. Omitting the key
    // entirely means "leave it alone" (null) — used by callers that don't edit
    // it; sending it blank/invalid is a validation error, not a silent default.
    $reorderLevel = null;
    if (array_key_exists('reorderLevel', $body) && $body['reorderLevel'] !== null && $body['reorderLevel'] !== '') {
        $raw = $body['reorderLevel'];
        // Server-side validation on purpose — the number input's min/step
        // attributes are a convenience, not a guarantee. Reject decimals,
        // negatives and anything non-numeric outright.
        if (!is_numeric($raw) || (float) $raw != (int) (float) $raw || (int) $raw < 0) {
            $errors[] = 'Reorder level must be a whole number of 0 or more.';
        } elseif ((int) $raw > 1000000) {
            $errors[] = 'Reorder level is unrealistically large — please check it.';
        } else {
            $reorderLevel = (int) $raw;
        }
    }

    if ($name === '') {
        $errors[] = 'Product name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Product name must be 150 characters or fewer.';
    }

    if ($category === '') {
        $errors[] = 'Category is required.';
    } elseif (mb_strlen($category) > 60) {
        $errors[] = 'Category must be 60 characters or fewer.';
    }

    if ($sku === '') {
        $errors[] = 'SKU is required.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{2,40}$/', $sku)) {
        $errors[] = 'SKU must be 2-40 characters: letters, numbers, dots, dashes, or underscores only.';
    }

    if ($barcode !== '' && !preg_match('/^[A-Za-z0-9._-]{2,64}$/', $barcode)) {
        $errors[] = 'Barcode must be 2-64 characters: letters, numbers, dots, dashes, or underscores only.';
    }

    if (!is_numeric($stock) || (float) $stock < 0) {
        $errors[] = 'Stock must be a non-negative number.';
    }

    if (!is_numeric($price) || (float) $price < 0) {
        $errors[] = 'Price must be a non-negative number.';
    }

    return [$errors, $name, $category, $sku, $barcode, (float) $stock, (float) $price, $expiryDate, $reorderLevel];
}

function find_or_create_category(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(:n) LIMIT 1');
    $stmt->execute([':n' => $name]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['category_id'];
    }
    $stmt = $pdo->prepare('INSERT INTO categories (category_name) VALUES (:n)');
    $stmt->execute([':n' => $name]);
    return (int) $pdo->lastInsertId();
}

/* ============================================================================
 *  GET — list or single product. Any authenticated role may view.
 * ========================================================================== */
if ($method === 'GET') {
    api_require_permission('products.view');

    if (isset($_GET['id'])) {
        $row = fetch_product_row($pdo, (int) $_GET['id']);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }
        echo json_encode($row);
        exit;
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $params = [];
    if ($q !== '') {
        $where = "p.status = 'active' AND (p.product_name LIKE :q1 OR p.sku LIKE :q2 OR p.barcode LIKE :q3)";
        $params[':q1'] = $params[':q2'] = $params[':q3'] = '%' . $q . '%';
    } else {
        $where = "p.status != 'discontinued'";
    }

    // batch_count / first_expiry let the Add/Edit form decide whether it can
    // safely offer a single expiry field for this product, or has to defer to
    // expiry.php because several batches carry different dates.
    $available = sql_available_stock('i');

    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.unit, p.sku, p.barcode, p.price, p.reorder_level,
               c.category_name,
               {$available} AS stock,
               (SELECT COUNT(*) FROM product_batches b WHERE b.product_id = p.product_id) AS batch_count,
               (SELECT b2.expiry_date FROM product_batches b2 WHERE b2.product_id = p.product_id ORDER BY b2.batch_id ASC LIMIT 1) AS first_expiry
        FROM   products p
        JOIN   categories c ON c.category_id = p.category_id
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE  {$where}
        GROUP BY p.product_id, p.product_name, p.unit, p.sku, p.barcode, p.price, p.reorder_level, c.category_name
        ORDER BY p.product_name
        " . ($q !== '' ? 'LIMIT 25' : '') . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = array_map(function (array $r): array {
        $batchCount = (int) $r['batch_count'];
        return [
            'id'         => (int) $r['product_id'],
            'name'       => $r['product_name'],
            'sub'        => $r['unit'],
            'category'   => $r['category_name'],
            'sku'        => $r['sku'],
            'barcode'    => $r['barcode'],
            'stock'      => (int) $r['stock'],
            'price'      => (float) $r['price'],
            'threshold'  => (int) $r['reorder_level'],
            'batchCount' => $batchCount,
            'expiryDate' => $batchCount === 1 ? $r['first_expiry'] : null,
        ];
    }, $rows);

    echo json_encode($out);
    exit;
}

/* ============================================================================
 *  POST — create a product. Admin/Manager only.
 * ========================================================================== */
if ($method === 'POST') {
    $user = api_require_permission('products.manage');
    api_verify_csrf();

    $body = api_json_body();
    [$errors, $name, $category, $sku, $barcode, $stock, $price, $expiryDate, $reorderLevel] = validate_product_payload($body);
    if ($errors) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT product_id FROM products WHERE LOWER(sku) = LOWER(:sku) LIMIT 1');
    $stmt->execute([':sku' => $sku]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => "SKU \"$sku\" is already used by another product."]);
        exit;
    }
    if ($barcode !== '') {
        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE barcode = :bc LIMIT 1');
        $stmt->execute([':bc' => $barcode]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => "Barcode \"$barcode\" is already used by another product."]);
            exit;
        }
    }

    try {
        $pdo->beginTransaction();

        $categoryId = find_or_create_category($pdo, $category);

        // cost_price defaults to the same value as price until a dedicated
        // cost-price field is added to the Add/Edit form.
        // reorder_level comes from the form. It used to be the literal 10 here,
        // which meant every new product silently shared one threshold that
        // could only be changed with SQL. When the field is omitted we fall
        // back to the column's own DEFAULT rather than repeating a number in
        // PHP — the default lives in the schema, in exactly one place.
        $stmt = $pdo->prepare("
            INSERT INTO products (product_name, sku, barcode, category_id, unit, price, cost_price, reorder_level, status, created_by)
            VALUES (:name, :sku, :barcode, :cat, 'pcs', :price, :cost_price, COALESCE(:reorder, DEFAULT(reorder_level)), 'active', :uid)
        ");
        $stmt->execute([
            ':name'       => $name,
            ':sku'        => $sku,
            ':barcode'    => $barcode !== '' ? $barcode : null,
            ':cat'        => $categoryId,
            ':price'      => $price,
            ':cost_price' => $price,
            ':reorder'    => $reorderLevel,
            ':uid'     => $user['id'],
        ]);
        $productId = (int) $pdo->lastInsertId();

        if ($stock > 0) {
            $pdo->prepare("
                INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand, quantity_reserved)
                VALUES (:pid, :wid, :qty, 0)
            ")->execute([
                ':pid' => $productId,
                ':wid' => DEFAULT_WAREHOUSE_ID,
                ':qty' => (int) $stock,
            ]);
        }

        // Records the first batch when an expiry date was supplied. Blank means
        // non-perishable and creates no batch at all — no invented dates.
        sync_product_expiry($pdo, $productId, $expiryDate, (int) $stock);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not save product: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'add', 'products', $productId, "{$name} was added to inventory");

    http_response_code(201);
    echo json_encode(fetch_product_row($pdo, $productId));
    exit;
}

/* ============================================================================
 *  PUT — update a product. Admin/Manager only.
 * ========================================================================== */
if ($method === 'PUT') {
    $user = api_require_permission('products.manage');
    api_verify_csrf();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Product id is required.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    $body = api_json_body();
    [$errors, $name, $category, $sku, $barcode, $stock, $price, $expiryDate, $reorderLevel] = validate_product_payload($body);
    if ($errors) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT product_id FROM products WHERE LOWER(sku) = LOWER(:sku) AND product_id != :id LIMIT 1');
    $stmt->execute([':sku' => $sku, ':id' => $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => "SKU \"$sku\" is already used by another product."]);
        exit;
    }
    if ($barcode !== '') {
        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE barcode = :bc AND product_id != :id LIMIT 1');
        $stmt->execute([':bc' => $barcode, ':id' => $id]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => "Barcode \"$barcode\" is already used by another product."]);
            exit;
        }
    }

    try {
        $pdo->beginTransaction();

        $categoryId = find_or_create_category($pdo, $category);

        $pdo->prepare("
            UPDATE products
            SET product_name = :name, sku = :sku, barcode = :barcode, category_id = :cat, price = :price,
                reorder_level = COALESCE(:reorder, reorder_level)
            WHERE product_id = :id
        ")->execute([
            ':name'    => $name,
            ':sku'     => $sku,
            ':barcode' => $barcode !== '' ? $barcode : null,
            ':cat'     => $categoryId,
            ':price'   => $price,
            // COALESCE keeps the current value when the caller omits the field,
            // so an API client that doesn't manage thresholds can't wipe one.
            ':reorder' => $reorderLevel,
            ':id'      => $id,
        ]);

        // Reconcile total stock across warehouses. The form only edits a
        // single flat "stock" number, so we adjust whichever existing
        // warehouse row holds the most stock by the difference; if the
        // product has no inventory row yet, we create one in the default
        // warehouse. Existing reserved quantity is clamped down if needed
        // so it never exceeds the new on-hand total (DB CHECK constraint).
        $stmt = $pdo->prepare('
            SELECT inventory_id, quantity_on_hand, quantity_reserved
            FROM inventory WHERE product_id = :id
            ORDER BY quantity_on_hand DESC LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $invRow = $stmt->fetch();

        // The form's "stock" box now shows AVAILABLE stock (on_hand - reserved),
        // matching the table beside it, so the current total it is compared
        // against has to be available too. Comparing an available figure with a
        // raw on_hand total would silently subtract the reserved quantity from
        // on_hand every time a product with reservations was saved unchanged.
        $stmt2 = $pdo->prepare('
            SELECT COALESCE(SUM(quantity_on_hand), 0) - COALESCE(SUM(quantity_reserved), 0) AS total
            FROM inventory WHERE product_id = :id
        ');
        $stmt2->execute([':id' => $id]);
        $currentTotal = (float) $stmt2->fetch()['total'];
        $delta        = $stock - $currentTotal;

        if ($invRow) {
            $newOnHand   = max(0, (int) $invRow['quantity_on_hand'] + (int) round($delta));
            $newReserved = min((int) $invRow['quantity_reserved'], $newOnHand);
            $pdo->prepare('
                UPDATE inventory SET quantity_on_hand = :qty, quantity_reserved = :res
                WHERE inventory_id = :iid
            ')->execute([':qty' => $newOnHand, ':res' => $newReserved, ':iid' => $invRow['inventory_id']]);
        } elseif ($stock > 0) {
            $pdo->prepare('
                INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand, quantity_reserved)
                VALUES (:pid, :wid, :qty, 0)
            ')->execute([':pid' => $id, ':wid' => DEFAULT_WAREHOUSE_ID, ':qty' => (int) $stock]);
        }

        // Only touches expiry when this product has 0 or 1 batch — see
        // sync_product_expiry(). Products with several batches keep their
        // individual dates and are edited on expiry.php instead.
        sync_product_expiry($pdo, $id, $expiryDate, (int) $stock);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not update product: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'update', 'products', $id, "{$name} was updated");

    echo json_encode(fetch_product_row($pdo, $id));
    exit;
}

/* ============================================================================
 *  DELETE — remove a product. Admin/Manager only.
 *  If the product has order history, MySQL's ON DELETE RESTRICT on
 *  order_items blocks the hard delete — we catch that and archive
 *  (status = 'discontinued') instead, so past sales/reports stay intact.
 *  Archived products are excluded from GET, so the row still disappears
 *  from the table exactly as the UI expects.
 * ========================================================================== */
if ($method === 'DELETE') {
    $user = api_require_permission('products.manage');
    api_verify_csrf();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Product id is required.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT product_name FROM products WHERE product_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    $name = $row['product_name'];

    $archived = false;
    try {
        $pdo->prepare('DELETE FROM products WHERE product_id = :id')->execute([':id' => $id]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1451) { // FK restrict violation — has sales history
            $pdo->prepare("UPDATE products SET status = 'discontinued' WHERE product_id = :id")
                ->execute([':id' => $id]);
            $archived = true;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not delete product: ' . $e->getMessage()]);
            exit;
        }
    }

    api_log_activity(
        $pdo,
        $user['id'],
        'delete',
        'products',
        $id,
        $archived ? "{$name} was archived (has existing sales history)" : "{$name} was deleted"
    );

    echo json_encode(['ok' => true, 'archived' => $archived]);
    exit;
}

/* ============================================================================
 *  Anything else
 * ========================================================================== */
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
