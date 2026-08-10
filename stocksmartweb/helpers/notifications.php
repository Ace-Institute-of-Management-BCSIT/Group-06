<?php
/**
 * ============================================================================
 *  StockSmart — Stock & Expiry Alert Detection (helpers/notifications.php)
 * ============================================================================
 *  Scans products and batches for restocking and expiry conditions, writing to
 *  the `alerts` table (the audit/history record) and mirroring each new alert
 *  into `notifications` (the bell feed — broadcast, user_id NULL, since stock
 *  alerts matter to every role with notifications.view).
 *
 *  Every threshold used here comes from helpers/stock_status.php. This file
 *  deliberately contains no numbers of its own: it used to compare
 *  SUM(quantity_on_hand) against reorder_level while the Products page
 *  compared a different quantity with a different operator, which is exactly
 *  the divergence stock_status.php now prevents.
 *
 *  Two behaviours worth knowing about:
 *
 *  1. RESOLUTION. Alerts are not just created, they are retired. When a
 *     product is restocked above its reorder level, or an expiring batch is
 *     sold/discarded, the matching unacknowledged alert is acknowledged
 *     automatically. Without this the sidebar badge only ever counted up:
 *     restocking an item left its "low stock" row sitting in `alerts` forever,
 *     so the badge disagreed with the Products page permanently.
 *
 *  2. LIVE COUNTS. alert_counts() derives the sidebar badge numbers from
 *     products/batches directly rather than from rows in `alerts`. The badge
 *     therefore always equals what the Inventory and Expiry pages list, even
 *     if alert scanning has not run since the last stock change.
 *
 *  Idempotent throughout — safe to call on every dashboard load, stock
 *  adjustment and checkout, which is what the callers do.
 * ========================================================================== */

declare(strict_types=1);

require_once __DIR__ . '/stock_status.php';

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

/* ============================================================================
 *  SHARED QUERIES
 * ==========================================================================
 *  These two functions are the only place the app asks "which products need
 *  restocking?" and "which batches are expiring?". The dashboard widget, the
 *  sidebar badge, the notification bell, the Inventory restock filter and the
 *  Expiry page all resolve to one of them, so they cannot disagree.
 */

/**
 * Every product whose available stock is at or below its own reorder level.
 *
 * @return array<int, array{product_id:int, product_name:string, sku:string, available:int, reorder_level:int, state:array}>
 */
function stock_products_needing_restock(PDO $pdo): array
{
    $available = sql_available_stock('i');

    $rows = $pdo->query("
        SELECT p.product_id, p.product_name, p.sku, p.reorder_level,
               {$available} AS available
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE p.status = 'active'
        GROUP BY p.product_id, p.product_name, p.sku, p.reorder_level
        HAVING available <= p.reorder_level
        ORDER BY available ASC, p.product_name ASC
    ")->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'product_id'    => (int) $r['product_id'],
            'product_name'  => (string) $r['product_name'],
            'sku'           => (string) $r['sku'],
            'available'     => (int) $r['available'],
            'reorder_level' => (int) $r['reorder_level'],
            'state'         => stock_state((int) $r['available'], (int) $r['reorder_level']),
        ];
    }
    return $out;
}

/**
 * Every batch that is expired or expiring inside the warning window, still
 * holding stock. Sorted expired-first, then soonest — the order the dashboard
 * and Expiry page both present.
 *
 * @return array<int, array{batch_id:int, product_id:int, product_name:string, batch_number:string, warehouse_name:string, quantity:int, expiry_date:string, state:array}>
 */
function expiry_batches_alerting(PDO $pdo): array
{
    $predicate = sql_expiry_alerting('b.expiry_date', 'b.quantity');

    $rows = $pdo->query("
        SELECT b.batch_id, b.product_id, b.batch_number, b.quantity, b.expiry_date,
               p.product_name, w.warehouse_name
        FROM product_batches b
        JOIN products p ON p.product_id = b.product_id
        LEFT JOIN warehouses w ON w.warehouse_id = b.warehouse_id
        WHERE {$predicate}
        ORDER BY b.expiry_date ASC
    ")->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'batch_id'       => (int) $r['batch_id'],
            'product_id'     => (int) $r['product_id'],
            'product_name'   => (string) $r['product_name'],
            'batch_number'   => (string) $r['batch_number'],
            'warehouse_name' => (string) ($r['warehouse_name'] ?? 'Unassigned'),
            'quantity'       => (int) $r['quantity'],
            'expiry_date'    => (string) $r['expiry_date'],
            'state'          => expiry_state((string) $r['expiry_date']),
        ];
    }
    return $out;
}

/**
 * The two sidebar badge numbers, computed live from the same rules the pages
 * they link to use.
 *
 * @return array{restock:int, expiry:int}
 */
function alert_counts(PDO $pdo): array
{
    try {
        return [
            'restock' => count(stock_products_needing_restock($pdo)),
            'expiry'  => count(expiry_batches_alerting($pdo)),
        ];
    } catch (Throwable $e) {
        return ['restock' => 0, 'expiry' => 0];
    }
}

/* ============================================================================
 *  ALERT WRITING / RESOLUTION
 * ========================================================================== */

function check_stock_level_alerts(PDO $pdo): void
{
    $needing = stock_products_needing_restock($pdo);

    $existsStmt  = $pdo->prepare('SELECT alert_id FROM alerts WHERE product_id = :pid AND alert_type = :type AND is_acknowledged = 0 LIMIT 1');
    $insertAlert = $pdo->prepare('INSERT INTO alerts (alert_type, product_id, message, severity) VALUES (:type, :pid, :msg, :sev)');

    $stillAlerting = [];

    foreach ($needing as $p) {
        $state = $p['state'];
        // alerts.alert_type only has low_stock / out_of_stock; "Critical" is a
        // display refinement of low stock, so it maps onto low_stock here and
        // carries severity=critical instead.
        $type = $state['key'] === 'out_of_stock' ? 'out_of_stock' : 'low_stock';
        $stillAlerting[$p['product_id'] . ':' . $type] = true;

        $existsStmt->execute([':pid' => $p['product_id'], ':type' => $type]);
        if ($existsStmt->fetch()) {
            continue;
        }

        $message = $state['key'] === 'out_of_stock'
            ? "{$p['product_name']} is out of stock."
            : "{$p['product_name']} is low on stock ({$p['available']} left, reorder at {$p['reorder_level']}).";

        $insertAlert->execute([
            ':type' => $type,
            ':pid'  => $p['product_id'],
            ':msg'  => $message,
            ':sev'  => $state['severity'],
        ]);

        create_notification(
            $pdo, null, $type,
            $state['key'] === 'out_of_stock' ? 'Out of Stock' : $state['label'],
            $message, 'products', $p['product_id']
        );
    }

    resolve_stale_stock_alerts($pdo, $stillAlerting);
}

/**
 * Acknowledges low_stock/out_of_stock alerts whose condition no longer holds —
 * the product was restocked, or its reorder level was lowered below current
 * stock, or it was discontinued.
 *
 * @param array<string, true> $stillAlerting keys of "productId:type" still valid
 */
function resolve_stale_stock_alerts(PDO $pdo, array $stillAlerting): void
{
    $open = $pdo->query("
        SELECT alert_id, product_id, alert_type
        FROM alerts
        WHERE alert_type IN ('low_stock','out_of_stock') AND is_acknowledged = 0
    ")->fetchAll();

    if ($open === []) {
        return;
    }

    $resolve = $pdo->prepare("UPDATE alerts SET is_acknowledged = 1, acknowledged_at = NOW() WHERE alert_id = :id");
    foreach ($open as $a) {
        $key = (int) $a['product_id'] . ':' . $a['alert_type'];
        if (!isset($stillAlerting[$key])) {
            $resolve->execute([':id' => (int) $a['alert_id']]);
            resolve_notification($pdo, $a['alert_type'], 'products', (int) $a['product_id']);
        }
    }
}

/**
 * Marks the bell notification behind a resolved alert as read.
 *
 * The `notifications` feed is a chronological log, so the row itself stays —
 * but leaving it UNREAD would keep "Whole Milk 1L is low on stock" sitting in
 * the bell as a live alert after the product was restocked, contradicting the
 * badge beside it, which is computed from current stock. Clearing the unread
 * flag keeps the bell's alert state in step with the badge and the pages,
 * while preserving the history.
 */
function resolve_notification(PDO $pdo, string $type, string $entityType, int $entityId): void
{
    try {
        $pdo->prepare('
            UPDATE notifications
            SET read_at = NOW()
            WHERE notification_type = :type
              AND entity_type = :etype
              AND entity_id = :eid
              AND read_at IS NULL
        ')->execute([':type' => $type, ':etype' => $entityType, ':eid' => $entityId]);
    } catch (Throwable $e) {
        // Non-fatal — never let feed housekeeping break the calling request.
    }
}

function check_expiry_alerts(PDO $pdo): void
{
    $batches = expiry_batches_alerting($pdo);

    $existsStmt  = $pdo->prepare("SELECT alert_id FROM alerts WHERE batch_id = :bid AND alert_type = 'expiry' AND is_acknowledged = 0 LIMIT 1");
    $insertAlert = $pdo->prepare("INSERT INTO alerts (alert_type, product_id, batch_id, message, severity) VALUES ('expiry', :pid, :bid, :msg, :sev)");

    $stillAlerting = [];

    foreach ($batches as $b) {
        $stillAlerting[$b['batch_id']] = true;

        $existsStmt->execute([':bid' => $b['batch_id']]);
        if ($existsStmt->fetch()) {
            continue;
        }

        // "Whole Wheat Bread (batch BT-2291) expired on 05 Aug 2026."
        // "Whole Milk 1L (batch BT-2410) expires on 15 Oct 2026 (in 21 days)."
        $message = "{$b['product_name']} (batch {$b['batch_number']}) " . expiry_phrase($b['expiry_date']) . '.';

        $insertAlert->execute([
            ':pid' => $b['product_id'],
            ':bid' => $b['batch_id'],
            ':msg' => $message,
            ':sev' => $b['state']['severity'],
        ]);

        // entity points at the BATCH, so the notification can link straight to
        // the row responsible rather than making the user hunt for it.
        create_notification(
            $pdo, null, 'expiry',
            $b['state']['key'] === 'expired' ? 'Expired Stock' : 'Expiring Soon',
            $message, 'product_batches', $b['batch_id']
        );
    }

    resolve_stale_expiry_alerts($pdo, $stillAlerting);
}

/**
 * Acknowledges expiry alerts for batches that have been sold down to zero,
 * deleted, or had their expiry date corrected to somewhere outside the window.
 *
 * @param array<int, true> $stillAlerting batch ids still legitimately alerting
 */
function resolve_stale_expiry_alerts(PDO $pdo, array $stillAlerting): void
{
    $open = $pdo->query("
        SELECT alert_id, batch_id FROM alerts
        WHERE alert_type = 'expiry' AND is_acknowledged = 0 AND batch_id IS NOT NULL
    ")->fetchAll();

    if ($open === []) {
        return;
    }

    $resolve = $pdo->prepare('UPDATE alerts SET is_acknowledged = 1, acknowledged_at = NOW() WHERE alert_id = :id');
    foreach ($open as $a) {
        if (!isset($stillAlerting[(int) $a['batch_id']])) {
            $resolve->execute([':id' => (int) $a['alert_id']]);
            resolve_notification($pdo, 'expiry', 'product_batches', (int) $a['batch_id']);
        }
    }
}
