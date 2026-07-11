<?php
/**
 * Authenticated profile API used by profile.html.
 * GET                 -> profile, activity and login history
 * PUT                 -> update name, email, phone and avatar
 * POST ?action=change_password -> change the current user's password
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$user = api_require_login();

function profile_time(string $datetime): string
{
    $timestamp = strtotime($datetime);
    return $timestamp === false ? '—' : date('d M Y, H:i', $timestamp);
}

function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) { $out .= strtoupper(substr($part, 0, 1)); }
    return $out ?: 'US';
}

function profile_payload(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT u.full_name, u.username, u.email, u.phone, u.avatar_emoji, u.status,
               u.created_at, u.last_login_at, r.role_name
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $userId]);
    $account = $stmt->fetch();
    if (!$account) {
        http_response_code(404);
        echo json_encode(['error' => 'User account was not found.']);
        exit;
    }

    $history = $pdo->prepare('
        SELECT activity_type, description, created_at
        FROM activity_logs
        WHERE user_id = :id AND activity_type NOT IN (\'login\', \'logout\')
        ORDER BY created_at DESC
        LIMIT 12
    ');
    $history->execute([':id' => $userId]);

    $logins = $pdo->prepare('
        SELECT activity_type, created_at
        FROM activity_logs
        WHERE user_id = :id AND activity_type IN (\'login\', \'logout\')
        ORDER BY created_at DESC
        LIMIT 12
    ');
    $logins->execute([':id' => $userId]);

    return [
        'name'             => $account['full_name'],
        'username'         => $account['username'],
        'email'            => $account['email'],
        'phone'            => $account['phone'] ?? '',
        'avatar'           => preg_match('/^[A-Z]{1,3}$/', (string) $account['avatar_emoji']) ? $account['avatar_emoji'] : profile_initials($account['full_name']),
        'role'             => $account['role_name'],
        'status'           => ucfirst($account['status']),
        'memberSince'      => profile_time($account['created_at']),
        'lastLogin'        => $account['last_login_at'] ? profile_time($account['last_login_at']) : '—',
        'activityHistory'  => array_map(static fn (array $row): array => [
            'type' => $row['activity_type'],
            'text' => $row['description'],
            'time' => profile_time($row['created_at']),
        ], $history->fetchAll()),
        'loginHistory'     => array_map(static fn (array $row): array => [
            'type' => $row['activity_type'],
            'time' => profile_time($row['created_at']),
        ], $logins->fetchAll()),
    ];
}

if ($method === 'GET') {
    echo json_encode(profile_payload($pdo, $user['id']));
    exit;
}

if ($method === 'PUT') {
    api_verify_csrf();
    $body = api_json_body();
    $name = trim((string) ($body['full_name'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $avatar = profile_initials($name);

    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(422); echo json_encode(['error' => 'Please provide a name of up to 100 characters.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        http_response_code(422); echo json_encode(['error' => 'Please provide a valid email address.']); exit;
    }
    if (mb_strlen($phone) > 20) {
        http_response_code(422); echo json_encode(['error' => 'Phone number must be 20 characters or fewer.']); exit;
    }

    $duplicate = $pdo->prepare('SELECT user_id FROM users WHERE LOWER(email) = LOWER(:email) AND user_id != :id LIMIT 1');
    $duplicate->execute([':email' => $email, ':id' => $user['id']]);
    if ($duplicate->fetch()) {
        http_response_code(409); echo json_encode(['error' => 'That email address is already in use.']); exit;
    }

    $pdo->prepare('UPDATE users SET full_name = :name, email = :email, phone = :phone, avatar_emoji = :avatar WHERE user_id = :id')
        ->execute([':name' => $name, ':email' => $email, ':phone' => $phone ?: null, ':avatar' => $avatar, ':id' => $user['id']]);
    $_SESSION['user_name'] = $name;
    $_SESSION['avatar'] = $avatar;
    api_log_activity($pdo, $user['id'], 'update', 'users', $user['id'], 'Profile details updated');
    echo json_encode(['ok' => true, 'profile' => profile_payload($pdo, $user['id'])]);
    exit;
}

if ($method === 'POST' && $action === 'change_password') {
    api_verify_csrf();
    $body = api_json_body();
    $current = (string) ($body['current_password'] ?? '');
    $new = (string) ($body['new_password'] ?? '');
    $confirmation = (string) ($body['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirmation === '') {
        http_response_code(422); echo json_encode(['error' => 'Please complete every password field.']); exit;
    }
    if (strlen($new) < 8) {
        http_response_code(422); echo json_encode(['error' => 'Your new password must contain at least 8 characters.']); exit;
    }
    if ($new !== $confirmation) {
        http_response_code(422); echo json_encode(['error' => 'New passwords do not match.']); exit;
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $account = $stmt->fetch();
    if (!$account || !password_verify($current, $account['password_hash'])) {
        http_response_code(422); echo json_encode(['error' => 'Your current password is incorrect.']); exit;
    }

    $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id')
        ->execute([':hash' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']]);
    api_log_activity($pdo, $user['id'], 'update', 'users', $user['id'], 'Account password changed');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
