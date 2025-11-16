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

// Check if user is logged in (admin or staff)
if (!isset($_SESSION['restaurant_id']) && (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id']))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];
$action = $_POST['action'] ?? '';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'create_kot':
            handleCreateKOT($conn, $restaurant_id);
            break;
            
        case 'hold_order':
            handleHoldOrder($conn, $restaurant_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("PDO Error in pos_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
    exit();
} catch (Exception $e) {
    error_log("Error in pos_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

function handleCreateKOT($conn, $restaurant_id) {
    $tableId = $_POST['tableId'] ?? null;
    $tableIdParam = $tableId ? $tableId : null;
    $orderType = $_POST['orderType'] ?? 'Dine-in';
    $customerName = $_POST['customerName'] ?? '';
    $cartItems = json_decode($_POST['cartItems'] ?? '[]', true);
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = $_POST['paymentMethod'] ?? 'Cash';
    
    // Validation
    if (empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        return;
    }
    
    // Generate KOT number
    $kotNumber = 'KOT-' . date('Ymd') . '-' . rand(1000, 9999);
    // Generate Order number
    $orderNumber = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // For Takeaway, skip creating KOT; otherwise create KOT
        $kotId = null;
        $orderId = null;
        
        if ($orderType !== 'Takeaway') {
            // For Dine-in orders: Only create KOT, order will be created when KOT is marked as Ready
            $stmt = $conn->prepare("INSERT INTO kot (restaurant_id, kot_number, table_id, order_type, customer_name, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$restaurant_id, $kotNumber, $tableIdParam, $orderType, $customerName, $subtotal, $tax, $total, $notes]);
            $kotId = $conn->lastInsertId();
            $itemStmt = $conn->prepare("INSERT INTO kot_items (kot_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    $kotId,
                    $item['id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                ]);
            }
            
            // Record payment for KOT (order will be created later when KOT is ready)
            if (!empty($paymentMethod)) {
                // Store payment info in a temporary way or link to KOT
                // Payment will be recorded when order is created from KOT
            }
        } else {
            // For Takeaway orders: Create order immediately (no KOT needed)
            $findHeld = $conn->prepare("SELECT id FROM orders WHERE restaurant_id = ? AND payment_status = 'Pending' AND order_status = 'Pending' AND ((table_id IS NULL AND ? IS NULL) OR table_id = ?) ORDER BY id DESC LIMIT 1");
            $tidNullable = $tableId ? $tableId : null;
            $findHeld->execute([$restaurant_id, $tidNullable, $tableId]);
            $row = $findHeld->fetch();
            if ($row && isset($row['id'])) {
                // Update the held order into a live order
                $orderId = (int)$row['id'];
                $upd = $conn->prepare("UPDATE orders SET order_number = ?, order_type = ?, payment_method = ?, payment_status = 'Paid', order_status = 'Preparing', subtotal = ?, tax = ?, total = ?, notes = ? WHERE id = ?");
                $upd->execute([$orderNumber, $orderType, $paymentMethod, $subtotal, $tax, $total, $notes, $orderId]);
                // Replace items
                $conn->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
                $orderItemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    $orderItemStmt->execute([
                        $orderId,
                        $item['id'],
                        $item['name'],
                        $item['quantity'],
                        $item['price'],
                        $item['price'] * $item['quantity']
                    ]);
                }
            } else {
                // Create a new order
                $orderStmt = $conn->prepare("INSERT INTO orders (restaurant_id, table_id, order_number, order_type, payment_method, payment_status, order_status, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, 'Paid', 'Preparing', ?, ?, ?, ?)");
                $orderStmt->execute([$restaurant_id, $tableIdParam, $orderNumber, $orderType, $paymentMethod, $subtotal, $tax, $total, $notes]);
                $orderId = $conn->lastInsertId();
                $orderItemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    $orderItemStmt->execute([
                        $orderId,
                        $item['id'],
                        $item['name'],
                        $item['quantity'],
                        $item['price'],
                        $item['price'] * $item['quantity']
                    ]);
                }
            }
            
            // Record payment for Takeaway orders
            if (!empty($paymentMethod) && $orderId) {
                $payment_sql = "INSERT INTO payments (restaurant_id, order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, 'Success')";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([$restaurant_id, $orderId, $total, $paymentMethod]);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $resp = [
            'success' => true
        ];
        if ($kotId) {
            $resp['message'] = 'KOT created successfully. Order will be created when KOT is marked as Ready.';
            $resp['kot_number'] = $kotNumber;
            $resp['kot_id'] = $kotId;
        } else {
            $resp['message'] = 'Order created successfully';
            $resp['order_number'] = $orderNumber;
            $resp['order_id'] = $orderId;
        }
        echo json_encode($resp);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error creating KOT: ' . $e->getMessage()]);
    }
}

function handleHoldOrder($conn, $restaurant_id) {
    $tableId = $_POST['tableId'] ?? null;
    $cartItems = json_decode($_POST['cartItems'] ?? '[]', true);
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);
    
    // Validation
    if (empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        return;
    }
    
    // Generate order number
    $orderNumber = 'HOLD-' . date('Ymd') . '-' . rand(1000, 9999);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert order with "Pending" payment status
        $orderType = empty($tableId) ? 'Takeaway' : 'Dine-in';
        $stmt = $conn->prepare("INSERT INTO orders (restaurant_id, table_id, order_number, order_type, payment_method, payment_status, order_status, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, 'Cash', 'Pending', 'Pending', ?, ?, ?, 'Order on hold')");
        $stmt->execute([$restaurant_id, $tableId, $orderNumber, $orderType, $subtotal, $tax, $total]);
        
        $orderId = $conn->lastInsertId();
        
        // Insert order items
        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($cartItems as $item) {
            $itemStmt->execute([
                $orderId,
                $item['id'],
                $item['name'],
                $item['quantity'],
                $item['price'],
                $item['price'] * $item['quantity']
            ]);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Order held successfully',
            'order_number' => $orderNumber,
            'order_id' => $orderId
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error holding order: ' . $e->getMessage()]);
    }
}

