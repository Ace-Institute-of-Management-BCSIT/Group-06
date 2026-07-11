<?php
/**
 * Shared topbar component — the ONE place that renders the title/breadcrumb
 * row, notification bell, and user profile chip (avatar/name/role/dropdown
 * with My Profile / Change Password / Logout). Every page previously kept
 * its own copy of this markup with drifting details (avatar size, an extra
 * caret on some pages, an extra menu link on others) and three of them
 * duplicated ~50 lines of near-identical JS to wire it up; the other nine
 * pages had no notif bell or profile menu at all, meaning no Logout link.
 * render_topbar() fixes both: one markup source, populated/wired centrally
 * by assets/js/nav-guard.js (fillUserChip()/initNotifBell()).
 */

declare(strict_types=1);

function render_topbar(string $title, string $breadcrumbHtml, ?array $search = null): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    $searchHtml = '';
    if ($search !== null) {
        $id = htmlspecialchars($search['id'] ?? 'topSearchInput', ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars($search['placeholder'] ?? 'Search...', ENT_QUOTES, 'UTF-8');
        $searchHtml = <<<HTML
          <div class="search-wrap" style="min-width:260px;">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input type="text" id="{$id}" placeholder="{$placeholder}" autocomplete="off">
          </div>
HTML;
    }

    return <<<HTML
    <div class="topbar">
        <div>
            <h1>{$safeTitle}</h1>
            <div class="breadcrumb">{$breadcrumbHtml}</div>
        </div>
        <div class="top-actions">
{$searchHtml}
          <div style="position:relative;">
            <button class="icon-btn" aria-label="Notifications" id="notifBtn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <span class="notif-dot" id="notifDot"></span>
            </button>
            <div class="notif-panel" id="notifPanel">
              <h4>Alerts</h4>
              <div id="notifPanelBody"><div class="notif-empty">Loading…</div></div>
            </div>
          </div>
          <div class="user-chip" id="userChip" style="position:relative;">
              <div class="avatar sm" id="userChipAvatar">US</div>
              <div class="who">
                  <b id="userChipName">User</b>
                  <span id="userChipRole">—</span>
              </div>
              <div class="account-menu" id="accountMenu">
                <a href="profile.php">My Profile</a>
                <a href="profile.php#security">Change Password</a>
                <a href="logout.php" class="danger">Logout</a>
              </div>
          </div>
        </div>
    </div>
HTML;
}

function topbar_breadcrumb(string $activeNavKey, string $title): string
{
    if ($activeNavKey === 'dashboard') {
        return '<span id="liveDate"></span> &nbsp;·&nbsp; <span id="liveClock"></span>';
    }
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return '<a href="dashboard.php">Dashboard</a> &nbsp;›&nbsp; ' . $safeTitle . ' &nbsp;·&nbsp; <span id="liveClock"></span>';
}
