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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// 1) CONNECTION SETTINGS — edit these to match your XAMPP / phpMyAdmin setup
// ---------------------------------------------------------------------------
const DB_HOST = '127.0.0.1';   // XAMPP default; use 'localhost' if you prefer
const DB_PORT = '3306';        // XAMPP default MySQL port
const DB_NAME = 'stocksmart';
const DB_USER = 'root';        // XAMPP default user
const DB_PASS = '';            // XAMPP default has no password

// ---------------------------------------------------------------------------
// 2) BUILD THE PDO CONNECTION
// ---------------------------------------------------------------------------
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // return assoc arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                    // use real prepared statements
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log this instead of echoing it to the browser.
    http_response_code(500);
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
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
