<?php
/**
 * ============================================================================
 *  StockSmart — Reports (reports.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "reports.view" permission, injects
 *  the user (with CSRF token), and prints reports.html. All SQL/export logic
 *  lives in the SEPARATE file api/reports.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('reports.view');

render_ui_template('reports.html', auth_user(), 'reports', 'Reports');
