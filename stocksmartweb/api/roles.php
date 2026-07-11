<?php
/**
 * Role + permission matrix management (gated by the "roles.manage" permission).
 * GET  api/roles.php               → all roles, all permissions, and the current role→permission matrix
 * POST api/roles.php                → create a new role
 * PUT  api/roles.php?id=1           → replace a role's permission set (and/or its description)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    api_require_permission('roles.manage');
    $roles = $pdo->query('SELECT role_id, role_name, role_description FROM roles ORDER BY role_id')->fetchAll();
    $permissions = $pdo->query('SELECT permission_id, permission_key, permission_name, module_name FROM permissions ORDER BY module_name, permission_key')->fetchAll();
    $matrix = $pdo->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll();

    $rolePermissions = [];
    foreach ($matrix as $row) {
        $rolePermissions[(int) $row['role_id']][] = (int) $row['permission_id'];
    }

    echo json_encode(['roles' => $roles, 'permissions' => $permissions, 'role_permissions' => $rolePermissions]);
    exit;
}

$user = api_require_permission('roles.manage');
api_verify_csrf();
$body = api_json_body();

if ($method === 'POST') {
    $name = trim((string) ($body['role_name'] ?? ''));
    $description = trim((string) ($body['role_description'] ?? ''));
    if ($name === '' || mb_strlen($name) > 30) {
        http_response_code(422);
        echo json_encode(['error' => 'A role name of up to 30 characters is required.']);
        exit;
    }
    try {
        $pdo->prepare('INSERT INTO roles (role_name, role_description) VALUES (:name, :desc)')
            ->execute([':name' => $name, ':desc' => $description !== '' ? $description : null]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) { http_response_code(409); echo json_encode(['error' => 'A role with that name already exists.']); exit; }
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();
    api_log_activity($pdo, $user['id'], 'add', 'roles', $id, "Role {$name} created");
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(422); echo json_encode(['error' => 'Role id is required.']); exit; }

if ($method === 'PUT') {
    $description = $body['role_description'] ?? null;
    $permissionIds = array_values(array_unique(array_map('intval', (array) ($body['permission_ids'] ?? []))));

    $roleStmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = :id');
    $roleStmt->execute([':id' => $id]);
    $roleName = $roleStmt->fetchColumn();
    if ($roleName === false) { http_response_code(404); echo json_encode(['error' => 'Role not found.']); exit; }

    $pdo->beginTransaction();
    try {
        if ($description !== null) {
            $pdo->prepare('UPDATE roles SET role_description = :desc WHERE role_id = :id')
                ->execute([':desc' => trim((string) $description) ?: null, ':id' => $id]);
        }
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :id')->execute([':id' => $id]);
        if (!empty($permissionIds)) {
            $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
            foreach ($permissionIds as $permissionId) {
                $insert->execute([':role_id' => $id, ':permission_id' => $permissionId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Could not update permissions for this role.']);
        exit;
    }

    unset($_SESSION['permissions']); // in case the editing user's own role changed
    api_log_activity($pdo, $user['id'], 'update', 'roles', $id, "Permissions updated for role {$roleName}");
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
