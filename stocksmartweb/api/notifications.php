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
 * Each notification carries a `link`, resolved SERVER-SIDE from its stable
 * entity_type/entity_id — never by matching the product name out of the
 * message text. The target row is looked up before the link is emitted, so a
 * notification about a since-deleted product or batch comes back with
 * link: null and the bell renders it as plain text instead of a dead link.
 *
 *   low_stock / out_of_stock -> products.php?product=<id>   (highlights the row)
 *   expiry                   -> expiry.php?batch=<id>       (highlights the batch)
 *                               expiry.php?product=<id>     (older rows that
 *                               recorded the product rather than the batch)
 *
 * badgeCounts backs the sidebar's ALERTS section (see partials/sidebar.php and
 * assets/js/nav-guard.js). They come from alert_counts() in
 * helpers/notifications.php, which counts live products/batches using
 * helpers/stock_status.php — the same rules the Inventory and Expiry pages
 * those badges link to apply. Counting unacknowledged `alerts` rows instead
 * (the old approach) drifted: restocking a product left its alert row behind,
 * so the badge stayed high while the page it linked to showed nothing.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/notifications.php';
require_once __DIR__ . '/../helpers/alert_routes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

/**
 * Attaches a `link` to each notification, verifying the target still exists.
 * Lookups are batched — two queries total, regardless of feed length.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function notifications_attach_links(PDO $pdo, array $rows): array
{
    $productIds = [];
    $batchIds = [];

    foreach ($rows as $r) {
        $id = (int) ($r['entity_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if (($r['entity_type'] ?? '') === 'product_batches') {
            $batchIds[$id] = $id;
        } elseif (($r['entity_type'] ?? '') === 'products') {
            $productIds[$id] = $id;
        }
    }

    $liveProducts = [];
    if ($productIds !== []) {
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $pdo->prepare("SELECT product_id FROM products WHERE product_id IN ($in)");
        $stmt->execute(array_values($productIds));
        foreach ($stmt->fetchAll() as $row) {
            $liveProducts[(int) $row['product_id']] = true;
        }
    }

    $liveBatches = [];
    if ($batchIds !== []) {
        $in = implode(',', array_fill(0, count($batchIds), '?'));
        $stmt = $pdo->prepare("SELECT batch_id FROM product_batches WHERE batch_id IN ($in)");
        $stmt->execute(array_values($batchIds));
        foreach ($stmt->fetchAll() as $row) {
            $liveBatches[(int) $row['batch_id']] = true;
        }
    }

    foreach ($rows as &$r) {
        $r['link'] = null;
        $id = (int) ($r['entity_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $entity = (string) ($r['entity_type'] ?? '');
        $type = (string) ($r['notification_type'] ?? '');

        // Destinations come from helpers/alert_routes.php — the same functions
        // the sidebar and dashboard use, so no page invents its own URL.
        if ($entity === 'product_batches' && isset($liveBatches[$id])) {
            $r['link'] = alert_route_batch($id);
        } elseif ($entity === 'products' && isset($liveProducts[$id])) {
            $r['link'] = $type === 'expiry'
                ? alert_route_product_batches($id)
                : alert_route_product($id);
        }
    }
    unset($r);

    return $rows;
}

if ($method === 'GET') {
    api_require_permission('notifications.view');
    $rows = $pdo->query("
        SELECT notification_id, notification_type, title, message, entity_type, entity_id, read_at, created_at
        FROM notifications
        ORDER BY created_at DESC
        LIMIT 30
    ")->fetchAll();
    $unread = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL')->fetchColumn();

    echo json_encode([
        'notifications' => notifications_attach_links($pdo, $rows),
        'unreadCount' => $unread,
        'badgeCounts' => alert_counts($pdo),
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
