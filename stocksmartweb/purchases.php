<?php
/**
 * ============================================================================
 *  StockSmart — Purchases (purchases.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "purchases.view" permission, injects
 *  the user (with CSRF token), and prints purchases.html. All SQL lives in
 *  the SEPARATE file api/purchases.php.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('purchases.view');

render_ui_template('purchases.html', auth_user(), 'purchases', 'Purchases');
