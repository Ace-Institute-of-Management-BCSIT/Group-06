<?php
/**
 * ============================================================================
 *  StockSmart — Suppliers (suppliers.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "suppliers.view" permission, injects
 *  the user (with CSRF token), and prints suppliers.html. All SQL lives in
 *  the SEPARATE file api/suppliers.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('suppliers.view');

render_ui_template('suppliers.html', auth_user(), 'suppliers', 'Suppliers');
