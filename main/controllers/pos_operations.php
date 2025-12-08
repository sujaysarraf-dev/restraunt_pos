<?php
// Register error handler to catch all errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false, 
            'message' => 'Fatal error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
});

// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

try {
    // Include secure session configuration
    if (!file_exists(__DIR__ . '/../config/session_config.php')) {
        throw new Exception('session_config.php not found');
    }
    require_once __DIR__ . '/../config/session_config.php';
    
    if (!function_exists('startSecureSession')) {
        throw new Exception('startSecureSession function not found');
    }
    startSecureSession();

    // Clean any output that might have been generated
    ob_clean();

    header('Content-Type: application/json; charset=UTF-8');

    // Include authorization configuration
    if (!file_exists(__DIR__ . '/../config/authorization_config.php')) {
        throw new Exception('authorization_config.php not found');
    }
    require_once __DIR__ . '/../config/authorization_config.php';

    // Require permission to manage orders
    if (!function_exists('requirePermission')) {
        throw new Exception('requirePermission function not found');
    }
    if (!defined('PERMISSION_MANAGE_ORDERS')) {
        throw new Exception('PERMISSION_MANAGE_ORDERS constant not defined');
    }
    requirePermission(PERMISSION_MANAGE_ORDERS);
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log("Initialization error in pos_operations.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Initialization error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    error_log("Fatal initialization error in pos_operations.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Fatal initialization error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found'], JSON_UNESCAPED_UNICODE);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];
$action = $_POST['action'] ?? '';

try {
    // Validate database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    $conn = $pdo;
    
    // Validate action
    if (empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Action is required'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    switch ($action) {
        case 'create_kot':
            handleCreateKOT($conn, $restaurant_id);
            break;
            
        case 'hold_order':
            handleHoldOrder($conn, $restaurant_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    error_log("PDO Error in pos_operations.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Exception $e) {
    error_log("Error in pos_operations.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    // Catch fatal errors (PHP 7+)
    error_log("Fatal Error in pos_operations.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}

function handleCreateKOT($conn, $restaurant_id) {
    $tableId = $_POST['tableId'] ?? null;
    $tableIdParam = $tableId ? $tableId : null;
    $orderType = $_POST['orderType'] ?? 'Dine-in';
    $customerName = $_POST['customerName'] ?? '';
    $customerPhone = $_POST['customerPhone'] ?? '';
    $customerEmail = $_POST['customerEmail'] ?? '';
    $customerAddress = $_POST['customerAddress'] ?? '';
    
    // Decode cart items with error handling
    $cartItemsJson = $_POST['cartItems'] ?? '[]';
    $cartItems = json_decode($cartItemsJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in handleCreateKOT: " . json_last_error_msg());
        error_log("Cart items JSON: " . substr($cartItemsJson, 0, 500));
        echo json_encode(['success' => false, 'message' => 'Invalid cart data: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = $_POST['paymentMethod'] ?? 'Cash';
    
    // Validation
    if (empty($cartItems) || !is_array($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty or invalid'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Include helper functions for generating unique numbers
    // Only include the helper functions, not the handler functions
    if (!file_exists(__DIR__ . '/kot_operations.php')) {
        throw new Exception('kot_operations.php file not found');
    }
    
    // Check if functions already exist (to avoid redeclaration)
    if (!function_exists('generateKOTNumber') || !function_exists('generateOrderNumber')) {
        // Extract only the helper functions we need
        // We'll define them here instead of requiring the whole file
        if (!function_exists('generateKOTNumber')) {
            function generateKOTNumber($conn, $restaurant_id) {
                $maxAttempts = 100;
                $attempt = 0;
                do {
                    $kotNumber = 'KOT-' . date('Ymd') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM kot WHERE kot_number = ? AND restaurant_id = ?");
                    $checkStmt->execute([$kotNumber, $restaurant_id]);
                    $exists = $checkStmt->fetchColumn() > 0;
                    $attempt++;
                    if ($attempt >= $maxAttempts) {
                        $kotNumber = 'KOT-' . date('YmdHis') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                        break;
                    }
                } while ($exists);
                return $kotNumber;
            }
        }
        
        if (!function_exists('generateOrderNumber')) {
            function generateOrderNumber($conn, $restaurant_id) {
                $maxAttempts = 100;
                $attempt = 0;
                do {
                    $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ? AND restaurant_id = ?");
                    $checkStmt->execute([$orderNumber, $restaurant_id]);
                    $exists = $checkStmt->fetchColumn() > 0;
                    $attempt++;
                    if ($attempt >= $maxAttempts) {
                        $orderNumber = 'ORD-' . date('YmdHis') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                        break;
                    }
                } while ($exists);
                return $orderNumber;
            }
        }
    }
    
    // Generate unique KOT number with collision check
    $kotNumber = generateKOTNumber($conn, $restaurant_id);
    // Generate unique Order number with collision check
    $orderNumber = generateOrderNumber($conn, $restaurant_id);
    
    // Ensure customer detail columns exist in orders table
    try {
        $checkCols = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_phone'");
        if ($checkCols->rowCount() == 0) {
            $conn->exec("ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(20) NULL AFTER customer_name");
            $conn->exec("ALTER TABLE orders ADD COLUMN customer_email VARCHAR(100) NULL AFTER customer_phone");
            $conn->exec("ALTER TABLE orders ADD COLUMN customer_address TEXT NULL AFTER customer_email");
        }
    } catch (PDOException $e) {
        // Columns might already exist or error occurred, continue anyway
        error_log("Error checking/adding customer columns: " . $e->getMessage());
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // For Takeaway, skip creating KOT; otherwise create KOT
        $kotId = null;
        $orderId = null;
        
        if ($orderType !== 'Takeaway') {
            // For Dine-in orders: Only create KOT, order will be created when KOT is marked as Ready
            // Check if customer columns exist in kot table
            $checkKotCols = $conn->query("SHOW COLUMNS FROM kot LIKE 'customer_phone'");
            if ($checkKotCols->rowCount() > 0) {
                $stmt = $conn->prepare("INSERT INTO kot (restaurant_id, kot_number, table_id, order_type, customer_name, customer_phone, customer_email, customer_address, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$restaurant_id, $kotNumber, $tableIdParam, $orderType, $customerName, $customerPhone, $customerEmail, $customerAddress, $subtotal, $tax, $total, $notes]);
            } else {
                $stmt = $conn->prepare("INSERT INTO kot (restaurant_id, kot_number, table_id, order_type, customer_name, subtotal, tax, total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$restaurant_id, $kotNumber, $tableIdParam, $orderType, $customerName, $subtotal, $tax, $total, $notes]);
            }
            $kotId = $conn->lastInsertId();
            $itemStmt = $conn->prepare("INSERT INTO kot_items (kot_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cartItems as $item) {
                // Validate item structure
                if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                    error_log("Invalid cart item structure: " . json_encode($item));
                    throw new Exception("Invalid cart item structure. Missing required fields.");
                }
                
                $itemStmt->execute([
                    $kotId,
                    (int)$item['id'],
                    (string)$item['name'],
                    (int)$item['quantity'],
                    (float)$item['price'],
                    (float)($item['price'] * $item['quantity'])
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
                $upd = $conn->prepare("UPDATE orders SET order_number = ?, customer_name = ?, order_type = ?, payment_method = ?, payment_status = 'Paid', order_status = 'Preparing', subtotal = ?, tax = ?, total = ?, notes = ?, customer_phone = ?, customer_email = ?, customer_address = ? WHERE id = ?");
                $upd->execute([$orderNumber, $customerName, $orderType, $paymentMethod, $subtotal, $tax, $total, $notes, $customerPhone, $customerEmail, $customerAddress, $orderId]);
                // Replace items
                $conn->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
                $orderItemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    // Validate item structure
                    if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                        error_log("Invalid cart item structure: " . json_encode($item));
                        throw new Exception("Invalid cart item structure. Missing required fields.");
                    }
                    
                    $orderItemStmt->execute([
                        $orderId,
                        (int)$item['id'],
                        (string)$item['name'],
                        (int)$item['quantity'],
                        (float)$item['price'],
                        (float)($item['price'] * $item['quantity'])
                    ]);
                }
            } else {
                // Create a new order
                $orderStmt = $conn->prepare("INSERT INTO orders (restaurant_id, table_id, order_number, customer_name, order_type, payment_method, payment_status, order_status, subtotal, tax, total, notes, customer_phone, customer_email, customer_address) VALUES (?, ?, ?, ?, ?, ?, 'Paid', 'Preparing', ?, ?, ?, ?, ?, ?, ?)");
                $orderStmt->execute([$restaurant_id, $tableIdParam, $orderNumber, $customerName, $orderType, $paymentMethod, $subtotal, $tax, $total, $notes, $customerPhone, $customerEmail, $customerAddress]);
                $orderId = $conn->lastInsertId();
                $orderItemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    // Validate item structure
                    if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                        error_log("Invalid cart item structure: " . json_encode($item));
                        throw new Exception("Invalid cart item structure. Missing required fields.");
                    }
                    
                    $orderItemStmt->execute([
                        $orderId,
                        (int)$item['id'],
                        (string)$item['name'],
                        (int)$item['quantity'],
                        (float)$item['price'],
                        (float)($item['price'] * $item['quantity'])
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
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Error in handleCreateKOT: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        if (ob_get_level()) {
            ob_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Error creating KOT: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
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
        echo json_encode(['success' => false, 'message' => 'Cart is empty'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Generate unique order number for held order (using same function but with HOLD prefix)
    // For held orders, we'll use a similar pattern but check against orders table
    $maxAttempts = 100;
    $attempt = 0;
    do {
        $orderNumber = 'HOLD-' . date('Ymd') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ? AND restaurant_id = ?");
        $checkStmt->execute([$orderNumber, $restaurant_id]);
        $exists = $checkStmt->fetchColumn() > 0;
        $attempt++;
        if ($attempt >= $maxAttempts) {
            $orderNumber = 'HOLD-' . date('YmdHis') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            break;
        }
    } while ($exists);
    
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
            // Validate item structure
            if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                error_log("Invalid cart item structure: " . json_encode($item));
                throw new Exception("Invalid cart item structure. Missing required fields.");
            }
            
            $itemStmt->execute([
                $orderId,
                (int)$item['id'],
                (string)$item['name'],
                (int)$item['quantity'],
                (float)$item['price'],
                (float)($item['price'] * $item['quantity'])
            ]);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Order held successfully',
            'order_number' => $orderNumber,
            'order_id' => $orderId
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Error in handleHoldOrder: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        if (ob_get_level()) {
            ob_clean();
        }
        echo json_encode(['success' => false, 'message' => 'Error holding order: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

