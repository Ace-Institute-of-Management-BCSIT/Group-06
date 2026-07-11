<?php
/**
 * Real notification feed (backs the notification bell on every page).
 * Notifications are broadcast (user_id NULL, visible to anyone with
 * notifications.view) since stock alerts matter to the whole team, not
 * one cashier — so "read" is a shared state, not per-user.
 *
 * GET  api/notifications.php                 -> { notifications[], unreadCount, badgeCounts:{restock,expiry} }
 * POST api/notifications.php?action=read&id=X -> mark one notification read
 * POST api/notifications.php?action=read_all  -> mark every notification read
 *
 * badgeCounts backs the sidebar's ALERTS section (see partials/sidebar.php
 * and assets/js/nav-guard.js) — the same two numbers on every page, sourced
 * from the same unacknowledged `alerts` rows check_stock_alerts() writes.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    api_require_permission('notifications.view');
    $rows = $pdo->query("
        SELECT notification_id, notification_type, title, message, entity_type, entity_id, read_at, created_at
        FROM notifications
        ORDER BY created_at DESC
        LIMIT 30
    ")->fetchAll();
    $unread = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL')->fetchColumn();

    $restockCount = 0;
    $expiryCount = 0;
    try {
        $restockCount = (int) $pdo->query("SELECT COUNT(*) FROM alerts WHERE alert_type IN ('low_stock','out_of_stock') AND is_acknowledged = 0")->fetchColumn();
        $expiryCount = (int) $pdo->query("SELECT COUNT(*) FROM alerts WHERE alert_type = 'expiry' AND is_acknowledged = 0")->fetchColumn();
    } catch (Throwable $e) { /* alerts table not migrated yet */ }

    echo json_encode([
        'notifications' => $rows,
        'unreadCount' => $unread,
        'badgeCounts' => ['restock' => $restockCount, 'expiry' => $expiryCount],
    ]);
    exit;
}

$user = api_require_permission('notifications.view');
api_verify_csrf();

if ($method === 'POST' && $action === 'read') {
    $id = (int) ($_GET['id'] ?? 0);
    $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE notification_id = :id AND read_at IS NULL')->execute([':id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'POST' && $action === 'read_all') {
    $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL')->execute();
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
