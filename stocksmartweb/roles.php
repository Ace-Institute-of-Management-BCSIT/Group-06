<?php
/**
 * ============================================================================
 *  StockSmart — Roles & Permissions (roles.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "roles.manage" permission, injects the
 *  user (with CSRF token), and prints roles.html. All SQL lives in the
 *  SEPARATE file api/roles.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('roles.manage');

render_ui_template('roles.html', auth_user(), 'roles', 'Roles & Permissions');
