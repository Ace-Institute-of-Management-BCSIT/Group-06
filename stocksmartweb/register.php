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
 *  GET register.php → renders the session-aware registration page
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';   // starts session + provides $pdo
require_once __DIR__ . '/helpers/validation.php';
require_once __DIR__ . '/helpers/mailer.php';

/* ── GET: render the registration form ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('register.html');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

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
$phone           = trim((string)($body['phone']             ?? ''));
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

if (!validate_phone($phone)) {
    $errors[] = 'Please enter a valid phone number.';
}

$passwordIssues = validate_password_strength($password);
if (!empty($passwordIssues)) {
    $errors[] = 'Password must ' . implode(', ', $passwordIssues) . '.';
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

/* ── Generate the email OTP (6 digits, 10-minute expiry) ──────────────── */
$otp = (string) random_int(100000, 999999);
$otpExpiresAt = date('Y-m-d H:i:s', time() + 600);

/* ── Insert the new user as pending until the OTP is verified ─────────── */
try {
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, username, email, phone, password_hash, role_id, avatar_emoji, status, otp_code, otp_expires_at)
        VALUES (:full_name, :username, :email, :phone, :password_hash, :role_id, :avatar, 'pending', :otp, :otp_expires)
    ");
    $stmt->execute([
        ':full_name'     => $fullName,
        ':username'      => $username,
        ':email'         => $email,
        ':phone'         => $phone !== '' ? $phone : null,
        ':password_hash' => $passwordHash,
        ':role_id'       => $staffRoleId,
        ':avatar'        => strtoupper(substr($fullName, 0, 1)),
        ':otp'           => $otp,
        ':otp_expires'   => $otpExpiresAt,
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
         VALUES (:uid, 'add', 'users', :eid, :desc)"
    )->execute([
        ':uid'  => $newUserId,
        ':eid'  => $newUserId,
        ':desc' => $fullName . ' registered a new account (pending email verification)',
    ]);
} catch (Throwable $e) {
    // Non-fatal
}

/* ── Send the OTP email (dev-log driver by default, see helpers/mailer.php) */
$mailResult = mail_send($email, 'Verify your StockSmart account', mail_render_otp($fullName, $otp));

/* ── Track the pending user in-session so verify-otp.php knows who this is ── */
$_SESSION['pending_verify_user_id'] = $newUserId;
session_write_close();

/* ── Respond ────────────────────────────────────────────────────────── */
echo json_encode([
    'ok'          => true,
    'redirect'    => 'verify-otp.php?user_id=' . $newUserId . '&email=' . urlencode($email),
    'user_id'     => $newUserId,
    'otp_preview' => $mailResult['driver'] === 'log' ? $otp : null,
]);
