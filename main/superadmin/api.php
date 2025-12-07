<?php
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/auth.php';
require_superadmin();

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

try {
  switch ($action) {
    case 'getRestaurants':
      $q = trim($_GET['q'] ?? '');
      $page = max(1, (int)($_GET['page'] ?? 1));
      $limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
      $offset = ($page - 1) * $limit;

      $where = '';
      $params = [];
      if ($q !== '') {
        $where = "WHERE username LIKE :q OR restaurant_name LIKE :q OR restaurant_id LIKE :q";
        $params[':q'] = "%$q%";
      }
      $sql = "SELECT 
                id, username, restaurant_id, restaurant_name, is_active, created_at,
                subscription_status,
                trial_end_date,
                renewal_date,
                GREATEST(DATEDIFF(COALESCE(renewal_date, trial_end_date, DATE_ADD(DATE(created_at), INTERVAL 7 DAY)), CURRENT_DATE()), 0) AS days_left,
                CASE 
                  WHEN subscription_status = 'disabled' THEN 'Disabled'
                  WHEN CURRENT_DATE() < COALESCE(renewal_date, trial_end_date, DATE_ADD(DATE(created_at), INTERVAL 7 DAY)) THEN 'Active'
                  ELSE 'Expired'
                END AS trial_status
              FROM users $where 
              ORDER BY created_at DESC 
              LIMIT :limit OFFSET :offset";
      $stmt = $pdo->prepare($sql);
      foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();

      $countStmt = $pdo->prepare("SELECT COUNT(*) as c FROM users $where");
      foreach ($params as $k=>$v) $countStmt->bindValue($k, $v);
      $countStmt->execute();
      $total = (int)($countStmt->fetch()['c'] ?? 0);

      echo json_encode(['success' => true, 'restaurants' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit]);
      break;

    case 'createRestaurant':
      $data = json_decode(file_get_contents('php://input'), true) ?? [];
      $username = trim($data['username'] ?? '');
      $password = $data['password'] ?? '';
      $restaurant_name = trim($data['restaurant_name'] ?? '');
      if (!$username || !$password || !$restaurant_name) {
        throw new Exception('Missing required fields');
      }
      // Auto-generate unique restaurant_id: RES + 3 letters + 3 digits
      $generateId = function() use ($pdo) {
        $letters = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 3);
        $digits = str_pad(strval(random_int(0, 999)), 3, '0', STR_PAD_LEFT);
        $rid = 'RES' . $letters . $digits;
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE restaurant_id = :rid');
        $check->execute([':rid' => $rid]);
        return $check->fetchColumn() == 0 ? $rid : null;
      };
      $attempts = 0; $restaurant_id = null;
      while ($attempts < 5 && !$restaurant_id) { $restaurant_id = $generateId(); $attempts++; }
      if (!$restaurant_id) { throw new Exception('Failed to generate restaurant id'); }

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users (username, password, restaurant_id, restaurant_name, is_active) VALUES (:u, :p, :rid, :rname, 1)");
      $stmt->execute([':u' => $username, ':p' => $hash, ':rid' => $restaurant_id, ':rname' => $restaurant_name]);
      echo json_encode(['success' => true, 'message' => 'Restaurant created', 'restaurant_id' => $restaurant_id]);
      break;

    case 'toggleRestaurant':
      $data = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int)($data['id'] ?? 0);
      $active = (int)($data['is_active'] ?? 1);
      if ($id <= 0) throw new Exception('Invalid id');
      if ($active === 1) {
        // Re-enable: set renewal_date +7d and status trial
        $stmt = $pdo->prepare("UPDATE users SET is_active=1, subscription_status='trial', renewal_date = DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY), disabled_at=NULL WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        $info = $pdo->prepare('SELECT renewal_date FROM users WHERE id=:id');
        $info->execute([':id'=>$id]);
        $renew = $info->fetchColumn();
        echo json_encode(['success'=>true,'message'=>'Enabled; renewal set','renewal_date'=>$renew]);
      } else {
        // Disable
        $stmt = $pdo->prepare("UPDATE users SET is_active=0, subscription_status='disabled', disabled_at=NOW() WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        echo json_encode(['success'=>true,'message'=>'Disabled']);
      }
      break;

    case 'resetPassword':
      $data = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int)($data['id'] ?? 0);
      $password = $data['password'] ?? '';
      if ($id <= 0 || !$password) throw new Exception('Invalid payload');
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
      $stmt->execute([':p' => $hash, ':id' => $id]);
      echo json_encode(['success' => true, 'message' => 'Password reset']);
      break;

    case 'getPasswordResetData':
      $page = max(1, (int)($_GET['page'] ?? 1));
      $limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
      $offset = ($page - 1) * $limit;

      try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        if ($tableCheck->rowCount() == 0) {
          echo json_encode(['success' => false, 'message' => 'password_reset_tokens table does not exist. Please run the migration first.']);
          break;
        }

        $sql = "SELECT 
                  prt.id, prt.user_id, prt.token, prt.expires_at, prt.used, prt.created_at,
                  u.username, u.restaurant_name, u.email
                FROM password_reset_tokens prt
                LEFT JOIN users u ON prt.user_id = u.id
                ORDER BY prt.created_at DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $countStmt = $pdo->query("SELECT COUNT(*) as c FROM password_reset_tokens");
        $total = (int)($countStmt->fetch()['c'] ?? 0);

        echo json_encode(['success' => true, 'tokens' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit]);
      } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
      }
      break;

    case 'getStats':
      $stats = [];
      // totals
      $stats['restaurants'] = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());
      $stats['active'] = (int)($pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn());
      // revenue and orders today (sum across restaurants)
      $today = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
      $revStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(created_at)=?");
      $revStmt->execute([$today]);
      $stats['todayRevenue'] = (float)$revStmt->fetchColumn();
      $ordStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=?");
      $ordStmt->execute([$today]);
      $stats['todayOrders'] = (int)$ordStmt->fetchColumn();
      echo json_encode(['success'=>true,'stats'=>$stats]);
      break;

    case 'getPayments':
      $search = trim($_GET['search'] ?? '');
      $status = trim($_GET['status'] ?? '');
      $limit = min(200, max(10, (int)($_GET['limit'] ?? 100)));
      $offset = max(0, (int)($_GET['offset'] ?? 0));

      $sql = "SELECT 
                p.id,
                p.transaction_id,
                p.restaurant_id,
                p.amount,
                p.payment_method,
                p.payment_status,
                p.subscription_type,
                p.created_at,
                u.restaurant_name
              FROM payments p
              LEFT JOIN users u ON u.restaurant_id = p.restaurant_id
              WHERE 1=1";
      $params = [];
      if ($search !== '') {
        $sql .= " AND (p.transaction_id LIKE :search OR p.restaurant_id LIKE :search OR u.restaurant_name LIKE :search)";
        $params[':search'] = "%$search%";
      }
      if ($status !== '') {
        $sql .= " AND p.payment_status = :status";
        $params[':status'] = $status;
      }
      $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
      $stmt = $pdo->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      echo json_encode([
        'success' => true,
        'payments' => $stmt->fetchAll()
      ]);
      break;

    default:
      echo json_encode(['success' => false, 'message' => 'Invalid action']);
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


