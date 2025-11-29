<?php
// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

header('Content-Type: application/json');

// Require permission to manage payments
requirePermission(PERMISSION_MANAGE_PAYMENTS);

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
        $host = 'localhost';
        $dbname = 'restro2';
        $username = 'root';
        $password = '';
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    $restaurant_id = $_SESSION['restaurant_id'];

    // Check if payment_methods table exists, if not return empty array
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'payment_methods'");
        if ($checkTable->rowCount() == 0) {
            // Table doesn't exist, return empty array
            echo json_encode([
                'success' => true,
                'data' => []
            ]);
            exit();
        }
    } catch (PDOException $e) {
        // If we can't check table, try to query anyway
    }

    $stmt = $conn->prepare("
        SELECT id, method_name, emoji, is_active, display_order 
        FROM payment_methods 
        WHERE restaurant_id = ? 
        ORDER BY display_order ASC, method_name ASC
    ");
    $stmt->execute([$restaurant_id]);
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $methods
    ]);

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    error_log("PDO Error in get_payment_methods.php: " . $errorMsg);
    
    // If table doesn't exist, return empty array instead of error
    if (stripos($errorMsg, "doesn't exist") !== false || 
        stripos($errorMsg, "Unknown table") !== false ||
        stripos($errorMsg, "Table '") !== false ||
        stripos($errorMsg, "Base table or view not found") !== false ||
        $e->getCode() == '42S02') {
        // Table doesn't exist - return empty array
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
    } else {
        // Other database error - return error but don't break the page
        echo json_encode([
            'success' => true,
            'data' => [],
            'warning' => 'Payment methods table not available'
        ]);
    }
} catch (Exception $e) {
    error_log("Error in get_payment_methods.php: " . $e->getMessage());
    // Return empty array instead of error to prevent page break
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
}
?>



