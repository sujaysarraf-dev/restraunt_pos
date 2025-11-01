<?php
require_once __DIR__ . '/../db_connection.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get';
// When opened from admin, restaurant_id comes from session; when opened directly, query param can be used
session_start();
$restaurant_id = $_GET['restaurant_id'] ?? ($_SESSION['restaurant_id'] ?? 'RES001');

try {
  if ($action === 'get') {
    $stmt = $pdo->prepare('SELECT primary_red, dark_red, primary_yellow, banner_image FROM website_settings WHERE restaurant_id = :rid');
    $stmt->execute([':rid' => $restaurant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { 
      $row = ['primary_red'=>'#F70000','dark_red'=>'#DA020E','primary_yellow'=>'#FFD100','banner_image'=>null]; 
    } else {
      // Ensure banner_image is null if empty string
      if (empty($row['banner_image']) || trim($row['banner_image']) === '') {
        $row['banner_image'] = null;
      }
    }
    
    // Get all banners from website_banners table
    $bannersStmt = $pdo->prepare('SELECT id, banner_path, display_order FROM website_banners WHERE restaurant_id = :rid ORDER BY display_order ASC, id ASC');
    $bannersStmt->execute([':rid' => $restaurant_id]);
    $banners = $bannersStmt->fetchAll(PDO::FETCH_ASSOC);
    $row['banners'] = $banners ?: [];
    
    echo json_encode(['success'=>true,'settings'=>$row]);
    exit;
  }
  
  if ($action === 'get_banners' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $bannersStmt = $pdo->prepare('SELECT id, banner_path, display_order FROM website_banners WHERE restaurant_id = :rid ORDER BY display_order ASC, id ASC');
    $bannersStmt->execute([':rid' => $restaurant_id]);
    $banners = $bannersStmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'banners' => $banners ?: []]);
    exit;
  }

  if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $pr = $data['primary_red'] ?? '#F70000';
    $dr = $data['dark_red'] ?? '#DA020E';
    $py = $data['primary_yellow'] ?? '#FFD100';
    $bi = $data['banner_image'] ?? null;
    $stmt = $pdo->prepare('INSERT INTO website_settings (restaurant_id, primary_red, dark_red, primary_yellow, banner_image) VALUES (:rid,:pr,:dr,:py,:bi)
      ON DUPLICATE KEY UPDATE primary_red=VALUES(primary_red), dark_red=VALUES(dark_red), primary_yellow=VALUES(primary_yellow), banner_image=VALUES(banner_image)');
    $stmt->execute([':rid'=>$restaurant_id, ':pr'=>$pr, ':dr'=>$dr, ':py'=>$py, ':bi'=>$bi]);
    echo json_encode(['success'=>true]);
    exit;
  }

  if ($action === 'upload_banner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if table exists, if not return error asking to run migration
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'website_banners'")->fetch();
    if (!$tableCheck) {
      throw new Exception('Website banners table not found. Please run the migration first.');
    }
    
    $uploadedBanners = [];
    $uploadDir = __DIR__ . '/../uploads/banners/';
    if (!file_exists($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    // Handle multiple file uploads
    $files = [];
    
    // Check for banners[] (multiple files) - when using FormData with banners[], PHP creates array structure
    if (isset($_FILES['banners'])) {
      // Multiple files - check if it's an array
      if (isset($_FILES['banners']['name']) && is_array($_FILES['banners']['name'])) {
        $fileCount = count($_FILES['banners']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
          if (isset($_FILES['banners']['error'][$i]) && $_FILES['banners']['error'][$i] === UPLOAD_ERR_OK) {
            $files[] = [
              'name' => $_FILES['banners']['name'][$i],
              'type' => $_FILES['banners']['type'][$i] ?? 'image/jpeg',
              'tmp_name' => $_FILES['banners']['tmp_name'][$i],
              'size' => $_FILES['banners']['size'][$i] ?? 0,
              'error' => $_FILES['banners']['error'][$i]
            ];
          }
        }
      } elseif (isset($_FILES['banners']['error']) && $_FILES['banners']['error'] === UPLOAD_ERR_OK) {
        // Single file uploaded as banners[]
        $files[] = [
          'name' => $_FILES['banners']['name'],
          'type' => $_FILES['banners']['type'] ?? 'image/jpeg',
          'tmp_name' => $_FILES['banners']['tmp_name'],
          'size' => $_FILES['banners']['size'] ?? 0,
          'error' => $_FILES['banners']['error']
        ];
      }
    }
    
    // Check for banner (single file - backward compatibility)
    if (empty($files) && isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
      $files[] = $_FILES['banner'];
    }
    
    if (empty($files)) {
      throw new Exception('No files uploaded or upload error');
    }
    
    // Get current max display_order
    $orderStmt = $pdo->prepare('SELECT MAX(display_order) as max_order FROM website_banners WHERE restaurant_id = :rid');
    $orderStmt->execute([':rid' => $restaurant_id]);
    $orderResult = $orderStmt->fetch();
    $nextOrder = ($orderResult['max_order'] ?? 0) + 1;
    
    foreach ($files as $file) {
      if (!in_array($file['type'], $allowedTypes)) {
        continue; // Skip invalid files
      }
      
      if ($file['size'] > 5 * 1024 * 1024) {
        continue; // Skip files larger than 5MB
      }
      
      $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = 'banner_' . $restaurant_id . '_' . time() . '_' . uniqid() . '.' . $extension;
      $filepath = $uploadDir . $filename;
      
      if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $bannerPath = 'uploads/banners/' . $filename;
        $stmt = $pdo->prepare('INSERT INTO website_banners (restaurant_id, banner_path, display_order) VALUES (:rid, :path, :order)');
        $stmt->execute([
          ':rid' => $restaurant_id,
          ':path' => $bannerPath,
          ':order' => $nextOrder++
        ]);
        $uploadedBanners[] = [
          'id' => $pdo->lastInsertId(),
          'banner_path' => $bannerPath
        ];
      }
    }
    
    if (empty($uploadedBanners)) {
      throw new Exception('No valid files were uploaded. Please check file types and sizes.');
    }
    
    echo json_encode(['success' => true, 'banners' => $uploadedBanners]);
    exit;
  }

  if ($action === 'delete_banner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bannerId = $_POST['banner_id'] ?? $_GET['banner_id'] ?? null;
    
    if ($bannerId) {
      // Delete from website_banners table
      $stmt = $pdo->prepare('SELECT banner_path FROM website_banners WHERE id = :id AND restaurant_id = :rid');
      $stmt->execute([':id' => $bannerId, ':rid' => $restaurant_id]);
      $banner = $stmt->fetch();
      
      if ($banner && file_exists(__DIR__ . '/../' . $banner['banner_path'])) {
        @unlink(__DIR__ . '/../' . $banner['banner_path']);
      }
      
      $deleteStmt = $pdo->prepare('DELETE FROM website_banners WHERE id = :id AND restaurant_id = :rid');
      $deleteStmt->execute([':id' => $bannerId, ':rid' => $restaurant_id]);
      
      echo json_encode(['success' => true]);
      exit;
    } else {
      // Backward compatibility: delete from website_settings
      $oldStmt = $pdo->prepare('SELECT banner_image FROM website_settings WHERE restaurant_id = :rid');
      $oldStmt->execute([':rid' => $restaurant_id]);
      $old = $oldStmt->fetch();
      if ($old && $old['banner_image'] && file_exists(__DIR__ . '/../' . $old['banner_image'])) {
        @unlink(__DIR__ . '/../' . $old['banner_image']);
      }
      
      $stmt = $pdo->prepare('UPDATE website_settings SET banner_image = NULL WHERE restaurant_id = :rid');
      $stmt->execute([':rid' => $restaurant_id]);
      
      echo json_encode(['success' => true]);
      exit;
    }
  }
  
  if ($action === 'reorder_banners' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bannerIds = $data['banner_ids'] ?? [];
    
    if (empty($bannerIds) || !is_array($bannerIds)) {
      throw new Exception('Invalid banner IDs');
    }
    
    $pdo->beginTransaction();
    try {
      foreach ($bannerIds as $order => $bannerId) {
        $stmt = $pdo->prepare('UPDATE website_banners SET display_order = :order WHERE id = :id AND restaurant_id = :rid');
        $stmt->execute([':order' => $order, ':id' => $bannerId, ':rid' => $restaurant_id]);
      }
      $pdo->commit();
      echo json_encode(['success' => true]);
      exit;
    } catch (Exception $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  echo json_encode(['success'=>false,'message'=>'Invalid action']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>


