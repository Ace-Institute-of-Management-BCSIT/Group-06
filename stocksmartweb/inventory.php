<?php
/**
 * ============================================================================
 *  StockSmart — Inventory (inventory.php)
 * ============================================================================
 *  Session-protected page wrapper. Validates the session before serving
 *  inventory.html. Unauthenticated requests redirect to login.html.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user     = auth_user();
$userJson = json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<script>window.STOCKSMART_USER = <?= $userJson ?>;</script>
<?php
$html = file_get_contents(__DIR__ . '/inventory.html');
$html = preg_replace('/^<!DOCTYPE[^>]*>\s*/i', '', $html);
$html = preg_replace('/^<html[^>]*>\s*/i', '', $html);
echo $html;
