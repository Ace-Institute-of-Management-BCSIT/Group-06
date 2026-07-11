<?php
/**
 * ============================================================================
 *  StockSmart — Shared Sidebar Component (partials/sidebar.php)
 * ============================================================================
 *  Single source of truth for the sidebar markup used on every app page.
 *  page_renderer.php calls render_sidebar($activeKey) and splices the output
 *  into each HTML template in place of the <!--SIDEBAR--> marker, so every
 *  page renders byte-identical sidebar HTML — same width, spacing, fonts,
 *  icon sizes, section order, and badge/profile markup, with only the
 *  "active" nav item differing per page.
 *
 *  Add a new nav destination in exactly one place: the $NAV_SECTIONS array
 *  below. Every page picks up the change automatically.
 * ============================================================================
 */

declare(strict_types=1);

const NAV_ICON = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'products'  => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13V21"/>',
    'inventory' => '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9z"/><path d="M9 13h6"/>',
    'checkout'  => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
    'sales'     => '<path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-6"/>',
    'suppliers' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    'customers' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'purchases' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'reports'   => '<path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-6"/>',
    'restock'   => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
    'expiry'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'profile'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'roles'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    'logs'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
];

/**
 * @return array<int, array{title:string, items:array<int, array{key:string, label:string, href:?string, perm:?string, badge:?string}>}>
 */
function nav_sections(): array
{
    return [
        [
            'title' => 'MAIN',
            'items' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'perm' => 'dashboard.view'],
                ['key' => 'products',  'label' => 'Products',  'href' => 'products.php',  'perm' => 'products.view'],
                ['key' => 'inventory', 'label' => 'Inventory', 'href' => 'inventory.php', 'perm' => 'inventory.view'],
                ['key' => 'checkout',  'label' => 'Checkout',  'href' => 'checkout.php',  'perm' => 'checkout.use'],
                ['key' => 'sales',     'label' => 'Sales',     'href' => 'sales.php',     'perm' => 'sales.view'],
                ['key' => 'suppliers', 'label' => 'Suppliers', 'href' => 'suppliers.php', 'perm' => 'suppliers.view'],
                ['key' => 'customers', 'label' => 'Customers', 'href' => 'customers.php', 'perm' => 'customers.view'],
                ['key' => 'purchases', 'label' => 'Purchases', 'href' => 'purchases.php', 'perm' => 'purchases.view'],
                ['key' => 'reports',   'label' => 'Reports',   'href' => 'reports.php',   'perm' => 'reports.view'],
            ],
        ],
        [
            'title' => 'ALERTS',
            'items' => [
                ['key' => 'restock', 'label' => 'Restocking Alerts', 'href' => null, 'perm' => null, 'badge' => ['id' => 'badgeRestock', 'color' => 'amber']],
                ['key' => 'expiry',  'label' => 'Expiry Alerts',     'href' => null, 'perm' => null, 'badge' => ['id' => 'badgeExpiry', 'color' => 'red']],
            ],
        ],
        [
            'title' => 'ACCOUNT',
            'items' => [
                ['key' => 'profile', 'label' => 'My Profile', 'href' => 'profile.php', 'perm' => null],
            ],
        ],
        [
            'title' => 'ADMIN',
            'items' => [
                ['key' => 'users',    'label' => 'Users',                 'href' => 'users.php',    'perm' => 'users.manage'],
                ['key' => 'roles',    'label' => 'Roles &amp; Permissions', 'href' => 'roles.php',    'perm' => 'roles.manage'],
                ['key' => 'settings', 'label' => 'Settings',              'href' => 'settings.php', 'perm' => 'settings.manage'],
                ['key' => 'logs',     'label' => 'Activity Logs',         'href' => 'logs.php',     'perm' => 'logs.view'],
            ],
        ],
    ];
}

function render_sidebar(string $activeKey): string
{
    $icon = static fn (string $key): string => NAV_ICON[$key] ?? '';

    // Mobile off-canvas toggle + backdrop travel with the sidebar itself
    // (see the @media(max-width:880px) rules in assets/css/app.css) so
    // every page gets working mobile nav, not just the ones that happened
    // to hand-write this button.
    $html = '<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">' . "\n";
    $html .= '  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>' . "\n";
    $html .= '</button>' . "\n";
    $html .= '<div class="sidebar-backdrop" id="sidebarBackdrop"></div>' . "\n";
    $html .= '<aside class="sidebar" id="sidebar">' . "\n";
    $html .= '  <a class="brand" href="index.php" aria-label="StockSmart home">' . "\n";
    $html .= '    <div class="logo"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg></div>' . "\n";
    $html .= '    <div><div class="brand-text">Stock<span>Smart</span></div><div class="brand-sub">INVENTORY OS</div></div>' . "\n";
    $html .= '  </a>' . "\n";
    $html .= '  <div class="nav-section">' . "\n";

    foreach (nav_sections() as $i => $section) {
        $marginStyle = $i === 0 ? '' : ' style="margin-top:18px;"';
        $html .= "    <div class=\"nav-label\"{$marginStyle}>{$section['title']}</div>\n";
        foreach ($section['items'] as $item) {
            $svg = $icon($item['key']);
            $activeClass = $item['key'] === $activeKey ? ' active' : '';
            $permAttr = $item['perm'] ? ' data-perm="' . htmlspecialchars($item['perm']) . '"' : '';

            if ($item['href'] !== null) {
                $html .= "    <a class=\"nav-item{$activeClass}\" href=\"{$item['href']}\"{$permAttr}>\n";
                $html .= "      <svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$svg}</svg>\n";
                $html .= "      {$item['label']}</a>\n";
            } else {
                $badge = $item['badge'] ?? null;
                $badgeHtml = $badge
                    ? "<span class=\"badge {$badge['color']}\" id=\"{$badge['id']}\" style=\"margin-left:auto;\">0</span>"
                    : '';
                $html .= "    <span class=\"nav-item\" style=\"cursor:default;\">\n";
                $html .= "      <svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$svg}</svg>\n";
                $html .= "      {$item['label']}{$badgeHtml}</span>\n";
            }
        }
    }

    $html .= '  </div>' . "\n";
    $html .= '  <div class="sidebar-footer" id="sidebarProfile" style="cursor:pointer;">' . "\n";
    $html .= '    <div class="avatar" id="sidebarAvatar">US</div>' . "\n";
    $html .= '    <div class="who"><b id="sidebarName">User</b><span id="sidebarRole">—</span></div>' . "\n";
    $html .= '  </div>' . "\n";
    $html .= '</aside>';

    return $html;
}
