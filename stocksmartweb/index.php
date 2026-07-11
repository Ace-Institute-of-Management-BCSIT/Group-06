<?php
/** Public, session-aware landing page. */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/page_renderer.php';

$user = !empty($_SESSION['user_id']) ? [
    'id' => (int) $_SESSION['user_id'],
    'name' => $_SESSION['user_name'] ?? '',
    'username' => $_SESSION['username'] ?? '',
    'role' => $_SESSION['user_role'] ?? '',
    'avatar' => $_SESSION['avatar'] ?? 'US',
    'csrf' => $_SESSION['csrf_token'] ?? '',
] : null;

render_ui_template('index.html', $user);
