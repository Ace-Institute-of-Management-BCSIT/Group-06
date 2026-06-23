<?php
/**
 * ============================================================================
 *  StockSmart — Dashboard API (api/dashboard.php)
 * ============================================================================
 *  GET api/dashboard.php
 *  Returns a single JSON object shaped exactly like the old SAMPLE_DATA
 *  object dashboard.html used to hold, so only the data layer changes —
 *  every render*() function in dashboard.html is untouched.
 *
 *  Trend/percentage figures (the little up/down arrows on stat cards) have
 *  no historical baseline to compare against yet in a fresh install, so
 *  they are computed as 0% / 'up' rather than invented. Once the system
 *  has been running for a few weeks, these can be upgraded to real
 *  week-over-week comparisons using the same orders/stock_movements data.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode([
        'stats'             => getStats($pdo),
        'weeklySales'       => getWeeklySales($pdo),
        'recentSales'       => getRecentSales($pdo),
        'lowStockProducts'  => getLowStockProducts($pdo),
        'expiryAlerts'      => getExpiryAlerts($pdo),
        'activity'          => getActivity($pdo),
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
          (SELECT COUNT(*) FROM products WHERE status = 'active') AS total_products,
          (SELECT COUNT(*) FROM vw_product_stock_summary WHERE stock_status = 'Low Stock') AS low_stock_count,
          (SELECT COUNT(*) FROM vw_expiry_alerts WHERE days_left BETWEEN 0 AND 7) AS expiring_soon_count,
          (SELECT COUNT(*) FROM suppliers WHERE status = 'active') AS total_suppliers,
          (SELECT ROUND(COALESCE(SUM(grand_total),0),2) FROM orders
             WHERE order_status='completed' AND DATE(order_date)=CURDATE()) AS todays_sales,
          (SELECT COUNT(*) FROM vw_product_stock_summary WHERE stock_status = 'Out of Stock') AS restock_alerts
    ")[0];

    // Shape matches SAMPLE_DATA.stats: { key: {value, trend, dir} }
    // trend/dir are placeholders (no historical baseline yet) — see file header.
    $mk = fn($value) => ['value' => (float) $value, 'trend' => 0, 'dir' => 'up'];

    return [
        'totalProducts'  => $mk($row['total_products']),
        'lowStock'       => $mk($row['low_stock_count']),
        'expiringSoon'   => $mk($row['expiring_soon_count']),
        'totalSuppliers' => $mk($row['total_suppliers']),
        'todaysSales'    => $mk($row['todays_sales']),
        'restockAlerts'  => $mk($row['restock_alerts']),
    ];
}

function getWeeklySales(PDO $pdo): array
{
    // Last 7 days, oldest first, matching {day, thisWeek, lastWeek}.
    $rows = db_select($pdo, "
        SELECT DATE(order_date) AS sale_date, DAYNAME(order_date) AS day_name,
               SUM(grand_total) AS day_total
        FROM orders
        WHERE order_status = 'completed'
          AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(order_date), DAYNAME(order_date)
        ORDER BY sale_date ASC
    ");

    $byDate = [];
    foreach ($rows as $r) {
        $byDate[$r['sale_date']] = (float) $r['day_total'];
    }

    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $out[] = [
            'day'      => date('D', strtotime($date)),
            'thisWeek' => $byDate[$date] ?? 0,
            'lastWeek' => 0, // no prior-period data available yet on a fresh install
        ];
    }
    return $out;
}

function getRecentSales(PDO $pdo): array
{
    // Grouped by category + date to match {category, qty, amount, date}.
    $rows = db_select($pdo, "
        SELECT c.category_name AS category,
               SUM(oi.quantity) AS qty,
               ROUND(SUM(oi.line_total),2) AS amount,
               DATE(o.order_date) AS date
        FROM order_items oi
        JOIN orders o     ON o.order_id = oi.order_id
        JOIN products p   ON p.product_id = oi.product_id
        JOIN categories c ON c.category_id = p.category_id
        WHERE o.order_status = 'completed'
        GROUP BY c.category_name, DATE(o.order_date)
        ORDER BY date DESC
        LIMIT 10
    ");
    foreach ($rows as &$r) {
        $r['qty'] = (float) $r['qty'];
        $r['amount'] = (float) $r['amount'];
    }
    return $rows;
}

function getLowStockProducts(PDO $pdo): array
{
    // Matches {name, stock, reorder}.
    $rows = db_select($pdo, "
        SELECT product_name AS name, total_available AS stock, reorder_level AS `reorder`
        FROM vw_product_stock_summary
        WHERE stock_status = 'Low Stock'
        ORDER BY total_available ASC
        LIMIT 8
    ");
    foreach ($rows as &$r) {
        $r['stock'] = (int) $r['stock'];
        $r['reorder'] = (int) $r['reorder'];
    }
    return $rows;
}

function getExpiryAlerts(PDO $pdo): array
{
    // Matches {name, batch, stock, daysLeft}.
    $rows = db_select($pdo, "
        SELECT product_name AS name, batch_number AS batch, quantity AS stock, days_left AS daysLeft
        FROM vw_expiry_alerts
        WHERE days_left <= 14
        ORDER BY days_left ASC
        LIMIT 8
    ");
    foreach ($rows as &$r) {
        $r['stock'] = (int) $r['stock'];
        $r['daysLeft'] = (int) $r['daysLeft'];
    }
    return $rows;
}

function getActivity(PDO $pdo): array
{
    // Matches {type, text, time}. 'type' must be one of the keys the
    // front-end's iconMap understands: add, update, checkout, alert.
    $rows = db_select($pdo, "
        SELECT activity_type, description, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $allowedTypes = ['add', 'update', 'checkout', 'alert'];
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
