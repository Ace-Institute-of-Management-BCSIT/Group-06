<?php
/**
 * Stock-alert detection. Scans products/batches for low stock, out-of-stock,
 * and near/past-expiry conditions, writing to the existing `alerts` table
 * (dashboard low-stock/expiry panels already read from it) and mirroring
 * each new alert into `notifications` (broadcast — user_id NULL — since
 * stock alerts are relevant to every role with notifications.view).
 *
 * Idempotent: skips creating a duplicate alert while an unacknowledged one
 * already exists for the same product/batch+type, so this is safe to call
 * on every dashboard load, stock adjustment, and checkout.
 */

declare(strict_types=1);

function create_notification(PDO $pdo, ?int $userId, string $type, string $title, string $message, ?string $entityType = null, ?int $entityId = null): void
{
    $pdo->prepare('
        INSERT INTO notifications (user_id, notification_type, title, message, entity_type, entity_id)
        VALUES (:uid, :type, :title, :msg, :etype, :eid)
    ')->execute([
        ':uid' => $userId, ':type' => $type, ':title' => $title,
        ':msg' => $message, ':etype' => $entityType, ':eid' => $entityId,
    ]);
}

function check_stock_alerts(PDO $pdo): void
{
    try {
        check_stock_level_alerts($pdo);
        check_expiry_alerts($pdo);
    } catch (Throwable $e) {
        // Non-fatal — never let alert scanning break the calling request.
    }
}

function check_stock_level_alerts(PDO $pdo): void
{
    $rows = $pdo->query("
        SELECT p.product_id, p.product_name, p.reorder_level,
               COALESCE(SUM(i.quantity_on_hand), 0) AS stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE p.status = 'active'
        GROUP BY p.product_id, p.product_name, p.reorder_level
    ")->fetchAll();

    $existsStmt = $pdo->prepare('SELECT alert_id FROM alerts WHERE product_id = :pid AND alert_type = :type AND is_acknowledged = 0 LIMIT 1');
    $insertAlert = $pdo->prepare('INSERT INTO alerts (alert_type, product_id, message, severity) VALUES (:type, :pid, :msg, :sev)');

    foreach ($rows as $r) {
        $stock = (int) $r['stock'];
        $reorder = (int) $r['reorder_level'];
        $type = $stock <= 0 ? 'out_of_stock' : ($stock <= $reorder ? 'low_stock' : null);
        if ($type === null) {
            continue;
        }

        $existsStmt->execute([':pid' => $r['product_id'], ':type' => $type]);
        if ($existsStmt->fetch()) {
            continue;
        }

        $severity = $type === 'out_of_stock' ? 'critical' : 'warning';
        $message = $type === 'out_of_stock'
            ? "{$r['product_name']} is out of stock."
            : "{$r['product_name']} is low on stock ({$stock} left, reorder at {$reorder}).";

        $insertAlert->execute([':type' => $type, ':pid' => $r['product_id'], ':msg' => $message, ':sev' => $severity]);
        create_notification(
            $pdo, null, $type,
            $type === 'out_of_stock' ? 'Out of Stock' : 'Low Stock',
            $message, 'products', (int) $r['product_id']
        );
    }
}

function check_expiry_alerts(PDO $pdo): void
{
    $batches = $pdo->query("
        SELECT b.batch_id, b.product_id, b.expiry_date, p.product_name
        FROM product_batches b
        JOIN products p ON p.product_id = b.product_id
        WHERE b.quantity > 0 AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ")->fetchAll();

    $existsStmt = $pdo->prepare("SELECT alert_id FROM alerts WHERE batch_id = :bid AND alert_type = 'expiry' AND is_acknowledged = 0 LIMIT 1");
    $insertAlert = $pdo->prepare("INSERT INTO alerts (alert_type, product_id, batch_id, message, severity) VALUES ('expiry', :pid, :bid, :msg, :sev)");

    foreach ($batches as $b) {
        $existsStmt->execute([':bid' => $b['batch_id']]);
        if ($existsStmt->fetch()) {
            continue;
        }

        $daysLeft = (int) round((strtotime((string) $b['expiry_date']) - strtotime(date('Y-m-d'))) / 86400);
        $severity = $daysLeft <= 7 ? 'critical' : 'warning';
        $message = $daysLeft < 0
            ? "{$b['product_name']} batch has expired."
            : "{$b['product_name']} batch expires in {$daysLeft} day(s).";

        $insertAlert->execute([':pid' => $b['product_id'], ':bid' => $b['batch_id'], ':msg' => $message, ':sev' => $severity]);
        create_notification($pdo, null, 'expiry', 'Expiry Alert', $message, 'products', (int) $b['product_id']);
    }
}
