<?php
/**
 * Customer CRUD, phone lookup (used by checkout), and purchase history.
 * GET  api/customers.php                        -> list, with lifetime value
 * GET  api/customers.php?action=lookup&phone=X   -> single customer match by phone (checkout)
 * GET  api/customers.php?action=history&id=X     -> a customer's order history
 * POST api/customers.php                         -> create
 * PUT  api/customers.php?id=X                    -> update
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'lookup') {
    api_require_permission('customers.view');
    $phone = trim((string) ($_GET['phone'] ?? ''));
    if ($phone === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Phone number is required.']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT customer_id, customer_name, phone, email, loyalty_points FROM customers WHERE phone = :phone LIMIT 1');
    $stmt->execute([':phone' => $phone]);
    $row = $stmt->fetch();
    echo json_encode(['customer' => $row ?: null]);
    exit;
}

if ($method === 'GET' && $action === 'history') {
    api_require_permission('customers.view');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(422); echo json_encode(['error' => 'Customer id is required.']); exit; }
    $stmt = $pdo->prepare("
        SELECT order_id, order_number, grand_total, payment_method, order_status, order_date
        FROM orders WHERE customer_id = :id ORDER BY order_date DESC LIMIT 100
    ");
    $stmt->execute([':id' => $id]);
    echo json_encode(['orders' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'GET') {
    api_require_permission('customers.view');
    $rows = $pdo->query("
        SELECT c.*, COUNT(o.order_id) order_count, COALESCE(SUM(o.grand_total), 0) lifetime_value
        FROM customers c
        LEFT JOIN orders o ON o.customer_id = c.customer_id AND o.order_status = 'completed'
        GROUP BY c.customer_id
        ORDER BY c.customer_name
    ")->fetchAll();
    echo json_encode(['customers' => $rows]);
    exit;
}

$user = api_require_permission('customers.manage');
api_verify_csrf();
$body = api_json_body();
$name = trim((string) ($body['customer_name'] ?? ''));

if ($name === '' || mb_strlen($name) > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'A customer name of up to 100 characters is required.']);
    exit;
}

$data = [
    ':name' => $name,
    ':phone' => trim((string) ($body['phone'] ?? '')) ?: null,
    ':email' => trim((string) ($body['email'] ?? '')) ?: null,
    ':points' => max(0, (float) ($body['loyalty_points'] ?? 0)),
];

if ($data[':email'] && !filter_var($data[':email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a valid customer email address.']);
    exit;
}

if ($method === 'POST') {
    $pdo->prepare('INSERT INTO customers (customer_name, phone, email, loyalty_points) VALUES (:name, :phone, :email, :points)')
        ->execute($data);
    $id = (int) $pdo->lastInsertId();
    api_log_activity($pdo, $user['id'], 'add', 'customers', $id, "Customer {$name} created");
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(422); echo json_encode(['error' => 'Customer id is required.']); exit; }

if ($method === 'PUT') {
    $data[':id'] = $id;
    $pdo->prepare('UPDATE customers SET customer_name = :name, phone = :phone, email = :email, loyalty_points = :points WHERE customer_id = :id')
        ->execute($data);
    api_log_activity($pdo, $user['id'], 'update', 'customers', $id, "Customer {$name} updated");
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
