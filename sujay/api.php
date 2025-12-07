<?php
/**
 * Sujay Testing API
 * Simplified API for testing without full authentication
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db_connection.php';

// OpenRouter API Configuration
define('OPENROUTER_API_KEY', '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');
define('OPENROUTER_MODEL', 'google/gemini-flash-1.5'); // Free lifetime model

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get restaurant ID from POST or use sujay's restaurant
$restaurant_id = $_POST['restaurant_id'] ?? $_GET['restaurant_id'] ?? null;
if (!$restaurant_id && $action !== 'getRestaurants') {
    try {
        $restaurantStmt = $pdo->query("SELECT id FROM users WHERE username = 'sujay' LIMIT 1");
        $restaurant = $restaurantStmt->fetch(PDO::FETCH_ASSOC);
        $restaurant_id = $restaurant ? $restaurant['id'] : 1;
    } catch (Exception $e) {
        $restaurant_id = 1;
    }
}

try {
    switch ($action) {
        case 'getRestaurants':
            $stmt = $pdo->query("SELECT id, restaurant_name, username FROM users ORDER BY id ASC");
            $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'restaurants' => $restaurants,
                'count' => count($restaurants)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getTables':
            $stmt = $pdo->prepare("
                SELECT t.id, t.table_number, t.capacity, a.area_name 
                FROM tables t 
                LEFT JOIN areas a ON t.area_id = a.id 
                WHERE t.restaurant_id = ? 
                ORDER BY t.table_number ASC
            ");
            $stmt->execute([$restaurant_id]);
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'tables' => $tables,
                'count' => count($tables)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getMenuItems':
            $stmt = $pdo->prepare("
                SELECT id, item_name_en, item_category, item_type, base_price 
                FROM menu_items 
                WHERE restaurant_id = ? 
                ORDER BY item_name_en ASC
            ");
            $stmt->execute([$restaurant_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'count' => count($items)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getMenus':
            $stmt = $pdo->prepare("SELECT id, menu_name, is_active, created_at, updated_at FROM menu WHERE restaurant_id = ? ORDER BY sort_order ASC, created_at DESC");
            $stmt->execute([$restaurant_id]);
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'menus' => $menus,
                'count' => count($menus)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getAreas':
            $stmt = $pdo->prepare("
                SELECT a.id, a.area_name, a.created_at, a.updated_at, 
                       COUNT(t.id) as no_of_tables 
                FROM areas a 
                LEFT JOIN tables t ON a.id = t.area_id 
                WHERE a.restaurant_id = ? 
                GROUP BY a.id, a.area_name, a.created_at, a.updated_at
                ORDER BY a.sort_order ASC, a.created_at DESC
            ");
            $stmt->execute([$restaurant_id]);
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'areas' => $areas,
                'count' => count($areas)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addMenu':
            $menuName = $_POST['menuName'] ?? '';
            if (empty($menuName)) {
                throw new Exception('Menu name is required');
            }
            
            // Check if menu name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$menuName, $restaurant_id]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Menu name already exists');
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurant_id, $menuName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Menu added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'name' => $menuName]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addArea':
            $areaName = $_POST['areaName'] ?? '';
            if (empty($areaName)) {
                throw new Exception('Area name is required');
            }
            
            // Check if area name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$areaName, $restaurant_id]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Area name already exists');
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurant_id, $areaName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Area added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'name' => $areaName]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addTable':
            $tableNumber = $_POST['tableNumber'] ?? '';
            $capacity = (int)($_POST['capacity'] ?? 4);
            $areaId = (int)($_POST['chooseArea'] ?? 0);
            
            if (empty($tableNumber)) {
                throw new Exception('Table number is required');
            }
            if ($areaId <= 0) {
                throw new Exception('Please select an area');
            }
            if ($capacity <= 0) {
                throw new Exception('Capacity must be greater than 0');
            }
            
            // Verify area belongs to restaurant
            $areaCheck = $pdo->prepare("SELECT id FROM areas WHERE id = ? AND restaurant_id = ?");
            $areaCheck->execute([$areaId, $restaurant_id]);
            if (!$areaCheck->fetch()) {
                throw new Exception('Invalid area selection');
            }
            
            // Check if table number already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE table_number = ? AND area_id = ?");
            $checkStmt->execute([$tableNumber, $areaId]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Table number already exists in this area');
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO tables (restaurant_id, area_id, table_number, capacity, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurant_id, $areaId, $tableNumber, $capacity]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Table added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'table_number' => $tableNumber]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addMenuItem':
            $menuId = (int)($_POST['chooseMenu'] ?? 0);
            $itemNameEn = $_POST['itemNameEn'] ?? '';
            $itemDescriptionEn = $_POST['itemDescriptionEn'] ?? '';
            $itemCategory = $_POST['itemCategory'] ?? '';
            $itemType = $_POST['itemType'] ?? 'Veg';
            $basePrice = (float)($_POST['basePrice'] ?? 0);
            $preparationTime = (int)($_POST['preparationTime'] ?? 15);
            $isAvailable = (int)($_POST['isAvailable'] ?? 1);
            
            if (empty($itemNameEn)) {
                throw new Exception('Item name is required');
            }
            if ($menuId <= 0) {
                throw new Exception('Please select a menu');
            }
            
            // Verify menu belongs to restaurant
            $menuCheck = $pdo->prepare("SELECT id FROM menu WHERE id = ? AND restaurant_id = ?");
            $menuCheck->execute([$menuId, $restaurant_id]);
            if (!$menuCheck->fetch()) {
                throw new Exception('Invalid menu selection');
            }
            
            // Ensure columns exist
            try {
                $checkCol = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_data'");
                if ($checkCol->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_data LONGBLOB NULL AFTER item_image");
                    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_mime_type VARCHAR(50) NULL AFTER image_data");
                }
            } catch (PDOException $e) {
                // Columns might already exist
            }
            
            $insertStmt = $pdo->prepare("
                INSERT INTO menu_items 
                (restaurant_id, menu_id, item_name_en, item_description_en, item_category, item_type, preparation_time, is_available, base_price, has_variations, item_image, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NOW(), NOW())
            ");
            $insertStmt->execute([
                $restaurant_id, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, 
                $itemType, $preparationTime, $isAvailable, $basePrice
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Menu item added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'name' => $itemNameEn]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'createDemo':
            // Generate random demo data
            $demoMenus = ['Breakfast Menu', 'Lunch Menu', 'Dinner Menu', 'Desserts', 'Beverages'];
            $demoAreas = ['Main Hall', 'Outdoor Seating', 'VIP Section', 'Bar Area'];
            $demoItems = [
                ['Margherita Pizza', 'Classic Italian pizza', 'Pizza', 'Veg', 12.99],
                ['Chicken Burger', 'Juicy chicken burger', 'Burgers', 'Non-Veg', 9.99],
                ['Caesar Salad', 'Fresh garden salad', 'Salads', 'Veg', 8.99],
                ['Chocolate Cake', 'Rich chocolate cake', 'Desserts', 'Veg', 6.99],
                ['Coca Cola', 'Refreshing soft drink', 'Beverages', 'Veg', 2.99],
            ];
            
            $created = [];
            
            // Create demo menus
            foreach ($demoMenus as $menuName) {
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND restaurant_id = ?");
                    $checkStmt->execute([$menuName, $restaurant_id]);
                    if ($checkStmt->fetchColumn() == 0) {
                        $insertStmt = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $insertStmt->execute([$restaurant_id, $menuName]);
                        $created[] = "Menu: $menuName";
                    }
                } catch (Exception $e) {
                    // Skip if already exists
                }
            }
            
            // Create demo areas
            $areaIds = [];
            foreach ($demoAreas as $areaName) {
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ? AND restaurant_id = ?");
                    $checkStmt->execute([$areaName, $restaurant_id]);
                    if ($checkStmt->fetchColumn() == 0) {
                        $insertStmt = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $insertStmt->execute([$restaurant_id, $areaName]);
                        $areaIds[] = $pdo->lastInsertId();
                        $created[] = "Area: $areaName";
                    } else {
                        // Get existing area ID
                        $getStmt = $pdo->prepare("SELECT id FROM areas WHERE area_name = ? AND restaurant_id = ? LIMIT 1");
                        $getStmt->execute([$areaName, $restaurant_id]);
                        $area = $getStmt->fetch(PDO::FETCH_ASSOC);
                        if ($area) $areaIds[] = $area['id'];
                    }
                } catch (Exception $e) {
                    // Skip if error
                }
            }
            
            // Create demo tables
            if (!empty($areaIds)) {
                for ($i = 1; $i <= 5; $i++) {
                    try {
                        $areaId = $areaIds[array_rand($areaIds)];
                        $tableNumber = "T$i";
                        $capacity = rand(2, 8);
                        
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE table_number = ? AND area_id = ?");
                        $checkStmt->execute([$tableNumber, $areaId]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $insertStmt = $pdo->prepare("INSERT INTO tables (restaurant_id, area_id, table_number, capacity, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                            $insertStmt->execute([$restaurant_id, $areaId, $tableNumber, $capacity]);
                            $created[] = "Table: $tableNumber";
                        }
                    } catch (Exception $e) {
                        // Skip if error
                    }
                }
            }
            
            // Create demo menu items
            $menuIds = [];
            $menuStmt = $pdo->prepare("SELECT id FROM menu WHERE restaurant_id = ?");
            $menuStmt->execute([$restaurant_id]);
            while ($menu = $menuStmt->fetch(PDO::FETCH_ASSOC)) {
                $menuIds[] = $menu['id'];
            }
            
            if (!empty($menuIds)) {
                foreach ($demoItems as $item) {
                    try {
                        $menuId = $menuIds[array_rand($menuIds)];
                        list($name, $desc, $cat, $type, $price) = $item;
                        
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE item_name_en = ? AND restaurant_id = ?");
                        $checkStmt->execute([$name, $restaurant_id]);
                        if ($checkStmt->fetchColumn() == 0) {
                            // Ensure columns exist
                            try {
                                $checkCol = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_data'");
                                if ($checkCol->rowCount() == 0) {
                                    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_data LONGBLOB NULL AFTER item_image");
                                    $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_mime_type VARCHAR(50) NULL AFTER image_data");
                                }
                            } catch (PDOException $e) {
                                // Columns might already exist
                            }
                            
                            $insertStmt = $pdo->prepare("
                                INSERT INTO menu_items 
                                (restaurant_id, menu_id, item_name_en, item_description_en, item_category, item_type, preparation_time, is_available, base_price, has_variations, item_image, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, 15, 1, ?, 0, NULL, NOW(), NOW())
                            ");
                            $insertStmt->execute([$restaurant_id, $menuId, $name, $desc, $cat, $type, $price]);
                            $created[] = "Menu Item: $name";
                        }
                    } catch (Exception $e) {
                        // Skip if error
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Demo data created successfully!',
                'created' => $created,
                'count' => count($created)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

