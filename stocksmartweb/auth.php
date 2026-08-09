<?php
/**
 * ============================================================================
 *  StockSmart — Authentication Guard (auth.php)
 * ============================================================================
 *  Include this at the top of every protected PHP page:
 *
 *    require_once __DIR__ . '/auth.php';
 *
 *  What it does:
 *    1. Requires db.php (which starts the session if not already started).
 *    2. Checks that $_SESSION['user_id'] is set and is a positive integer.
 *    3. If not authenticated, sends a JSON 401 for AJAX requests or
 *       redirects to login.php for normal page requests.
 *
 *  It exposes a helper auth_user() that returns the session user array so
 *  pages don't repeat the same session-key lookups.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';   // starts session + provides $pdo

/** Permissions are cached in the session and refreshed at the next login. */
function auth_permissions(): array
{
    global $pdo;
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return $_SESSION['permissions'];
    }

    try {
        $stmt = $pdo->prepare('
            SELECT p.permission_key
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.permission_id
            JOIN users u ON u.role_id = rp.role_id
            WHERE u.user_id = :id
            ORDER BY p.permission_key
        ');
        $stmt->execute([':id' => (int) ($_SESSION['user_id'] ?? 0)]);
        $permissions = array_column($stmt->fetchAll(), 'permission_key');
    } catch (Throwable $e) {
        // Enables existing installations until the production upgrade is imported.
        $permissions = ($_SESSION['user_role'] ?? '') === 'Admin' ? ['*'] : [];
    }
    $_SESSION['permissions'] = $permissions;
    return $permissions;
}

function auth_has_permission(string $permission): bool
{
    $permissions = auth_permissions();
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

/**
 * Returns the currently authenticated user's data from the session,
 * or null if no one is logged in.
 */
function auth_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => (int) $_SESSION['user_id'],
        'name'     => $_SESSION['user_name'] ?? '',
        'username' => $_SESSION['username']  ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'avatar'   => $_SESSION['avatar']    ?? 'US',
        'csrf'     => $_SESSION['csrf_token'] ?? '',
        'permissions' => auth_permissions(),
    ];
}

/**
 * Redirect unauthenticated requests.
 * AJAX requests (Accept: application/json or X-Requested-With) get 401.
 * Browser requests get a redirect to login.php.
 */
function auth_require(): void
{
    global $pdo;
    $now = time();
    if (!empty($_SESSION['user_id'])) {
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
        $timeout = !empty($_SESSION['remember_me']) ? APP_REMEMBER_TIMEOUT : APP_SESSION_TIMEOUT;
        if (($now - $lastActivity) <= $timeout) {
            $_SESSION['last_activity'] = $now;
            try {
                $pdo->prepare('UPDATE user_sessions SET last_seen_at = NOW(), expires_at = FROM_UNIXTIME(:expiry) WHERE session_id = :sid')
                    ->execute([':expiry' => $now + $timeout, ':sid' => session_id()]);
            } catch (Throwable $e) { /* session-audit migration may not yet be installed */ }
            return;
        }
        $_SESSION = [];
        session_destroy();
    }

    $isAjax = (
        (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_CONTENT_TYPE']) && str_contains($_SERVER['HTTP_CONTENT_TYPE'], 'application/json'))
    );

    if (!empty($_SESSION['2fa_pending_user_id'])) {
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => '2FA authentication required', 'redirect' => 'verify-2fa.php']);
            exit;
        }
        header('Location: verify-2fa.php');
        exit;
    }

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not authenticated', 'redirect' => 'login.php']);
        exit;
    }

    header('Location: login.php');
    exit;
}

// Run the guard immediately when this file is included.
auth_require();

/**
 * Call this AFTER require_once auth.php on any page that should only be
 * reachable by specific roles (e.g. products.php should block Cashiers).
 * Shows a plain 403 page rather than a JSON error, since this guards
 * full HTML page loads, not API calls.
 */
function auth_require_role(array $allowedRoles): void
{
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        require_once __DIR__ . '/page_renderer.php';
        render_ui_template('403.html', auth_user());
        exit;
    }
}

function auth_require_permission(string $permission): void
{
    if (auth_has_permission($permission)) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/page_renderer.php';
    render_ui_template('403.html', auth_user());
    exit;
}
