<?php
/**
 * ============================================================================
 *  StockSmart — My Profile (profile.php)
 * ============================================================================
 *  Session-protected page wrapper, same pattern as products.php/inventory.php.
 *  Serves profile.html with the logged-in user injected as window.STOCKSMART_USER
 *  (which now includes the CSRF token via auth.php's auth_user()).
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

render_ui_template('profile.html', auth_user(), 'profile', 'My Profile');
 
