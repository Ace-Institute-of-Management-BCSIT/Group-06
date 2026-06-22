<?php
/**
 * ============================================================================
 *  StockSmart — Dashboard (dashboard.php)
 * ============================================================================
 *  This is the authenticated entry point after login.
 *
 *  What it does:
 *    1. Requires auth.php, which checks $_SESSION['user_id'].
 *       Unauthenticated requests are redirected to login.html automatically.
 *    2. Passes the logged-in user's name, role, and avatar to the page
 *       via a small inline JSON block so dashboard.html's JS can populate
 *       the header greeting without an extra fetch().
 *    3. Serves the full dashboard.html content (via include) with one
 *       modification: the <head> section injects the user data variable
 *       before any other scripts run.
 *
 *  Why not just redirect to dashboard.html?
 *    dashboard.html is a static file — it has no session check and can be
 *    opened directly by anyone who knows the URL. dashboard.php gates it.
 *
 *  How to link to this page everywhere:
 *    All "go to dashboard" links and redirects should point to dashboard.php,
 *    not dashboard.html.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';   // redirects to login.html if not logged in

$user = auth_user();   // returns ['id', 'name', 'username', 'role', 'avatar']

// Encode user data safely for inline JS injection
$userJson = json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<!-- dashboard.php injects STOCKSMART_USER before dashboard.html's scripts run -->
<script>window.STOCKSMART_USER = <?= $userJson ?>;</script>
<?php
// Include the actual dashboard HTML content.
// This means dashboard.html continues to work as a standalone dev preview
// while dashboard.php adds the authentication layer on top.
$dashboardHtml = file_get_contents(__DIR__ . '/dashboard.html');

// Strip the <!DOCTYPE html> from the included file to avoid duplication
// (we already outputted it above). Also strip the opening <html> tag if present.
$dashboardHtml = preg_replace('/^<!DOCTYPE[^>]*>\s*/i', '', $dashboardHtml);
$dashboardHtml = preg_replace('/^<html[^>]*>\s*/i', '', $dashboardHtml);

echo $dashboardHtml;
