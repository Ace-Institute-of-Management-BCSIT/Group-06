<?php
/**
 * ============================================================================
 *  StockSmart — Expiry & Batches (expiry.php) — ROOT FILE
 * ============================================================================
 *  PAGE WRAPPER — checks the session + "inventory.view" permission, injects
 *  the user (with CSRF token), and prints expiry.html. All SQL lives in the
 *  SEPARATE file api/batches.php.
 *
 *  This is the destination behind the sidebar's "Expiry Alerts" entry, the
 *  dashboard's Expiry Alerts panel, and every expiry notification in the bell.
 *  Before it existed those were all dead ends: expiry data was displayed in
 *  three places and editable in none, because no screen exposed
 *  product_batches at all.
 *
 *  Deep links, used by the alert navigation:
 *    expiry.php?batch=<id>    highlights and scrolls to one batch
 *    expiry.php?product=<id>  filters to one product's batches
 *    expiry.php?status=alerting|expired|expiring_soon|valid|none
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page_renderer.php';

auth_require_permission('inventory.view');

render_ui_template('expiry.html', auth_user(), 'expiry', 'Expiry & Batches');
