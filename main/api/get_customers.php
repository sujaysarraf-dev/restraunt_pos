<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

header('Content-Type: application/json');

// Require permission to manage customers
requirePermission(PERMISSION_MANAGE_CUSTOMERS);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    $conn = $pdo;
    
    $stmt = $conn->prepare("SELECT * FROM customers WHERE restaurant_id = ? ORDER BY customer_name ASC");
    $stmt->execute([$restaurant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recalculate spending and visits from orders table for accuracy
    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(total),0) AS spent, COUNT(*) AS orders, MAX(created_at) AS last_order
                               FROM orders WHERE restaurant_id = ? AND customer_name = ?");
    $updStmt = $conn->prepare("UPDATE customers SET total_spent = ?, total_visits = ?, last_visit_date = DATE(?) WHERE id = ?");

    foreach ($customers as &$c) {
        $name = $c['customer_name'];
        $sumStmt->execute([$restaurant_id, $name]);
        $row = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['spent'=>0,'orders'=>0,'last_order'=>null];
        $calcSpent = (float)$row['spent'];
        $calcVisits = (int)$row['orders'];
        $lastOrder = $row['last_order'];

        // If values differ, update DB so future fetches are fast
        if ((float)$c['total_spent'] !== $calcSpent || (int)$c['total_visits'] !== $calcVisits || ($lastOrder && $c['last_visit_date'] != substr($lastOrder,0,10))) {
            $updStmt->execute([$calcSpent, $calcVisits, $lastOrder, $c['id']]);
            $c['total_spent'] = $calcSpent;
            $c['total_visits'] = $calcVisits;
            $c['last_visit_date'] = $lastOrder ? substr($lastOrder,0,10) : $c['last_visit_date'];
        }
    }

    echo json_encode(['success' => true, 'data' => $customers]);
} catch (PDOException $e) {
    error_log("PDO Error in get_customers.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_customers.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}

