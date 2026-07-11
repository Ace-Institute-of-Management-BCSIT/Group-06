<?php
/**
 * ============================================================================
 *  StockSmart — Email OTP Verification (verify-otp.php)
 * ============================================================================
 *  GET  verify-otp.php                 → renders the OTP entry page
 *  POST verify-otp.php {user_id, otp}  → activates the account + logs in
 *  POST verify-otp.php {user_id, action:"resend"} → re-sends a fresh OTP
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('verify-otp.html');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$userId = (int) ($body['user_id'] ?? ($_SESSION['pending_verify_user_id'] ?? 0));
$action = (string) ($body['action'] ?? 'verify');

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing account reference. Please register again.']);
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, full_name, username, email, avatar_emoji, status, role_id, otp_code, otp_expires_at FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Account not found.']);
    exit;
}

if ($user['status'] !== 'pending') {
    http_response_code(409);
    echo json_encode(['error' => 'This account is already verified. Please log in.']);
    exit;
}

if ($action === 'resend') {
    $otp = (string) random_int(100000, 999999);
    $otpExpiresAt = date('Y-m-d H:i:s', time() + 600);
    $pdo->prepare('UPDATE users SET otp_code = :otp, otp_expires_at = :exp WHERE user_id = :id')
        ->execute([':otp' => $otp, ':exp' => $otpExpiresAt, ':id' => $userId]);
    $mailResult = mail_send($user['email'], 'Your new StockSmart verification code', mail_render_otp($user['full_name'], $otp));
    echo json_encode(['ok' => true, 'message' => 'A new code has been sent.', 'otp_preview' => $mailResult['driver'] === 'log' ? $otp : null]);
    exit;
}

$submittedOtp = trim((string) ($body['otp'] ?? ''));

if ($submittedOtp === '' || $user['otp_code'] === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter the verification code.']);
    exit;
}

if (empty($user['otp_expires_at']) || strtotime((string) $user['otp_expires_at']) < time()) {
    http_response_code(410);
    echo json_encode(['error' => 'This code has expired. Please request a new one.']);
    exit;
}

if (!hash_equals((string) $user['otp_code'], $submittedOtp)) {
    http_response_code(401);
    echo json_encode(['error' => 'Incorrect verification code.']);
    exit;
}

/* ── Activate the account ─────────────────────────────────────────────── */
$pdo->prepare("UPDATE users SET status = 'active', email_verified_at = NOW(), otp_code = NULL, otp_expires_at = NULL WHERE user_id = :id")
    ->execute([':id' => $userId]);

$roleStmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :rid');
$roleStmt->execute([':rid' => $user['role_id']]);
$roleName = $roleStmt->fetchColumn() ?: 'Staff';

try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([':uid' => $userId, ':eid' => $userId, ':desc' => $user['full_name'] . ' verified their email and activated their account']);
} catch (Throwable $e) { /* non-fatal */ }

/* ── Log the user in ──────────────────────────────────────────────────── */
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['user_id']   = (int) $user['user_id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['username']  = $user['username'];
$_SESSION['user_role'] = $roleName;
$_SESSION['avatar']    = $user['avatar_emoji'] ?? strtoupper(substr($user['full_name'], 0, 1));
$_SESSION['last_activity'] = time();
unset($_SESSION['pending_verify_user_id'], $_SESSION['permissions']);
session_write_close();

echo json_encode([
    'ok' => true,
    'redirect' => 'dashboard.php',
    'user' => ['id' => (int) $user['user_id'], 'name' => $user['full_name'], 'role' => $roleName],
]);
