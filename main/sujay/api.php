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

// Handle JSON POST data
$jsonData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true) ?? [];
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? $jsonData['action'] ?? '';

// Get restaurant ID from JSON, POST, GET - DO NOT default to sujay's restaurant
// The frontend MUST always send restaurant_id for actions that need it
$restaurant_id = null;
if (!empty($jsonData['restaurant_id'])) {
    $restaurant_id = (int)$jsonData['restaurant_id'];
} elseif (!empty($_POST['restaurant_id'])) {
    $restaurant_id = (int)$_POST['restaurant_id'];
} elseif (!empty($_GET['restaurant_id'])) {
    $restaurant_id = (int)$_GET['restaurant_id'];
}

// Helper function to convert numeric restaurant ID to restaurant code (RES005)
function getRestaurantCode($pdo, $restaurant_id) {
    if (!$restaurant_id || !is_numeric($restaurant_id)) {
        return $restaurant_id; // Return as-is if not numeric
    }
    try {
        $codeStmt = $pdo->prepare("SELECT restaurant_id FROM users WHERE id = ?");
        $codeStmt->execute([$restaurant_id]);
        $codeResult = $codeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$codeResult || empty($codeResult['restaurant_id'])) {
            throw new Exception('Restaurant code not found for ID: ' . $restaurant_id);
        }
        return $codeResult['restaurant_id'];
    } catch (PDOException $e) {
        throw new Exception('Database error getting restaurant code: ' . $e->getMessage());
    }
}

// Only default for actions that don't require restaurant_id
// For actions that need restaurant_id, we'll validate it in each case

try {
    switch ($action) {
        case 'getRestaurants':
            // Return both id (numeric) and restaurant_id (code) so frontend can use numeric id
            $stmt = $pdo->query("SELECT id, restaurant_id, restaurant_name, username FROM users ORDER BY id ASC");
            $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'restaurants' => $restaurants,
                'count' => count($restaurants)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getTables':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            $stmt = $pdo->prepare("
                SELECT t.id, t.table_number, t.capacity, a.area_name 
                FROM tables t 
                LEFT JOIN areas a ON t.area_id = a.id 
                WHERE t.restaurant_id = ? 
                ORDER BY t.table_number ASC
            ");
            $stmt->execute([$restaurantCode]);
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'tables' => $tables,
                'count' => count($tables)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getMenuItems':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            $stmt = $pdo->prepare("
                SELECT id, item_name_en, item_category, item_type, base_price 
                FROM menu_items 
                WHERE restaurant_id = ? 
                ORDER BY item_name_en ASC
            ");
            $stmt->execute([$restaurantCode]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'count' => count($items)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getMenus':
            // Convert numeric ID to restaurant code if needed
            $restaurantCode = $restaurant_id;
            if (is_numeric($restaurant_id)) {
                $codeStmt = $pdo->prepare("SELECT restaurant_id FROM users WHERE id = ?");
                $codeStmt->execute([$restaurant_id]);
                $codeResult = $codeStmt->fetch(PDO::FETCH_ASSOC);
                if ($codeResult) {
                    $restaurantCode = $codeResult['restaurant_id'];
                }
            }
            error_log("getMenus called with restaurant_id: " . $restaurant_id . " -> using code: " . $restaurantCode);
            
            $stmt = $pdo->prepare("SELECT id, menu_name, is_active, created_at, updated_at FROM menu WHERE restaurant_id = ? ORDER BY sort_order ASC, created_at DESC");
            $stmt->execute([$restaurantCode]);
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug: Log the results
            error_log("getMenus found " . count($menus) . " menus for restaurant_id: " . $restaurant_id);
            
            echo json_encode([
                'success' => true,
                'menus' => $menus,
                'count' => count($menus),
                'debug' => ['restaurant_id' => $restaurant_id, 'query_count' => count($menus)]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'getAreas':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            $stmt = $pdo->prepare("
                SELECT a.id, a.area_name, a.created_at, a.updated_at, 
                       COUNT(t.id) as no_of_tables 
                FROM areas a 
                LEFT JOIN tables t ON a.id = t.area_id 
                WHERE a.restaurant_id = ? 
                GROUP BY a.id, a.area_name, a.created_at, a.updated_at
                ORDER BY a.sort_order ASC, a.created_at DESC
            ");
            $stmt->execute([$restaurantCode]);
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'areas' => $areas,
                'count' => count($areas)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addMenu':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            $menuName = $_POST['menuName'] ?? '';
            if (empty($menuName)) {
                throw new Exception('Menu name is required');
            }
            
            // Check if menu name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$menuName, $restaurantCode]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Menu name already exists');
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurantCode, $menuName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Menu added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'name' => $menuName]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addArea':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            $areaName = $_POST['areaName'] ?? '';
            if (empty($areaName)) {
                throw new Exception('Area name is required');
            }
            
            // Check if area name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$areaName, $restaurantCode]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Area name already exists');
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurantCode, $areaName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Area added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'name' => $areaName]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addTable':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
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
            $areaCheck->execute([$areaId, $restaurantCode]);
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
            $insertStmt->execute([$restaurantCode, $areaId, $tableNumber, $capacity]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Table added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'table_number' => $tableNumber]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'addMenuItem':
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
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
            $menuCheck->execute([$menuId, $restaurantCode]);
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
                $restaurantCode, $menuId, $itemNameEn, $itemDescriptionEn, $itemCategory, 
                $itemType, $preparationTime, $isAvailable, $basePrice
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Menu item added successfully',
                'data' => ['id' => $pdo->lastInsertId(), 'name' => $itemNameEn]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'createDemo':
            // Verify restaurant_id is valid
            if (!$restaurant_id || $restaurant_id <= 0) {
                throw new Exception('Invalid restaurant_id: ' . $restaurant_id . '. Please select a restaurant.');
            }
            
            // Get restaurant code
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            
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
                    $checkStmt->execute([$menuName, $restaurantCode]);
                    if ($checkStmt->fetchColumn() == 0) {
                        $insertStmt = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $insertStmt->execute([$restaurantCode, $menuName]);
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
                    $checkStmt->execute([$areaName, $restaurantCode]);
                    if ($checkStmt->fetchColumn() == 0) {
                        $insertStmt = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $insertStmt->execute([$restaurantCode, $areaName]);
                        $areaIds[] = $pdo->lastInsertId();
                        $created[] = "Area: $areaName";
                    } else {
                        // Get existing area ID
                        $getStmt = $pdo->prepare("SELECT id FROM areas WHERE area_name = ? AND restaurant_id = ? LIMIT 1");
                        $getStmt->execute([$areaName, $restaurantCode]);
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
            $menuStmt->execute([$restaurantCode]);
            while ($menu = $menuStmt->fetch(PDO::FETCH_ASSOC)) {
                $menuIds[] = $menu['id'];
            }
            
            if (!empty($menuIds)) {
                foreach ($demoItems as $item) {
                    try {
                        $menuId = $menuIds[array_rand($menuIds)];
                        list($name, $desc, $cat, $type, $price) = $item;
                        
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE item_name_en = ? AND restaurant_id = ?");
                        $checkStmt->execute([$name, $restaurantCode]);
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
                            $insertStmt->execute([$restaurantCode, $menuId, $name, $desc, $cat, $type, $price]);
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
            
        case 'processAI':
            $input = $jsonData; // Use already parsed JSON data
            $prompt = $input['prompt'] ?? '';
            $restaurant_id = $input['restaurant_id'] ?? $restaurant_id;
            
            if (empty($prompt)) {
                throw new Exception('Prompt is required');
            }
            
            // Get restaurant code
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            
            // Get current state
            $menusStmt = $pdo->prepare("SELECT id, menu_name FROM menu WHERE restaurant_id = ?");
            $menusStmt->execute([$restaurantCode]);
            $menus = $menusStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $areasStmt = $pdo->prepare("SELECT id, area_name FROM areas WHERE restaurant_id = ?");
            $areasStmt->execute([$restaurantCode]);
            $areas = $areasStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $itemsStmt = $pdo->prepare("SELECT item_name_en, item_category FROM menu_items WHERE restaurant_id = ? LIMIT 10");
            $itemsStmt->execute([$restaurantCode]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build context for AI
            $context = "You are a restaurant management assistant. Current state:\n";
            $context .= "Menus: " . implode(', ', array_column($menus, 'menu_name')) . "\n";
            $context .= "Areas: " . implode(', ', array_column($areas, 'area_name')) . "\n";
            $context .= "Sample items: " . implode(', ', array_slice(array_column($items, 'item_name_en'), 0, 5)) . "\n\n";
            $context .= "User request: $prompt\n\n";
            $context .= "Respond with a JSON plan. Format:\n";
            $context .= '{"action": "add_items|add_menu|add_area|add_table", "items": [{"name": "...", "description": "...", "category": "...", "type": "Veg|Non-Veg", "price": 0.00, "menu": "menu_name"}], "requiresApproval": true, "plan": "human readable plan"}';
            
            // Call OpenRouter API
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . OPENROUTER_API_KEY,
                    'Content-Type: application/json',
                    'HTTP-Referer: https://restrogrow.com',
                    'X-Title: RestroGrow Testing',
                    'User-Agent: RestroGrow/1.0'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => OPENROUTER_MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful restaurant management assistant. Always respond with valid JSON only. Do not include any text outside the JSON.'],
                        ['role' => 'user', 'content' => $context]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                ])
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('AI service error: ' . $response);
            }
            
            $aiResponse = json_decode($response, true);
            
            if (isset($aiResponse['error'])) {
                throw new Exception('AI API Error: ' . ($aiResponse['error']['message'] ?? 'Unknown error'));
            }
            
            $aiContent = $aiResponse['choices'][0]['message']['content'] ?? '';
            
            if (empty($aiContent)) {
                throw new Exception('Empty response from AI. Full response: ' . substr($response, 0, 500));
            }
            
            // Extract JSON from response (handle markdown code blocks)
            if (preg_match('/```json\s*(.*?)\s*```/s', $aiContent, $matches)) {
                $aiContent = $matches[1];
            } elseif (preg_match('/```\s*(.*?)\s*```/s', $aiContent, $matches)) {
                $aiContent = $matches[1];
            }
            
            // Try to find JSON object in the response
            if (preg_match('/\{.*\}/s', $aiContent, $jsonMatch)) {
                $aiContent = $jsonMatch[0];
            }
            
            $plan = json_decode(trim($aiContent), true);
            
            if (!$plan || json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse AI response. JSON Error: ' . json_last_error_msg() . '. Raw: ' . substr($aiContent, 0, 300));
            }
            
            echo json_encode([
                'success' => true,
                'requiresApproval' => true,
                'plan' => $plan['plan'] ?? 'Execute the requested action',
                'action' => $plan['action'] ?? '',
                'items' => $plan['items'] ?? [],
                'rawPlan' => $plan
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'quickAddMenu':
            // Debug: Log all sources of restaurant_id
            error_log("quickAddMenu - JSON data: " . json_encode($jsonData));
            error_log("quickAddMenu - POST data: " . json_encode($_POST));
            error_log("quickAddMenu - GET data: " . json_encode($_GET));
            error_log("quickAddMenu - Final restaurant_id: " . $restaurant_id);
            
            // Verify restaurant_id is valid
            if (!$restaurant_id || $restaurant_id <= 0) {
                throw new Exception('Invalid restaurant_id: ' . $restaurant_id);
            }
            
            // Verify restaurant exists and get the restaurant_id CODE (like RES005)
            $verifyStmt = $pdo->prepare("SELECT id, username, restaurant_name, restaurant_id as restaurant_code FROM users WHERE id = ?");
            $verifyStmt->execute([$restaurant_id]);
            $restaurant = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            if (!$restaurant) {
                throw new Exception('Restaurant with ID ' . $restaurant_id . ' not found');
            }
            
            // Use the restaurant_id CODE (like RES005) for menu.restaurant_id, NOT the numeric ID
            $restaurantCode = $restaurant['restaurant_code'];
            error_log("quickAddMenu - Restaurant verified: " . json_encode($restaurant) . " - Using restaurant code: " . $restaurantCode);
            
            // Get next menu number - use restaurant code
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE restaurant_id = ?");
            $countStmt->execute([$restaurantCode]);
            $count = $countStmt->fetchColumn();
            $menuName = 'Menu ' . ($count + 1);
            
            // Check if exists, increment if needed - use restaurant code
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$menuName, $restaurantCode]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists > 0) {
                $menuName = 'Menu ' . ($count + 2);
            }
            
            // Insert menu - use restaurant CODE (like RES005), NOT the numeric ID
            $insertStmt = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insertResult = $insertStmt->execute([$restaurantCode, $menuName]);
            
            if (!$insertResult) {
                $errorInfo = $insertStmt->errorInfo();
                throw new Exception('Database insert failed: ' . json_encode($errorInfo));
            }
            
            $insertedId = $pdo->lastInsertId();
            error_log("quickAddMenu - Successfully inserted menu '$menuName' with ID $insertedId for restaurant_code: $restaurantCode (username: " . $restaurant['username'] . ", numeric ID: " . $restaurant['id'] . ")");
            
            // Verify the insert
            $verifyInsertStmt = $pdo->prepare("SELECT id, menu_name, restaurant_id FROM menu WHERE id = ?");
            $verifyInsertStmt->execute([$insertedId]);
            $insertedMenu = $verifyInsertStmt->fetch(PDO::FETCH_ASSOC);
            error_log("quickAddMenu - Verified inserted menu: " . json_encode($insertedMenu));
            
            echo json_encode([
                'success' => true,
                'message' => 'Menu added successfully',
                'name' => $menuName,
                'id' => $insertedId,
                'debug' => [
                    'restaurant_id_sent' => $restaurant_id,
                    'restaurant_code_used' => $restaurantCode,
                    'restaurant_username' => $restaurant['username'],
                    'restaurant_name' => $restaurant['restaurant_name'],
                    'inserted_id' => $insertedId,
                    'inserted_menu' => $insertedMenu
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'quickAddArea':
            // Verify restaurant_id is valid
            if (!$restaurant_id || $restaurant_id <= 0) {
                throw new Exception('Invalid restaurant_id: ' . $restaurant_id);
            }
            
            // Get restaurant code
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            
            // Get next area number - use restaurant code
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE restaurant_id = ?");
            $countStmt->execute([$restaurantCode]);
            $count = $countStmt->fetchColumn();
            $areaName = 'Area ' . ($count + 1);
            
            // Check if exists, increment if needed
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ? AND restaurant_id = ?");
            $checkStmt->execute([$areaName, $restaurantCode]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists > 0) {
                $areaName = 'Area ' . ($count + 2);
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurantCode, $areaName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Area added successfully',
                'name' => $areaName,
                'id' => $pdo->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'quickAddTable':
            // Verify restaurant_id is valid
            if (!$restaurant_id || $restaurant_id <= 0) {
                throw new Exception('Invalid restaurant_id: ' . $restaurant_id);
            }
            
            // Get restaurant code
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            
            // Get a random area or create one if none exists
            $areaStmt = $pdo->prepare("SELECT id FROM areas WHERE restaurant_id = ? LIMIT 1");
            $areaStmt->execute([$restaurantCode]);
            $area = $areaStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$area) {
                // Create an area first
                $insertArea = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, 'Area 1', NOW(), NOW())");
                $insertArea->execute([$restaurantCode]);
                $areaId = $pdo->lastInsertId();
            } else {
                $areaId = $area['id'];
            }
            
            // Get next table number for this area
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE area_id = ?");
            $countStmt->execute([$areaId]);
            $count = $countStmt->fetchColumn();
            $tableNumber = 'T' . ($count + 1);
            
            // Check if exists, increment if needed
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE table_number = ? AND area_id = ?");
            $checkStmt->execute([$tableNumber, $areaId]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists > 0) {
                $tableNumber = 'T' . ($count + 2);
            }
            
            $capacity = rand(2, 8); // Random capacity between 2-8
            $insertStmt = $pdo->prepare("INSERT INTO tables (restaurant_id, area_id, table_number, capacity, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $insertStmt->execute([$restaurantCode, $areaId, $tableNumber, $capacity]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Table added successfully',
                'table_number' => $tableNumber,
                'capacity' => $capacity,
                'id' => $pdo->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'quickAddMenuItem':
            // Verify restaurant_id is valid
            if (!$restaurant_id || $restaurant_id <= 0) {
                throw new Exception('Invalid restaurant_id: ' . $restaurant_id);
            }
            
            // Get restaurant code
            try {
                $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            } catch (Exception $e) {
                throw new Exception('Failed to get restaurant code: ' . $e->getMessage());
            }
            
            // Get a random menu or create one if none exists
            $menuStmt = $pdo->prepare("SELECT id FROM menu WHERE restaurant_id = ? LIMIT 1");
            $menuStmt->execute([$restaurantCode]);
            $menu = $menuStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$menu) {
                // Create a menu first
                $insertMenu = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, 'Menu 1', NOW(), NOW())");
                $insertMenu->execute([$restaurantCode]);
                $menuId = $pdo->lastInsertId();
            } else {
                $menuId = $menu['id'];
            }
            
            // Get next item number
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE restaurant_id = ?");
            $countStmt->execute([$restaurantCode]);
            $count = $countStmt->fetchColumn();
            $itemName = 'Item ' . ($count + 1);
            
            // Check if exists, increment if needed
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE item_name_en = ? AND restaurant_id = ?");
            $checkStmt->execute([$itemName, $restaurantCode]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists > 0) {
                $itemName = 'Item ' . ($count + 2);
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
            
            $price = round(rand(50, 500) / 10, 2); // Random price between 5.00 and 50.00
            $types = ['Veg', 'Non-Veg'];
            $type = $types[array_rand($types)];
            
            // Load default image if available
            $imageData = null;
            $imageMimeType = null;
            $itemImagePath = null;
            
            // Try multiple possible paths for the default image
            $possibleImagePaths = [
                __DIR__ . '/../../assets/images/default-menu-item.jpg',
                __DIR__ . '/../../../assets/images/default-menu-item.jpg',
                dirname(__DIR__, 2) . '/assets/images/default-menu-item.jpg'
            ];
            
            foreach ($possibleImagePaths as $imagePath) {
                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    if ($imageData !== false) {
                        $imageMimeType = 'image/jpeg';
                        $itemImagePath = 'db:' . uniqid(); // Reference ID for database storage
                        break;
                    }
                }
            }
            
            $insertStmt = $pdo->prepare("
                INSERT INTO menu_items 
                (restaurant_id, menu_id, item_name_en, item_description_en, item_category, item_type, preparation_time, is_available, base_price, has_variations, item_image, image_data, image_mime_type, created_at, updated_at) 
                VALUES (?, ?, ?, 'Delicious item', 'General', ?, 15, 1, ?, 0, ?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([$restaurantCode, $menuId, $itemName, $type, $price, $itemImagePath, $imageData, $imageMimeType]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Menu item added successfully',
                'name' => $itemName,
                'price' => $price,
                'id' => $pdo->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'executeAIPlan':
            $input = $jsonData; // Use already parsed JSON data
            $plan = $input['rawPlan'] ?? $input['plan'] ?? [];
            $restaurant_id = $input['restaurant_id'] ?? $restaurant_id;
            
            // Get restaurant code
            $restaurantCode = getRestaurantCode($pdo, $restaurant_id);
            
            $created = [];
            $action = $plan['action'] ?? '';
            $items = $plan['items'] ?? [];
            
            if ($action === 'add_items' || $action === 'add_menu_items') {
                // Get menu IDs
                $menuMap = [];
                $menusStmt = $pdo->prepare("SELECT id, menu_name FROM menu WHERE restaurant_id = ?");
                $menusStmt->execute([$restaurantCode]);
                while ($menu = $menusStmt->fetch(PDO::FETCH_ASSOC)) {
                    $menuMap[strtolower($menu['menu_name'])] = $menu['id'];
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
                
                foreach ($items as $item) {
                    $menuName = $item['menu'] ?? '';
                    $menuId = $menuMap[strtolower($menuName)] ?? null;
                    
                    if (!$menuId && !empty($menuName)) {
                        // Create menu if it doesn't exist
                        $insertMenu = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $insertMenu->execute([$restaurantCode, $menuName]);
                        $menuId = $pdo->lastInsertId();
                        $menuMap[strtolower($menuName)] = $menuId;
                        $created[] = "Menu: $menuName";
                    }
                    
                    if ($menuId) {
                        $insertItem = $pdo->prepare("
                            INSERT INTO menu_items 
                            (restaurant_id, menu_id, item_name_en, item_description_en, item_category, item_type, preparation_time, is_available, base_price, has_variations, item_image, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 15, 1, ?, 0, NULL, NOW(), NOW())
                        ");
                        $insertItem->execute([
                            $restaurantCode,
                            $menuId,
                            $item['name'] ?? 'Untitled Item',
                            $item['description'] ?? '',
                            $item['category'] ?? 'General',
                            $item['type'] ?? 'Veg',
                            (float)($item['price'] ?? 0)
                        ]);
                        $created[] = "Menu Item: " . ($item['name'] ?? 'Untitled');
                    }
                }
            } elseif ($action === 'add_menu') {
                foreach ($items as $item) {
                    $menuName = $item['menu'] ?? $item['name'] ?? '';
                    if ($menuName) {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE menu_name = ? AND restaurant_id = ?");
                        $checkStmt->execute([$menuName, $restaurantCode]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $insertStmt = $pdo->prepare("INSERT INTO menu (restaurant_id, menu_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                            $insertStmt->execute([$restaurantCode, $menuName]);
                            $created[] = "Menu: $menuName";
                        }
                    }
                }
            } elseif ($action === 'add_area') {
                foreach ($items as $item) {
                    $areaName = $item['name'] ?? $item['area'] ?? '';
                    if ($areaName) {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ? AND restaurant_id = ?");
                        $checkStmt->execute([$areaName, $restaurantCode]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $insertStmt = $pdo->prepare("INSERT INTO areas (restaurant_id, area_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                            $insertStmt->execute([$restaurantCode, $areaName]);
                            $created[] = "Area: $areaName";
                        }
                    }
                }
            } elseif ($action === 'add_table') {
                // Get area IDs
                $areaMap = [];
                $areasStmt = $pdo->prepare("SELECT id, area_name FROM areas WHERE restaurant_id = ?");
                $areasStmt->execute([$restaurantCode]);
                while ($area = $areasStmt->fetch(PDO::FETCH_ASSOC)) {
                    $areaMap[strtolower($area['area_name'])] = $area['id'];
                }
                
                foreach ($items as $item) {
                    $areaName = $item['area'] ?? '';
                    $areaId = $areaMap[strtolower($areaName)] ?? null;
                    $tableNumber = $item['table'] ?? $item['name'] ?? '';
                    $capacity = (int)($item['capacity'] ?? 4);
                    
                    if ($areaId && $tableNumber) {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE table_number = ? AND area_id = ?");
                        $checkStmt->execute([$tableNumber, $areaId]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $insertStmt = $pdo->prepare("INSERT INTO tables (restaurant_id, area_id, table_number, capacity, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                            $insertStmt->execute([$restaurantCode, $areaId, $tableNumber, $capacity]);
                            $created[] = "Table: $tableNumber in $areaName";
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Plan executed successfully',
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

