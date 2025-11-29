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
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found'
    ]);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Check if request method is POST
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
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
    
    // Get the action and data from POST
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $menuItemId = isset($_POST['menuItemId']) ? (int)$_POST['menuItemId'] : 0;
    $menuId = isset($_POST['chooseMenu']) ? (int)$_POST['chooseMenu'] : 0;
    $itemNameEn = isset($_POST['itemNameEn']) ? trim($_POST['itemNameEn']) : '';
    $itemDescriptionEn = isset($_POST['itemDescriptionEn']) ? trim($_POST['itemDescriptionEn']) : '';
    $itemCategory = isset($_POST['itemCategory']) ? trim($_POST['itemCategory']) : '';
    $itemType = isset($_POST['itemType']) ? trim($_POST['itemType']) : 'Veg';
    $preparationTime = isset($_POST['preparationTime']) ? (int)$_POST['preparationTime'] : 0;
    $isAvailable = isset($_POST['isAvailable']) ? (int)$_POST['isAvailable'] : 1;
    $basePrice = isset($_POST['basePrice']) ? (float)$_POST['basePrice'] : 0.00;
    $hasVariations = isset($_POST['hasVariations']) ? 1 : 0;
    
    // Validate action
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    // Validate required fields for add and update actions
    if (in_array($action, ['add', 'update'])) {
        if (empty($itemNameEn)) {
            throw new Exception('Item name is required');
        }
        if ($menuId <= 0) {
            throw new Exception('Please select a menu');
        }
    }
    
    // Validate menu item ID for update and delete actions
    if (in_array($action, ['update', 'delete']) && $menuItemId <= 0) {
        throw new Exception('Invalid menu item ID');
    }
    
    switch ($action) {
        case 'add':
            handleAddMenuItemBase64($conn, $restaurant_id, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, $preparationTime, $isAvailable, $basePrice, $hasVariations);
            break;
            
        case 'update':
            handleUpdateMenuItemBase64($conn, $restaurant_id, $menuItemId, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, $preparationTime, $isAvailable, $basePrice, $hasVariations);
            break;
            
        case 'delete':
            handleDeleteMenuItemBase64($conn, $restaurant_id, $menuItemId);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("PDO Error in menu_items_operations_base64.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in menu_items_operations_base64.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

function handleAddMenuItemBase64($conn, $restaurant_id, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, $preparationTime, $isAvailable, $basePrice, $hasVariations) {
    // Check if menu exists and belongs to this restaurant
    $checkMenuStmt = $conn->prepare("SELECT id FROM menu WHERE id = ? AND restaurant_id = ?");
    $checkMenuStmt->execute([$menuId, $restaurant_id]);
    if (!$checkMenuStmt->fetch()) {
        throw new Exception('Selected menu does not exist');
    }
    
    // Handle base64 image - store in database
    $itemImageData = null;
    $itemImageMimeType = null;
    $itemImagePath = null;
    
    if (isset($_POST['itemImageBase64']) && !empty($_POST['itemImageBase64'])) {
        $imageInfo = handleBase64Image($_POST['itemImageBase64']);
        if (is_array($imageInfo)) {
            $itemImageData = $imageInfo['data'];
            $itemImageMimeType = $imageInfo['mime_type'];
            $itemImagePath = 'db:' . uniqid(); // Reference ID for database storage
        } else {
            $itemImagePath = $imageInfo; // Fallback for old format
        }
    }
    
    // Ensure columns exist
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'image_data'");
        if ($checkCol->rowCount() == 0) {
            $conn->exec("ALTER TABLE menu_items ADD COLUMN image_data LONGBLOB NULL AFTER item_image");
            $conn->exec("ALTER TABLE menu_items ADD COLUMN image_mime_type VARCHAR(50) NULL AFTER image_data");
        }
    } catch (PDOException $e) {
        // Columns might already exist, continue
    }
    
    // Insert new menu item with image data
    $insertStmt = $conn->prepare("
        INSERT INTO menu_items 
        (restaurant_id, menu_id, item_name_en, item_description_en, item_category, item_type, preparation_time, is_available, base_price, has_variations, item_image, image_data, image_mime_type, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $insertStmt->execute([
        $restaurant_id, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, 
        $preparationTime, $isAvailable, $basePrice, $hasVariations, 
        $itemImagePath, $itemImageData, $itemImageMimeType
    ]);
    
    if ($result) {
        $newMenuItemId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Menu item added successfully',
            'data' => [
                'id' => $newMenuItemId,
                'item_name_en' => $itemNameEn,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Failed to add menu item');
    }
}

function handleUpdateMenuItemBase64($conn, $restaurant_id, $menuItemId, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, $preparationTime, $isAvailable, $basePrice, $hasVariations) {
    // Check if menu item exists and belongs to this restaurant
    $checkStmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$menuItemId, $restaurant_id]);
    $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingItem) {
        throw new Exception('Menu item not found');
    }
    
    // Check if menu exists and belongs to this restaurant
    $checkMenuStmt = $conn->prepare("SELECT id FROM menu WHERE id = ? AND restaurant_id = ?");
    $checkMenuStmt->execute([$menuId, $restaurant_id]);
    if (!$checkMenuStmt->fetch()) {
        throw new Exception('Selected menu does not exist');
    }
    
    // Handle base64 image - store in database
    $itemImageData = $existingItem['image_data'] ?? null; // Keep existing image by default
    $itemImageMimeType = $existingItem['image_mime_type'] ?? null;
    $itemImagePath = $existingItem['item_image'] ?? null;
    
    if (isset($_POST['itemImageBase64']) && !empty($_POST['itemImageBase64'])) {
        // Delete old image file if exists (for backward compatibility)
        if (!empty($existingItem['item_image']) && strpos($existingItem['item_image'], 'db:') !== 0 && file_exists($existingItem['item_image'])) {
            @unlink($existingItem['item_image']);
        }
        
        $imageInfo = handleBase64Image($_POST['itemImageBase64']);
        if (is_array($imageInfo)) {
            $itemImageData = $imageInfo['data'];
            $itemImageMimeType = $imageInfo['mime_type'];
            $itemImagePath = 'db:' . uniqid(); // Reference ID for database storage
        } else {
            $itemImagePath = $imageInfo; // Fallback for old format
        }
    }
    
    // Ensure columns exist
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'image_data'");
        if ($checkCol->rowCount() == 0) {
            $conn->exec("ALTER TABLE menu_items ADD COLUMN image_data LONGBLOB NULL AFTER item_image");
            $conn->exec("ALTER TABLE menu_items ADD COLUMN image_mime_type VARCHAR(50) NULL AFTER image_data");
        }
    } catch (PDOException $e) {
        // Columns might already exist, continue
    }
    
    // Update menu item with image data
    $updateStmt = $conn->prepare("
        UPDATE menu_items SET 
        menu_id = ?, item_name_en = ?, item_description_en = ?, item_category = ?, 
        item_type = ?, preparation_time = ?, is_available = ?, base_price = ?, 
        has_variations = ?, item_image = ?, image_data = ?, image_mime_type = ?, updated_at = NOW()
        WHERE id = ? AND restaurant_id = ?
    ");
    
    $result = $updateStmt->execute([
        $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, 
        $preparationTime, $isAvailable, $basePrice, $hasVariations, 
        $itemImagePath, $itemImageData, $itemImageMimeType, $menuItemId, $restaurant_id
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Menu item updated successfully',
            'data' => [
                'id' => $menuItemId,
                'item_name_en' => $itemNameEn,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Failed to update menu item');
    }
}

function handleDeleteMenuItemBase64($conn, $restaurant_id, $menuItemId) {
    // Check if menu item exists and belongs to this restaurant
    $checkStmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$menuItemId, $restaurant_id]);
    $menuItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$menuItem) {
        throw new Exception('Menu item not found');
    }
    
    // Delete the image file if it exists
    if (!empty($menuItem['item_image']) && file_exists($menuItem['item_image'])) {
        unlink($menuItem['item_image']);
    }
    
    // Delete menu item
    $deleteStmt = $conn->prepare("DELETE FROM menu_items WHERE id = ? AND restaurant_id = ?");
    $result = $deleteStmt->execute([$menuItemId, $restaurant_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Menu item deleted successfully',
            'data' => [
                'id' => $menuItemId,
                'item_name_en' => $menuItem['item_name_en']
            ]
        ]);
    } else {
        throw new Exception('Failed to delete menu item');
    }
}

function handleBase64Image($base64String) {
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

