<?php
/**
 * ============================================================================
 *  StockSmart — Cart API (api/cart.php)  [FIXED]
 * ============================================================================
 *  POST api/cart.php
 *  Body: { "productId": 8, "quantity": 2 }
 *
 *  Sets a product's quantity in the session cart.
 *  Sending quantity <= 0 removes the item entirely.
 *
 *  FIX 1: Added session_write_close() before sending the JSON response.
 *          PHP sessions are file-locked while a request runs. If checkout.php
 *          (which holds the session open) and api/cart.php fire concurrently,
 *          one blocks waiting for the other's file lock to release — causing
 *          the saveCartQuantity() call to appear to hang in the browser.
 *          session_write_close() releases the lock as soon as we're done
 *          writing, so concurrent requests never deadlock.
 *
 *  FIX 2: cart_set_quantity() now always sets $_SESSION['cart_initialized']
 *          (see cart_helper.php), so even if this API endpoint is the first
 *          thing hit in a fresh session, cart_init() won't re-seed on the
 *          next page load.
 *
 *  Response: { "ok": true, "itemCount": 3, "quantity": 2 }
 *        or: { "error": "..." }
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../cart_helper.php';
require_once __DIR__ . '/../auth.php';   // returns 401 JSON if not logged in

header('Content-Type: application/json; charset=utf-8');

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

$productId = (int)   ($body['productId'] ?? 0);
$quantity  = (float) ($body['quantity']  ?? 0);

if ($productId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'productId is required and must be > 0']);
    exit;
}

// Make sure the cart session exists. cart_init() only seeds on the very first
// call in a brand-new session thanks to the cart_initialized flag fix.
cart_init($pdo);

// Cap quantity at currently available stock so the cart can never hold more
// than what's actually sellable.
if ($quantity > 0) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(i.quantity_available), 0) AS available
        FROM   products p
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE  p.product_id = :pid
          AND  p.status     = 'active'
        GROUP BY p.product_id
    ");
    $stmt->execute([':pid' => $productId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found or is discontinued']);
        exit;
    }

    $available = (float) $row['available'];
    if ($quantity > $available) {
        $quantity = $available;
    }
    if ($quantity <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'This product is currently out of stock']);
        exit;
    }
}

// Persist the change to the session.
cart_set_quantity($productId, $quantity);

// FIX: release the session file-lock now that we've finished writing.
// This prevents concurrent requests from checkout.php blocking this API.
session_write_close();

echo json_encode([
    'ok'        => true,
    'itemCount' => count($_SESSION['cart'] ?? []),
    'quantity'  => $quantity > 0 ? $quantity : 0,
]);
