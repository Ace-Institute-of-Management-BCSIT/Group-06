<?php
/**
 * ============================================================================
 *  StockSmart — Database Connection (PDO)
 * ============================================================================
 *  Drop this file into your project root (or /includes) and
 *  require_once 'db.php'; from any PHP page that needs database access.
 *
 *  Uses PDO with prepared statements (sql injection-safe), UTF-8mb4
 *  (for the emoji icon fields used throughout the UI), and exceptions
 *  for error handling so failures surface clearly during development.
 *
 *  Also starts the PHP session here (once, centrally) since every page
 *  that needs the database already requires this file — this is what
 *  lets the checkout cart persist across page loads and navigation via
 *  $_SESSION['cart']. See api/cart.php for the cart logic itself.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/middleware/security_headers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------------
// CSRF TOKEN — lazily generated once per session, reused by every page and
// every api/*.php endpoint. Pages inject it into the DOM (see auth.php's
// auth_user(), which now returns it as `csrf`); JS sends it back via the
// `X-CSRF-Token` header on POST/PUT/DELETE requests. See api/_bootstrap.php
// for the server-side check.
// ---------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------------------
// 1) CONNECTION SETTINGS — edit these to match your XAMPP / phpMyAdmin setup
// ---------------------------------------------------------------------------
$dbHost = app_env('STOCKSMART_DB_HOST', '127.0.0.1');
$dbPort = app_env('STOCKSMART_DB_PORT', '3306');
$dbName = app_env('STOCKSMART_DB_NAME', 'stocksmart');
$dbUser = app_env('STOCKSMART_DB_USER', 'root');
$dbPass = app_env('STOCKSMART_DB_PASS');

// ---------------------------------------------------------------------------
// 2) BUILD THE PDO CONNECTION
// ---------------------------------------------------------------------------
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // return assoc arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                    // use real prepared statements
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    error_log('StockSmart database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Check the server configuration.');
}

// ---------------------------------------------------------------------------
// 2b) OPTIONAL AUTOMATIC MIGRATIONS
//
// Disabled unless DB_AUTO_MIGRATE=true is set in the environment/.env. When
// off (the default, and what the README recommends) this call returns after a
// single getenv() and touches nothing. When on, it applies pending migrations
// exactly once after a deploy adds new migration files — guarded by a marker
// file and a MySQL named lock, never re-running DDL per request. See the
// commentary at the bottom of helpers/migrator.php.
// ---------------------------------------------------------------------------
if (app_env('DB_AUTO_MIGRATE', 'false') === 'true') {
    require_once __DIR__ . '/helpers/migrator.php';
    migrator_auto($pdo);
}

// ---------------------------------------------------------------------------
// 3) USAGE EXAMPLE (in any other PHP file):
//
//   require_once __DIR__ . '/db.php';
//
//   $stmt = $pdo->prepare('SELECT * FROM products WHERE status = :status');
//   $stmt->execute([':status' => 'active']);
//   $products = $stmt->fetchAll();
//
//   foreach ($products as $row) {
//       echo htmlspecialchars($row['product_name']);
//   }
// ---------------------------------------------------------------------------

/**
 * Small helper: run a prepared SELECT and return all rows.
 * Optional convenience wrapper — purely optional to use.
 */
function db_select(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Small helper: run a prepared INSERT/UPDATE/DELETE and return affected rows.
 */
function db_execute(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
