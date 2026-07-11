<?php
/**
 * ============================================================================
 *  StockSmart — Products (products.php) — ROOT FILE
 * ============================================================================
 *  Goes in project ROOT (stocksmartweb/products.php). This is the PAGE
 *  WRAPPER — checks the session, injects the user (with CSRF token), and
 *  prints products.html. It contains NO SQL and NO api_require_login() call.
 *  If you see those in this file, it has the wrong contents — that belongs
 *  in the SEPARATE file api/products.php instead.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';   // redirects to login.php if not logged in
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('products.view');

render_ui_template('products.html', auth_user(), 'products', 'Products', [
    'id' => 'topSearchInput',
    'placeholder' => 'Search products...',
]);
