<?php
// Clean any output buffers and prevent any output before JSON
if (ob_get_level()) {
    ob_clean();
}
ob_start();

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    http_response_code(200);
    ob_end_clean();
    exit();
}

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession(true); // Skip timeout validation for public customer website

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
}

// Get JSON data
$input = json_decode(file_get_contents('php://input'), true);

$customer_name = $input['customer_name'] ?? '';
$customer_phone = $input['customer_phone'] ?? '';
$customer_email = $input['customer_email'] ?? '';
$customer_address = $input['customer_address'] ?? '';
$items = $input['items'] ?? [];
$total = $input['total'] ?? 0;
$payment_method = $input['payment_method'] ?? 'Cash';

// Validate required fields
if (empty($customer_name) || empty($customer_phone)) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Name and phone are required'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (empty($items)) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Cart is empty'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $conn = getConnection();
    
    // Resolve restaurant ID: session > query param > default
    $restaurant_id = $_SESSION['restaurant_id'] ?? ($_GET['restaurant_id'] ?? 'RES001');
    
    // Generate unique order number function (extracted to avoid authorization requirement)
    if (!function_exists('generateOrderNumber')) {
        function generateOrderNumber($conn = null, $restaurant_id = null) {
            global $pdo;
            
            // Use provided connection or global
            $db = $conn ?? $pdo;
            
            // If no connection or restaurant_id, generate without check (fallback)
            if (!$db || !$restaurant_id) {
                return 'ORD-' . date('Ymd') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Generate unique number with collision check
            $maxAttempts = 100;
            $attempt = 0;
            
            do {
                $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Check if number already exists
                try {
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ? AND restaurant_id = ?");
                    $checkStmt->execute([$orderNumber, $restaurant_id]);
                    $exists = $checkStmt->fetchColumn() > 0;
                } catch (PDOException $e) {
                    // If query fails, return generated number (fallback)
                    error_log("Error checking Order number uniqueness: " . $e->getMessage());
                    return $orderNumber;
                }
                
                $attempt++;
                
                // Safety check to prevent infinite loop
                if ($attempt >= $maxAttempts) {
                    // Add timestamp to make it unique if we can't find a unique number
                    $orderNumber = 'ORD-' . date('YmdHis') . '-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                    break;
                }
            } while ($exists);
            
            return $orderNumber;
        }
    }
    
    // Generate unique order number with collision check
    $order_number = generateOrderNumber($conn, $restaurant_id);
    
    // Check if customer exists, if not create
    $customerStmt = $conn->prepare("SELECT id FROM customers WHERE restaurant_id = ? AND phone = ?");
    $customerStmt->execute([$restaurant_id, $customer_phone]);
    $customer = $customerStmt->fetch();
    
    if ($customer) {
        $customer_id = $customer['id'];
        // Update visit count and total spent
        $updateStmt = $conn->prepare("UPDATE customers SET total_visits = total_visits + 1, last_visit_date = CURDATE(), total_spent = total_spent + ? WHERE id = ?");
        $updateStmt->execute([$total, $customer_id]);
    } else {
        // Create new customer
        $insertStmt = $conn->prepare("INSERT INTO customers (restaurant_id, customer_name, phone, email) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$restaurant_id, $customer_name, $customer_phone, $customer_email]);
        $customer_id = $conn->lastInsertId();
    }
    
    // Create order
    $orderStmt = $conn->prepare("INSERT INTO orders (restaurant_id, order_number, customer_name, order_type, payment_method, payment_status, order_status, subtotal, tax, total) VALUES (?, ?, ?, 'Dine-in', ?, 'Paid', 'Pending', ?, 0, ?)");
    $orderStmt->execute([$restaurant_id, $order_number, $customer_name, $payment_method, $total, $total]);
    $order_id = $conn->lastInsertId();
    
    // Insert order items
    $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($items as $item) {
        $itemStmt->execute([
            $order_id,
            $item['id'],
            $item['name'],
            $item['quantity'],
            $item['price'],
            $item['price'] * $item['quantity']
        ]);
    }
    
    // Record payment (if payments table exists)
    try {
        $paymentStmt = $conn->prepare("INSERT INTO payments (restaurant_id, order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, 'Success')");
        $paymentStmt->execute([$restaurant_id, $order_id, $total, $payment_method]);
    } catch (Exception $e) {
        // Payments table might not exist, skip it
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'message' => 'Order placed successfully'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Order processing error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

