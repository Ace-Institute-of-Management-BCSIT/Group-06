<?php
/**
 * Sales history, invoice detail, returns (partial or full), and void.
 * GET  api/sales.php                      -> list orders (filters: status, from, to, q)
 * GET  api/sales.php?action=detail&id=X   -> one order with items + prior returns
 * POST api/sales.php?action=return&id=X   -> return specific items (restores stock, refunds)
 * POST api/sales.php?action=void&id=X     -> void the entire order (restores all stock)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/notifications.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

/** Warehouse the sale was fulfilled from, needed to restore stock on return/void. */
function sales_order_warehouse(PDO $pdo, int $orderId): int
{
    $stmt = $pdo->prepare('SELECT warehouse_id FROM orders WHERE order_id = :id');
    $stmt->execute([':id' => $orderId]);
    return (int) $stmt->fetchColumn();
}

if ($method === 'GET' && $action === 'detail') {
    api_require_permission('sales.view');
    $id = (int) ($_GET['id'] ?? 0);

    $orderStmt = $pdo->prepare("
        SELECT o.*, c.customer_name, c.phone AS customer_phone, u.full_name AS cashier_name
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        JOIN users u ON u.user_id = o.cashier_id
        WHERE o.order_id = :id
    ");
    $orderStmt->execute([':id' => $id]);
    $order = $orderStmt->fetch();
    if (!$order) { http_response_code(404); echo json_encode(['error' => 'Order not found.']); exit; }

    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.product_name, p.sku,
               COALESCE((SELECT SUM(ri.quantity) FROM return_items ri WHERE ri.order_item_id = oi.order_item_id), 0) AS returned_quantity
        FROM order_items oi
        JOIN products p ON p.product_id = oi.product_id
        WHERE oi.order_id = :id
    ");
    $itemsStmt->execute([':id' => $id]);
    $order['items'] = $itemsStmt->fetchAll();

    $returnsStmt = $pdo->prepare("
        SELECT r.return_id, r.reason, r.refund_amount, r.created_at, u.full_name AS processed_by_name
        FROM returns r LEFT JOIN users u ON u.user_id = r.processed_by
        WHERE r.order_id = :id ORDER BY r.created_at DESC
    ");
    $returnsStmt->execute([':id' => $id]);
    $order['returns'] = $returnsStmt->fetchAll();

    echo json_encode(['order' => $order]);
    exit;
}

if ($method === 'GET') {
    api_require_permission('sales.view');
    $status = (string) ($_GET['status'] ?? '');
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    $q = trim((string) ($_GET['q'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') { $where[] = 'o.order_status = :status'; $params[':status'] = $status; }
    if ($from !== '') { $where[] = 'o.order_date >= :from'; $params[':from'] = $from . ' 00:00:00'; }
    if ($to !== '') { $where[] = 'o.order_date <= :to'; $params[':to'] = $to . ' 23:59:59'; }
    if ($q !== '') {
        $where[] = '(o.order_number LIKE :q OR c.customer_name LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT o.order_id, o.order_number, o.grand_total, o.payment_method, o.order_status, o.order_date,
               c.customer_name, u.full_name AS cashier_name,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
        FROM orders o
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        JOIN users u ON u.user_id = o.cashier_id
        {$whereSql}
        ORDER BY o.order_date DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    echo json_encode(['orders' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST' && $action === 'return') {
    $user = api_require_permission('sales.manage');
    api_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    $body = api_json_body();
    $reason = trim((string) ($body['reason'] ?? ''));
    $lines = is_array($body['items'] ?? null) ? $body['items'] : [];

    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = :id');
    $orderStmt->execute([':id' => $id]);
    $order = $orderStmt->fetch();
    if (!$order) { http_response_code(404); echo json_encode(['error' => 'Order not found.']); exit; }
    if (in_array($order['order_status'], ['refunded', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'This order is already ' . $order['order_status'] . '.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $itemStmt = $pdo->prepare('
            SELECT oi.*, COALESCE((SELECT SUM(ri.quantity) FROM return_items ri WHERE ri.order_item_id = oi.order_item_id), 0) AS already_returned
            FROM order_items oi WHERE oi.order_item_id = :id AND oi.order_id = :oid
        ');
        $insertReturnItem = $pdo->prepare('
            INSERT INTO return_items (return_id, order_item_id, product_id, quantity, unit_price, line_refund)
            VALUES (:rid, :oiid, :pid, :qty, :price, :refund)
        ');
        $updateInventory = $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + :qty WHERE product_id = :pid AND warehouse_id = :wid');
        $insertMovement = $pdo->prepare("
            INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, performed_by, notes)
            VALUES (:pid, :wid, 'return_in', :qty, 'orders', :ref, :uid, :notes)
        ");

        $pdo->prepare('INSERT INTO returns (order_id, processed_by, reason, refund_amount) VALUES (:oid, :uid, :reason, 0)')
            ->execute([':oid' => $id, ':uid' => $user['id'], ':reason' => $reason !== '' ? $reason : null]);
        $returnId = (int) $pdo->lastInsertId();

        $warehouseId = (int) $order['warehouse_id'];
        $totalRefund = 0.0;
        $anyProcessed = false;

        foreach ($lines as $line) {
            $orderItemId = (int) ($line['order_item_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($orderItemId <= 0 || $qty <= 0) continue;

            $itemStmt->execute([':id' => $orderItemId, ':oid' => $id]);
            $item = $itemStmt->fetch();
            if (!$item) continue;

            $availableToReturn = (float) $item['quantity'] - (float) $item['already_returned'];
            $qty = min($qty, max(0, $availableToReturn));
            if ($qty <= 0) continue;

            $effectiveUnitPrice = (float) $item['quantity'] > 0 ? (float) $item['line_total'] / (float) $item['quantity'] : 0;
            $lineRefund = round($effectiveUnitPrice * $qty, 2);
            $totalRefund += $lineRefund;
            $anyProcessed = true;

            $insertReturnItem->execute([
                ':rid' => $returnId, ':oiid' => $orderItemId, ':pid' => $item['product_id'],
                ':qty' => $qty, ':price' => $item['unit_price'], ':refund' => $lineRefund,
            ]);
            $updateInventory->execute([':qty' => (int) $qty, ':pid' => $item['product_id'], ':wid' => $warehouseId]);
            $insertMovement->execute([
                ':pid' => $item['product_id'], ':wid' => $warehouseId, ':qty' => (int) $qty,
                ':ref' => $id, ':uid' => $user['id'], ':notes' => "Returned from {$order['order_number']}",
            ]);
        }

        if (!$anyProcessed) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['error' => 'No valid items to return.']);
            exit;
        }

        $pdo->prepare('UPDATE returns SET refund_amount = :amt WHERE return_id = :id')->execute([':amt' => $totalRefund, ':id' => $returnId]);

        // Determine whether every unit across the order has now been returned.
        $totalsStmt = $pdo->prepare('
            SELECT COALESCE(SUM(oi.quantity), 0) AS total_qty,
                   COALESCE(SUM((SELECT SUM(ri.quantity) FROM return_items ri WHERE ri.order_item_id = oi.order_item_id)), 0) AS returned_qty
            FROM order_items oi WHERE oi.order_id = :id
        ');
        $totalsStmt->execute([':id' => $id]);
        $totals = $totalsStmt->fetch();
        $fullyReturned = (float) $totals['total_qty'] > 0 && (float) $totals['returned_qty'] >= (float) $totals['total_qty'];
        $newStatus = $fullyReturned ? 'refunded' : 'partially_refunded';

        $pdo->prepare('UPDATE orders SET order_status = :status WHERE order_id = :id')->execute([':status' => $newStatus, ':id' => $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not process the return: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'update', 'orders', $id, "Return processed for {$order['order_number']} (Rs. " . number_format($totalRefund, 2) . ")");
    check_stock_alerts($pdo);

    echo json_encode(['ok' => true, 'status' => $newStatus, 'refund_amount' => $totalRefund]);
    exit;
}

if ($method === 'POST' && $action === 'void') {
    $user = api_require_permission('sales.manage');
    api_verify_csrf();
    $id = (int) ($_GET['id'] ?? 0);
    $body = api_json_body();
    $reason = trim((string) ($body['reason'] ?? 'Voided by staff'));

    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = :id');
    $orderStmt->execute([':id' => $id]);
    $order = $orderStmt->fetch();
    if (!$order) { http_response_code(404); echo json_encode(['error' => 'Order not found.']); exit; }
    if ($order['order_status'] !== 'completed') {
        http_response_code(409);
        echo json_encode(['error' => 'Only a completed order can be voided (this one is ' . $order['order_status'] . ').']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $itemsStmt->execute([':id' => $id]);
        $items = $itemsStmt->fetchAll();

        $pdo->prepare('INSERT INTO returns (order_id, processed_by, reason, refund_amount) VALUES (:oid, :uid, :reason, :amt)')
            ->execute([':oid' => $id, ':uid' => $user['id'], ':reason' => $reason, ':amt' => $order['grand_total']]);
        $returnId = (int) $pdo->lastInsertId();

        $insertReturnItem = $pdo->prepare('
            INSERT INTO return_items (return_id, order_item_id, product_id, quantity, unit_price, line_refund)
            VALUES (:rid, :oiid, :pid, :qty, :price, :refund)
        ');
        $updateInventory = $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + :qty WHERE product_id = :pid AND warehouse_id = :wid');
        $insertMovement = $pdo->prepare("
            INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, performed_by, notes)
            VALUES (:pid, :wid, 'return_in', :qty, 'orders', :ref, :uid, :notes)
        ");

        foreach ($items as $item) {
            $insertReturnItem->execute([
                ':rid' => $returnId, ':oiid' => $item['order_item_id'], ':pid' => $item['product_id'],
                ':qty' => $item['quantity'], ':price' => $item['unit_price'], ':refund' => $item['line_total'],
            ]);
            $updateInventory->execute([':qty' => (int) $item['quantity'], ':pid' => $item['product_id'], ':wid' => (int) $order['warehouse_id']]);
            $insertMovement->execute([
                ':pid' => $item['product_id'], ':wid' => (int) $order['warehouse_id'], ':qty' => (int) $item['quantity'],
                ':ref' => $id, ':uid' => $user['id'], ':notes' => "Voided {$order['order_number']}",
            ]);
        }

        $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE order_id = :id")->execute([':id' => $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not void the order: ' . $e->getMessage()]);
        exit;
    }

    api_log_activity($pdo, $user['id'], 'update', 'orders', $id, "{$order['order_number']} was voided ({$reason})");
    check_stock_alerts($pdo);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
