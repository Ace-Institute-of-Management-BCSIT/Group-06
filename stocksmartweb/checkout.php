<?php
/**
 * ============================================================================
 *  StockSmart — Checkout / POS (checkout.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "checkout.use" permission, injects
 *  the user (with CSRF token), and prints checkout.html. All SQL/business
 *  logic lives in api/cart.php, api/checkout.php, api/customers.php,
 *  api/products.php, and api/receipt.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('checkout.use');

render_ui_template('checkout.html', auth_user(), 'checkout', 'Checkout / POS');
