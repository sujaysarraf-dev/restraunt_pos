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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Require permission to manage menu
requirePermission(PERMISSION_MANAGE_MENU);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found',
        'data' => []
    ]);
    exit();
}

// Get connection
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback connection
        $host = 'localhost';
        $dbname = 'restro2';
        $username = 'root';
        $password = '';
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'data' => []
    ]);
    exit();
}

// Check if user is logged in (admin or staff)
if (!isset($_SESSION['restaurant_id']) && (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id']))) {
    // Allow restaurant_id from query parameter for staff logins
    if (!isset($_GET['restaurant_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Please login to continue',
            'data' => []
        ]);
        exit();
    }
}

$restaurant_id = $_GET['restaurant_id'] ?? $_SESSION['restaurant_id'] ?? null;

if (!$restaurant_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Restaurant ID is required',
        'data' => []
    ]);
    exit();
}

try {
    // Get all menus for this restaurant
    $stmt = $conn->prepare("SELECT id, menu_name, is_active, created_at, updated_at FROM menu WHERE restaurant_id = ? ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute([$restaurant_id]);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $menus,
        'count' => count($menus)
    ]);
    
} catch (PDOException $e) {
    // Database error
    error_log("PDO Error in get_menus.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.',
        'data' => []
    ]);
    exit();
    
} catch (Exception $e) {
    // General error
    error_log("Error in get_menus.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
    exit();
}
?>