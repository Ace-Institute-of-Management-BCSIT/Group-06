/**
 * Shared chrome behavior — runs on every page against the ONE sidebar
 * (partials/sidebar.php) and ONE topbar (partials/topbar.php) components
 * rendered server-side, so this is the single place that:
 *   1. Hides nav items / action buttons the signed-in role can't use, from
 *      window.STOCKSMART_USER.permissions (page_renderer.php injects this;
 *      see auth.php::auth_user()). Server-side checks remain the real
 *      authorization boundary — this only avoids showing affordances that
 *      would 403 on click.
 *   2. Fills the sidebar-footer profile card (avatar/name/role) and the
 *      topbar user-chip + its dropdown (My Profile/Change Password/Logout)
 *      — every page shares the same markup/ids, so this one snippet
 *      replaces what used to be copy-pasted (with drifting details) per
 *      page, and gives every page a Logout link for the first time.
 *   3. Populates the ALERTS section badge counts and the topbar notif bell
 *      (unread dot + dropdown list + mark-as-read) from the same live
 *      notifications data on every page (api/notifications.php).
 *   4. Drives the topbar's live clock (#liveClock, and #liveDate where the
 *      page has one) so no page keeps its own setInterval copy.
 */
(function () {
  function applyPermissions() {
    var user = window.STOCKSMART_USER;
    var permissions = (user && Array.isArray(user.permissions)) ? user.permissions : [];
    var hasAll = permissions.indexOf('*') !== -1;

    document.querySelectorAll('[data-perm]').forEach(function (el) {
      var required = el.getAttribute('data-perm').split(',').map(function (p) { return p.trim(); });
      var allowed = hasAll || required.some(function (p) { return permissions.indexOf(p) !== -1; });
      el.style.display = allowed ? '' : 'none';
    });
  }

  function fillProfileCard() {
    var user = window.STOCKSMART_USER;
    if (!user) return;
    var initials = (user.name || '').split(' ').map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase() || 'US';

    var avatarEl = document.getElementById('sidebarAvatar');
    var nameEl = document.getElementById('sidebarName');
    var roleEl = document.getElementById('sidebarRole');
    if (avatarEl) avatarEl.textContent = user.avatar || initials;
    if (nameEl) nameEl.textContent = user.name || user.username || 'User';
    if (roleEl) roleEl.textContent = user.role || '';

    var profileLink = document.getElementById('sidebarProfile');
    if (profileLink) {
      profileLink.addEventListener('click', function () { window.location.href = 'profile.php'; });
    }
  }

  function fillUserChip() {
    var chip = document.getElementById('userChip');
    if (!chip) return;
    var user = window.STOCKSMART_USER;
    var initials = ((user && user.name) || '').split(' ').map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase() || 'US';

    if (user) {
      var avatarEl = document.getElementById('userChipAvatar');
      var nameEl = document.getElementById('userChipName');
      var roleEl = document.getElementById('userChipRole');
      if (avatarEl) avatarEl.textContent = user.avatar || initials;
      if (nameEl) nameEl.textContent = user.name || user.username || 'User';
      if (roleEl) roleEl.textContent = user.role || '';
    }

    var menu = document.getElementById('accountMenu');
    if (!menu) return;
    chip.addEventListener('click', function (e) {
      e.stopPropagation();
      menu.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target) && e.target !== chip) menu.classList.remove('open');
    });
  }

  function fillBadgeCounts() {
    var restockEl = document.getElementById('badgeRestock');
    var expiryEl = document.getElementById('badgeExpiry');
    if (!restockEl && !expiryEl) return;

    fetch('api/notifications.php')
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data || !data.badgeCounts) return;
        if (restockEl) restockEl.textContent = data.badgeCounts.restock;
        if (expiryEl) expiryEl.textContent = data.badgeCounts.expiry;
      })
      .catch(function () { /* non-fatal */ });
  }

  function timeAgoShort(iso) {
    var diff = (Date.now() - new Date(String(iso).replace(' ', 'T'))) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function renderNotifPanel(items) {
    var body = document.getElementById('notifPanelBody');
    if (!body) return;
    if (!items.length) {
      body.innerHTML = '<div class="notif-empty">No alerts right now — everything looks good.</div>';
      return;
    }
    body.innerHTML = items.map(function (n) {
      return '<div class="notif-row ' + (n.read_at ? '' : 'unread') + '" data-id="' + n.notification_id + '">' +
        '<b>' + escapeHtml(n.title) + '</b>' + escapeHtml(n.message) +
        '<div style="color:var(--text-400);font-size:11px;margin-top:2px;">' + timeAgoShort(n.created_at) + '</div>' +
        '</div>';
    }).join('');
  }

  function loadNotifications() {
    fetch('api/notifications.php')
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data) return;
        renderNotifPanel(data.notifications || []);
        var dot = document.getElementById('notifDot');
        if (dot) dot.classList.toggle('show', (data.unreadCount || 0) > 0);
      })
      .catch(function () { /* non-fatal */ });
  }

  function initNotifBell() {
    var btn = document.getElementById('notifBtn');
    var panel = document.getElementById('notifPanel');
    if (!btn || !panel) return;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) loadNotifications();
    });
    document.addEventListener('click', function (e) {
      if (!panel.contains(e.target) && e.target !== btn) panel.classList.remove('open');
    });

    var body = document.getElementById('notifPanelBody');
    if (body) {
      body.addEventListener('click', function (e) {
        var row = e.target.closest('.notif-row');
        if (!row || !row.classList.contains('unread')) return;
        row.classList.remove('unread');
        var user = window.STOCKSMART_USER;
        fetch('api/notifications.php?action=read&id=' + row.dataset.id, {
          method: 'POST',
          headers: { 'X-CSRF-Token': (user && user.csrf) || '' }
        }).then(loadNotifications);
      });
    }

    loadNotifications();
    setInterval(loadNotifications, 60000);
  }

  function initSidebarToggle() {
    var sidebar = document.getElementById('sidebar');
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !toggle || !backdrop) return;

    function open() { sidebar.classList.add('open'); backdrop.classList.add('show'); }
    function close() { sidebar.classList.remove('open'); backdrop.classList.remove('show'); }
    toggle.addEventListener('click', function () {
      sidebar.classList.contains('open') ? close() : open();
    });
    backdrop.addEventListener('click', close);
  }

  function initLiveClock() {
    var clockEl = document.getElementById('liveClock');
    var dateEl = document.getElementById('liveDate');
    if (!clockEl && !dateEl) return;

    function tick() {
      var now = new Date();
      if (clockEl) clockEl.textContent = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      if (dateEl) dateEl.textContent = now.toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    }
    tick();
    setInterval(tick, 1000);
  }

  function apply() {
    applyPermissions();
    fillProfileCard();
    fillUserChip();
    fillBadgeCounts();
    initNotifBell();
    initSidebarToggle();
    initLiveClock();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', apply);
  } else {
    apply();
  }
})();
