<?php
/**
 * ============================================================================
 *  StockSmart — Checkout / POS Payment API (api/checkout.php)
 * ============================================================================
 *  POST api/checkout.php
 *      body: { items:[{productId, quantity, unitPrice}], itemsTotal,
 *              discountAmount, loyaltyDiscount, grandTotal, paymentMethod,
 *              customerId? }
 *      -> { ok: true, orderNumber, orderId, grandTotal }
 *
 *  Validates stock, recomputes item pricing AND tax from the DB (never
 *  trusts the client's numbers for money), writes the order + order_items,
 *  deducts inventory across whichever warehouse(s) actually have stock, and
 *  logs every movement for the audit trail — all inside one transaction.
 *
 *  Design notes / simplifications, stated plainly rather than hidden:
 *    - orders.warehouse_id is a single column per order (one sale = one
 *      selling location), but the POS UI has no warehouse picker and
 *      products.html/inventory.html show stock split across warehouses.
 *      CHECKOUT_WAREHOUSE_ID below is the "selling location" recorded on
 *      the order itself; stock is actually deducted from whichever real
 *      warehouse(s) have it (see deduct_stock()), and each deduction is
 *      logged with its TRUE warehouse_id in stock_movements, so the audit
 *      trail stays accurate even though the order header uses one location.
 *    - customer_id comes from the request body, falling back to whatever
 *      customer is attached to the session cart (see cart_helper.php);
 *      NULL means a walk-in sale.
 *    - tax_amount is recomputed server-side from system_settings.default_tax_rate
 *      applied to (itemsTotal - discountAmount) — the client's tax figure is
 *      informational only and never trusted for the actual charge.
 *    - Payment method is Cash-only in the current UI; the payment_method
 *      ENUM still supports card/mobile_wallet/bank_transfer for when
 *      additional methods are added later.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../cart_helper.php';
require_once __DIR__ . '/../helpers/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = api_require_permission('checkout.use');
api_verify_csrf();

const CHECKOUT_WAREHOUSE_ID = 4; // "Store Front" in the seeded warehouses table

function map_payment_method(string $label): string
{
    $label = strtolower(trim($label));
    if (str_contains($label, 'card') || str_contains($label, 'contactless')) return 'card';
    if (str_contains($label, 'qr') || str_contains($label, 'wallet') || str_contains($label, 'nepal pay')) return 'mobile_wallet';
    if (str_contains($label, 'cash')) return 'cash';
    if (str_contains($label, 'bank')) return 'bank_transfer';
    return 'other';
}

/**
 * Deducts $qty units of $productId from whichever warehouse(s) actually
 * have stock (preferring CHECKOUT_WAREHOUSE_ID first), splitting across
 * multiple warehouse rows if necessary. Throws if total available stock
 * (checked by the caller beforehand) somehow isn't enough — this should
 * never fire in practice since we pre-validate, but it's a safety net.
 */
function deduct_stock(PDO $pdo, int $productId, float $qty, int $orderId, int $performedBy): void
{
    $stmt = $pdo->prepare('
        SELECT inventory_id, warehouse_id, quantity_on_hand, quantity_reserved,
               (quantity_on_hand - quantity_reserved) AS available
        FROM inventory
        WHERE product_id = :pid AND (quantity_on_hand - quantity_reserved) > 0
        ORDER BY (warehouse_id = :checkout_wh) DESC, available DESC
    ');
    $stmt->execute([':pid' => $productId, ':checkout_wh' => CHECKOUT_WAREHOUSE_ID]);
    $rows = $stmt->fetchAll();

    $remaining = $qty;
    foreach ($rows as $row) {
        if ($remaining <= 0) break;
        $take = min($remaining, (float) $row['available']);
        if ($take <= 0) continue;

        $newOnHand = (int) $row['quantity_on_hand'] - (int) round($take);
        $pdo->prepare('UPDATE inventory SET quantity_on_hand = :qty WHERE inventory_id = :iid')
            ->execute([':qty' => $newOnHand, ':iid' => $row['inventory_id']]);

        $pdo->prepare("
            INSERT INTO stock_movements (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, performed_by)
            VALUES (:pid, :wid, 'sale_out', :qty, 'orders', :oid, :uid)
        ")->execute([
            ':pid' => $productId, ':wid' => (int) $row['warehouse_id'], ':qty' => (int) round($take),
            ':oid' => $orderId, ':uid' => $performedBy,
        ]);

        $remaining -= $take;
    }

    if ($remaining > 0.0001) {
        throw new RuntimeException("Ran out of stock partway through fulfilling product #{$productId}.");
    }
}

/* ---------- Parse + validate the request ---------- */
$body  = api_json_body();
$items = $body['items'] ?? [];

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Cart is empty — add at least one item before paying.']);
    exit;
}

$paymentLabel = trim((string) ($body['paymentMethod'] ?? ''));
if ($paymentLabel === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please select a payment method.']);
    exit;
}

$discountAmount  = max(0, (float) ($body['discountAmount']  ?? 0));
$loyaltyDiscount = max(0, (float) ($body['loyaltyDiscount'] ?? 0));

$customerId = (int) ($body['customerId'] ?? 0);
if ($customerId <= 0) {
    $customerId = (int) (cart_get_customer_id() ?? 0);
}
if ($customerId > 0) {
    $custCheck = $pdo->prepare('SELECT customer_id FROM customers WHERE customer_id = :id');
    $custCheck->execute([':id' => $customerId]);
    if (!$custCheck->fetch()) {
        $customerId = 0;
    }
}

$cleanItems = [];
foreach ($items as $it) {
    $productId = (int) ($it['productId'] ?? 0);
    $quantity  = (float) ($it['quantity'] ?? 0);
    if ($productId <= 0 || $quantity <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Every cart item needs a valid product and quantity.']);
        exit;
    }
    $cleanItems[] = ['productId' => $productId, 'quantity' => $quantity];
}

/* ---------- Recompute pricing from the DB — never trust client prices ---------- */
$productIds = array_column($cleanItems, 'productId');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $pdo->prepare("
    SELECT p.product_id, p.product_name, p.price,
           COALESCE(SUM(i.quantity_on_hand),0) - COALESCE(SUM(i.quantity_reserved),0) AS available
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.product_id
    WHERE p.product_id IN ($placeholders) AND p.status = 'active'
    GROUP BY p.product_id, p.product_name, p.price
");
$stmt->execute($productIds);
$productsById = [];
foreach ($stmt->fetchAll() as $r) {
    $productsById[(int) $r['product_id']] = $r;
}

$itemsTotal = 0.0;
$lineItems  = [];
foreach ($cleanItems as $it) {
    $p = $productsById[$it['productId']] ?? null;
    if (!$p) {
        http_response_code(404);
        echo json_encode(['error' => "One of the items in your cart is no longer available."]);
        exit;
    }
    if ((float) $p['available'] < $it['quantity']) {
        http_response_code(422);
        echo json_encode(['error' => "Not enough stock for \"{$p['product_name']}\" — only {$p['available']} left."]);
        exit;
    }
    $unitPrice = (float) $p['price'];
    $lineTotal = round($unitPrice * $it['quantity'], 2);
    $itemsTotal += $lineTotal;
    $lineItems[] = [
        'productId' => $it['productId'],
        'name'      => $p['product_name'],
        'quantity'  => $it['quantity'],
        'unitPrice' => $unitPrice,
        'lineTotal' => $lineTotal,
    ];
}

// Discounts can never exceed what's actually in the cart.
$discountAmount = min($discountAmount, $itemsTotal);

// Tax is recomputed server-side from system_settings, never trusted from the client.
$taxRatePct = 0.0;
try {
    $taxStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_tax_rate'");
    $taxStmt->execute();
    $taxRatePct = (float) ($taxStmt->fetchColumn() ?: 0);
} catch (Throwable $e) { /* system_settings not migrated yet — 0% tax */ }
$taxAmount = round(max(0, $itemsTotal - $discountAmount) * ($taxRatePct / 100), 2);

$loyaltyDiscount = min($loyaltyDiscount, max(0, $itemsTotal - $discountAmount + $taxAmount));
$grandTotal      = round($itemsTotal - $discountAmount - $loyaltyDiscount + $taxAmount, 2);

$paymentMethod = map_payment_method($paymentLabel);

/* ---------- Generate a unique order number ---------- */
$orderNumber = null;
for ($attempt = 0; $attempt < 5; $attempt++) {
    $candidate = 'SC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
    $check = $pdo->prepare('SELECT order_id FROM orders WHERE order_number = :n LIMIT 1');
    $check->execute([':n' => $candidate]);
    if (!$check->fetch()) {
        $orderNumber = $candidate;
        break;
    }
}
if ($orderNumber === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not generate a unique order number. Please try again.']);
    exit;
}

/* ---------- Write everything in one transaction ---------- */
try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO orders (order_number, customer_id, cashier_id, warehouse_id,
                             items_total, discount_amount, loyalty_discount, tax_amount,
                             grand_total, payment_method, order_status)
        VALUES (:num, :cust, :cashier, :wh, :items_total, :disc, :loyalty, :tax, :grand, :method, 'completed')
    ")->execute([
        ':num'         => $orderNumber,
        ':cust'        => $customerId > 0 ? $customerId : null,
        ':cashier'     => $user['id'],
        ':wh'          => CHECKOUT_WAREHOUSE_ID,
        ':items_total' => $itemsTotal,
        ':disc'        => $discountAmount,
        ':loyalty'     => $loyaltyDiscount,
        ':tax'         => $taxAmount,
        ':grand'       => $grandTotal,
        ':method'      => $paymentMethod,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, discount_amount)
        VALUES (:oid, :pid, :qty, :price, 0)
    ");
    foreach ($lineItems as $li) {
        $itemStmt->execute([
            ':oid' => $orderId, ':pid' => $li['productId'], ':qty' => $li['quantity'], ':price' => $li['unitPrice'],
        ]);
        deduct_stock($pdo, $li['productId'], $li['quantity'], $orderId, $user['id']);
    }

    // Clear the session cart now that the sale is recorded.
    cart_clear();

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not complete the sale: ' . $e->getMessage()]);
    exit;
}

api_log_activity(
    $pdo, $user['id'], 'checkout', 'orders', $orderId,
    "Checkout completed — order #{$orderNumber} (Rs. " . number_format($grandTotal, 2) . ")"
);
check_stock_alerts($pdo);

echo json_encode([
    'ok'          => true,
    'orderId'     => $orderId,
    'orderNumber' => $orderNumber,
    'grandTotal'  => $grandTotal,
]);
