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

// Check if user is logged in (admin or staff)
if (!isset($_SESSION['restaurant_id']) && (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id']))) {
    // Allow restaurant_id from query parameter for staff logins
    if (!isset($_GET['restaurant_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
}

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
    
    // Get KOT orders (not completed)
    $sql = "SELECT 
                k.id,
                k.kot_number,
                k.table_id,
                k.kot_status,
                k.order_type,
                k.customer_name,
                k.created_at,
                k.subtotal,
                k.tax,
                k.total,
                k.notes,
                t.table_number,
                a.area_name,
                COUNT(ki.id) as item_count
            FROM kot k
            LEFT JOIN tables t ON k.table_id = t.id
            LEFT JOIN areas a ON t.area_id = a.id
            LEFT JOIN kot_items ki ON k.id = ki.kot_id
            WHERE k.restaurant_id = ? 
            AND k.kot_status IN ('Pending', 'Preparing', 'Ready')
            GROUP BY k.id
            ORDER BY k.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$restaurant_id]);
    $result = $stmt->fetchAll();
    
    $kots = [];
    foreach ($result as $row) {
        // Get KOT items
        $items_sql = "SELECT 
                        ki.item_name,
                        ki.quantity,
                        ki.unit_price,
                        ki.total_price,
                        ki.notes
                      FROM kot_items ki
                      WHERE ki.kot_id = ?
                      ORDER BY ki.id";
        
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->execute([$row['id']]);
        $items = $items_stmt->fetchAll();
        
        $row['items'] = $items;
        $row['table_name'] = $row['table_number'] ? $row['table_number'] . ' - ' . $row['area_name'] : null;
        
        $kots[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'kots' => $kots
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_kot.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_kot.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching KOT orders: ' . $e->getMessage()
    ]);
    exit();
}
?>
