<?php
/** Supplier CRUD and purchase-performance data. */
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    api_require_permission('suppliers.view');
    $rows = $pdo->query("SELECT s.*, COALESCE(pc.product_count,0) product_count, COALESCE(po.purchase_count,0) purchase_count, COALESCE(po.purchase_total,0) purchase_total FROM suppliers s LEFT JOIN (SELECT supplier_id, COUNT(*) product_count FROM products GROUP BY supplier_id) pc ON pc.supplier_id=s.supplier_id LEFT JOIN (SELECT supplier_id, COUNT(*) purchase_count, SUM(grand_total) purchase_total FROM purchase_orders GROUP BY supplier_id) po ON po.supplier_id=s.supplier_id ORDER BY s.supplier_name")->fetchAll();
    echo json_encode(['suppliers' => $rows]); exit;
}
$user = api_require_permission('suppliers.manage'); api_verify_csrf();
$body = api_json_body();
$name = trim((string)($body['supplier_name'] ?? ''));
if ($name === '' || mb_strlen($name) > 120) { http_response_code(422); echo json_encode(['error'=>'A supplier name of up to 120 characters is required.']); exit; }
$data = [':name'=>$name, ':person'=>trim((string)($body['contact_person']??'')) ?: null, ':phone'=>trim((string)($body['phone']??'')) ?: null, ':email'=>trim((string)($body['email']??'')) ?: null, ':address'=>trim((string)($body['address']??'')) ?: null, ':status'=>in_array(($body['status']??'active'),['active','inactive'],true)?$body['status']:'active'];
if ($data[':email'] && !filter_var($data[':email'], FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['error'=>'Enter a valid supplier email address.']); exit; }
if ($method === 'POST') {
    $pdo->prepare('INSERT INTO suppliers (supplier_name,contact_person,phone,email,address,status) VALUES (:name,:person,:phone,:email,:address,:status)')->execute($data);
    $id=(int)$pdo->lastInsertId(); api_log_activity($pdo,$user['id'],'add','suppliers',$id,"Supplier {$name} created"); echo json_encode(['ok'=>true,'id'=>$id]); exit;
}
$id=(int)($_GET['id']??0); if ($id<=0) { http_response_code(422); echo json_encode(['error'=>'Supplier id is required.']); exit; }
if ($method === 'PUT') { $data[':id']=$id; $pdo->prepare('UPDATE suppliers SET supplier_name=:name,contact_person=:person,phone=:phone,email=:email,address=:address,status=:status WHERE supplier_id=:id')->execute($data); api_log_activity($pdo,$user['id'],'update','suppliers',$id,"Supplier {$name} updated"); echo json_encode(['ok'=>true]); exit; }
if ($method === 'DELETE') { $pdo->prepare("UPDATE suppliers SET status='inactive' WHERE supplier_id=:id")->execute([':id'=>$id]); api_log_activity($pdo,$user['id'],'delete','suppliers',$id,'Supplier archived'); echo json_encode(['ok'=>true]); exit; }
http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
