<?php
/**
 * User management (Admin/Super Admin only, gated by the "users.manage" permission).
 * GET    api/users.php            → list all users with role + lockout status
 * POST   api/users.php            → create a user (active immediately — no email verification step)
 * PUT    api/users.php?id=1       → update a user's profile/role/status, optionally reset their password
 * POST   api/users.php?id=1&action=unlock → clear a lockout
 * DELETE api/users.php?id=1       → deactivate (soft delete)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../helpers/validation.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    api_require_permission('users.manage');
    $rows = $pdo->query("
        SELECT u.user_id, u.full_name, u.username, u.email, u.phone, u.avatar_emoji,
               u.status, u.role_id, r.role_name, u.last_login_at, u.locked_until,
               u.failed_login_attempts, u.created_at
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        ORDER BY u.full_name
    ")->fetchAll();
    $roles = $pdo->query('SELECT role_id, role_name FROM roles ORDER BY role_name')->fetchAll();
    echo json_encode(['users' => $rows, 'roles' => $roles]);
    exit;
}

$user = api_require_permission('users.manage');
api_verify_csrf();
$body = api_json_body();

if ($method === 'POST' && ($_GET['action'] ?? '') === 'unlock') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(422); echo json_encode(['error' => 'User id is required.']); exit; }
    $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = :id')->execute([':id' => $id]);
    api_log_activity($pdo, $user['id'], 'security', 'users', $id, 'Account unlocked by an administrator');
    echo json_encode(['ok' => true]);
    exit;
}

function users_validate_role(PDO $pdo, $roleId): ?int
{
    $roleId = (int) $roleId;
    $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_id = :id');
    $stmt->execute([':id' => $roleId]);
    return $stmt->fetch() ? $roleId : null;
}

if ($method === 'POST') {
    $fullName = trim((string) ($body['full_name'] ?? ''));
    $username = trim((string) ($body['username'] ?? ''));
    $email    = trim((string) ($body['email'] ?? ''));
    $phone    = trim((string) ($body['phone'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $roleId   = users_validate_role($pdo, $body['role_id'] ?? 0);

    $errors = [];
    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) $errors[] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!validate_phone($phone)) $errors[] = 'Please enter a valid phone number.';
    if ($roleId === null) $errors[] = 'Please choose a valid role.';
    $pwIssues = validate_password_strength($password);
    if (!empty($pwIssues)) $errors[] = 'Password must ' . implode(', ', $pwIssues) . '.';

    if (!empty($errors)) { http_response_code(422); echo json_encode(['error' => implode(' ', $errors)]); exit; }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, email, phone, password_hash, role_id, avatar_emoji, status, email_verified_at)
            VALUES (:name, :username, :email, :phone, :hash, :role_id, :avatar, 'active', NOW())
        ");
        $stmt->execute([
            ':name' => $fullName, ':username' => $username, ':email' => $email,
            ':phone' => $phone !== '' ? $phone : null,
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role_id' => $roleId, ':avatar' => strtoupper(substr($fullName, 0, 1)),
        ]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) { http_response_code(409); echo json_encode(['error' => 'That username or email is already registered.']); exit; }
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();
    api_log_activity($pdo, $user['id'], 'add', 'users', $id, "User {$fullName} created by an administrator");
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(422); echo json_encode(['error' => 'User id is required.']); exit; }

if ($method === 'PUT') {
    $fullName = trim((string) ($body['full_name'] ?? ''));
    $email    = trim((string) ($body['email'] ?? ''));
    $phone    = trim((string) ($body['phone'] ?? ''));
    $roleId   = users_validate_role($pdo, $body['role_id'] ?? 0);
    $status   = (string) ($body['status'] ?? 'active');
    $newPassword = (string) ($body['new_password'] ?? '');

    $errors = [];
    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!validate_phone($phone)) $errors[] = 'Please enter a valid phone number.';
    if ($roleId === null) $errors[] = 'Please choose a valid role.';
    if (!in_array($status, ['active', 'inactive', 'suspended'], true)) $errors[] = 'Invalid status.';
    if ($id === $user['id'] && $status !== 'active') $errors[] = 'You cannot deactivate your own account.';
    if ($newPassword !== '') {
        $pwIssues = validate_password_strength($newPassword);
        if (!empty($pwIssues)) $errors[] = 'Password must ' . implode(', ', $pwIssues) . '.';
    }
    if (!empty($errors)) { http_response_code(422); echo json_encode(['error' => implode(' ', $errors)]); exit; }

    $sql = 'UPDATE users SET full_name = :name, email = :email, phone = :phone, role_id = :role_id, status = :status';
    $params = [':name' => $fullName, ':email' => $email, ':phone' => $phone !== '' ? $phone : null, ':role_id' => $roleId, ':status' => $status, ':id' => $id];
    if ($newPassword !== '') {
        $sql .= ', password_hash = :hash';
        $params[':hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $sql .= ' WHERE user_id = :id';

    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) { http_response_code(409); echo json_encode(['error' => 'That email is already in use by another account.']); exit; }
        throw $e;
    }
    unset($_SESSION['permissions']); // in case the editing admin changed their own role
    api_log_activity($pdo, $user['id'], 'update', 'users', $id, "User {$fullName} updated" . ($newPassword !== '' ? ' (password reset)' : ''));
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    if ($id === $user['id']) { http_response_code(422); echo json_encode(['error' => 'You cannot deactivate your own account.']); exit; }
    $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = :id")->execute([':id' => $id]);
    api_log_activity($pdo, $user['id'], 'delete', 'users', $id, 'User deactivated by an administrator');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
