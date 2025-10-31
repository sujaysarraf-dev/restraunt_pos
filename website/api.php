<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Resolve restaurant id: explicit query param > session > error
$restaurantId = isset($_GET['restaurant_id']) && $_GET['restaurant_id'] !== ''
    ? $_GET['restaurant_id']
    : (isset($_SESSION['restaurant_id']) ? $_SESSION['restaurant_id'] : null);

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
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
            
            $query = "SELECT mi.*, m.menu_name FROM menu_items mi 
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
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
            
            echo json_encode($items);
            break;
            
        case 'getCategories':
            $stmt = $pdo->prepare("SELECT DISTINCT item_category FROM menu_items WHERE restaurant_id = :rid AND item_category IS NOT NULL AND item_category != '' ORDER BY item_category");
            $stmt->execute([':rid' => $restaurantId]);
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($categories);
            break;
            
        case 'searchItems':
            $searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
            $stmt = $pdo->prepare("SELECT mi.*, m.menu_name FROM menu_items mi 
                                   JOIN menu m ON mi.menu_id = m.id 
                                   WHERE (mi.item_name_en LIKE :search OR mi.item_description_en LIKE :search OR mi.item_category LIKE :search)
                                   AND mi.is_available = 1 AND mi.restaurant_id = :rid
                                   ORDER BY mi.item_name_en LIMIT 20");
            $like = '%' . $searchTerm . '%';
            $stmt->execute([':search' => $like, ':rid' => $restaurantId]);
            $items = $stmt->fetchAll();
            echo json_encode($items);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

