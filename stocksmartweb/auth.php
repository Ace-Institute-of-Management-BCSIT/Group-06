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
 *       redirects to login.html for normal page requests.
 *
 *  It exposes a helper auth_user() that returns the session user array so
 *  pages don't repeat the same session-key lookups.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';   // starts session + provides $pdo

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
        'avatar'   => $_SESSION['avatar']    ?? '🧑',
    ];
}

/**
 * Redirect unauthenticated requests.
 * AJAX requests (Accept: application/json or X-Requested-With) get 401.
 * Browser requests get a redirect to login.html.
 */
function auth_require(): void
{
    if (!empty($_SESSION['user_id'])) {
        return; // authenticated — proceed
    }

    $isAjax = (
        (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_CONTENT_TYPE']) && str_contains($_SERVER['HTTP_CONTENT_TYPE'], 'application/json'))
    );

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not authenticated', 'redirect' => 'login.html']);
        exit;
    }

    header('Location: login.html');
    exit;
}

// Run the guard immediately when this file is included.
auth_require();
