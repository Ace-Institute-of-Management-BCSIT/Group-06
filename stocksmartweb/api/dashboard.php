<?php
/**
 * ============================================================================
 *  StockSmart — Dashboard API (api/dashboard.php)
 * ============================================================================
 *  GET api/dashboard.php -> the exact shape dashboard.html's init() expects:
 *    { stats, weeklySales[], recentSales[], lowStockProducts[], expiryAlerts[], activity[] }
 *
 *  Honesty notes:
 *    - Most stat cards report trend/dir as neutral (0%, 'up') because the
 *      schema has no historical snapshot table to compare against — see the
 *      same note in api/inventory.php. "Today's Sales" is the one exception:
 *      its trend is a REAL comparison against yesterday's completed orders,
 *      since that only needs orders.order_date, which we already have.
 *    - The seeded demo orders are dated around 2026-06-18 to 06-20. Once the
 *      real checkout endpoint goes live and new orders start landing with
 *      today's date, weeklySales/todaysSales will reflect that immediately —
 *      there's nothing to "turn on" later, it's already querying live data.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/stock_status.php';
require_once __DIR__ . '/../helpers/notifications.php';
require_once __DIR__ . '/../helpers/alert_routes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

api_require_permission('dashboard.view');
check_stock_alerts($pdo);

function time_ago_dash(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  { $h = floor($diff / 3600); return $h . ' hour' . ($h == 1 ? '' : 's') . ' ago'; }
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}

function map_activity_type_dash(string $type): string
{
    switch ($type) {
        case 'add':      return 'add';
        case 'checkout': return 'checkout';
        case 'alert':    return 'alert';
        case 'update':
        case 'delete':
        case 'transfer':
        default:         return 'update';
    }
}

/* ---------- Counts / stats ---------- */
$totalProducts = (int) $pdo->query("SELECT COUNT(*) c FROM products WHERE status != 'discontinued'")->fetch()['c'];

// Counted through stock_products_needing_restock() — the ONE query behind the
// sidebar badge, the Inventory restock filter and the Products "Needs
// Restocking" filter. This block used to run its own copy of the comparison
// over `status != 'discontinued'` while that helper uses `status = 'active'`,
// so an inactive product was counted here and nowhere else and the dashboard
// card disagreed with the badge beside it.
$needingRestock = stock_products_needing_restock($pdo);

$lowStockCount = 0;
$outOfStockCount = 0;
foreach ($needingRestock as $p) {
    if ($p['state']['key'] === 'out_of_stock') {
        $outOfStockCount++;
    } else {
        $lowStockCount++; // Low Stock + Critical — both are "on the shelf but too few"
    }
}

$totalSuppliers = (int) $pdo->query("SELECT COUNT(*) c FROM suppliers WHERE status = 'active'")->fetch()['c'];

// Expired + expiring inside the shared 2-month window. The stat card, the
// panel below, the sidebar badge and the bell now all count the same thing.
$expiringSoonCount = (int) $pdo->query('
    SELECT COUNT(*) c FROM product_batches b
    WHERE ' . sql_expiry_alerting('b.expiry_date', 'b.quantity') . '
')->fetch()['c'];

$todaySales = (float) $pdo->query("
    SELECT COALESCE(SUM(grand_total),0) v FROM orders
    WHERE order_status = 'completed' AND DATE(order_date) = CURDATE()
")->fetch()['v'];

$yesterdaySales = (float) $pdo->query("
    SELECT COALESCE(SUM(grand_total),0) v FROM orders
    WHERE order_status = 'completed' AND DATE(order_date) = CURDATE() - INTERVAL 1 DAY
")->fetch()['v'];

$salesTrend = 0;
$salesDir   = 'up';
if ($yesterdaySales > 0) {
    $salesTrend = round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100);
    $salesDir   = $salesTrend >= 0 ? 'up' : 'down';
    $salesTrend = abs($salesTrend);
} elseif ($todaySales > 0) {
    $salesTrend = 100;
    $salesDir   = 'up';
}

$neutral = ['trend' => 0, 'dir' => 'up'];

$stats = [
    'totalProducts'  => ['value' => $totalProducts]              + $neutral,
    'lowStock'       => ['value' => $lowStockCount]               + $neutral,
    'expiringSoon'   => ['value' => $expiringSoonCount]           + $neutral,
    'totalSuppliers' => ['value' => $totalSuppliers]              + $neutral,
    'todaysSales'    => ['value' => round($todaySales, 2), 'trend' => $salesTrend, 'dir' => $salesDir],
    'restockAlerts'  => ['value' => $lowStockCount + $outOfStockCount] + $neutral,
];

/* ---------- Weekly sales chart (this week vs. same weekday last week) ---------- */
$salesByDay = $pdo->query("
    SELECT DATE(order_date) d, SUM(grand_total) total
    FROM orders
    WHERE order_status = 'completed' AND order_date >= (CURDATE() - INTERVAL 14 DAY)
    GROUP BY DATE(order_date)
")->fetchAll(PDO::FETCH_KEY_PAIR);

$weeklySales = [];
for ($i = 6; $i >= 0; $i--) {
    $thisDate = date('Y-m-d', strtotime("-{$i} days"));
    $lastDate = date('Y-m-d', strtotime("-" . ($i + 7) . " days"));
    $weeklySales[] = [
        'day'      => date('D', strtotime($thisDate)),
        'thisWeek' => (float) ($salesByDay[$thisDate] ?? 0),
        'lastWeek' => (float) ($salesByDay[$lastDate] ?? 0),
    ];
}
// Chart needs a non-zero max to render bar heights sensibly when there's no
// sales data yet (e.g. a brand new install before any checkout has run).
if (array_sum(array_column($weeklySales, 'thisWeek')) + array_sum(array_column($weeklySales, 'lastWeek')) === 0.0) {
    $weeklySales[count($weeklySales) - 1]['thisWeek'] = 0.01;
}

/* ---------- Recent sales (latest completed order line items) ---------- */
$recentRows = $pdo->query("
    SELECT c.category_name, oi.quantity, oi.line_total, DATE(o.order_date) AS order_date
    FROM order_items oi
    JOIN orders o     ON o.order_id = oi.order_id
    JOIN products p   ON p.product_id = oi.product_id
    JOIN categories c ON c.category_id = p.category_id
    WHERE o.order_status = 'completed'
    ORDER BY o.order_date DESC
    LIMIT 6
")->fetchAll();
$recentSales = array_map(fn($r) => [
    'category' => $r['category_name'],
    'qty'      => (float) $r['quantity'],
    'amount'   => (float) $r['line_total'],
    'date'     => $r['order_date'],
], $recentRows);

/* ---------- Low stock products (most critical first) ----------
 * Reuses $needingRestock — the same rows the stat cards above were counted
 * from — instead of issuing a second, near-identical query. The panel is
 * therefore guaranteed to list exactly the products the "Restocking Alerts"
 * number claims, including out-of-stock ones (something at zero needs
 * restocking more urgently than something merely low).
 *
 * Each row carries its own `link`, built by helpers/alert_routes.php, so the
 * page does not construct URLs itself.
 */
$lowStockProducts = array_map(static function (array $p): array {
    return [
        'id'      => $p['product_id'],
        'name'    => $p['product_name'],
        'stock'   => $p['available'],
        'reorder' => $p['reorder_level'],
        'status'  => $p['state']['label'],
        'cls'     => $p['state']['cls'],
        'link'    => alert_route_product($p['product_id']),
    ];
}, array_slice($needingRestock, 0, 5));

/* ---------- Expiry alerts ----------
 * Expired first, then soonest — expiry_batches_alerting() already returns the
 * set ordered by date, and expired dates sort before upcoming ones naturally.
 * The window is the shared 2 months; batches with no expiry never appear.
 * Each row carries batch_id so the panel can link straight to the batch.
 */
$expiryAlerts = array_map(static fn (array $b): array => [
    'batchId'    => $b['batch_id'],
    'productId'  => $b['product_id'],
    'name'       => $b['product_name'],
    'batch'      => $b['batch_number'],
    'stock'      => $b['quantity'],
    'expiryDate' => $b['expiry_date'],
    'daysLeft'   => $b['state']['daysLeft'],
    'status'     => $b['state']['key'],
    'link'       => alert_route_batch($b['batch_id']),
], array_slice(expiry_batches_alerting($pdo), 0, 5));

/* ---------- Activity feed ---------- */
$activityRows = $pdo->query("
    SELECT activity_type, description, created_at
    FROM activity_logs
    WHERE activity_type IN ('add','update','delete','checkout','alert','transfer')
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();
$activity = array_map(fn($a) => [
    'type' => map_activity_type_dash($a['activity_type']),
    'text' => $a['description'],
    'time' => time_ago_dash($a['created_at']),
], $activityRows);

echo json_encode([
    'stats'            => $stats,
    'weeklySales'      => $weeklySales,
    'recentSales'      => $recentSales,
    'lowStockProducts' => $lowStockProducts,
    'expiryAlerts'     => $expiryAlerts,
    'activity'         => $activity,
]);
