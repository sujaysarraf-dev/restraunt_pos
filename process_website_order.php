<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connection.php';

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
    echo json_encode([
        'success' => false,
        'message' => 'Name and phone are required'
    ]);
    exit();
}

if (empty($items)) {
    echo json_encode([
        'success' => false,
        'message' => 'Cart is empty'
    ]);
    exit();
}

try {
    $conn = getConnection();
    
    // Resolve restaurant ID: session > query param > default
    $restaurant_id = $_SESSION['restaurant_id'] ?? ($_GET['restaurant_id'] ?? 'RES001');
    
    // Generate order number
    $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
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
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'message' => 'Order placed successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

