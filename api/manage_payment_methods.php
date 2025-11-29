<?php
// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

header('Content-Type: application/json');

// Require admin permission to manage payment methods
requireAdmin();

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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'add':
            $method_name = trim($_POST['method_name'] ?? '');
            $emoji = trim($_POST['emoji'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);

            if (empty($method_name)) {
                echo json_encode(['success' => false, 'message' => 'Method name is required']);
                exit();
            }

            $stmt = $conn->prepare("
                INSERT INTO payment_methods (restaurant_id, method_name, emoji, display_order) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$restaurant_id, $method_name, $emoji ?: null, $display_order]);

            echo json_encode([
                'success' => true,
                'message' => 'Payment method added successfully',
                'id' => $conn->lastInsertId()
            ]);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $method_name = trim($_POST['method_name'] ?? '');
            $emoji = trim($_POST['emoji'] ?? '');
            $is_active = intval($_POST['is_active'] ?? 1);
            $display_order = intval($_POST['display_order'] ?? 0);

            if (empty($method_name) || $id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }

            $stmt = $conn->prepare("
                UPDATE payment_methods 
                SET method_name = ?, emoji = ?, is_active = ?, display_order = ? 
                WHERE id = ? AND restaurant_id = ?
            ");
            $stmt->execute([$method_name, $emoji ?: null, $is_active, $display_order, $id, $restaurant_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Payment method updated successfully'
            ]);
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit();
            }

            // Check if method is being used in orders
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count FROM orders 
                WHERE restaurant_id = ? AND payment_method = (
                    SELECT method_name FROM payment_methods WHERE id = ? AND restaurant_id = ?
                )
            ");
            $checkStmt->execute([$restaurant_id, $id, $restaurant_id]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                // Deactivate instead of delete
                $stmt = $conn->prepare("
                    UPDATE payment_methods 
                    SET is_active = 0 
                    WHERE id = ? AND restaurant_id = ?
                ");
                $stmt->execute([$id, $restaurant_id]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment method deactivated (it is being used in orders)'
                ]);
            } else {
                // Safe to delete
                $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ? AND restaurant_id = ?");
                $stmt->execute([$id, $restaurant_id]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment method deleted successfully'
                ]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    error_log("PDO Error in manage_payment_methods.php: " . $e->getMessage());
    
    // Check for duplicate entry error
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'This payment method already exists'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred. Please try again later.'
        ]);
    }
}
?>



