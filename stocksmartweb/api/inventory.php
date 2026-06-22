<?php
/**
 * ============================================================================
 *  StockSmart — Inventory API (api/inventory.php)
 * ============================================================================
 *  GET api/inventory.php
 *  Returns { stats, products, activity } shaped exactly like the old
 *  SAMPLE_DATA object in inventory.html, so every render*() function in
 *  that file is left untouched — only the data layer changes.
 *
 *  Field names match what inventory.html actually reads:
 *    products[]: { id, name, sku, icon, category, inStock, reserved,
 *                  reorderLevel, location }
 *  inStock/reserved/available are summed across all warehouses per
 *  product, and `location` shows the warehouse holding the largest share
 *  of that product's stock (since the UI's table has one location column
 *  per row, not one row per warehouse).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';   // returns 401 JSON if not logged in

header('Content-Type: application/json; charset=utf-8');

try {
    $products = getProducts($pdo);
    echo json_encode([
        'stats'    => getStats($pdo),
        'products' => $products,
        'activity' => getActivity($pdo),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/* ---------------------------------------------------------------------- */

function getStats(PDO $pdo): array
{
    $row = db_select($pdo, "
        SELECT
          (SELECT COUNT(*) FROM vw_product_stock_summary) AS total_items,
          (SELECT COUNT(*) FROM vw_product_stock_summary WHERE stock_status = 'In Stock') AS in_stock,
          (SELECT COUNT(*) FROM vw_product_stock_summary WHERE stock_status = 'Low Stock') AS low_stock,
          (SELECT COUNT(*) FROM vw_product_stock_summary WHERE stock_status = 'Out of Stock') AS out_of_stock,
          (SELECT ROUND(SUM(i.quantity_on_hand * p.price),2)
             FROM inventory i JOIN products p ON p.product_id = i.product_id) AS total_value
    ")[0];

    $mk = fn($value) => ['value' => (float) $value, 'trend' => 0, 'dir' => 'up'];

    return [
        'totalItems' => $mk($row['total_items']),
        'inStock'    => $mk($row['in_stock']),
        'lowStock'   => $mk($row['low_stock']),
        'outOfStock' => $mk($row['out_of_stock']),
        'totalValue' => $mk($row['total_value']),
    ];
}

function getProducts(PDO $pdo): array
{
    // Sum stock across all warehouses per product...
    $rows = db_select($pdo, "
        SELECT
            p.product_id   AS id,
            p.product_name  AS name,
            p.sku           AS sku,
            p.icon_emoji     AS icon,
            c.category_name  AS category,
            p.reorder_level  AS reorderLevel,
            COALESCE(SUM(i.quantity_on_hand),0)  AS inStock,
            COALESCE(SUM(i.quantity_reserved),0) AS reserved
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE p.status = 'active'
        GROUP BY p.product_id, p.product_name, p.sku, p.icon_emoji,
                 c.category_name, p.reorder_level
        ORDER BY p.product_name
    ");

    // ...then attach the warehouse holding the largest stock share for
    // each product, so the single "Location" column has one sensible value.
    $primaryLocation = [];
    foreach (db_select($pdo, "
        SELECT i.product_id, w.warehouse_name, i.quantity_on_hand,
               ROW_NUMBER() OVER (PARTITION BY i.product_id ORDER BY i.quantity_on_hand DESC) AS rn
        FROM inventory i
        JOIN warehouses w ON w.warehouse_id = i.warehouse_id
    ") as $r) {
        if ($r['rn'] == 1) {
            $primaryLocation[$r['product_id']] = $r['warehouse_name'];
        }
    }

    foreach ($rows as &$row) {
        $row['id']           = (int) $row['id'];
        $row['inStock']      = (int) $row['inStock'];
        $row['reserved']     = (int) $row['reserved'];
        $row['reorderLevel'] = (int) $row['reorderLevel'];
        $row['location']     = $primaryLocation[$row['id']] ?? 'Unassigned';
    }
    return $rows;
}

function getActivity(PDO $pdo): array
{
    // Matches {type, text, time}. The inventory page's iconMap
    // understands: add, update, transfer, alert.
    $rows = db_select($pdo, "
        SELECT activity_type, description, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $allowedTypes = ['add', 'update', 'transfer', 'alert'];
    $out = [];
    foreach ($rows as $r) {
        $type = in_array($r['activity_type'], $allowedTypes, true) ? $r['activity_type'] : 'update';
        $out[] = [
            'type' => $type,
            'text' => htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8'),
            'time' => relativeTime($r['created_at']),
        ];
    }
    return $out;
}

function relativeTime(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) { $m = (int) floor($diff / 60); return "$m minute" . ($m === 1 ? '' : 's') . " ago"; }
    if ($diff < 86400) { $h = (int) floor($diff / 3600); return "$h hour" . ($h === 1 ? '' : 's') . " ago"; }
    $d = (int) floor($diff / 86400);
    return "$d day" . ($d === 1 ? '' : 's') . " ago";
}
