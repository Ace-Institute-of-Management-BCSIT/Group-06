<?php
/**
 * ============================================================================
 *  StockSmart — Products API (api/products.php)
 * ============================================================================
 *  A small JSON REST endpoint over the `products` + `inventory` tables.
 *  Mirrors the shape of the in-memory `products` array that products.html
 *  used to hold, so the front-end JS only needs its data layer swapped —
 *  the rendering code (renderTable, renderStats, etc.) stays the same.
 *
 *  Endpoints:
 *    GET    api/products.php          -> list all products (with live stock)
 *    POST   api/products.php          -> create a product (JSON body)
 *    PUT    api/products.php?id=5     -> update a product   (JSON body)
 *    DELETE api/products.php?id=5     -> delete a product
 *
 *  Stock is read from / written to a single "default" warehouse so the
 *  Products page can show one stock number per product, matching the
 *  original UI. (The full multi-warehouse picture lives on the Inventory
 *  page, backed by the same `inventory` table.)
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

// The warehouse used for the single "Stock (units)" field on the Products
// page. Change this if you want Add Product to stock a different location.
const DEFAULT_WAREHOUSE_ID = 1; // Warehouse A (see stocksmart.sql seed data)

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleList($pdo);
            break;
        case 'POST':
            handleCreate($pdo);
            break;
        case 'PUT':
            handleUpdate($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/* ---------------------------------------------------------------------- */

/**
 * GET — list every product with its category name and total available
 * stock across all warehouses, shaped to match the front-end's expected
 * fields: id, name, sub, category, sku, stock, threshold, price, icon.
 */
function handleList(PDO $pdo): void
{
    $sql = "
        SELECT
            p.product_id        AS id,
            p.product_name       AS name,
            c.category_name      AS category,
            c.category_name      AS sub,
            p.sku                AS sku,
            p.reorder_level       AS threshold,
            p.price              AS price,
            p.icon_emoji          AS icon,
            COALESCE(SUM(i.quantity_available), 0) AS stock
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE p.status = 'active'
        GROUP BY p.product_id, p.product_name, c.category_name, p.sku,
                 p.reorder_level, p.price, p.icon_emoji
        ORDER BY p.product_name
    ";
    $rows = db_select($pdo, $sql);

    // Cast numeric strings to real numbers so the front-end's Number()
    // math (e.g. stock*price) works without surprises.
    foreach ($rows as &$row) {
        $row['id']        = (int) $row['id'];
        $row['threshold'] = (int) $row['threshold'];
        $row['stock']     = (int) $row['stock'];
        $row['price']     = (float) $row['price'];
    }
    echo json_encode($rows);
}

/**
 * POST — create a new product, look up (or create) its category, and
 * open an inventory row at the default warehouse with the given stock.
 */
function handleCreate(PDO $pdo): void
{
    $data = readJsonBody();

    $name     = trim((string)($data['name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $sku      = trim((string)($data['sku'] ?? ''));
    $stock    = max(0, (int)($data['stock'] ?? 0));
    $price    = max(0, (float)($data['price'] ?? 0));

    if ($name === '' || $category === '' || $sku === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name, category and sku are required']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $categoryId = findOrCreateCategory($pdo, $category);

        // A reasonable default reorder level, same heuristic the old
        // front-end-only version used: max(10, 1.4x opening stock).
        $reorderLevel = max(10, (int) round($stock * 1.4));

        $stmt = $pdo->prepare(
            'INSERT INTO products (product_name, sku, category_id, icon_emoji, price, reorder_level, status)
             VALUES (:name, :sku, :category_id, :icon, :price, :reorder, "active")'
        );
        $stmt->execute([
            ':name'      => $name,
            ':sku'       => $sku,
            ':category_id' => $categoryId,
            ':icon'      => '📦',
            ':price'     => $price,
            ':reorder'   => $reorderLevel,
        ]);
        $productId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand)
             VALUES (:pid, :wid, :qty)'
        );
        $stmt->execute([
            ':pid' => $productId,
            ':wid' => DEFAULT_WAREHOUSE_ID,
            ':qty' => $stock,
        ]);

        logActivity($pdo, 'add', 'products', $productId, "$name was added to inventory");

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($e instanceof PDOException && (int) $e->errorInfo[1] === 1062) {
            http_response_code(409);
            echo json_encode(['error' => 'A product with that SKU already exists']);
            return;
        }
        throw $e;
    }

    http_response_code(201);
    echo json_encode(['id' => $productId, 'message' => 'Product created']);
}

/**
 * PUT — update name/category/sku/price/reorder level, and adjust stock
 * at the default warehouse to match the submitted value.
 */
function handleUpdate(PDO $pdo): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'id is required']);
        return;
    }

    $data = readJsonBody();
    $name     = trim((string)($data['name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $sku      = trim((string)($data['sku'] ?? ''));
    $stock    = max(0, (int)($data['stock'] ?? 0));
    $price    = max(0, (float)($data['price'] ?? 0));

    if ($name === '' || $category === '' || $sku === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name, category and sku are required']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $categoryId = findOrCreateCategory($pdo, $category);

        $stmt = $pdo->prepare(
            'UPDATE products
             SET product_name = :name, sku = :sku, category_id = :category_id, price = :price
             WHERE product_id = :id'
        );
        $stmt->execute([
            ':name' => $name, ':sku' => $sku, ':category_id' => $categoryId,
            ':price' => $price, ':id' => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            // product_id may not exist
            $exists = db_select($pdo, 'SELECT product_id FROM products WHERE product_id = :id', [':id' => $id]);
            if (!$exists) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
                return;
            }
        }

        // Upsert the stock figure at the default warehouse.
        $stmt = $pdo->prepare(
            'INSERT INTO inventory (product_id, warehouse_id, quantity_on_hand)
             VALUES (:pid, :wid, :qty)
             ON DUPLICATE KEY UPDATE quantity_on_hand = VALUES(quantity_on_hand)'
        );
        $stmt->execute([':pid' => $id, ':wid' => DEFAULT_WAREHOUSE_ID, ':qty' => $stock]);

        logActivity($pdo, 'update', 'products', $id, "Stock count updated for $name");

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($e instanceof PDOException && (int) $e->errorInfo[1] === 1062) {
            http_response_code(409);
            echo json_encode(['error' => 'A product with that SKU already exists']);
            return;
        }
        throw $e;
    }

    echo json_encode(['id' => $id, 'message' => 'Product updated']);
}

/**
 * DELETE — soft-delete by default (status = 'discontinued') so sales
 * history referencing this product is never broken. Pass ?hard=1 to
 * attempt a real DELETE instead (will fail with 409 if the product has
 * order history, by design — see fk_order_items_product RESTRICT).
 */
function handleDelete(PDO $pdo): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'id is required']);
        return;
    }

    $hard = isset($_GET['hard']) && $_GET['hard'] === '1';

    if ($hard) {
        try {
            $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = :id');
            $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] === 1451) {
                http_response_code(409);
                echo json_encode(['error' => 'Cannot delete: this product has sales history. It was not removed.']);
                return;
            }
            throw $e;
        }
    } else {
        $stmt = $pdo->prepare("UPDATE products SET status = 'discontinued' WHERE product_id = :id");
        $stmt->execute([':id' => $id]);
    }

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }

    echo json_encode(['id' => $id, 'message' => 'Product deleted']);
}

/* ---------------------------------------------------------------------- */

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function findOrCreateCategory(PDO $pdo, string $categoryName): int
{
    $stmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = :name');
    $stmt->execute([':name' => $categoryName]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['category_id'];
    }
    $stmt = $pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)');
    $stmt->execute([':name' => $categoryName]);
    return (int) $pdo->lastInsertId();
}

function logActivity(PDO $pdo, string $type, string $entityType, int $entityId, string $description): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (activity_type, entity_type, entity_id, description)
         VALUES (:type, :entity_type, :entity_id, :description)'
    );
    $stmt->execute([
        ':type' => $type, ':entity_type' => $entityType,
        ':entity_id' => $entityId, ':description' => $description,
    ]);
}
