<?php
/**
 * ============================================================================
 *  StockSmart — Mandatory 2FA Auth App Setup (setup-2fa.php)
 * ============================================================================
 *  GET  setup-2fa.php  → renders setup-2fa.html with Secret Key & QR Code info
 *  POST setup-2fa.php  → verifies 6-digit TOTP code and enables 2FA for account
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/totp.php';

$userId = (int) ($_SESSION['2fa_pending_user_id'] ?? $_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, full_name, email, role_id, avatar_emoji, totp_secret, totp_enabled FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

/* ── GET: Render setup-2fa.html ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Generate temporary secret if not already set or in session
    if (empty($_SESSION['temp_totp_secret'])) {
        $_SESSION['temp_totp_secret'] = totp_generate_secret();
    }
    $secret = $_SESSION['temp_totp_secret'];
    $otpauthUrl = totp_get_provisioning_uri($user['email'], $secret, 'StockSmart');

    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('setup-2fa.html', [
        'id'          => $userId,
        'name'        => $user['full_name'],
        'email'       => $user['email'],
        'totp_secret' => $secret,
        'otpauth_url' => $otpauthUrl,
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$code   = trim((string) ($body['code'] ?? ''));
$secret = trim((string) ($body['secret'] ?? $_SESSION['temp_totp_secret'] ?? ''));

if ($secret === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing TOTP secret key. Please refresh the page and try again.']);
    exit;
}

if (!totp_verify_code($secret, $code)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid 6-digit Auth App code. Check your Ente Auth / Authenticator app and try again.']);
    exit;
}

/* ── Enable 2FA on account ────────────────────────────────────────────── */
try {
    $pdo->prepare('UPDATE users SET totp_secret = :sec, totp_enabled = 1 WHERE user_id = :id')
        ->execute([':sec' => $secret, ':id' => $userId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save 2FA settings. Please try again.']);
    exit;
}

// Fetch role_name
$stmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :rid LIMIT 1');
$stmt->execute([':rid' => $user['role_id']]);
$roleRow = $stmt->fetch();
$roleName = $roleRow['role_name'] ?? 'Staff';

// Establish full login session
unset($_SESSION['2fa_pending_user_id']);
unset($_SESSION['temp_totp_secret']);

session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['user_id'];
$_SESSION['user_name']     = $user['full_name'];
$_SESSION['username']      = $user['email'];
$_SESSION['user_role']     = $roleName;
$_SESSION['avatar']        = $user['avatar_emoji'] ?? strtoupper(substr($user['full_name'], 0, 1));
$_SESSION['last_activity'] = time();

try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([
        ':uid'  => $user['user_id'],
        ':eid'  => $user['user_id'],
        ':desc' => $user['full_name'] . ' configured Auth App 2FA (Ente Auth / TOTP)',
    ]);
} catch (Throwable $e) { /* non-fatal */ }

session_write_close();

echo json_encode([
    'ok'       => true,
    'message'  => 'Authenticator App configured successfully! Redirecting to workspace...',
    'redirect' => 'dashboard.php',
]);
