<?php
/**
 * ============================================================================
 *  StockSmart — Forgot Password Request (forgot-password.php)
 * ============================================================================
 *  GET  forgot-password.php          → renders the request form
 *  POST forgot-password.php {email}  → issues a reset token (always returns
 *                                        ok:true regardless of whether the
 *                                        email exists, to avoid user
 *                                        enumeration)
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

$body = json_decode(file_get_contents('php://input'), true);
$email = trim((string) ($body['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

$genericResponse = ['ok' => true, 'message' => 'If an account with that email exists, a reset link has been sent.'];

$stmt = $pdo->prepare('SELECT user_id, full_name, email, status FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'suspended') {
    echo json_encode($genericResponse);
    exit;
}

$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

try {
    $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)')
        ->execute([':uid' => $user['user_id'], ':hash' => $tokenHash, ':exp' => $expiresAt]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not process the request. Please try again.']);
    exit;
}

$scheme = app_is_https() ? 'https://' : 'http://';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$resetUrl = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/reset-password.php?token=' . $rawToken;

$mailResult = mail_send($user['email'], 'Reset your StockSmart password', mail_render_reset_link($user['full_name'], $resetUrl));

try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([':uid' => $user['user_id'], ':eid' => $user['user_id'], ':desc' => $user['full_name'] . ' requested a password reset']);
} catch (Throwable $e) { /* non-fatal */ }

$genericResponse['reset_preview'] = $mailResult['driver'] === 'log' ? $resetUrl : null;
echo json_encode($genericResponse);
