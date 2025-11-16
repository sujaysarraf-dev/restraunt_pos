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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    
    $sql = "SELECT 
                p.*,
                o.order_number,
                o.table_id,
                t.table_number
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            LEFT JOIN tables t ON o.table_id = t.id
            WHERE p.restaurant_id = ?";
    
    $params = [$restaurant_id];
    
    // Add filters
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $sql .= " AND (o.order_number LIKE ? OR p.amount LIKE ? OR p.transaction_id LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (isset($_GET['method']) && !empty($_GET['method'])) {
        $sql .= " AND p.payment_method = ?";
        $params[] = $_GET['method'];
    }
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $sql .= " AND p.payment_status = ?";
        $params[] = $_GET['status'];
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'count' => count($payments)
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_payments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.',
        'payments' => []
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_payments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching payments: ' . $e->getMessage(),
        'payments' => []
    ]);
    exit();
}
?>

