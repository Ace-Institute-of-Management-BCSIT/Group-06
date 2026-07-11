<?php
/**
 * ============================================================================
 *  StockSmart — Activity Logs (logs.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "logs.view" permission, injects the
 *  user (with CSRF token), and prints logs.html. All SQL lives in the
 *  SEPARATE file api/logs.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('logs.view');

render_ui_template('logs.html', auth_user(), 'logs', 'Activity Logs');
