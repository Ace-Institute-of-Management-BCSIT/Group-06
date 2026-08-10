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

$stockRow = $pdo->query("
    SELECT
        SUM(CASE WHEN available <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN available > 0 AND available <= reorder_level THEN 1 ELSE 0 END) AS low_stock
    FROM (
        SELECT p.product_id, p.reorder_level,
               COALESCE(SUM(i.quantity_on_hand),0) - COALESCE(SUM(i.quantity_reserved),0) AS available
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE p.status != 'discontinued'
        GROUP BY p.product_id, p.reorder_level
    ) t
")->fetch();
$lowStockCount    = (int) ($stockRow['low_stock'] ?? 0);
$outOfStockCount  = (int) ($stockRow['out_of_stock'] ?? 0);

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
 * Carries product_id so each row can link to the product it names, instead of
 * being dead text the user has to go and search for. Out-of-stock products are
 * included: the panel is the restocking shortlist, and something at zero needs
 * restocking more urgently than something merely low.
 */
$available = sql_available_stock('i');
$lowStockRows = $pdo->query("
    SELECT p.product_id, p.product_name,
           {$available} AS available,
           p.reorder_level
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.product_id
    WHERE p.status != 'discontinued'
    GROUP BY p.product_id, p.product_name, p.reorder_level
    HAVING available <= p.reorder_level
    ORDER BY available ASC
    LIMIT 5
")->fetchAll();
$lowStockProducts = array_map(static function (array $r): array {
    $state = stock_state((int) $r['available'], (int) $r['reorder_level']);
    return [
        'id'      => (int) $r['product_id'],
        'name'    => $r['product_name'],
        'stock'   => (int) $r['available'],
        'reorder' => (int) $r['reorder_level'],
        'status'  => $state['label'],
        'cls'     => $state['cls'],
    ];
}, $lowStockRows);

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
