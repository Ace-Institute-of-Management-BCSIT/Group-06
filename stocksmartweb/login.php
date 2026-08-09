<?php
/**
 * ============================================================================
 *  StockSmart — Login API (login.php)
 * ============================================================================
 *  GET  login.php → renders login.html
 *  POST login.php {username/email, password, remember} → authenticates user
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['logout'])) {
        header('Location: logout.php');
        exit;
    }
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('login.html');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$email    = trim((string)($body['username'] ?? $body['email'] ?? ''));
$password = (string)($body['password'] ?? '');
$remember = !empty($body['remember']);

if ($email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.username, u.email, u.password_hash,
               u.avatar_emoji, u.status, u.failed_login_attempts, u.locked_until,
               u.totp_enabled, r.role_name
        FROM   users u
        JOIN   roles r ON r.role_id = u.role_id
        WHERE  LOWER(u.email)    = LOWER(:cred1)
           OR  LOWER(u.username) = LOWER(:cred2)
        LIMIT  1
    ");
    $stmt->execute([':cred1' => $email, ':cred2' => $email]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB query failed: ' . $e->getMessage()]);
    exit;
}

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900); // 15 minutes

if ($user && !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
    $minutesLeft = (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60);
    http_response_code(423);
    echo json_encode(['error' => "Too many failed attempts. Your account is locked for {$minutesLeft} more minute(s)."]);
    exit;
}

$hashToCheck = $user['password_hash'] ?? '$2y$10$invalidHashThatWillNeverMatch00000000000000000000000000000';
$valid = password_verify($password, $hashToCheck);

if (!$user || !$valid) {
    if ($user) {
        $attempts = (int) $user['failed_login_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS);
        }
        try {
            $pdo->prepare('UPDATE users SET failed_login_attempts = :a, locked_until = :l WHERE user_id = :id')
                ->execute([':a' => $attempts, ':l' => $lockedUntil, ':id' => $user['user_id']]);
        } catch (Throwable $e) { /* non-fatal */ }
    }
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// Check pending verification status
if ($user['status'] === 'pending') {
    http_response_code(403);
    echo json_encode([
        'error'    => 'Your account is pending email OTP verification.',
        'redirect' => 'verify-otp.php?email=' . urlencode($user['email']) . '&type=register',
    ]);
    exit;
}

if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Your account is inactive or suspended. Please contact an administrator.']);
    exit;
}

// Clear lockout state on successful password verification
try {
    $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = :id')
        ->execute([':id' => $user['user_id']]);
} catch (Throwable $e) { /* non-fatal */ }

/* ── Check 2FA (TOTP) Requirement ────────────────────────────────────── */
$_SESSION['2fa_pending_user_id'] = (int) $user['user_id'];
$_SESSION['remember_me']          = $remember;

session_write_close();

$totpEnabled = (int) ($user['totp_enabled'] ?? 0);

if ($totpEnabled === 1) {
    echo json_encode([
        'ok'          => true,
        'require_2fa' => true,
        'redirect'    => 'verify-2fa.php',
    ]);
} else {
    // First login / 2FA not set up yet -> mandatory setup
    echo json_encode([
        'ok'                => true,
        'require_2fa_setup' => true,
        'redirect'          => 'setup-2fa.php',
    ]);
}
