<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in (admin or staff)
if (!isset($_SESSION['restaurant_id']) && (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id']))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
}

try {
    $conn = getConnection();
    $restaurant_id = $_SESSION['restaurant_id'] ?? null;
    
    // If restaurant_id not in session, get from staff
    if (!$restaurant_id && isset($_SESSION['staff_id'])) {
        $staff_sql = "SELECT restaurant_id FROM staff WHERE id = ?";
        $staff_stmt = $conn->prepare($staff_sql);
        $staff_stmt->execute([$_SESSION['staff_id']]);
        $staff = $staff_stmt->fetch();
        if ($staff) {
            $restaurant_id = $staff['restaurant_id'];
        }
    }
    
    if (!$restaurant_id) {
        echo json_encode(['success' => false, 'message' => 'Restaurant ID not found']);
        exit();
    }
    
    // Get order ID and new status
    $orderId = $_POST['orderId'] ?? null;
    $status = $_POST['status'] ?? null;
    
    if (!$orderId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    // Validate status
    $validStatuses = ['Pending', 'Preparing', 'Ready', 'Served', 'Completed', 'Cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Check if order exists and belongs to restaurant
    $check_sql = "SELECT id FROM orders WHERE id = ? AND restaurant_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$orderId, $restaurant_id]);
    $order = $check_stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }
    
    // Update order status
    $update_sql = "UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND restaurant_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([$status, $orderId, $restaurant_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating order status: ' . $e->getMessage()
    ]);
}
?>
