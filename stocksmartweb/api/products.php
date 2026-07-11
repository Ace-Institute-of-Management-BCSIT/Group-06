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
 *    { id, name, sub, category, sku, stock, price, threshold }
 *
 *  Field mapping notes:
 *    - "sub"       -> products.unit (e.g. "pcs", "kg", "pack") — shown as the
 *                     small subtitle under the product name in the table.
 *    - "category"  -> categories.category_name (a plain string). The Add/Edit
 *                     form is a free-text input, so on save we find-or-create
 *                     a matching category by name rather than requiring a
 *                     dropdown of existing category IDs.
 *    - "stock"     -> SUM(inventory.quantity_on_hand) across every warehouse.
 *                     The current UI has no per-warehouse stock entry, so a
 *                     new product's stock is written to DEFAULT_WAREHOUSE_ID,
 *                     and an edit reconciles the total by adjusting whichever
 *                     existing warehouse row currently holds the most stock.
 *    - "threshold" -> products.reorder_level (used for Low/Critical labels).
 *                     Not yet editable from this form — defaults to 10 for
 *                     new products, left untouched on edit.
 *
 *  Permission rules: GET requires "products.view"; POST/PUT/DELETE require
 *  "products.manage" (see database/production_upgrade.sql role_permissions
 *  seed data, editable per-role from roles.html).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Fetch one product in the exact shape the frontend expects.
 */
function fetch_product_row(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.unit, p.sku, p.barcode, p.price, p.reorder_level,
               c.category_name,
               COALESCE(SUM(i.quantity_on_hand), 0) AS stock
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
    return [
        'id'        => (int) $r['product_id'],
        'name'      => $r['product_name'],
        'sub'       => $r['unit'],
        'category'  => $r['category_name'],
        'sku'       => $r['sku'],
        'barcode'   => $r['barcode'],
        'stock'     => (int) $r['stock'],
        'price'     => (float) $r['price'],
        'threshold' => (int) $r['reorder_level'],
    ];
}

/**
 * Validates the Add/Edit payload. Returns [errors[], name, category, sku, stock, price].
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

    return [$errors, $name, $category, $sku, $barcode, (float) $stock, (float) $price];
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

    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.unit, p.sku, p.barcode, p.price, p.reorder_level,
               c.category_name,
               COALESCE(SUM(i.quantity_on_hand), 0) - COALESCE(SUM(i.quantity_reserved), 0) AS stock
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
        return [
            'id'        => (int) $r['product_id'],
            'name'      => $r['product_name'],
            'sub'       => $r['unit'],
            'category'  => $r['category_name'],
            'sku'       => $r['sku'],
            'barcode'   => $r['barcode'],
            'stock'     => (int) $r['stock'],
            'price'     => (float) $r['price'],
            'threshold' => (int) $r['reorder_level'],
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
    [$errors, $name, $category, $sku, $barcode, $stock, $price] = validate_product_payload($body);
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
        $stmt = $pdo->prepare("
            INSERT INTO products (product_name, sku, barcode, category_id, unit, price, cost_price, reorder_level, status, created_by)
            VALUES (:name, :sku, :barcode, :cat, 'pcs', :price, :cost_price, 10, 'active', :uid)
        ");
        $stmt->execute([
            ':name'       => $name,
            ':sku'        => $sku,
            ':barcode'    => $barcode !== '' ? $barcode : null,
            ':cat'        => $categoryId,
            ':price'      => $price,
            ':cost_price' => $price,
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
    [$errors, $name, $category, $sku, $barcode, $stock, $price] = validate_product_payload($body);
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
            SET product_name = :name, sku = :sku, barcode = :barcode, category_id = :cat, price = :price
            WHERE product_id = :id
        ")->execute([
            ':name'    => $name,
            ':sku'     => $sku,
            ':barcode' => $barcode !== '' ? $barcode : null,
            ':cat'     => $categoryId,
            ':price'   => $price,
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

        $stmt2 = $pdo->prepare('SELECT COALESCE(SUM(quantity_on_hand), 0) AS total FROM inventory WHERE product_id = :id');
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
