<?php
/**
 * ============================================================================
 *  StockSmart — 2FA Code Verification (verify-2fa.php)
 * ============================================================================
 *  GET  verify-2fa.php  → renders verify-2fa.html
 *  POST verify-2fa.php  → verifies 6-digit TOTP code and logs user in
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/totp.php';

$pendingUserId = (int) ($_SESSION['2fa_pending_user_id'] ?? 0);

if ($pendingUserId <= 0) {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, full_name, email, role_id, avatar_emoji, totp_secret, totp_enabled FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $pendingUserId]);
$user = $stmt->fetch();

if (!$user || empty($user['totp_secret']) || (int)$user['totp_enabled'] !== 1) {
    // If user somehow doesn't have 2FA set up yet, redirect to setup
    header('Location: setup-2fa.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('verify-2fa.html', [
        'email' => $user['email'],
        'name'  => $user['full_name'],
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$code = trim((string) ($body['code'] ?? ''));

if (strlen($code) !== 6 || !ctype_digit($code)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid 6-digit code from your Authenticator App.']);
    exit;
}

if (!totp_verify_code($user['totp_secret'], $code)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid 6-digit 2FA code. Please check your Auth App (Ente Auth) and try again.']);
    exit;
}

/* ── 2FA code is valid! Establish full session ───────────────────────── */
$stmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :rid LIMIT 1');
$stmt->execute([':rid' => $user['role_id']]);
$roleRow = $stmt->fetch();
$roleName = $roleRow['role_name'] ?? 'Staff';

unset($_SESSION['2fa_pending_user_id']);

session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['user_id'];
$_SESSION['user_name']     = $user['full_name'];
$_SESSION['username']      = $user['email'];
$_SESSION['user_role']     = $roleName;
$_SESSION['avatar']        = $user['avatar_emoji'] ?? strtoupper(substr($user['full_name'], 0, 1));
$_SESSION['last_activity'] = time();

// Log the successful 2FA login
try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'login', 'users', :eid, :desc)"
    )->execute([
        ':uid'  => $user['user_id'],
        ':eid'  => $user['user_id'],
        ':desc' => $user['full_name'] . ' logged in via 2FA Auth App',
    ]);
} catch (Throwable $e) { /* non-fatal */ }

session_write_close();

echo json_encode([
    'ok'       => true,
    'message'  => '2FA verified! Redirecting to workspace...',
    'redirect' => 'dashboard.php',
]);
