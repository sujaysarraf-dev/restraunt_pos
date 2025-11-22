<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found',
        'data' => []
    ]);
    exit();
}

// Include authorization system
if (file_exists(__DIR__ . '/../config/authorization.php')) {
    require_once __DIR__ . '/../config/authorization.php';
}

// Require authentication
requireAuth();

// Get restaurant_id from query parameter or session
$restaurant_id = $_GET['restaurant_id'] ?? getRestaurantId();

if (!$restaurant_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Restaurant ID is required',
        'data' => []
    ]);
    exit();
}

// Verify restaurant access
requireRestaurantAccess($restaurant_id);

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
    
    // Get all tables for this restaurant with area names
    $stmt = $conn->prepare("SELECT t.*, a.area_name FROM tables t JOIN areas a ON t.area_id = a.id WHERE t.restaurant_id = ? ORDER BY t.sort_order ASC, t.created_at DESC");
    $stmt->execute([$restaurant_id]);
    $tables = $stmt->fetchAll();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $tables,
        'tables' => $tables,
        'count' => count($tables)
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_tables.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.',
        'data' => []
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_tables.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
    exit();
}
?>
