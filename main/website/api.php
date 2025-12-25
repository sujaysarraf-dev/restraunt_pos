<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    http_response_code(200);
    exit();
}

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
// Skip timeout validation for public customer website API - sessions are just for restaurant context
startSecureSession(true);
require_once 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Resolve restaurant id: explicit query param > session > default
$restaurantId = isset($_GET['restaurant_id']) && $_GET['restaurant_id'] !== ''
    ? $_GET['restaurant_id']
    : (isset($_SESSION['restaurant_id']) ? $_SESSION['restaurant_id'] : 'RES001');

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Get connection using getConnection() for lazy connection support
    if (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback to $pdo if getConnection() doesn't exist (backward compatibility)
        global $pdo;
        $conn = $pdo ?? null;
        if (!$conn) {
            throw new Exception('Database connection not available');
        }
    }
    
    // Verify connection is valid
    if (!$conn) {
        throw new Exception('Database connection not available');
    }
    
    if (!$restaurantId) {
        // No restaurant context available
        echo json_encode(['error' => 'RESTAURANT_NOT_SET']);
        exit;
    }

    switch ($action) {
        case 'getRestaurantDetails':
            try {
                // Get user_id first
                $userStmt = $conn->prepare("SELECT id FROM users WHERE restaurant_id = ? LIMIT 1");
                $userStmt->execute([$restaurantId]);
                $userResult = $userStmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $userResult ? $userResult['id'] : null;
                
                // Get restaurant details
                if ($user_id) {
                    $stmt = $conn->prepare("SELECT id, restaurant_name, restaurant_logo, currency_symbol FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $conn->prepare("SELECT id, restaurant_name, restaurant_logo, currency_symbol FROM users WHERE restaurant_id = ? LIMIT 1");
                    $stmt->execute([$restaurantId]);
                }
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $result = ['success' => false];
                if ($userRow) {
                    $result['success'] = true;
                    $result['restaurant_name'] = $userRow['restaurant_name'] ?? 'Restaurant';
                    
                    // Logo URL
                    if (!empty($userRow['restaurant_logo'])) {
                        if (strpos($userRow['restaurant_logo'], 'db:') === 0) {
                            $result['restaurant_logo'] = '../api/image.php?type=logo&id=' . ($userRow['id'] ?? $user_id ?? '');
                        } elseif (strpos($userRow['restaurant_logo'], 'http') === 0) {
                            $result['restaurant_logo'] = $userRow['restaurant_logo'];
                        } else {
                            $logo = $userRow['restaurant_logo'];
                            if (strpos($logo, 'uploads/') !== 0) {
                                $logo = '../uploads/' . $logo;
                            } else {
                                $logo = '../' . $logo;
                            }
                            $result['restaurant_logo'] = $logo;
                        }
                    }
                    
                    // Currency
                    if (array_key_exists('currency_symbol', $userRow) && $userRow['currency_symbol'] !== null && $userRow['currency_symbol'] !== '') {
                        require_once __DIR__ . '/../config/unicode_utils.php';
                        $result['currency_symbol'] = fixCurrencySymbol($userRow['currency_symbol']);
                    }
                    
                    // Theme colors
                    $themeStmt = $conn->prepare("SELECT primary_red, dark_red, primary_yellow FROM website_settings WHERE restaurant_id = ? LIMIT 1");
                    $themeStmt->execute([$restaurantId]);
                    $themeRow = $themeStmt->fetch(PDO::FETCH_ASSOC);
                    if ($themeRow) {
                        $result['theme'] = [
                            'primary_red' => $themeRow['primary_red'] ?? '#F70000',
                            'dark_red' => $themeRow['dark_red'] ?? '#DA020E',
                            'primary_yellow' => $themeRow['primary_yellow'] ?? '#FFD100'
                        ];
                    }
                }
                echo json_encode($result);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'getMenus':
            // Check if menu_image column exists
            $checkCol = $conn->query("SHOW COLUMNS FROM menu LIKE 'menu_image'");
            $hasImageColumns = $checkCol->rowCount() > 0;
            
            if ($hasImageColumns) {
                $stmt = $conn->prepare("SELECT id, menu_name, menu_image, is_active, sort_order FROM menu WHERE restaurant_id = :rid AND is_active = 1 ORDER BY sort_order ASC, created_at DESC");
            } else {
                $stmt = $conn->prepare("SELECT id, menu_name, is_active, sort_order FROM menu WHERE restaurant_id = :rid AND is_active = 1 ORDER BY sort_order ASC, created_at DESC");
            }
            $stmt->execute([':rid' => $restaurantId]);
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($menus);
            break;
            
        case 'getMenuItems':
            $menuId = isset($_GET['menu_id']) ? $_GET['menu_id'] : null;
            $category = isset($_GET['category']) ? $_GET['category'] : null;
            $type = isset($_GET['type']) ? $_GET['type'] : null;
            
            // Explicitly select columns to avoid issues with binary data and missing columns
            $query = "SELECT mi.id, mi.restaurant_id, mi.menu_id, mi.item_name_en, mi.item_description_en, 
                             mi.item_category, mi.item_type, mi.preparation_time, mi.is_available, 
                             mi.base_price, mi.has_variations, mi.item_image, 
                             mi.sort_order, mi.created_at, mi.updated_at, m.menu_name 
                      FROM menu_items mi 
                      JOIN menu m ON mi.menu_id = m.id 
                      WHERE mi.is_available = 1 AND mi.restaurant_id = :rid";
            
            $params = [':rid' => $restaurantId];
            
            if ($menuId) {
                $query .= " AND mi.menu_id = :menu_id";
                $params[':menu_id'] = $menuId;
            }
            
            if ($category) {
                $query .= " AND mi.item_category = :category";
                $params[':category'] = $category;
            }
            
            if ($type) {
                $query .= " AND mi.item_type = :type";
                $params[':type'] = $type;
            }
            
            $query .= " ORDER BY mi.sort_order, mi.item_name_en";
            
            try {
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Clean up any binary data that might have been included
                foreach ($items as &$item) {
                    if (isset($item['image_data'])) {
                        unset($item['image_data']); // Remove binary data from JSON
                    }
                    if (isset($item['image_mime_type'])) {
                        // Keep mime_type if needed, but we don't need it in the list
                        // unset($item['image_mime_type']);
                    }
                    
                    // Load variations for this item
                    try {
                        $checkTable = $conn->query("SHOW TABLES LIKE 'menu_item_variations'");
                        if ($checkTable->rowCount() > 0) {
                            $variationsStmt = $conn->prepare("
                                SELECT id, variation_name, price, sort_order, is_available 
                                FROM menu_item_variations 
                                WHERE menu_item_id = ? AND is_available = TRUE 
                                ORDER BY sort_order ASC
                            ");
                            $variationsStmt->execute([$item['id']]);
                            $item['variations'] = $variationsStmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $item['variations'] = [];
                        }
                    } catch (PDOException $e) {
                        $item['variations'] = [];
                    }
                }
                unset($item);
                
                echo json_encode($items);
            } catch (PDOException $e) {
                // If columns don't exist, try with basic columns only
                if (strpos($e->getMessage(), 'image_data') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                    $query = "SELECT mi.id, mi.restaurant_id, mi.menu_id, mi.item_name_en, mi.item_description_en, 
                                     mi.item_category, mi.item_type, mi.preparation_time, mi.is_available, 
                                     mi.base_price, mi.has_variations, mi.item_image, 
                                     mi.sort_order, mi.created_at, mi.updated_at, m.menu_name 
                              FROM menu_items mi 
                              JOIN menu m ON mi.menu_id = m.id 
                              WHERE mi.is_available = 1 AND mi.restaurant_id = :rid";
                    
                    if ($menuId) {
                        $query .= " AND mi.menu_id = :menu_id";
                    }
                    if ($category) {
                        $query .= " AND mi.item_category = :category";
                    }
                    if ($type) {
                        $query .= " AND mi.item_type = :type";
                    }
                    $query .= " ORDER BY mi.sort_order, mi.item_name_en";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Load variations for each item
                    try {
                        $checkTable = $conn->query("SHOW TABLES LIKE 'menu_item_variations'");
                        if ($checkTable->rowCount() > 0) {
                            foreach ($items as &$item) {
                                $variationsStmt = $conn->prepare("
                                    SELECT id, variation_name, price, sort_order, is_available 
                                    FROM menu_item_variations 
                                    WHERE menu_item_id = ? AND is_available = TRUE 
                                    ORDER BY sort_order ASC
                                ");
                                $variationsStmt->execute([$item['id']]);
                                $item['variations'] = $variationsStmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                            unset($item);
                        } else {
                            foreach ($items as &$item) {
                                $item['variations'] = [];
                            }
                            unset($item);
                        }
                    } catch (PDOException $e) {
                        foreach ($items as &$item) {
                            $item['variations'] = [];
                        }
                        unset($item);
                    }
                    
                    echo json_encode($items);
                } else {
                    throw $e;
                }
            }
            break;
            
        case 'getCategories':
            $menuId = isset($_GET['menu_id']) ? trim($_GET['menu_id']) : null;
            // Handle string "null" or empty string
            if ($menuId === 'null' || $menuId === '' || $menuId === null) {
                $menuId = null;
            }
            
            try {
                // First, get distinct categories
                $baseQuery = "SELECT DISTINCT mi.item_category
                              FROM menu_items mi 
                              WHERE mi.restaurant_id = :rid
                              AND mi.item_category IS NOT NULL 
                              AND mi.item_category != ''";
                
                $baseParams = [':rid' => $restaurantId];
                
                if ($menuId) {
                    $baseQuery .= " AND mi.menu_id = :menu_id";
                    $baseParams[':menu_id'] = $menuId;
                }
                
                $baseQuery .= " ORDER BY mi.item_category";
                
                $stmt = $conn->prepare($baseQuery);
                $stmt->execute($baseParams);
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Now get images for each category
                $result = [];
                foreach ($categories as $cat) {
                    $categoryName = $cat['item_category'];
                    
                    // Get first item image for this category
                    $imageQuery = "SELECT item_image FROM menu_items 
                                   WHERE restaurant_id = :rid 
                                   AND item_category = :category
                                   AND item_image IS NOT NULL 
                                   AND item_image != ''";
                    $imageParams = [':rid' => $restaurantId, ':category' => $categoryName];
                    
                    if ($menuId) {
                        $imageQuery .= " AND menu_id = :menu_id";
                        $imageParams[':menu_id'] = $menuId;
                    }
                    
                    $imageQuery .= " LIMIT 1";
                    
                    $imageStmt = $conn->prepare($imageQuery);
                    $imageStmt->execute($imageParams);
                    $imageRow = $imageStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $result[] = [
                        'name' => $categoryName,
                        'image' => $imageRow['item_image'] ?? null
                    ];
                }
                
                echo json_encode($result);
            } catch (PDOException $e) {
                error_log("Error in getCategories: " . $e->getMessage());
                error_log("Query: " . ($baseQuery ?? 'N/A'));
                error_log("Params: " . print_r($baseParams ?? [], true));
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            } catch (Exception $e) {
                error_log("Error in getCategories: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'searchItems':
            $searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
            // Explicitly select columns to avoid binary data issues
            $query = "SELECT mi.id, mi.restaurant_id, mi.menu_id, mi.item_name_en, mi.item_description_en, 
                             mi.item_category, mi.item_type, mi.preparation_time, mi.is_available, 
                             mi.base_price, mi.has_variations, mi.item_image, 
                             mi.sort_order, mi.created_at, mi.updated_at, m.menu_name 
                      FROM menu_items mi 
                      JOIN menu m ON mi.menu_id = m.id 
                      WHERE (mi.item_name_en LIKE :search1 OR mi.item_description_en LIKE :search2 OR mi.item_category LIKE :search3)
                      AND mi.is_available = 1 AND mi.restaurant_id = :rid
                      ORDER BY mi.item_name_en LIMIT 20";
            $like = '%' . $searchTerm . '%';
            try {
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':search1' => $like,
                    ':search2' => $like,
                    ':search3' => $like,
                    ':rid' => $restaurantId
                ]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Clean up any binary data and load variations
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'menu_item_variations'");
                    $hasVariationsTable = $checkTable->rowCount() > 0;
                } catch (PDOException $e) {
                    $hasVariationsTable = false;
                }
                
                foreach ($items as &$item) {
                    if (isset($item['image_data'])) {
                        unset($item['image_data']);
                    }
                    
                    // Load variations
                    if ($hasVariationsTable) {
                        try {
                            $variationsStmt = $conn->prepare("
                                SELECT id, variation_name, price, sort_order, is_available 
                                FROM menu_item_variations 
                                WHERE menu_item_id = ? AND is_available = TRUE 
                                ORDER BY sort_order ASC
                            ");
                            $variationsStmt->execute([$item['id']]);
                            $item['variations'] = $variationsStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $item['variations'] = [];
                        }
                    } else {
                        $item['variations'] = [];
                    }
                }
                unset($item);
                
                echo json_encode($items);
            } catch (PDOException $e) {
                // If columns don't exist, try with basic columns
                if (strpos($e->getMessage(), 'image_data') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                    $query = "SELECT mi.id, mi.restaurant_id, mi.menu_id, mi.item_name_en, mi.item_description_en, 
                                     mi.item_category, mi.item_type, mi.preparation_time, mi.is_available, 
                                     mi.base_price, mi.has_variations, mi.item_image, 
                                     mi.sort_order, mi.created_at, mi.updated_at, m.menu_name 
                              FROM menu_items mi 
                              JOIN menu m ON mi.menu_id = m.id 
                              WHERE (mi.item_name_en LIKE :search1 OR mi.item_description_en LIKE :search2 OR mi.item_category LIKE :search3)
                              AND mi.is_available = 1 AND mi.restaurant_id = :rid
                              ORDER BY mi.item_name_en LIMIT 20";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([
                        ':search1' => $like,
                        ':search2' => $like,
                        ':search3' => $like,
                        ':rid' => $restaurantId
                    ]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode($items);
                } else {
                    error_log("Search error: " . $e->getMessage());
                    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
                }
            }
            break;
            
        case 'getCustomerOrders':
            $phone = isset($_GET['phone']) ? $_GET['phone'] : '';
            if (!$phone) {
                echo json_encode(['error' => 'Phone number is required']);
                break;
            }
            
            // First, find customer by phone number
            $customerStmt = $conn->prepare("SELECT customer_name FROM customers WHERE phone = :phone AND restaurant_id = :rid LIMIT 1");
            $customerStmt->execute([':phone' => $phone, ':rid' => $restaurantId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                // No customer found with this phone, return empty array
                echo json_encode([]);
                break;
            }
            
            // Get orders for this customer name
            $stmt = $conn->prepare("SELECT o.*, 
                                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
                                   FROM orders o 
                                   WHERE o.customer_name = :customer_name AND o.restaurant_id = :rid 
                                   ORDER BY o.created_at DESC LIMIT 20");
            $stmt->execute([':customer_name' => $customer['customer_name'], ':rid' => $restaurantId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get order items for each order
            foreach ($orders as &$order) {
                $itemsStmt = $conn->prepare("SELECT item_name, quantity, unit_price, total_price 
                                           FROM order_items 
                                           WHERE order_id = :order_id");
                $itemsStmt->execute([':order_id' => $order['id']]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode($orders);
            break;
            
    default:
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Database error in website/api.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred. Please check your database connection.', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in website/api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

