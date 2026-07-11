<?php
/**
 * Receipt/invoice rendering — shared by checkout (post-sale receipt), the
 * Sales page (reprint), and PDF download.
 * GET api/receipt.php?order_id=X&format=html|pdf
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/receipt.php';
require_once __DIR__ . '/../helpers/export.php';

api_require_permission('sales.view');

$orderId = (int) ($_GET['order_id'] ?? 0);
$format = (string) ($_GET['format'] ?? 'html');

if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'order_id is required.']);
    exit;
}

$order = receipt_fetch_order($pdo, $orderId);
if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found.']);
    exit;
}

$html = receipt_render_html($pdo, $order);

if ($format === 'pdf') {
    export_pdf($html, 'receipt-' . $order['order_number'] . '.pdf');
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
