<?php
/**
 * ============================================================================
 *  StockSmart — OTP Verification (verify-otp.php)
 * ============================================================================
 *  GET  verify-otp.php?email=...&type=register  → renders verify-otp.html
 *  POST verify-otp.php {email, otp, type}        → verifies 6-digit OTP code
 *  POST verify-otp.php {email, action:'resend'}  → resends a new 6-digit OTP code
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

$body   = json_decode(file_get_contents('php://input'), true);
$email  = trim((string) ($body['email'] ?? ''));
$otp    = trim((string) ($body['otp']   ?? ''));
$type   = trim((string) ($body['type']  ?? 'register'));
$action = trim((string) ($body['action'] ?? 'verify'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

/* ── Resend action ─────────────────────────────────────────────────── */
if ($action === 'resend') {
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, status FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'No account found with that email address.']);
        exit;
    }

    $rawOtp    = sprintf('%06d', random_int(100000, 999999));
    $otpHash   = hash('sha256', $rawOtp);
    $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes

    $pdo->prepare('UPDATE users SET otp_code = :code, otp_expires_at = :exp WHERE user_id = :id')
        ->execute([':code' => $otpHash, ':exp' => $expiresAt, ':id' => $user['user_id']]);

    $mailResult = mail_send(
        $user['email'],
        'Your StockSmart Verification Code',
        mail_render_otp($user['full_name'], $rawOtp, $type),
        true
    );

    $res = ['ok' => true, 'message' => 'A new 6-digit verification code has been sent to your email.'];
    if ($mailResult['driver'] === 'log') {
        $res['otp_preview'] = $rawOtp;
    }
    echo json_encode($res);
    exit;
}

/* ── Verify action ──────────────────────────────────────────────────── */
if (strlen($otp) !== 6 || !ctype_digit($otp)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid 6-digit OTP code.']);
    exit;
}

$stmt = $pdo->prepare('
    SELECT user_id, full_name, email, status, otp_code, otp_expires_at, totp_enabled
    FROM users
    WHERE LOWER(email) = LOWER(:email)
    LIMIT 1
');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Account not found. Please register again.']);
    exit;
}

if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No verification code requested or code already used.']);
    exit;
}

if (strtotime((string) $user['otp_expires_at']) < time()) {
    http_response_code(410);
    echo json_encode(['error' => 'Verification code has expired. Click "Resend OTP" to get a new code.']);
    exit;
}

$expectedHash = $user['otp_code'];
$givenHash    = hash('sha256', $otp);

if (!hash_equals($expectedHash, $givenHash)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid OTP code. Please check your email and try again.']);
    exit;
}

/* ── OTP is valid! Activate account ──────────────────────────────────── */
$pdo->prepare("
    UPDATE users
    SET status = 'active', email_verified_at = NOW(), otp_code = NULL, otp_expires_at = NULL
    WHERE user_id = :id
")->execute([':id' => $user['user_id']]);

// Set 2FA pending session so user is guided directly to mandatory 2FA setup
$_SESSION['2fa_pending_user_id'] = (int) $user['user_id'];
$_SESSION['2fa_pending_email']   = $user['email'];
$_SESSION['2fa_pending_name']    = $user['full_name'];

try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([
        ':uid'  => $user['user_id'],
        ':eid'  => $user['user_id'],
        ':desc' => $user['full_name'] . ' verified email via OTP',
    ]);
} catch (Throwable $e) { /* non-fatal */ }

session_write_close();

echo json_encode([
    'ok'       => true,
    'message'  => 'Email verified successfully! Setting up your mandatory Auth App...',
    'redirect' => 'setup-2fa.php',
]);
