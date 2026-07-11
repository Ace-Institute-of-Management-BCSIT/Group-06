<?php
/**
 * ============================================================================
 *  StockSmart — API Bootstrap (api/_bootstrap.php)
 * ============================================================================
 *  require_once this at the top of every file under api/*.php.
 *
 *  Why this exists instead of reusing auth.php directly:
 *    auth.php's auth_require() redirects browsers to login.php, which is
 *    correct for full-page loads but wrong for a JSON API — a fetch() GET
 *    request doesn't send an "Accept: application/json" header by default,
 *    so auth.php would misdetect it as a normal page request and respond
 *    with an HTML redirect. The frontend's `res.json()` would then choke on
 *    HTML and surface a confusing "failed to load" toast instead of a clean
 *    401. Every api/*.php file must ALWAYS return JSON, never redirect.
 *
 *  What this file provides:
 *    - api_require_login()      : 401 JSON if no session, else user array
 *    - api_require_role([...])  : 403 JSON if role not permitted
 *    - api_verify_csrf()        : 419 JSON if X-CSRF-Token header is missing/wrong
 *    - api_json_body()          : parsed JSON request body as an array
 *    - api_log_activity(...)    : writes a row to activity_logs (non-fatal)
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';   // starts session, opens $pdo, ensures csrf_token

header('Content-Type: application/json; charset=utf-8');

// Warehouse used when a product's stock is set without picking a specific
// warehouse (the current UI doesn't expose per-warehouse stock entry).
if (!defined('DEFAULT_WAREHOUSE_ID')) {
    define('DEFAULT_WAREHOUSE_ID', 1); // Warehouse A
}

function api_current_user(): ?array
{
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $now = time();
    $timeout = !empty($_SESSION['remember_me']) ? APP_REMEMBER_TIMEOUT : APP_SESSION_TIMEOUT;
    if (($now - (int) ($_SESSION['last_activity'] ?? $now)) > $timeout) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $_SESSION['last_activity'] = $now;
    $permissions = [];
    try {
        $stmt = $pdo->prepare('SELECT p.permission_key FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.permission_id JOIN users u ON u.role_id = rp.role_id WHERE u.user_id = :id');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $permissions = array_column($stmt->fetchAll(), 'permission_key');
    } catch (Throwable $e) {
        $permissions = ($_SESSION['user_role'] ?? '') === 'Admin' ? ['*'] : [];
    }
    return [
        'id'   => (int) $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'permissions' => $permissions,
    ];
}

function api_require_permission(string $permission): array
{
    $user = api_require_login();
    if (!in_array('*', $user['permissions'], true) && !in_array($permission, $user['permissions'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to perform this action.']);
        exit;
    }
    return $user;
}

function api_require_login(): array
{
    $user = api_current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated. Please log in again.']);
        exit;
    }
    return $user;
}

/**
 * @param string[] $allowedRoles e.g. ['Admin', 'Manager']
 */
function api_require_role(array $allowedRoles): array
{
    $user = api_require_login();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Your role (' . $user['role'] . ') does not have permission to perform this action.']);
        exit;
    }
    return $user;
}

/**
 * Validates the X-CSRF-Token header against the session for any
 * state-changing request. Safe (GET) requests are never checked.
 */
function api_verify_csrf(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $sent     = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(419); // "Page Expired" convention borrowed from Laravel
        echo json_encode(['error' => 'Your session security token is missing or expired. Please refresh the page and try again.']);
        exit;
    }
}

function api_json_body(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function api_log_activity(PDO $pdo, ?int $userId, string $type, string $entityType, ?int $entityId, string $description): void
{
    try {
        $pdo->prepare(
            "INSERT INTO activity_logs (user_id, activity_type, entity_type, entity_id, description)
             VALUES (:uid, :type, :etype, :eid, :desc)"
        )->execute([
            ':uid'   => $userId,
            ':type'  => $type,
            ':etype' => $entityType,
            ':eid'   => $entityId,
            ':desc'  => $description,
        ]);
    } catch (Throwable $e) {
        // Non-fatal — never let logging break the actual request
    }
}
