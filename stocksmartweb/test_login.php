<?php
/** Administrative database health check; never exposes user credentials. */
declare(strict_types=1);

require_once __DIR__ . '/api/_bootstrap.php';

api_require_role(['Admin']);

try {
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo json_encode(['ok' => true, 'database' => 'connected', 'userCount' => $users]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database health check failed.']);
}
