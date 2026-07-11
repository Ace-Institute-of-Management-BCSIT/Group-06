<?php
/**
 * ============================================================================
 *  StockSmart — Dashboard (dashboard.php) — ROOT FILE
 * ============================================================================
 *  This file goes in your project ROOT (stocksmartweb/dashboard.php), NOT
 *  inside the api/ folder. It is the authenticated PAGE WRAPPER — it checks
 *  the session, injects the logged-in user (with CSRF token) into the page,
 *  and prints dashboard.html's markup.
 *
 *  It does NOT contain any SQL or JSON API code. That code belongs in the
 *  SEPARATE file api/dashboard.php, which is a different file with the same
 *  name in a different folder. If you see SQL queries or `api_require_login()`
 *  in this file, you have the wrong contents in the wrong place — swap them.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';   // redirects to login.php if not logged in
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('dashboard.view');

render_ui_template('dashboard.html', auth_user(), 'dashboard', 'Dashboard', [
    'id' => 'globalSearch',
    'placeholder' => 'Search products, suppliers, orders…',
]);
