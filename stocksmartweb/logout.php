<?php
/**
 * ============================================================================
 *  StockSmart — Logout (logout.php)
 * ============================================================================
 *  GET  logout.php   — destroys the session, redirects to login.php
 *  POST logout.php   — same, but returns JSON { "ok": true }
 *                      (for any future fetch()-based logout button)
 *
 *  Session destruction follows PHP best practice:
 *    1. Empty the $_SESSION superglobal.
 *    2. Delete the session cookie from the browser.
 *    3. Destroy the server-side session file.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';   // starts session if not already running

// Log the logout event before destroying the session so we still have user_id
if (!empty($_SESSION['user_id'])) {
    try {
        $pdo->prepare(
            "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
             VALUES (:uid, 'logout', 'users', :eid, :desc)"
        )->execute([
            ':uid'  => (int) $_SESSION['user_id'],
            ':eid'  => (int) $_SESSION['user_id'],
            ':desc' => ($_SESSION['user_name'] ?? 'Unknown') . ' logged out',
        ]);
    } catch (Throwable $e) {
        // Non-fatal — don't block logout if the log INSERT fails
    }
}

try {
    $pdo->prepare('DELETE FROM user_sessions WHERE session_id = :sid')->execute([':sid' => session_id()]);
} catch (Throwable $e) { /* session-audit migration may not yet be installed */ }

// 1. Clear the session data array
$_SESSION = [];

// 2. Delete the session cookie so the browser forgets the session ID
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,   // expire in the past
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the server-side session
session_destroy();

// Respond
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'redirect' => 'login.php']);
    exit;
}

header('Location: login.php');
exit;
