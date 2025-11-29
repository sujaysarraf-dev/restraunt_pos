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

// Require permission to manage orders
requirePermission(PERMISSION_MANAGE_ORDERS);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

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
    $restaurant_id = $_GET['restaurant_id'] ?? $_SESSION['restaurant_id'] ?? null;
    
    if (!$restaurant_id) {
        throw new Exception('Restaurant ID is required');
    }
    
    // Get filter parameters
    $statusFilter = $_GET['status'] ?? '';
    $paymentFilter = $_GET['payment_status'] ?? '';
    $typeFilter = $_GET['order_type'] ?? '';
    $searchTerm = $_GET['search'] ?? '';
    
    // Build WHERE clause with filters
    $whereConditions = ['o.restaurant_id = ?'];
    $params = [$restaurant_id];
    
    if ($statusFilter) {
        $whereConditions[] = 'o.order_status = ?';
        $params[] = $statusFilter;
    }
    
    if ($paymentFilter) {
        $whereConditions[] = 'o.payment_status = ?';
        $params[] = $paymentFilter;
    }
    
    if ($typeFilter) {
        $whereConditions[] = 'o.order_type = ?';
        $params[] = $typeFilter;
    }
    
    if ($searchTerm) {
        $whereConditions[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR t.table_number LIKE ?)';
        $searchPattern = '%' . $searchTerm . '%';
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get all orders with filters
    $sql = "SELECT 
                o.id,
                o.order_number,
                o.table_id,
                o.order_status,
                o.payment_status,
                o.order_type,
                o.payment_method,
                o.customer_name,
                o.created_at,
                o.subtotal,
                o.tax,
                o.total,
                o.notes,
                t.table_number,
                a.area_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN areas a ON t.area_id = a.id
            WHERE " . $whereClause . "
            ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    
    $orders = [];
    foreach ($result as $row) {
        // Get order items
        $items_sql = "SELECT 
                        oi.item_name,
                        oi.quantity,
                        oi.unit_price,
                        oi.total_price,
                        oi.notes
                      FROM order_items oi
                      WHERE oi.order_id = ?
                      ORDER BY oi.id";
        
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->execute([$row['id']]);
        $items = $items_stmt->fetchAll();
        
        $row['items'] = $items;
        $row['table_name'] = $row['table_number'] ? $row['table_number'] . ' - ' . $row['area_name'] : null;
        
        $orders[] = $row;
    }
    
    // Return orders with filter info
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders),
        'filters_applied' => [
            'status' => $statusFilter,
            'payment_status' => $paymentFilter,
            'order_type' => $typeFilter
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_orders.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_orders.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching orders: ' . $e->getMessage()
    ]);
    exit();
}
?>
