<?php
/**
 * ============================================================================
 *  StockSmart — Products (products.php)
 * ============================================================================
 *  When accessed as a normal GET page request:
 *    - Validates the session (redirects to login.html if not logged in).
 *    - Serves products.html with the logged-in user injected.
 *
 *  The products API (GET/POST/PUT/DELETE with JSON) lives at api/products.php.
 *  This file is only the authenticated page wrapper.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';   // redirects to login.html if not logged in

$user     = auth_user();
$userJson = json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<script>window.STOCKSMART_USER = <?= $userJson ?>;</script>
<?php
$html = file_get_contents(__DIR__ . '/products.html');
$html = preg_replace('/^<!DOCTYPE[^>]*>\s*/i', '', $html);
$html = preg_replace('/^<html[^>]*>\s*/i', '', $html);
echo $html;
