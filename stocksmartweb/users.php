<?php
/**
 * ============================================================================
 *  StockSmart — Users (users.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "users.manage" permission, injects the
 *  user (with CSRF token), and prints users.html. All SQL lives in the
 *  SEPARATE file api/users.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('users.manage');

render_ui_template('users.html', auth_user(), 'users', 'Users');
