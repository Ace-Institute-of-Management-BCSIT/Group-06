<?php
declare(strict_types=1);

/**
 * cart_init — seeds 5 sample products on a brand-new session only.
 *
 * BUG FIX: added $_SESSION['cart_initialized'] as the single guard flag.
 * The old code used array_key_exists('cart', $_SESSION), which failed when
 * cart_set_quantity() wrote the cart key directly without setting any marker.
 * Result: on a fresh session the guard never fired → re-seeded on every reload.
 */
function cart_init(PDO $pdo): void
{
    if (isset($_SESSION['cart_initialized'])) {
        if (!is_array($_SESSION['cart'] ?? null)) {
            $_SESSION['cart'] = [];
        }
        return;
    }

    $_SESSION['cart_initialized'] = true;
    $_SESSION['cart']             = [];

    $stmt = $pdo->query("
        SELECT p.product_id, COALESCE(SUM(i.quantity_available), 0) AS available
        FROM   products p
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE  p.status = 'active'
        GROUP BY p.product_id
        HAVING available > 0
        ORDER BY p.product_name
        LIMIT 5
    ");
    foreach ($stmt->fetchAll() as $row) {
        $_SESSION['cart'][(int)$row['product_id']] = 1.00;
    }
}

function cart_get_items(PDO $pdo): array
{
    if (empty($_SESSION['cart'])) {
        return [];
    }

    $ids          = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.sku, p.icon_emoji, p.price,
               COALESCE(SUM(i.quantity_available), 0) AS available
        FROM   products p
        LEFT JOIN inventory i ON i.product_id = p.product_id
        WHERE  p.product_id IN ($placeholders) AND p.status = 'active'
        GROUP BY p.product_id, p.product_name, p.sku, p.icon_emoji, p.price
    ");
    $stmt->execute($ids);

    $productsById = [];
    foreach ($stmt->fetchAll() as $row) {
        $productsById[(int)$row['product_id']] = $row;
    }

    $items = [];
    foreach ($_SESSION['cart'] as $productId => $qty) {
        $productId = (int)$productId;
        if (!isset($productsById[$productId])) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }
        $row     = $productsById[$productId];
        $qty     = (float)$qty;
        $items[] = [
            'id'        => $productId,
            'qty'       => $qty,
            'name'      => $row['product_name'],
            'sku'       => $row['sku'],
            'img'       => $row['icon_emoji'] ?: '',
            'disc'      => 0,
            'unit'      => (float)$row['price'],
            'price'     => round($qty * (float)$row['price'], 2),
            'combo'     => false,
            'available' => (float)$row['available'],
        ];
    }
    return $items;
}

/**
 * Set or remove a product quantity.
 * Also sets cart_initialized so cart_init() never re-seeds after this runs.
 */
function cart_set_quantity(int $productId, float $qty): void
{
    $_SESSION['cart_initialized'] = true;
    if (!is_array($_SESSION['cart'] ?? null)) {
        $_SESSION['cart'] = [];
    }
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
}

function cart_clear(): void
{
    $_SESSION['cart_initialized'] = true;
    $_SESSION['cart']             = [];
}
