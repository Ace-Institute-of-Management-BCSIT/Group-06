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
 *    - Saves the account to the users table with status = 'pending'.
 *    - Generates a 6-digit OTP code, saves hash to users.otp_code.
 *    - Delivers the OTP email via Brevo.
 *    - Returns { "ok": true, "require_otp": true, "email": $email, "redirect": "verify-otp.php?email=..." }
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

/* ── Duplicate checks ────────────────────────────────────────────── */
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

/* ── Look up Staff role_id ─────────────────────────────────────────── */
$stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Staff' LIMIT 1");
$stmt->execute();
$roleRow = $stmt->fetch();
$staffRoleId = $roleRow ? (int) $roleRow['role_id'] : 3;

/* ── Hash password & generate 6-digit OTP ───────────────────────────── */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$rawOtp       = sprintf('%06d', random_int(100000, 999999));
$otpHash      = hash('sha256', $rawOtp);
$expiresAt    = date('Y-m-d H:i:s', time() + 900); // 15 minutes

/* ── Insert new user with pending status ────────────────────────────── */
try {
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, username, email, phone, password_hash, role_id, avatar_emoji, status, otp_code, otp_expires_at)
        VALUES (:full_name, :username, :email, :phone, :password_hash, :role_id, :avatar, 'pending', :otp_code, :otp_expires_at)
    ");
    $stmt->execute([
        ':full_name'      => $fullName,
        ':username'       => $username,
        ':email'          => $email,
        ':phone'          => $phone !== '' ? $phone : null,
        ':password_hash'  => $passwordHash,
        ':role_id'        => $staffRoleId,
        ':avatar'         => strtoupper(substr($fullName, 0, 1)),
        ':otp_code'       => $otpHash,
        ':otp_expires_at' => $expiresAt,
    ]);
    $newUserId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        http_response_code(409);
        echo json_encode(['error' => 'That username or email is already registered.']);
        exit;
    }
    throw $e;
}

/* ── Send registration OTP via Brevo / Mailer ────────────────────────── */
$mailResult = mail_send(
    $email,
    'Verify Your StockSmart Registration',
    mail_render_otp($fullName, $rawOtp, 'register'),
    true
);

/* ── Log the registration attempt ──────────────────────────────────── */
try {
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
         VALUES (:uid, 'add', 'users', :eid, :desc)"
    )->execute([
        ':uid'  => $newUserId,
        ':eid'  => $newUserId,
        ':desc' => $fullName . ' registered a new account (pending OTP verification)',
    ]);
} catch (Throwable $e) {
    // Non-fatal
}

session_write_close();

/* ── Respond with redirect to verify-otp.php ───────────────────────── */
$redirectUrl = 'verify-otp.php?email=' . urlencode($email) . '&type=register';

$responsePayload = [
    'ok'          => true,
    'require_otp' => true,
    'email'       => $email,
    'redirect'    => $redirectUrl,
    'message'     => 'Registration initialised! Verification code sent to your email.',
];

if ($mailResult['driver'] === 'log') {
    $responsePayload['otp_preview'] = $rawOtp;
}

echo json_encode($responsePayload);
