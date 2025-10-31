<?php
require_once __DIR__ . '/../db_connection.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get';
// When opened from admin, restaurant_id comes from session; when opened directly, query param can be used
session_start();
$restaurant_id = $_GET['restaurant_id'] ?? ($_SESSION['restaurant_id'] ?? 'RES001');

try {
  if ($action === 'get') {
    $stmt = $pdo->prepare('SELECT primary_red, dark_red, primary_yellow FROM website_settings WHERE restaurant_id = :rid');
    $stmt->execute([':rid' => $restaurant_id]);
    $row = $stmt->fetch();
    if (!$row) { $row = ['primary_red'=>'#F70000','dark_red'=>'#DA020E','primary_yellow'=>'#FFD100']; }
    echo json_encode(['success'=>true,'settings'=>$row]);
    exit;
  }

  if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $pr = $data['primary_red'] ?? '#F70000';
    $dr = $data['dark_red'] ?? '#DA020E';
    $py = $data['primary_yellow'] ?? '#FFD100';
    $stmt = $pdo->prepare('INSERT INTO website_settings (restaurant_id, primary_red, dark_red, primary_yellow) VALUES (:rid,:pr,:dr,:py)
      ON DUPLICATE KEY UPDATE primary_red=VALUES(primary_red), dark_red=VALUES(dark_red), primary_yellow=VALUES(primary_yellow)');
    $stmt->execute([':rid'=>$restaurant_id, ':pr'=>$pr, ':dr'=>$dr, ':py'=>$py]);
    echo json_encode(['success'=>true]);
    exit;
  }

  echo json_encode(['success'=>false,'message'=>'Invalid action']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>


