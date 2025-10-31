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
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if (file_exists(__DIR__ . '/config/db_connection.php')) {
    require_once __DIR__ . '/config/db_connection.php';
} elseif (file_exists(__DIR__ . '/db_connection.php')) {
    require_once __DIR__ . '/db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    $conn = getConnection();
    
    // Get the action and data from POST
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $menuItemId = isset($_POST['menuItemId']) ? (int)$_POST['menuItemId'] : 0;
    
    // Validate action
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    switch ($action) {
        case 'add':
            handleAddMenuItem($conn, $uploadDir);
            break;
            
        case 'update':
            handleUpdateMenuItem($conn, $menuItemId, $uploadDir);
            break;
            
        case 'delete':
            handleDeleteMenuItem($conn, $menuItemId);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("PDO Error in menu_items_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in menu_items_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

function handleAddMenuItem($conn, $uploadDir) {
    // Validate required fields
    $requiredFields = ['itemNameEn', 'chooseMenu'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $itemNameEn = trim($_POST['itemNameEn']);
    $menuId = (int)$_POST['chooseMenu'];
    $itemDescriptionEn = isset($_POST['itemDescriptionEn']) ? trim($_POST['itemDescriptionEn']) : '';
    $itemCategory = isset($_POST['itemCategory']) ? trim($_POST['itemCategory']) : '';
    $itemType = isset($_POST['itemType']) ? trim($_POST['itemType']) : 'Veg';
    $preparationTime = isset($_POST['preparationTime']) ? (int)$_POST['preparationTime'] : 0;
    $isAvailable = isset($_POST['isAvailable']) ? (int)$_POST['isAvailable'] : 1;
    $basePrice = isset($_POST['basePrice']) ? (float)$_POST['basePrice'] : 0.00;
    $hasVariations = isset($_POST['hasVariations']) ? 1 : 0;
    
    // Validate menu exists
    $checkMenuStmt = $conn->prepare("SELECT id FROM menu WHERE id = ?");
    $checkMenuStmt->execute([$menuId]);
    if (!$checkMenuStmt->fetch()) {
        throw new Exception('Selected menu does not exist');
    }
    
    // Handle file upload
    $itemImage = null;
    if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
        $itemImage = handleFileUpload($_FILES['itemImage'], $uploadDir);
    }
    
    // Insert new menu item
    $insertStmt = $conn->prepare("
        INSERT INTO menu_items 
        (menu_id, item_name_en, item_description_en, item_category, item_type, preparation_time, is_available, base_price, has_variations, item_image, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $insertStmt->execute([
        $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, 
        $preparationTime, $isAvailable, $basePrice, $hasVariations, $itemImage
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

function handleUpdateMenuItem($conn, $menuItemId, $uploadDir) {
    if ($menuItemId <= 0) {
        throw new Exception('Invalid menu item ID');
    }
    
    // Check if menu item exists
    $checkStmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
    $checkStmt->execute([$menuItemId]);
    $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingItem) {
        throw new Exception('Menu item not found');
    }
    
    // Validate required fields
    $requiredFields = ['itemNameEn', 'chooseMenu'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $itemNameEn = trim($_POST['itemNameEn']);
    $menuId = (int)$_POST['chooseMenu'];
    $itemDescriptionEn = isset($_POST['itemDescriptionEn']) ? trim($_POST['itemDescriptionEn']) : '';
    $itemCategory = isset($_POST['itemCategory']) ? trim($_POST['itemCategory']) : '';
    $itemType = isset($_POST['itemType']) ? trim($_POST['itemType']) : 'Veg';
    $preparationTime = isset($_POST['preparationTime']) ? (int)$_POST['preparationTime'] : 0;
    $isAvailable = isset($_POST['isAvailable']) ? (int)$_POST['isAvailable'] : 1;
    $basePrice = isset($_POST['basePrice']) ? (float)$_POST['basePrice'] : 0.00;
    $hasVariations = isset($_POST['hasVariations']) ? 1 : 0;
    
    // Validate menu exists
    $checkMenuStmt = $conn->prepare("SELECT id FROM menu WHERE id = ?");
    $checkMenuStmt->execute([$menuId]);
    if (!$checkMenuStmt->fetch()) {
        throw new Exception('Selected menu does not exist');
    }
    
    // Handle file upload
    $itemImage = $existingItem['item_image']; // Keep existing image by default
    if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
        // Delete old image if exists
        if ($existingItem['item_image'] && file_exists($existingItem['item_image'])) {
            unlink($existingItem['item_image']);
        }
        $itemImage = handleFileUpload($_FILES['itemImage'], $uploadDir);
    }
    
    // Update menu item
    $updateStmt = $conn->prepare("
        UPDATE menu_items SET 
        menu_id = ?, item_name_en = ?, item_description_en = ?, item_category = ?, 
        item_type = ?, preparation_time = ?, is_available = ?, base_price = ?, 
        has_variations = ?, item_image = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $updateStmt->execute([
        $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, $itemType, 
        $preparationTime, $isAvailable, $basePrice, $hasVariations, $itemImage, $menuItemId
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

function handleDeleteMenuItem($conn, $menuItemId) {
    if ($menuItemId <= 0) {
        throw new Exception('Invalid menu item ID');
    }
    
    // Check if menu item exists and get its details
    $checkStmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
    $checkStmt->execute([$menuItemId]);
    $menuItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$menuItem) {
        throw new Exception('Menu item not found');
    }
    
    // Delete the image file if exists
    if ($menuItem['item_image'] && file_exists($menuItem['item_image'])) {
        unlink($menuItem['item_image']);
    }
    
    // Delete menu item
    $deleteStmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
    $result = $deleteStmt->execute([$menuItemId]);
    
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

function handleFileUpload($file, $uploadDir) {
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'item_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/' . $filename;
    } else {
        throw new Exception('Failed to upload file');
    }
}
?>

