<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

// Include authorization system
if (file_exists(__DIR__ . '/../config/authorization.php')) {
    require_once __DIR__ . '/../config/authorization.php';
}

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

// Require admin access - only admin or manager can view staff list
requireAuth();
requireRestaurantAccess();
if (getUserType() === 'staff') {
    requireAction('manage_staff');
}

$restaurant_id = getRestaurantId();

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Secure fallback connection
        if (file_exists(__DIR__ . '/../config/db_fallback.php')) {
            require_once __DIR__ . '/../config/db_fallback.php';
            $conn = getSecureFallbackConnection();
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database configuration not available']);
            exit();
        }
    }
    
    $stmt = $conn->prepare("SELECT id, restaurant_id, member_name, email, phone, role, is_active, created_at FROM staff WHERE restaurant_id = ? ORDER BY role, member_name ASC");
    $stmt->execute([$restaurant_id]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $staff]);
} catch (PDOException $e) {
    error_log("PDO Error in get_staff.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_staff.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>

