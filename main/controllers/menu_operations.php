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

header('Content-Type: application/json; charset=UTF-8');
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
    echo json_encode(['success' => false, 'message' => 'Database connection file not found'], JSON_UNESCAPED_UNICODE);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    // Get connection using getConnection() for lazy connection support
    if (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback to $pdo if getConnection() doesn't exist (backward compatibility)
        $conn = $pdo ?? null;
        if (!$conn) {
            throw new Exception('Database connection not available');
        }
    }
    
    // Get the action and data from POST
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $menuId = isset($_POST['menuId']) ? (int)$_POST['menuId'] : 0;
    $menuName = isset($_POST['menuName']) ? trim($_POST['menuName']) : '';
    
    // Handle image upload - check for base64 first (cropped), then file
    $menuImageData = null;
    $menuImageMimeType = null;
    $menuImagePath = null;
    
    if (!empty($_POST['menuImageBase64'])) {
        // Handle base64 cropped image
        $imageInfo = handleBase64MenuImage($_POST['menuImageBase64']);
        if (is_array($imageInfo)) {
            $menuImageData = $imageInfo['data'];
            $menuImageMimeType = $imageInfo['mime_type'];
            $menuImagePath = 'db:' . uniqid(); // Reference ID for database storage
        }
    } elseif (isset($_FILES['menuImage']) && $_FILES['menuImage']['error'] === UPLOAD_ERR_OK) {
        // Handle file upload
        $imageInfo = handleMenuImageUpload($_FILES['menuImage'], $conn);
        if (is_array($imageInfo)) {
            $menuImageData = $imageInfo['data'];
            $menuImageMimeType = $imageInfo['mime_type'];
            $menuImagePath = 'db:' . uniqid(); // Reference ID for database storage
        }
    }
    
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
            
            // Ensure image columns exist
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM menu LIKE 'menu_image'");
                if ($checkCol->rowCount() == 0) {
                    $conn->exec("ALTER TABLE menu ADD COLUMN menu_image VARCHAR(255) NULL AFTER menu_name");
                    $conn->exec("ALTER TABLE menu ADD COLUMN menu_image_data LONGBLOB NULL AFTER menu_image");
                    $conn->exec("ALTER TABLE menu ADD COLUMN menu_image_mime_type VARCHAR(50) NULL AFTER menu_image_data");
                }
            } catch (PDOException $e) {
                // Columns might already exist, continue
            }
            
            // Insert new menu with image
            $insertStmt = $conn->prepare("INSERT INTO menu (restaurant_id, menu_name, menu_image, menu_image_data, menu_image_mime_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $result = $insertStmt->execute([$restaurant_id, $menuName, $menuImagePath, $menuImageData, $menuImageMimeType]);
            
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
                ], JSON_UNESCAPED_UNICODE);
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
            
            // Get existing menu data
            $existingStmt = $conn->prepare("SELECT menu_image, menu_image_data, menu_image_mime_type FROM menu WHERE id = ? AND restaurant_id = ?");
            $existingStmt->execute([$menuId, $restaurant_id]);
            $existingMenu = $existingStmt->fetch(PDO::FETCH_ASSOC);
            
            // If no new image uploaded, keep existing image
            if ($menuImageData === null && $existingMenu) {
                $menuImageData = $existingMenu['menu_image_data'];
                $menuImageMimeType = $existingMenu['menu_image_mime_type'];
                $menuImagePath = $existingMenu['menu_image'];
            }
            
            // Ensure image columns exist
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM menu LIKE 'menu_image'");
                if ($checkCol->rowCount() == 0) {
                    $conn->exec("ALTER TABLE menu ADD COLUMN menu_image VARCHAR(255) NULL AFTER menu_name");
                    $conn->exec("ALTER TABLE menu ADD COLUMN menu_image_data LONGBLOB NULL AFTER menu_image");
                    $conn->exec("ALTER TABLE menu ADD COLUMN menu_image_mime_type VARCHAR(50) NULL AFTER menu_image_data");
                }
            } catch (PDOException $e) {
                // Columns might already exist, continue
            }
            
            // Update menu with image
            $updateStmt = $conn->prepare("UPDATE menu SET menu_name = ?, menu_image = ?, menu_image_data = ?, menu_image_mime_type = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?");
            $result = $updateStmt->execute([$menuName, $menuImagePath, $menuImageData, $menuImageMimeType, $menuId, $restaurant_id]);
            
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
                ], JSON_UNESCAPED_UNICODE);
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
                ], JSON_UNESCAPED_UNICODE);
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
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Exception $e) {
    error_log("Error in menu_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function handleMenuImageUpload($file, $conn) {
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Verify MIME type from file content
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($actualMimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }
    
    // Read image data
    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false) {
        throw new Exception('Failed to read image file');
    }
    
    return [
        'data' => $imageData,
        'mime_type' => $actualMimeType,
        'size' => $file['size']
    ];
}

function handleBase64MenuImage($base64String) {
    // Remove data URL prefix if present and extract MIME type
    $mimeType = 'image/jpeg'; // Default
    if (strpos($base64String, 'data:image/') === 0) {
        $mimePart = substr($base64String, 5, strpos($base64String, ';') - 5);
        $mimeType = str_replace('data:', '', $mimePart);
        $base64String = substr($base64String, strpos($base64String, ',') + 1);
    }
    
    // Decode base64
    $imageData = base64_decode($base64String);
    
    if ($imageData === false) {
        throw new Exception('Invalid base64 image data');
    }
    
    // Validate image size (5MB max)
    if (strlen($imageData) > 5 * 1024 * 1024) {
        throw new Exception('Image size too large. Maximum size is 5MB.');
    }
    
    // Get image info
    $imageInfo = getimagesizefromstring($imageData);
    if ($imageInfo === false) {
        throw new Exception('Invalid image format');
    }
    
    // Validate image type
    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($imageInfo[2], $allowedTypes)) {
        throw new Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }
    
    // Determine MIME type from image info
    $mimeTypes = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_GIF => 'image/gif',
        IMAGETYPE_WEBP => 'image/webp'
    ];
    $mimeType = $mimeTypes[$imageInfo[2]] ?? $mimeType;
    
    // Return array with image data and MIME type for database storage
    return [
        'data' => $imageData,
        'mime_type' => $mimeType,
        'size' => strlen($imageData)
    ];
}
?>

