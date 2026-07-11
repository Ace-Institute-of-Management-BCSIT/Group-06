<?php
/**
 * Audit/activity log browser (Admin-oriented, gated by "logs.view").
 * GET api/logs.php?type=&user_id=&from=&to=&q=  -> filtered activity_logs
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

api_require_permission('logs.view');

$type = (string) ($_GET['type'] ?? '');
$userId = (int) ($_GET['user_id'] ?? 0);
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($type !== '') { $where[] = 'a.activity_type = :type'; $params[':type'] = $type; }
if ($userId > 0) { $where[] = 'a.user_id = :uid'; $params[':uid'] = $userId; }
if ($from !== '') { $where[] = 'a.created_at >= :from'; $params[':from'] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'a.created_at <= :to'; $params[':to'] = $to . ' 23:59:59'; }
if ($q !== '') { $where[] = 'a.description LIKE :q'; $params[':q'] = '%' . $q . '%'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT a.log_id, a.activity_type, a.entity_type, a.entity_id, a.description, a.ip_address, a.created_at,
           u.full_name AS user_name, u.username
    FROM activity_logs a
    LEFT JOIN users u ON u.user_id = a.user_id
    {$whereSql}
    ORDER BY a.created_at DESC
    LIMIT 300
");
$stmt->execute($params);

$usersStmt = $pdo->query('SELECT user_id, full_name FROM users ORDER BY full_name');

echo json_encode(['logs' => $stmt->fetchAll(), 'users' => $usersStmt->fetchAll()]);
