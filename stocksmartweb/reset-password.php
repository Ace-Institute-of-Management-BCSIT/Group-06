<?php
/**
 * ============================================================================
 *  StockSmart — Password Reset (reset-password.php)
 * ============================================================================
 *  GET  reset-password.php?token=...                 → renders the reset form
 *  POST reset-password.php {token, password, confirm} → sets the new password
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

$body = json_decode(file_get_contents('php://input'), true);
$token = trim((string) ($body['token'] ?? ''));
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirm_password'] ?? '');

if ($token === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Missing reset token.']);
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

$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare('
    SELECT t.token_id, t.user_id, t.expires_at, t.used_at, u.full_name
    FROM password_reset_tokens t
    JOIN users u ON u.user_id = t.user_id
    WHERE t.token_hash = :hash
    LIMIT 1
');
$stmt->execute([':hash' => $tokenHash]);
$row = $stmt->fetch();

if (!$row || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
    http_response_code(410);
    echo json_encode(['error' => 'This reset link is invalid or has expired. Please request a new one.']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET password_hash = :hash, failed_login_attempts = 0, locked_until = NULL WHERE user_id = :id')
        ->execute([':hash' => $passwordHash, ':id' => $row['user_id']]);
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = :id')
        ->execute([':id' => $row['token_id']]);
    // Invalidate any other outstanding reset tokens for this user.
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
        ->execute([':uid' => $row['user_id']]);
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'security', 'users', :eid, :desc)"
    )->execute([':uid' => $row['user_id'], ':eid' => $row['user_id'], ':desc' => $row['full_name'] . ' reset their password']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not reset the password. Please try again.']);
    exit;
}

echo json_encode(['ok' => true, 'redirect' => 'login.php']);
