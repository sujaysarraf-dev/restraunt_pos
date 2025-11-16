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

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
    $restaurant_id = $_SESSION['restaurant_id'];
    
    $period = $_GET['period'] ?? 'today';
    
    // Calculate date range based on period
    $dateCondition = '';
    switch ($period) {
        case 'today':
            $dateCondition = "DATE(o.created_at) = CURDATE()";
            break;
        case 'week':
            $dateCondition = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $dateCondition = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $dateCondition = "o.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            break;
        default:
            $dateCondition = "DATE(o.created_at) = CURDATE()";
    }
    
    // Get total sales
    $salesStmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) as total_sales FROM orders WHERE restaurant_id = ? AND " . str_replace('o.', '', $dateCondition));
    $salesStmt->execute([$restaurant_id]);
    $sales = $salesStmt->fetch(PDO::FETCH_ASSOC);
    $totalSales = $sales['total_sales'] ?? 0;
    
    // Get total orders
    $ordersStmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE restaurant_id = ? AND " . str_replace('o.', '', $dateCondition));
    $ordersStmt->execute([$restaurant_id]);
    $orders = $ordersStmt->fetch(PDO::FETCH_ASSOC);
    $totalOrders = $orders['total_orders'] ?? 0;
    
    // Get total items sold
    $itemsStmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.quantity), 0) as total_items 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.restaurant_id = ? AND " . $dateCondition . "
    ");
    $itemsStmt->execute([$restaurant_id]);
    $items = $itemsStmt->fetch(PDO::FETCH_ASSOC);
    $totalItems = $items['total_items'] ?? 0;
    
    // Get total customers
    $customersStmt = $conn->prepare("
        SELECT COUNT(DISTINCT customer_name) as total_customers 
        FROM orders 
        WHERE restaurant_id = ? AND " . str_replace('o.', '', $dateCondition) . "
    ");
    $customersStmt->execute([$restaurant_id]);
    $customers = $customersStmt->fetch(PDO::FETCH_ASSOC);
    $totalCustomers = $customers['total_customers'] ?? 0;
    
    // Get sales details
    $detailsStmt = $conn->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.payment_method, o.total, o.created_at,
               COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.restaurant_id = ? AND " . $dateCondition . "
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 100
    ");
    $detailsStmt->execute([$restaurant_id]);
    $salesDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top items
    $topItemsStmt = $conn->prepare("
        SELECT oi.item_name, SUM(oi.quantity) as total_quantity, SUM(oi.total_price) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.restaurant_id = ? AND " . $dateCondition . "
        GROUP BY oi.item_name
        ORDER BY total_quantity DESC
        LIMIT 10
    ");
    $topItemsStmt->execute([$restaurant_id]);
    $topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment methods breakdown
    $paymentMethodsStmt = $conn->prepare("
        SELECT payment_method, COUNT(*) as count, SUM(total) as amount
        FROM orders
        WHERE restaurant_id = ? AND " . str_replace('o.', '', $dateCondition) . "
        GROUP BY payment_method
    ");
    $paymentMethodsStmt->execute([$restaurant_id]);
    $paymentMethods = $paymentMethodsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_sales' => floatval($totalSales),
            'total_orders' => intval($totalOrders),
            'total_items' => intval($totalItems),
            'total_customers' => intval($totalCustomers)
        ],
        'sales_details' => $salesDetails,
        'top_items' => $topItems,
        'payment_methods' => $paymentMethods
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_sales_report.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_sales_report.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}
?>

