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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if (file_exists(__DIR__ . '/config/db_connection.php')) {
    require_once __DIR__ . '/config/db_connection.php';
} elseif (file_exists(__DIR__ . '/db_connection.php')) {
    require_once __DIR__ . '/db_connection.php';
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found',
        'data' => []
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to continue',
        'data' => []
    ]);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    $conn = getConnection();
    
    // Get filter parameters
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';
    
    // Build the query
    $sql = "SELECT r.*, t.table_number, a.area_name 
            FROM reservations r 
            LEFT JOIN tables t ON r.table_id = t.id 
            LEFT JOIN areas a ON t.area_id = a.id 
            WHERE r.restaurant_id = ?";
    $params = [$restaurant_id];
    
    // Add date filter if provided
    if (!empty($dateFilter)) {
        $sql .= " AND r.reservation_date = ?";
        $params[] = $dateFilter;
    }
    
    $sql .= " ORDER BY r.reservation_date DESC, r.time_slot ASC";
    
    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $reservations,
        'count' => count($reservations)
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_reservations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.',
        'data' => []
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_reservations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
    exit();
}
?>

