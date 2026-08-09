<?php
/**
 * ============================================================================
 *  StockSmart — Password Reset (reset-password.php)
 * ============================================================================
 *  GET  reset-password.php?email=...                       → renders reset-password.html
 *  POST reset-password.php {email, otp, password, confirm}  → resets user password using OTP
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('reset-password.html');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body            = json_decode(file_get_contents('php://input'), true);
$email           = trim((string) ($body['email'] ?? ''));
$otp             = trim((string) ($body['otp'] ?? ''));
$password        = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirm_password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($otp) !== 6 || !ctype_digit($otp)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid 6-digit OTP code.']);
    exit;
}

$passwordIssues = validate_password_strength($password);
if (!empty($passwordIssues)) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must ' . implode(', ', $passwordIssues) . '.']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(['error' => 'Passwords do not match.']);
    exit;
}

// Find user by email
$stmt = $pdo->prepare('SELECT user_id, full_name FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid email or reset OTP code.']);
    exit;
}

$tokenHash = hash('sha256', $otp);
$stmt = $pdo->prepare('
    SELECT token_id, expires_at, used_at
    FROM password_reset_tokens
    WHERE user_id = :uid AND token_hash = :hash
    ORDER BY token_id DESC
    LIMIT 1
');
$stmt->execute([':uid' => $user['user_id'], ':hash' => $tokenHash]);
$row = $stmt->fetch();

if (!$row || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
    http_response_code(410);
    echo json_encode(['error' => 'This 6-digit reset code is invalid or has expired. Please request a new code.']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET password_hash = :hash, failed_login_attempts = 0, locked_until = NULL WHERE user_id = :id')
        ->execute([':hash' => $passwordHash, ':id' => $user['user_id']]);
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = :id')
        ->execute([':id' => $row['token_id']]);
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
        ->execute([':uid' => $user['user_id']]);

    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([':uid' => $user['user_id'], ':eid' => $user['user_id'], ':desc' => $user['full_name'] . ' reset their password via OTP']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not reset password. Please try again.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Password reset successfully!', 'redirect' => 'login.php?reset=1']);
