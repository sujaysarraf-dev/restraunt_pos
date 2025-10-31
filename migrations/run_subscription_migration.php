<?php
require_once __DIR__ . '/../db_connection.php';
header('Content-Type: application/json');
try {
  $sql = file_get_contents(__DIR__ . '/005_add_subscription_fields.sql');
  $pdo->exec($sql);
  echo json_encode(['success'=>true,'message'=>'Subscription fields added to users']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>


