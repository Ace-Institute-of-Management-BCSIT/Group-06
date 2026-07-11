<?php
/**
 * Shared receipt/invoice renderer — used by api/receipt.php (browser print
 * view + PDF download) so checkout, sales reprint, and PDF export all
 * produce byte-identical receipts from one template.
 */

declare(strict_types=1);

require_once __DIR__ . '/currency.php';

function receipt_system_settings(PDO $pdo): array
{
    $defaults = [
        'company_name' => 'StockSmart',
        'receipt_footer' => 'Thank you for your business.',
        'invoice_prefix' => 'INV-',
        'default_tax_rate' => '0',
    ];
    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
        foreach ($rows as $r) {
            $defaults[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Throwable $e) { /* system_settings not migrated yet — use defaults */ }
    return $defaults;
}

function receipt_fetch_order(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare("
        SELECT o.*, c.customer_name, c.phone AS customer_phone, u.full_name AS cashier_name
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        JOIN users u ON u.user_id = o.cashier_id
        WHERE o.order_id = :id
    ");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $itemsStmt = $pdo->prepare("
        SELECT oi.quantity, oi.unit_price, oi.discount_amount, oi.line_total, p.product_name, p.sku
        FROM order_items oi JOIN products p ON p.product_id = oi.product_id
        WHERE oi.order_id = :id
    ");
    $itemsStmt->execute([':id' => $orderId]);
    $order['items'] = $itemsStmt->fetchAll();

    return $order;
}

function receipt_render_html(PDO $pdo, array $order): string
{
    $settings = receipt_system_settings($pdo);
    $company = htmlspecialchars($settings['company_name']);
    $footer = htmlspecialchars($settings['receipt_footer']);

    $rows = '';
    foreach ($order['items'] as $item) {
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars($item['product_name']) . '<div class="sku">' . htmlspecialchars($item['sku']) . '</div></td>'
            . '<td class="num">' . number_format((float) $item['quantity'], 2) . '</td>'
            . '<td class="num">' . money_npr((float) $item['unit_price']) . '</td>'
            . '<td class="num">' . ((float) $item['discount_amount'] > 0 ? money_npr((float) $item['discount_amount']) : '—') . '</td>'
            . '<td class="num">' . money_npr((float) $item['line_total']) . '</td>'
            . '</tr>';
    }

    $statusLabel = ucwords(str_replace('_', ' ', (string) $order['order_status']));

    return '<!doctype html><html><head><meta charset="utf-8"><title>Receipt ' . htmlspecialchars($order['order_number']) . '</title><style>
        *{box-sizing:border-box;}
        body{font-family:"Courier New",monospace;font-size:12px;color:#111;max-width:380px;margin:0 auto;padding:20px;}
        .center{text-align:center;}
        h1{font-size:16px;margin:0 0 2px;}
        .muted{color:#555;font-size:11px;}
        .divider{border-top:1px dashed #999;margin:10px 0;}
        table{width:100%;border-collapse:collapse;margin:10px 0;}
        th{font-size:10px;text-transform:uppercase;text-align:left;border-bottom:1px solid #333;padding:4px 2px;}
        td{font-size:11.5px;padding:4px 2px;vertical-align:top;}
        td.num, th.num{text-align:right;}
        .sku{font-size:9.5px;color:#777;}
        .totals td{border:none;padding:2px;}
        .totals .label{color:#555;}
        .grand{font-weight:bold;font-size:14px;border-top:1px solid #333;padding-top:6px;}
        .footer{margin-top:16px;text-align:center;font-size:11px;color:#555;}
        .status{display:inline-block;padding:2px 8px;border:1px solid #999;border-radius:10px;font-size:10px;text-transform:uppercase;margin-top:4px;}
        @media print { body{padding:0;} }
    </style></head><body>
        <div class="center">
            <h1>' . $company . '</h1>
            <div class="muted">Invoice / Receipt</div>
            <div class="status">' . htmlspecialchars($statusLabel) . '</div>
        </div>
        <div class="divider"></div>
        <div>Invoice #: <b>' . htmlspecialchars($order['order_number']) . '</b></div>
        <div>Date: ' . htmlspecialchars(date('d M Y, H:i', strtotime((string) $order['order_date']))) . '</div>
        <div>Cashier: ' . htmlspecialchars($order['cashier_name']) . '</div>
        <div>Customer: ' . htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer') . ($order['customer_phone'] ? ' (' . htmlspecialchars($order['customer_phone']) . ')' : '') . '</div>
        <div class="divider"></div>
        <table>
            <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Disc.</th><th class="num">Total</th></tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>
        <div class="divider"></div>
        <table class="totals">
            <tr><td class="label">Subtotal</td><td class="num">' . money_npr((float) $order['items_total']) . '</td></tr>
            <tr><td class="label">Discount</td><td class="num">-' . money_npr((float) $order['discount_amount']) . '</td></tr>
            <tr><td class="label">Loyalty Discount</td><td class="num">-' . money_npr((float) $order['loyalty_discount']) . '</td></tr>
            <tr><td class="label">Tax</td><td class="num">' . money_npr((float) $order['tax_amount']) . '</td></tr>
            <tr class="grand"><td>Grand Total</td><td class="num">' . money_npr((float) $order['grand_total']) . '</td></tr>
        </table>
        <div class="divider"></div>
        <div>Payment Method: ' . htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $order['payment_method']))) . '</div>
        <div class="footer">' . $footer . '</div>
    </body></html>';
}
