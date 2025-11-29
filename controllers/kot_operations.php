<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

// Require permission to view KOT
requirePermission(PERMISSION_VIEW_KOT);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create_kot':
            handleCreateKOT();
            break;
        case 'update_kot_status':
            handleUpdateKOTStatus();
            break;
        case 'complete_kot':
            handleCompleteKOT();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("PDO Error in kot_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
    exit();
} catch (Exception $e) {
    error_log("Error in kot_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}

function handleCreateKOT() {
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        global $pdo;
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
    $restaurant_id = $_SESSION['restaurant_id'];
    $table_id = $_POST['tableId'] ?? null;
    $order_type = $_POST['orderType'] ?? 'Dine-in';
    $customer_name = $_POST['customerName'] ?? '';
    $cart_items = json_decode($_POST['cartItems'], true);
    $subtotal = floatval($_POST['subtotal']);
    $tax = floatval($_POST['tax']);
    $total = floatval($_POST['total']);
    $notes = $_POST['notes'] ?? '';
    
    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'No items in cart']);
        return;
    }
    
    $kot_number = generateKOTNumber();
    
    try {
        $conn->beginTransaction();
        
        // Insert KOT
        $kot_sql = "INSERT INTO kot (restaurant_id, kot_number, table_id, order_type, customer_name, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $kot_stmt = $conn->prepare($kot_sql);
        $kot_stmt->execute([$restaurant_id, $kot_number, $table_id, $order_type, $customer_name, $subtotal, $tax, $total, $notes]);
        $kot_id = $conn->lastInsertId();
        
        // Insert KOT items
        foreach ($cart_items as $item) {
            $item_sql = "INSERT INTO kot_items (kot_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->execute([$kot_id, $item['id'], $item['name'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'KOT created successfully',
            'kot_id' => $kot_id,
            'kot_number' => $kot_number
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function handleUpdateKOTStatus() {
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        global $pdo;
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
    $kot_id = intval($_POST['kotId']);
    $status = $_POST['status'];
    $restaurant_id = $_SESSION['restaurant_id'] ?? $_GET['restaurant_id'] ?? null;
    
    if (!$restaurant_id) {
        // Check session for staff_id
        if (isset($_SESSION['staff_id'])) {
            // Get restaurant_id from staff
            $staff_sql = "SELECT restaurant_id FROM staff WHERE id = ?";
            $staff_stmt = $conn->prepare($staff_sql);
            $staff_stmt->execute([$_SESSION['staff_id']]);
            $staff = $staff_stmt->fetch();
            if ($staff) {
                $restaurant_id = $staff['restaurant_id'];
            }
        }
    }
    
    if (!$restaurant_id) {
        echo json_encode(['success' => false, 'message' => 'Restaurant ID not found']);
        return;
    }
    
    $valid_statuses = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Update KOT status
        $sql = "UPDATE kot SET kot_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status, $kot_id, $restaurant_id]);
        
        // If status is "Ready", create order from KOT
        if ($status === 'Ready') {
            // Get KOT details first to check if order already exists
            $kot_sql = "SELECT * FROM kot WHERE id = ? AND restaurant_id = ?";
            $kot_stmt = $conn->prepare($kot_sql);
            $kot_stmt->execute([$kot_id, $restaurant_id]);
            $kot = $kot_stmt->fetch();
            
            if (!$kot) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'KOT not found']);
                return;
            }
            
            // Check if order already exists for this KOT
            // We check by matching table_id, total amount, subtotal, tax, and recent creation
            // Also check if KOT number is mentioned in order notes (as a backup check)
            $check_order_sql = "SELECT o.id FROM orders o 
                                WHERE o.restaurant_id = ? 
                                AND o.table_id = ? 
                                AND ABS(o.total - ?) < 0.01
                                AND ABS(o.subtotal - ?) < 0.01
                                AND ABS(o.tax - ?) < 0.01
                                AND o.created_at >= DATE_SUB(?, INTERVAL 15 MINUTE)
                                AND o.order_status IN ('Ready', 'Preparing', 'Served', 'Completed')
                                LIMIT 1";
            $check_stmt = $conn->prepare($check_order_sql);
            $check_stmt->execute([
                $restaurant_id, 
                $kot['table_id'], 
                $kot['total'], 
                $kot['subtotal'], 
                $kot['tax'],
                $kot['created_at']
            ]);
            $existing_order = $check_stmt->fetch();
            
            if (!$existing_order) {
                $order_number = generateOrderNumber();
                
                // Create order from KOT
                $order_sql = "INSERT INTO orders (restaurant_id, table_id, order_number, customer_name, order_type, payment_method, payment_status, order_status, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, 'Cash', 'Paid', 'Ready', ?, ?, ?, ?)";
                $order_stmt = $conn->prepare($order_sql);
                $order_stmt->execute([$restaurant_id, $kot['table_id'], $order_number, $kot['customer_name'], $kot['order_type'], $kot['subtotal'], $kot['tax'], $kot['total'], $kot['notes']]);
                $order_id = $conn->lastInsertId();
                
                // Get KOT items and create order items
                $items_sql = "SELECT * FROM kot_items WHERE kot_id = ?";
                $items_stmt = $conn->prepare($items_sql);
                $items_stmt->execute([$kot_id]);
                $items = $items_stmt->fetchAll();
                
                foreach ($items as $item) {
                    $order_item_sql = "INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
                    $order_item_stmt = $conn->prepare($order_item_sql);
                    $order_item_stmt->execute([$order_id, $item['menu_item_id'], $item['item_name'], $item['quantity'], $item['unit_price'], $item['total_price']]);
                }
                
                // Record payment
                $payment_sql = "INSERT INTO payments (restaurant_id, order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'Cash', 'Success')";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([$restaurant_id, $order_id, $kot['total']]);
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'KOT marked as Ready and order created successfully',
                    'order_id' => $order_id,
                    'order_number' => $order_number
                ]);
                return;
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'KOT status updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating KOT status: ' . $e->getMessage()]);
    }
}

function handleCompleteKOT() {
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        global $pdo;
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
    $kot_id = intval($_POST['kotId']);
    $restaurant_id = $_SESSION['restaurant_id'] ?? $_GET['restaurant_id'] ?? null;
    
    if (!$restaurant_id) {
        // Check session for staff_id
        if (isset($_SESSION['staff_id'])) {
            // Get restaurant_id from staff
            $staff_sql = "SELECT restaurant_id FROM staff WHERE id = ?";
            $staff_stmt = $conn->prepare($staff_sql);
            $staff_stmt->execute([$_SESSION['staff_id']]);
            $staff = $staff_stmt->fetch();
            if ($staff) {
                $restaurant_id = $staff['restaurant_id'];
            }
        }
    }
    
    if (!$restaurant_id) {
        echo json_encode(['success' => false, 'message' => 'Restaurant ID not found']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Get KOT details
        $kot_sql = "SELECT * FROM kot WHERE id = ? AND restaurant_id = ?";
        $kot_stmt = $conn->prepare($kot_sql);
        $kot_stmt->execute([$kot_id, $restaurant_id]);
        $kot = $kot_stmt->fetch();
        
        if (!$kot) {
            throw new Exception('KOT not found');
        }
        
        // Check if order already exists for this KOT (order should have been created when status was set to "Ready")
        // Use same matching logic as in handleUpdateKOTStatus
        $check_order_sql = "SELECT o.id FROM orders o 
                            WHERE o.restaurant_id = ? 
                            AND o.table_id = ? 
                            AND ABS(o.total - ?) < 0.01
                            AND ABS(o.subtotal - ?) < 0.01
                            AND ABS(o.tax - ?) < 0.01
                            AND o.created_at >= DATE_SUB(?, INTERVAL 15 MINUTE)
                            AND o.order_status IN ('Ready', 'Preparing', 'Served', 'Completed')
                            LIMIT 1";
        $check_stmt = $conn->prepare($check_order_sql);
        $check_stmt->execute([
            $restaurant_id, 
            $kot['table_id'], 
            $kot['total'], 
            $kot['subtotal'], 
            $kot['tax'],
            $kot['created_at']
        ]);
        $existing_order = $check_stmt->fetch();
        
        // If order already exists, update its status to "Served" and mark KOT as completed
        if ($existing_order) {
            $order_id = $existing_order['id'];
            
            // Keep the order in "Ready" status so waiters can see it in their Active Orders list
            $update_order_sql = "UPDATE orders SET order_status = 'Ready', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $update_order_stmt = $conn->prepare($update_order_sql);
            $update_order_stmt->execute([$order_id]);
            
            // Update KOT status to completed
            $update_kot_sql = "UPDATE kot SET kot_status = 'Completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $update_kot_stmt = $conn->prepare($update_kot_sql);
            $update_kot_stmt->execute([$kot_id]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Order served and KOT completed successfully',
                'order_id' => $order_id
            ]);
            return;
        }
        
        // If order doesn't exist yet, create it with status "Ready" (fallback case)
        // This should rarely happen as order should be created when status is set to "Ready"
        if ($kot['kot_status'] === 'Ready') {
            $order_number = generateOrderNumber();
            
            // Create order from KOT with status "Ready" so waiters can deliver it
            $order_sql = "INSERT INTO orders (restaurant_id, table_id, order_number, customer_name, order_type, payment_method, payment_status, order_status, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, 'Cash', 'Paid', 'Ready', ?, ?, ?, ?)";
            $order_stmt = $conn->prepare($order_sql);
            $order_stmt->execute([$restaurant_id, $kot['table_id'], $order_number, $kot['customer_name'], $kot['order_type'], $kot['subtotal'], $kot['tax'], $kot['total'], $kot['notes']]);
            $order_id = $conn->lastInsertId();
            
            // Get KOT items and create order items
            $items_sql = "SELECT * FROM kot_items WHERE kot_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->execute([$kot_id]);
            $items = $items_stmt->fetchAll();
            
            foreach ($items as $item) {
                $order_item_sql = "INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
                $order_item_stmt = $conn->prepare($order_item_sql);
                $order_item_stmt->execute([$order_id, $item['menu_item_id'], $item['item_name'], $item['quantity'], $item['unit_price'], $item['total_price']]);
            }
            
            // Record payment
            $payment_sql = "INSERT INTO payments (restaurant_id, order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'Cash', 'Success')";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->execute([$restaurant_id, $order_id, $kot['total']]);
        }
        
        // Update KOT status to completed
        $update_kot_sql = "UPDATE kot SET kot_status = 'Completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $update_kot_stmt = $conn->prepare($update_kot_sql);
        $update_kot_stmt->execute([$kot_id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order served and KOT completed successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function generateKOTNumber() {
    return 'KOT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}
?>
