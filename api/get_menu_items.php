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
        'data' => [],
        'categories' => []
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
            'data' => [],
            'categories' => []
        ]);
        exit();
    }
}

$restaurant_id = $_GET['restaurant_id'] ?? $_SESSION['restaurant_id'] ?? null;

if (!$restaurant_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Restaurant ID is required',
        'data' => [],
        'categories' => []
    ]);
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
    
    // Get filter parameters
    $menuFilter = isset($_GET['menu']) ? (int)$_GET['menu'] : 0;
    $categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
    $typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
    
    // Build the query
    $sql = "SELECT mi.*, m.menu_name 
            FROM menu_items mi 
            JOIN menu m ON mi.menu_id = m.id 
            WHERE mi.restaurant_id = ? AND mi.is_available = TRUE";
    $params = [$restaurant_id];
    
    // Add filters
    if ($menuFilter > 0) {
        $sql .= " AND mi.menu_id = ?";
        $params[] = $menuFilter;
    }
    
    if (!empty($categoryFilter)) {
        $sql .= " AND mi.item_category = ?";
        $params[] = $categoryFilter;
    }
    
    if (!empty($typeFilter)) {
        $sql .= " AND mi.item_type = ?";
        $params[] = $typeFilter;
    }
    
    $sql .= " ORDER BY mi.sort_order ASC, mi.created_at DESC";
    
    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $menuItems = $stmt->fetchAll();
    
    // Get unique categories for this restaurant
    $categoryStmt = $conn->prepare("SELECT DISTINCT item_category FROM menu_items WHERE restaurant_id = ? AND item_category IS NOT NULL AND item_category != '' ORDER BY item_category");
    $categoryStmt->execute([$restaurant_id]);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $menuItems,
        'categories' => $categories,
        'count' => count($menuItems)
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_menu_items.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.',
        'data' => [],
        'categories' => []
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_menu_items.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => [],
        'categories' => []
    ]);
    exit();
}
?>