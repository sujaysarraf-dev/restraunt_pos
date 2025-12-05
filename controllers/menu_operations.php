<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Require permission to manage menu
requirePermission(PERMISSION_MANAGE_MENU);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    $conn = $pdo;
    
    // Get the action and data from POST
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $menuId = isset($_POST['menuId']) ? (int)$_POST['menuId'] : 0;
    $menuName = isset($_POST['menuName']) ? trim($_POST['menuName']) : '';
    
    // Validate action
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    // Validate menu name for add and update actions
    if (in_array($action, ['add', 'update']) && empty($menuName)) {
        throw new Exception('Menu name is required');
    }
    
    if (in_array($action, ['add', 'update']) && strlen($menuName) > 100) {
        throw new Exception('Menu name must be less than 100 characters');
    }
    
    // Validate menu ID for update and delete actions
    if (in_array($action, ['update', 'delete']) && $menuId <= 0) {
        throw new Exception('Invalid menu ID');
    }
    
    switch ($action) {
        case 'add':
            // Check if menu name already exists for this restaurant
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$menuName, $restaurant_id]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists > 0) {
                throw new Exception('Menu name already exists');
            }
            
            // Insert new menu
            $insertStmt = $conn->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $result = $insertStmt->execute([$restaurant_id, $menuName]);
            
            if ($result) {
                $newMenuId = $conn->lastInsertId();
                
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu added successfully',
                    'data' => [
                        'id' => $newMenuId,
                        'name' => $menuName,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                throw new Exception('Failed to add menu');
            }
            break;
            
        case 'update':
            // Check if menu exists and belongs to this restaurant
            $checkStmt = $conn->prepare("SELECT id FROM menu WHERE id = ? AND restaurant_id = ?");
            $checkStmt->execute([$menuId, $restaurant_id]);
            if (!$checkStmt->fetch()) {
                throw new Exception('Menu not found');
            }
            
            // Check if menu name already exists for this restaurant (excluding current menu)
            $checkNameStmt = $conn->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND id != ? AND restaurant_id = ?");
            $checkNameStmt->execute([$menuName, $menuId, $restaurant_id]);
            $exists = $checkNameStmt->fetchColumn();
            
            if ($exists > 0) {
                throw new Exception('Menu name already exists');
            }
            
            // Update menu
            $updateStmt = $conn->prepare("UPDATE menu SET menu_name = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?");
            $result = $updateStmt->execute([$menuName, $menuId, $restaurant_id]);
            
            if ($result) {
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu updated successfully',
                    'data' => [
                        'id' => $menuId,
                        'name' => $menuName,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                throw new Exception('Failed to update menu');
            }
            break;
            
        case 'delete':
            // Check if menu exists and belongs to this restaurant
            $checkStmt = $conn->prepare("SELECT menu_name FROM menu WHERE id = ? AND restaurant_id = ?");
            $checkStmt->execute([$menuId, $restaurant_id]);
            $menu = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$menu) {
                throw new Exception('Menu not found');
            }
            
            // Delete menu
            $deleteStmt = $conn->prepare("DELETE FROM menu WHERE id = ? AND restaurant_id = ?");
            $result = $deleteStmt->execute([$menuId, $restaurant_id]);
            
            if ($result) {
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu deleted successfully',
                    'data' => [
                        'id' => $menuId,
                        'name' => $menu['menu_name']
                    ]
                ]);
            } else {
                throw new Exception('Failed to delete menu');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("PDO Error in menu_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in menu_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}
?>

