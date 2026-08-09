<?php
/**
 * ============================================================================
 *  StockSmart — Forgot Password Request (forgot-password.php)
 * ============================================================================
 *  GET  forgot-password.php          → renders the request form
 *  POST forgot-password.php {email}  → issues a 6-digit reset OTP via email
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/mailer.php';
require_once __DIR__ . '/config/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('forgot-password.html');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$email = trim((string) ($body['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

$redirectUrl = 'reset-password.php?email=' . urlencode($email);
$genericResponse = [
    'ok'       => true,
    'message'  => 'If an account with that email exists, a 6-digit reset code has been sent to your inbox.',
    'redirect' => $redirectUrl,
];

$stmt = $pdo->prepare('SELECT user_id, full_name, email, status FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'suspended') {
    echo json_encode($genericResponse);
    exit;
}

/* ── Generate 6-digit OTP code ─────────────────────────────────────── */
$rawOtp    = sprintf('%06d', random_int(100000, 999999));
$tokenHash = hash('sha256', $rawOtp);
$expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes

try {
    // Invalidate any previous reset tokens for this user
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
        ->execute([':uid' => $user['user_id']]);

    $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)')
        ->execute([':uid' => $user['user_id'], ':hash' => $tokenHash, ':exp' => $expiresAt]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not process password reset request. Please try again.']);
    exit;
}

/* ── Send reset OTP email via Brevo ────────────────────────────────── */
$mailResult = mail_send(
    $user['email'],
    'Reset Your StockSmart Password Code',
    mail_render_otp($user['full_name'], $rawOtp, 'reset'),
    true
);

try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([':uid' => $user['user_id'], ':eid' => $user['user_id'], ':desc' => $user['full_name'] . ' requested password reset OTP']);
} catch (Throwable $e) { /* non-fatal */ }

if ($mailResult['driver'] === 'log') {
    $genericResponse['otp_preview'] = $rawOtp;
}

echo json_encode($genericResponse);
