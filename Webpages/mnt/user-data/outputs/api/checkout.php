<?php
/**
 * ============================================================================
 *  StockSmart — Checkout API (api/checkout.php)
 * ============================================================================
 *  POST api/checkout.php
 *
 *  Request body (matches the payload checkout.php's payBtn handler sends):
 *  {
 *    "items": [ { "productId": 8, "quantity": 2, "unitPrice": 150.00 }, ... ],
 *    "itemsTotal": 300.00,
 *    "discountAmount": 0,
 *    "loyaltyDiscount": 0,
 *    "grandTotal": 300.00,
 *    "paymentMethod": "cash"
 *  }
 *
 *  On success (HTTP 201):
 *    { "orderId": 6, "orderNumber": "SC-10486", "message": "Order saved successfully" }
 *
 *  On failure (HTTP 400/422/500):
 *    { "error": "human-readable message" }
 *
 *  WHAT THIS DOES, STEP BY STEP (all inside one PDO transaction — see
 *  "TRANSACTION SAFETY" below):
 *    1. Validates the cart isn't empty and every line is well-formed.
 *    2. Pre-checks every line against inventory.quantity_available so a
 *       clear, itemised "insufficient stock" error can be returned before
 *       any row is written (requirement: prevent checkout when requested
 *       quantity exceeds available stock).
 *    3. Generates the next order_number (continues the existing SC-#####
 *       sequence already used by orders in stocksmart.sql).
 *    4. INSERTs the orders header row.
 *    5. For each cart line: INSERTs order_items, decrements
 *       inventory.quantity_on_hand (guarded, never goes negative), and
 *       INSERTs a stock_movements row with movement_type = 'sale_out'.
 *    6. Logs the sale to activity_logs (same pattern products.php /
 *       inventory.php already use, so the Dashboard's Recent Activity
 *       feed picks this up automatically).
 *    7. Commits. If anything in steps 2–6 fails, the whole transaction
 *       rolls back — no partial order, no partial stock deduction.
 *
 *  NO NEW TABLES: every write here targets a table that already exists
 *  in stocksmart.sql (orders, order_items, inventory, stock_movements,
 *  activity_logs).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../cart_helper.php';

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------------
// There is no login/session system in this project yet, but orders.cashier_id
// and orders.warehouse_id are NOT NULL in the schema, so a sale needs an
// explicit actor and location. These match the values products.php /
// checkout.php's cart-loading query already assume (Warehouse A, the
// seeded "Bibek Thapa" cashier). Update these two constants once a login
// system exists so the real signed-in user/location is used instead.
// ---------------------------------------------------------------------------
const CHECKOUT_WAREHOUSE_ID = 1; // Warehouse A
const CHECKOUT_CASHIER_ID   = 4; // Bibek Thapa (role: Cashier) — see stocksmart.sql seed data

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing JSON request body']);
    exit;
}

// ---------------------------------------------------------------------------
// 1) VALIDATE THE REQUEST SHAPE
// ---------------------------------------------------------------------------
$items = $body['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Cart is empty — nothing to charge']);
    exit;
}

$cartLines = [];
foreach ($items as $i => $item) {
    $productId = (int)   ($item['productId'] ?? 0);
    $quantity  = (float) ($item['quantity']  ?? 0);
    $unitPrice = (float) ($item['unitPrice'] ?? -1);

    if ($productId <= 0) {
        http_response_code(422);
        echo json_encode(['error' => "Cart line " . ($i + 1) . " is missing a valid productId"]);
        exit;
    }
    if ($quantity <= 0) {
        http_response_code(422);
        echo json_encode(['error' => "Cart line " . ($i + 1) . " has an invalid quantity"]);
        exit;
    }
    if ($unitPrice < 0) {
        http_response_code(422);
        echo json_encode(['error' => "Cart line " . ($i + 1) . " has an invalid unit price"]);
        exit;
    }

    // Merge duplicate productId lines (defensive — the UI shouldn't send
    // duplicates, but two rows for the same product would otherwise cause
    // the stock pre-check below to under-count the true requested amount).
    if (isset($cartLines[$productId])) {
        $cartLines[$productId]['quantity'] += $quantity;
    } else {
        $cartLines[$productId] = ['productId' => $productId, 'quantity' => $quantity, 'unitPrice' => $unitPrice];
    }
}

$itemsTotal      = (float) ($body['itemsTotal'] ?? 0);
$discountAmount  = (float) ($body['discountAmount'] ?? 0);
$loyaltyDiscount = (float) ($body['loyaltyDiscount'] ?? 0);
$grandTotal      = (float) ($body['grandTotal'] ?? 0);
$paymentMethod   = (string) ($body['paymentMethod'] ?? 'cash');

$allowedMethods = ['cash', 'card', 'mobile_wallet', 'bank_transfer', 'other'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $paymentMethod = 'other';
}

if ($grandTotal < 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Grand total cannot be negative']);
    exit;
}

// ---------------------------------------------------------------------------
// 2) TRANSACTION SAFETY
//    Everything from here on runs inside one PDO transaction. A pre-check
//    against current stock runs first so we can report a precise,
//    itemised error ("X units of Y available, Z requested") rather than a
//    generic database failure — but the guarded UPDATE in step 5 is the
//    real safety net, since stock could theoretically change between the
//    pre-check and the write under concurrent load.
// ---------------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    // 2a) Stock pre-check — clear, itemised errors before writing anything.
    foreach ($cartLines as $line) {
        $stockRow = db_select($pdo, "
            SELECT p.product_name,
                   COALESCE(i.quantity_available, 0) AS available
            FROM products p
            LEFT JOIN inventory i
                   ON i.product_id = p.product_id AND i.warehouse_id = :wid
            WHERE p.product_id = :pid
        ", [':wid' => CHECKOUT_WAREHOUSE_ID, ':pid' => $line['productId']]);

        if (!$stockRow) {
            throw new RuntimeException("Product #{$line['productId']} does not exist");
        }

        $available = (float) $stockRow[0]['available'];
        $name      = $stockRow[0]['product_name'];

        if ($line['quantity'] > $available) {
            throw new RuntimeException(
                "Insufficient stock for {$name}: requested {$line['quantity']}, only {$available} available"
            );
        }
    }

    // 2b) Generate the next order number (continues the SC-##### sequence).
    $row = db_select($pdo, "
        SELECT CONCAT('SC-', LPAD(IFNULL(MAX(CAST(SUBSTRING(order_number, 4) AS UNSIGNED)), 10478) + 1, 5, '0')) AS next_order_number
        FROM orders
    ")[0];
    $orderNumber = $row['next_order_number'];

    // 2c) Create the order header (customer_id NULL = walk-in sale).
    $stmt = $pdo->prepare(
        'INSERT INTO orders
            (order_number, customer_id, cashier_id, warehouse_id,
             items_total, discount_amount, loyalty_discount, tax_amount, grand_total,
             payment_method, order_status)
         VALUES
            (:order_number, NULL, :cashier_id, :warehouse_id,
             :items_total, :discount_amount, :loyalty_discount, 0,
             :grand_total, :payment_method, "completed")'
    );
    $stmt->execute([
        ':order_number'     => $orderNumber,
        ':cashier_id'       => CHECKOUT_CASHIER_ID,
        ':warehouse_id'     => CHECKOUT_WAREHOUSE_ID,
        ':items_total'      => $itemsTotal,
        ':discount_amount'  => $discountAmount,
        ':loyalty_discount' => $loyaltyDiscount,
        ':grand_total'      => $grandTotal,
        ':payment_method'   => $paymentMethod,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    // 2d) Insert each line item, reduce inventory, log the stock movement.
    foreach ($cartLines as $line) {
        $productId = $line['productId'];
        $quantity  = $line['quantity'];
        $unitPrice = $line['unitPrice'];

        $stmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price)
             VALUES (:order_id, :product_id, :quantity, :unit_price)'
        );
        $stmt->execute([
            ':order_id'   => $orderId,
            ':product_id' => $productId,
            ':quantity'   => $quantity,
            ':unit_price' => $unitPrice,
        ]);

        // Guarded decrement: only succeeds if enough stock is still on
        // hand at the moment of the write (belt-and-braces alongside the
        // pre-check above, and what actually prevents negative stock
        // under concurrent checkouts).
        $stmt = $pdo->prepare(
            'UPDATE inventory
             SET quantity_on_hand = quantity_on_hand - :qty
             WHERE product_id = :pid AND warehouse_id = :wid AND quantity_on_hand >= :qty2'
        );
        $stmt->execute([
            ':qty' => $quantity, ':pid' => $productId,
            ':wid' => CHECKOUT_WAREHOUSE_ID, ':qty2' => $quantity,
        ]);

        if ($stmt->rowCount() === 0) {
            $nameRow = db_select($pdo, 'SELECT product_name FROM products WHERE product_id = :id', [':id' => $productId]);
            $name = $nameRow[0]['product_name'] ?? "product #$productId";
            throw new RuntimeException("Insufficient stock for {$name} (stock changed during checkout)");
        }

        $stmt = $pdo->prepare(
            'INSERT INTO stock_movements
                (product_id, warehouse_id, movement_type, quantity, reference_table, reference_id, notes, performed_by)
             VALUES
                (:pid, :wid, "sale_out", :qty, "orders", :order_id, :notes, :cashier_id)'
        );
        $stmt->execute([
            ':pid' => $productId, ':wid' => CHECKOUT_WAREHOUSE_ID, ':qty' => $quantity,
            ':order_id' => $orderId, ':notes' => "Sold via order $orderNumber",
            ':cashier_id' => CHECKOUT_CASHIER_ID,
        ]);
    }

    // 2e) Log the activity for the dashboard feed (same pattern used by
    // products.php / inventory.php — no schema change needed).
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:user_id, "checkout", "orders", :order_id, :description)'
    );
    $stmt->execute([
        ':user_id'     => CHECKOUT_CASHIER_ID,
        ':order_id'    => $orderId,
        ':description' => "Checkout completed — order #$orderNumber",
    ]);

    $pdo->commit();

    // Order placed successfully — empty the session cart so the next
    // visit to checkout.php starts fresh instead of re-showing items
    // that were already paid for.
    cart_clear();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // A CHECK-constraint violation (e.g. chk_inventory_reserved_le_onhand)
    // surfaces as a PDOException with SQLSTATE 23000 / a 3819 driver code —
    // translate it into the same clear message instead of a raw SQL error.
    if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 3819) {
        http_response_code(422);
        echo json_encode(['error' => 'Stock update was rejected by the database (reserved stock exceeds on-hand). Please try again.']);
        exit;
    }

    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

http_response_code(201);
echo json_encode([
    'orderId'     => $orderId,
    'orderNumber' => $orderNumber,
    'message'     => 'Order saved successfully',
]);
