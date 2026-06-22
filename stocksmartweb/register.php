<?php
/**
 * ============================================================================
 *  StockSmart — Registration API (register.php)
 * ============================================================================
 *  POST register.php
 *  Body: {
 *    "full_name":        "Jane Doe",
 *    "username":         "jane.doe",
 *    "email":            "jane@example.com",
 *    "password":         "Secret@123",
 *    "confirm_password": "Secret@123"
 *  }
 *
 *  On success:
 *    - Saves the account to the users table (role = Staff, status = active).
 *    - Automatically logs the new user in (session fixation protection applied).
 *    - Returns { "ok": true, "redirect": "dashboard.php" }
 *
 *  On failure:
 *    - Returns the appropriate HTTP status + { "error": "..." }
 *
 *  GET register.php → redirects to register.html (convenience redirect)
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';   // starts session + provides $pdo

header('Content-Type: application/json; charset=utf-8');

/* ── GET: redirect to the registration form ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: register.html');
    exit;
}

/* ── Only POST beyond this point ─────────────────────────────────────── */
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

/* ── Extract + sanitise inputs ──────────────────────────────────────── */
$fullName        = trim((string)($body['full_name']        ?? ''));
$username        = trim((string)($body['username']         ?? ''));
$email           = trim((string)($body['email']            ?? ''));
$password        = (string)($body['password']              ?? '');
$confirmPassword = (string)($body['confirm_password']      ?? '');

/* ── Server-side validation ─────────────────────────────────────────── */
$errors = [];

if ($fullName === '') {
    $errors[] = 'Full name is required.';
}

if ($username === '') {
    $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3–50 characters and may only contain letters, numbers, dots, dashes, or underscores.';
}

if ($email === '') {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

/* ── Duplicate checks ───────────────────────────────────────────────── */
$stmt = $pdo->prepare('SELECT user_id FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1');
$stmt->execute([':username' => $username]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'That username is already taken. Please choose another.']);
    exit;
}

$stmt = $pdo->prepare('SELECT user_id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'An account with that email address already exists.']);
    exit;
}

/* ── Look up the Staff role_id ──────────────────────────────────────── */
$stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Staff' LIMIT 1");
$stmt->execute();
$roleRow = $stmt->fetch();
if (!$roleRow) {
    // Fallback: use role_id = 3 which the seed data assigns to Staff
    $staffRoleId = 3;
} else {
    $staffRoleId = (int) $roleRow['role_id'];
}

/* ── Hash the password ──────────────────────────────────────────────── */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

/* ── Insert the new user ────────────────────────────────────────────── */
try {
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, username, email, password_hash, role_id, avatar_emoji, status)
        VALUES (:full_name, :username, :email, :password_hash, :role_id, '🧑', 'active')
    ");
    $stmt->execute([
        ':full_name'     => $fullName,
        ':username'      => $username,
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        ':role_id'       => $staffRoleId,
    ]);
    $newUserId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        // Duplicate key — race condition between our check and insert
        http_response_code(409);
        echo json_encode(['error' => 'That username or email is already registered.']);
        exit;
    }
    throw $e;
}

/* ── Log the registration ───────────────────────────────────────────── */
try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'add', 'users', :uid, :desc)"
    )->execute([
        ':uid'  => $newUserId,
        ':desc' => $fullName . ' registered a new account',
    ]);
} catch (Throwable $e) {
    // Non-fatal
}

/* ── Auto-login: session fixation protection then write session ──────── */
session_regenerate_id(true);

$_SESSION['user_id']   = $newUserId;
$_SESSION['user_name'] = $fullName;
$_SESSION['username']  = $username;
$_SESSION['user_role'] = 'Staff';
$_SESSION['avatar']    = '🧑';

session_write_close();

/* ── Respond ────────────────────────────────────────────────────────── */
echo json_encode([
    'ok'       => true,
    'redirect' => 'dashboard.php',
    'user'     => [
        'id'     => $newUserId,
        'name'   => $fullName,
        'role'   => 'Staff',
        'avatar' => '🧑',
    ],
]);
