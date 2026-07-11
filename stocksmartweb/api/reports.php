<?php
/**
 * Reports: Sales, Inventory, Purchases, Suppliers, Customers, Profit, Revenue.
 * GET api/reports.php?type=sales|inventory|purchases|suppliers|customers|profit|revenue
 *     &from=YYYY-MM-DD&to=YYYY-MM-DD&format=json|pdf|excel|csv
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/export.php';
require_once __DIR__ . '/../helpers/currency.php';

api_require_permission('reports.view');

$type = (string) ($_GET['type'] ?? 'sales');
$format = (string) ($_GET['format'] ?? 'json');
$from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
$fromDt = $from . ' 00:00:00';
$toDt = $to . ' 23:59:59';

$title = '';
$headers = [];
$rows = [];

switch ($type) {
    case 'sales':
        $title = 'Sales Report';
        $headers = ['Date', 'Orders', 'Items Sold', 'Revenue'];
        $stmt = $pdo->prepare("
            SELECT DATE(o.order_date) AS d, COUNT(DISTINCT o.order_id) AS orders,
                   COALESCE(SUM(oi.quantity), 0) AS items,
                   COALESCE(SUM(o.grand_total), 0) AS revenue
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.order_id
            WHERE o.order_status IN ('completed','partially_refunded') AND o.order_date BETWEEN :from AND :to
            GROUP BY DATE(o.order_date) ORDER BY d
        ");
        $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['d'], (int) $r['orders'], (float) $r['items'], money_npr((float) $r['revenue'])];
        }
        break;

    case 'inventory':
        $title = 'Inventory Report';
        $headers = ['SKU', 'Product', 'Category', 'In Stock', 'Reorder Level', 'Stock Value'];
        $stmt = $pdo->query("
            SELECT p.sku, p.product_name, c.category_name, p.reorder_level, p.cost_price,
                   COALESCE(SUM(i.quantity_on_hand), 0) AS stock
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.product_id
            WHERE p.status = 'active'
            GROUP BY p.product_id, p.sku, p.product_name, c.category_name, p.reorder_level, p.cost_price
            ORDER BY p.product_name
        ");
        foreach ($stmt->fetchAll() as $r) {
            $value = (float) $r['stock'] * (float) $r['cost_price'];
            $rows[] = [$r['sku'], $r['product_name'], $r['category_name'], (int) $r['stock'], (int) $r['reorder_level'], money_npr($value)];
        }
        break;

    case 'purchases':
        $title = 'Purchases Report';
        $headers = ['PO Number', 'Supplier', 'Status', 'Date', 'Total'];
        $stmt = $pdo->prepare("
            SELECT po.po_number, s.supplier_name, po.status, po.created_at, po.grand_total
            FROM purchase_orders po
            JOIN suppliers s ON s.supplier_id = po.supplier_id
            WHERE po.created_at BETWEEN :from AND :to
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['po_number'], $r['supplier_name'], ucfirst($r['status']), substr($r['created_at'], 0, 10), money_npr((float) $r['grand_total'])];
        }
        break;

    case 'suppliers':
        $title = 'Suppliers Report';
        $headers = ['Supplier', 'Status', 'Products Supplied', 'Purchase Orders', 'Total Spend'];
        $stmt = $pdo->query("
            SELECT s.supplier_name, s.status,
                   COALESCE(pc.product_count, 0) AS product_count,
                   COALESCE(po.purchase_count, 0) AS purchase_count,
                   COALESCE(po.purchase_total, 0) AS purchase_total
            FROM suppliers s
            LEFT JOIN (SELECT supplier_id, COUNT(*) product_count FROM products GROUP BY supplier_id) pc ON pc.supplier_id = s.supplier_id
            LEFT JOIN (SELECT supplier_id, COUNT(*) purchase_count, SUM(grand_total) purchase_total FROM purchase_orders WHERE status != 'cancelled' GROUP BY supplier_id) po ON po.supplier_id = s.supplier_id
            ORDER BY s.supplier_name
        ");
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['supplier_name'], ucfirst($r['status']), (int) $r['product_count'], (int) $r['purchase_count'], money_npr((float) $r['purchase_total'])];
        }
        break;

    case 'customers':
        $title = 'Customers Report';
        $headers = ['Customer', 'Phone', 'Orders', 'Lifetime Value', 'Loyalty Points'];
        $stmt = $pdo->query("
            SELECT c.customer_name, c.phone, COUNT(o.order_id) order_count,
                   COALESCE(SUM(o.grand_total), 0) lifetime_value, c.loyalty_points
            FROM customers c
            LEFT JOIN orders o ON o.customer_id = c.customer_id AND o.order_status = 'completed'
            GROUP BY c.customer_id
            ORDER BY lifetime_value DESC
        ");
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['customer_name'], $r['phone'] ?: '—', (int) $r['order_count'], money_npr((float) $r['lifetime_value']), (int) $r['loyalty_points']];
        }
        break;

    case 'profit':
        $title = 'Profit Report';
        $headers = ['Date', 'Revenue', 'Cost of Goods', 'Profit', 'Margin %'];
        $stmt = $pdo->prepare("
            SELECT DATE(o.order_date) AS d,
                   COALESCE(SUM(oi.line_total), 0) AS revenue,
                   COALESCE(SUM(oi.quantity * p.cost_price), 0) AS cogs
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.order_id
            JOIN products p ON p.product_id = oi.product_id
            WHERE o.order_status IN ('completed','partially_refunded') AND o.order_date BETWEEN :from AND :to
            GROUP BY DATE(o.order_date) ORDER BY d
        ");
        $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
        foreach ($stmt->fetchAll() as $r) {
            $revenue = (float) $r['revenue'];
            $cogs = (float) $r['cogs'];
            $profit = $revenue - $cogs;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;
            $rows[] = [$r['d'], money_npr($revenue), money_npr($cogs), money_npr($profit), $margin . '%'];
        }
        break;

    case 'revenue':
        $title = 'Revenue Report';
        $headers = ['Month', 'Orders', 'Revenue'];
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS ym, COUNT(*) AS orders, COALESCE(SUM(o.grand_total), 0) AS revenue
            FROM orders o
            WHERE o.order_status IN ('completed','partially_refunded') AND o.order_date BETWEEN :from AND :to
            GROUP BY ym ORDER BY ym
        ");
        $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['ym'], (int) $r['orders'], money_npr((float) $r['revenue'])];
        }
        break;

    default:
        http_response_code(422);
        echo json_encode(['error' => 'Unknown report type.']);
        exit;
}

if ($format === 'json') {
    echo json_encode(['title' => $title, 'from' => $from, 'to' => $to, 'headers' => $headers, 'rows' => $rows]);
    exit;
}

$baseFilename = strtolower($type) . '-report-' . date('Ymd-His');

if ($format === 'csv') {
    export_csv($headers, $rows, $baseFilename . '.csv');
}

if ($format === 'excel') {
    export_excel($headers, $rows, $baseFilename . '.xlsx', $title);
}

if ($format === 'pdf') {
    $html = '<html><head><meta charset="utf-8"><style>
        body{font-family:sans-serif;font-size:12px;color:#0f1226;}
        h1{font-size:18px;margin-bottom:2px;}
        .meta{color:#666;font-size:11px;margin-bottom:16px;}
        table{width:100%;border-collapse:collapse;}
        th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:11px;}
        th{background:#f5f6fb;}
    </style></head><body>';
    $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
    $html .= '<div class="meta">StockSmart &middot; ' . htmlspecialchars($from) . ' to ' . htmlspecialchars($to) . ' &middot; Generated ' . date('Y-m-d H:i') . '</div>';
    $html .= '<table><thead><tr>';
    foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string) $cell) . '</td>';
        $html .= '</tr>';
    }
    if (empty($rows)) {
        $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center;color:#999;">No data for this range.</td></tr>';
    }
    $html .= '</tbody></table></body></html>';
    export_pdf($html, $baseFilename . '.pdf');
}

http_response_code(422);
echo json_encode(['error' => 'Unknown export format.']);
