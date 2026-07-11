<?php
/**
 * System settings (company name, currency, tax rate, invoice prefix, receipt footer).
 * GET api/settings.php -> any authenticated user (checkout/receipts need these values)
 * PUT api/settings.php -> requires "settings.manage"; body is {key: value, ...}
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$allowedKeys = ['company_name', 'currency', 'timezone', 'invoice_prefix', 'receipt_footer', 'default_tax_rate'];

if ($method === 'GET') {
    api_require_login();
    $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    echo json_encode(['settings' => $settings]);
    exit;
}

if ($method === 'PUT') {
    $user = api_require_permission('settings.manage');
    api_verify_csrf();
    $body = api_json_body();

    $stmt = $pdo->prepare('
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES (:key, :value, :uid)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
    ');
    foreach ($allowedKeys as $key) {
        if (!array_key_exists($key, $body)) continue;
        $stmt->execute([':key' => $key, ':value' => trim((string) $body[$key]), ':uid' => $user['id']]);
    }

    api_log_activity($pdo, $user['id'], 'update', 'system_settings', null, 'System settings updated');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
