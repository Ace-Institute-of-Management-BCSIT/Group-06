<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['logout'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
        echo json_encode(['ok' => true, 'loggedIn' => false]);
        exit;
    }
    echo json_encode([
        'loggedIn' => !empty($_SESSION['user_id']),
        'user'     => isset($_SESSION['user_id']) ? [
            'id'       => $_SESSION['user_id'],
            'name'     => $_SESSION['user_name'] ?? '',
            'username' => $_SESSION['username']  ?? '',
            'role'     => $_SESSION['user_role'] ?? '',
            'avatar'   => $_SESSION['avatar']    ?? '',
        ] : null,
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body', 'raw' => substr($rawBody, 0, 100)]);
    exit;
}

$credential = trim((string)($body['username'] ?? ''));
$password   = (string)($body['password'] ?? '');
$remember   = !empty($body['remember']);

if ($credential === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.username, u.password_hash,
               u.avatar_emoji, u.status, r.role_name
        FROM   users u
        JOIN   roles r ON r.role_id = u.role_id
        WHERE  LOWER(u.username) = LOWER(:cred1)
           OR  LOWER(u.email)    = LOWER(:cred2)
        LIMIT  1
    ");
    $stmt->execute([':cred1' => $credential, ':cred2' => $credential]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB query failed: ' . $e->getMessage()]);
    exit;
}

$hashToCheck = $user['password_hash'] ?? '$2y$10$invalidHashThatWillNeverMatch00000000000000000000000000000';
$valid = password_verify($password, $hashToCheck);

if (!$user || !$valid) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Your account is inactive. Please contact an administrator.']);
    exit;
}

// Session fixation protection
try {
    session_regenerate_id(true);
} catch (Throwable $e) {
    // non-fatal on some configs
}

if ($remember) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(),
        time() + 60 * 60 * 24 * 30,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']);
}

$_SESSION['user_id']   = (int) $user['user_id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['username']  = $user['username'];
$_SESSION['user_role'] = $user['role_name'];
$_SESSION['avatar']    = $user['avatar_emoji'] ?? '';

// Update last_login_at
try {
    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = :id")
        ->execute([':id' => $user['user_id']]);
} catch (Throwable $e) { /* non-fatal */ }

// Log the login
try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:id, 'login', 'users', :id, :desc)"
    )->execute([
        ':id'   => $user['user_id'],
        ':desc' => $user['full_name'] . ' logged in',
    ]);
} catch (Throwable $e) { /* non-fatal */ }

session_write_close();

echo json_encode([
    'ok'       => true,
    'redirect' => 'dashboard.php',
    'user'     => [
        'id'     => (int) $user['user_id'],
        'name'   => $user['full_name'],
        'role'   => $user['role_name'],
        'avatar' => $user['avatar_emoji'] ?? '',
    ],
]);
