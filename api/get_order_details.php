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

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

// Include authorization system
if (file_exists(__DIR__ . '/../config/authorization.php')) {
    require_once __DIR__ . '/../config/authorization.php';
}

// Require authentication
requireAuth();
requireRestaurantAccess();

$table_id = $_GET['table_id'] ?? null;

if (!$table_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Table ID required']);
    exit();
}

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
    $restaurant_id = $_GET['restaurant_id'] ?? $_SESSION['restaurant_id'] ?? null;
    
    if (!$restaurant_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Restaurant ID required']);
        exit();
    }
    
    // Get order details
    $sql = "SELECT 
                o.*,
                t.table_number,
                a.area_name,
                CONCAT(a.area_name, ' - ', t.table_number) as table_name
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN areas a ON t.area_id = a.id
            WHERE o.table_id = ? AND o.restaurant_id = ?
            ORDER BY o.created_at DESC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$table_id, $restaurant_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'No active order found for this table'
        ]);
        exit();
    }
    
    // Get order items
    $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->execute([$order['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order['items'] = $items;
    
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_order_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_order_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit();
}
?>

