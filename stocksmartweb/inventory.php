<?php
/**
 * ============================================================================
 *  StockSmart — Inventory (inventory.php) — ROOT FILE
 * ============================================================================
 *  Goes in project ROOT (stocksmartweb/inventory.php). This is the PAGE
 *  WRAPPER — checks the session, injects the user (with CSRF token), and
 *  prints inventory.html. It contains NO SQL and NO api_require_login() call.
 *  If you see those in this file, it has the wrong contents — that belongs
 *  in the SEPARATE file api/inventory.php instead.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('inventory.view');

render_ui_template('inventory.html', auth_user(), 'inventory', 'Inventory', [
    'id' => 'searchInput',
    'placeholder' => 'Search products, SKU...',
]);
