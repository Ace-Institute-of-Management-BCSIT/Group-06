<?php
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
               u.avatar_emoji, u.status, u.failed_login_attempts, u.locked_until, r.role_name
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
            if ($lockedUntil !== null) {
                $pdo->prepare(
                    "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
                     VALUES (:uid, 'security', 'users', :eid, :desc)"
                )->execute([':uid' => $user['user_id'], ':eid' => $user['user_id'], ':desc' => $user['full_name'] . ' account locked after repeated failed logins']);
            }
        } catch (Throwable $e) { /* non-fatal */ }
    }
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Your account is inactive. Please contact an administrator.']);
    exit;
}

// Successful credential check — clear any lockout state.
try {
    $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = :id')
        ->execute([':id' => $user['user_id']]);
} catch (Throwable $e) { /* non-fatal */ }

// Session fixation protection
try {
    session_regenerate_id(true);
} catch (Throwable $e) {
    // non-fatal on some configs
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
$_SESSION['last_activity'] = time();
$_SESSION['remember_me'] = $remember;
unset($_SESSION['permissions']);

// Update last_login_at
try {
    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = :id")
        ->execute([':id' => $user['user_id']]);
} catch (Throwable $e) { /* non-fatal */ }

// Log the login
try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'login', 'users', :eid, :desc)"
    )->execute([
        ':uid'  => $user['user_id'],
        ':eid'  => $user['user_id'],
        ':desc' => $user['full_name'] . ' logged in',
    ]);
} catch (Throwable $e) { /* non-fatal */ }

// Session inventory is used by the admin security screen once the production
// upgrade has been imported. It must never block an otherwise valid login.
try {
    $pdo->prepare('
        INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, last_seen_at, expires_at)
        VALUES (:sid, :uid, :ip, :agent, NOW(), FROM_UNIXTIME(:expiry))
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent), last_seen_at = VALUES(last_seen_at), expires_at = VALUES(expires_at)
    ')->execute([
        ':sid' => session_id(), ':uid' => $user['user_id'], ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ':agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), ':expiry' => time() + ($remember ? APP_REMEMBER_TIMEOUT : APP_SESSION_TIMEOUT),
    ]);
} catch (Throwable $e) { /* migration not yet imported or session audit unavailable */ }

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
