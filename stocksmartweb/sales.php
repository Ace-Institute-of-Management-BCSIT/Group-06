<?php
/**
 * ============================================================================
 *  StockSmart — Sales (sales.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "sales.view" permission, injects the
 *  user (with CSRF token), and prints sales.html. All SQL lives in the
 *  SEPARATE file api/sales.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('sales.view');

render_ui_template('sales.html', auth_user(), 'sales', 'Sales');
