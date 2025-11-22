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
require_once 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Resolve restaurant id: explicit query param > session > default
$restaurantId = isset($_GET['restaurant_id']) && $_GET['restaurant_id'] !== ''
    ? $_GET['restaurant_id']
    : (isset($_SESSION['restaurant_id']) ? $_SESSION['restaurant_id'] : 'RES001');

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Check if database connection is available
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    if (!$restaurantId) {
        // No restaurant context available
        echo json_encode(['error' => 'RESTAURANT_NOT_SET']);
        exit;
    }

    switch ($action) {
        case 'getMenus':
            $stmt = $pdo->prepare("SELECT * FROM menu WHERE restaurant_id = :rid AND is_active = 1 ORDER BY sort_order");
            $stmt->execute([':rid' => $restaurantId]);
            $menus = $stmt->fetchAll();
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
                $stmt = $pdo->prepare($query);
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
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode($items);
                } else {
                    throw $e;
                }
            }
            break;
            
        case 'getCategories':
            $stmt = $pdo->prepare("SELECT DISTINCT item_category FROM menu_items WHERE restaurant_id = :rid AND item_category IS NOT NULL AND item_category != '' ORDER BY item_category");
            $stmt->execute([':rid' => $restaurantId]);
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($categories);
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
                      WHERE (mi.item_name_en LIKE :search OR mi.item_description_en LIKE :search OR mi.item_category LIKE :search)
                      AND mi.is_available = 1 AND mi.restaurant_id = :rid
                      ORDER BY mi.item_name_en LIMIT 20";
            $like = '%' . $searchTerm . '%';
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute([':search' => $like, ':rid' => $restaurantId]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Clean up any binary data
                foreach ($items as &$item) {
                    if (isset($item['image_data'])) {
                        unset($item['image_data']);
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
                              WHERE (mi.item_name_en LIKE :search OR mi.item_description_en LIKE :search OR mi.item_category LIKE :search)
                              AND mi.is_available = 1 AND mi.restaurant_id = :rid
                              ORDER BY mi.item_name_en LIMIT 20";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':search' => $like, ':rid' => $restaurantId]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode($items);
                } else {
                    throw $e;
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
            $customerStmt = $pdo->prepare("SELECT customer_name FROM customers WHERE phone = :phone AND restaurant_id = :rid LIMIT 1");
            $customerStmt->execute([':phone' => $phone, ':rid' => $restaurantId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                // No customer found with this phone, return empty array
                echo json_encode([]);
                break;
            }
            
            // Get orders for this customer name
            $stmt = $pdo->prepare("SELECT o.*, 
                                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
                                   FROM orders o 
                                   WHERE o.customer_name = :customer_name AND o.restaurant_id = :rid 
                                   ORDER BY o.created_at DESC LIMIT 20");
            $stmt->execute([':customer_name' => $customer['customer_name'], ':rid' => $restaurantId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get order items for each order
            foreach ($orders as &$order) {
                $itemsStmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price 
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
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred. Please check your database connection.']);
} catch (Exception $e) {
    error_log("Error in website/api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

