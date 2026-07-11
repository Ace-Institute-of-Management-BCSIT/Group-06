<?php
/**
 * Shared renderer for the existing UI files.
 *
 * The project keeps its established HTML/CSS/JS files as presentation
 * templates. Protected PHP routes use this helper to inject the signed-in
 * user (including the CSRF token) before the page scripts execute, and to
 * splice in the single shared sidebar component (see partials/sidebar.php)
 * so every page renders byte-identical sidebar markup — no per-page copies.
 */

declare(strict_types=1);

require_once __DIR__ . '/partials/sidebar.php';
require_once __DIR__ . '/partials/topbar.php';

function render_ui_template(
    string $filename,
    ?array $user = null,
    string $activeNavKey = '',
    string $pageTitle = '',
    ?array $topSearch = null
): void {
    $path = __DIR__ . '/' . $filename;
    if (!is_file($path)) {
        http_response_code(500);
        echo 'Page template is unavailable.';
        exit;
    }

    $html = file_get_contents($path);
    if ($html === false) {
        http_response_code(500);
        echo 'Page template could not be read.';
        exit;
    }

    $payload = json_encode(
        $user,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    );
    $bootstrap = "<script>window.STOCKSMART_USER = {$payload};</script>";

    // Keep the template's document structure intact; inject while parsing
    // <head>, before any page JavaScript runs.
    $rendered = preg_replace('/<head([^>]*)>/i', '<head$1>' . $bootstrap, $html, 1);
    $rendered = $rendered === null ? $html : $rendered;

    // Replace the page's own <aside class="sidebar">...</aside> (if any is
    // still hard-coded) or a <!--SIDEBAR--> marker with the one shared
    // sidebar component, so no template maintains its own copy.
    if ($activeNavKey !== '') {
        $sidebarHtml = render_sidebar($activeNavKey);
        $withSidebar = preg_replace('/<aside class="sidebar"[^>]*>.*?<\/aside>/s', $sidebarHtml, $rendered, 1, $count);
        if ($count === 0) {
            $withSidebar = str_replace('<!--SIDEBAR-->', $sidebarHtml, $rendered, $count);
        }
        $rendered = $count > 0 ? $withSidebar : $rendered;
    }

    // Replace the page's <!--TOPBAR--> marker with the one shared topbar
    // component, so title/breadcrumb/search/notif-bell/profile-chip markup
    // and behavior are never duplicated per page. (A regex swap of the old
    // hand-written <div class="topbar">...</div> block isn't safe here —
    // unlike <aside>, topbar markup nests other <div>s, so every template
    // has its old block replaced with the marker up front instead.)
    if ($pageTitle !== '') {
        $breadcrumbHtml = topbar_breadcrumb($activeNavKey, $pageTitle);
        $topbarHtml = render_topbar($pageTitle, $breadcrumbHtml, $topSearch);
        $rendered = str_replace('<!--TOPBAR-->', $topbarHtml, $rendered);
    }

    echo $rendered;
}
