<?php
// Quick diagnostic — delete this file after testing
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Test 1: Can we query the users table?
try {
    $stmt = $pdo->prepare("SELECT user_id, full_name, username, email, password_hash, status FROM users WHERE username = 'annie.admin' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['step' => 'query', 'result' => 'NO USER FOUND - annie.admin does not exist in DB']);
        exit;
    }
    
    // Test 2: Does password_verify work?
    $hash = $user['password_hash'];
    $testPwd = 'Admin@123';
    $verified = password_verify($testPwd, $hash);
    
    echo json_encode([
        'step'       => 'all_ok',
        'user_found' => true,
        'username'   => $user['username'],
        'status'     => $user['status'],
        'hash_len'   => strlen($hash),
        'hash_prefix'=> substr($hash, 0, 7),
        'pwd_verify' => $verified,
    ]);
} catch (Throwable $e) {
    echo json_encode(['step' => 'error', 'message' => $e->getMessage()]);
}
