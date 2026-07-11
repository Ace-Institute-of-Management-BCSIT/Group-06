<?php
/**
 * ============================================================================
 *  StockSmart — Settings (settings.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "settings.manage" permission, injects
 *  the user (with CSRF token), and prints settings.html. All SQL lives in
 *  the SEPARATE file api/settings.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('settings.manage');

render_ui_template('settings.html', auth_user(), 'settings', 'Settings');
