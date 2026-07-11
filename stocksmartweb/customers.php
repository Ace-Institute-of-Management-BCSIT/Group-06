<?php
/**
 * ============================================================================
 *  StockSmart — Customers (customers.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "customers.view" permission, injects
 *  the user (with CSRF token), and prints customers.html. All SQL lives in
 *  the SEPARATE file api/customers.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('customers.view');

render_ui_template('customers.html', auth_user(), 'customers', 'Customers');
