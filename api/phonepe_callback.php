<?php
session_start();
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
}

// PhonePe Test API Configuration
define('PHONEPE_SALT_KEY', '099eb0cd-02cf-4e2a-8aca-3e6c6aff8719'); // Test Salt Key
define('PHONEPE_SALT_INDEX', '1'); // Test Salt Index

try {
    $conn = getConnection();
    
    // Get callback data
    $callback_data = file_get_contents('php://input');
    $decoded_data = json_decode($callback_data, true);
    
    if (!$decoded_data || !isset($decoded_data['response'])) {
        error_log('Invalid callback data: ' . $callback_data);
        http_response_code(400);
        exit();
    }
    
    $response = base64_decode($decoded_data['response']);
    $response_data = json_decode($response, true);
    
    if (!$response_data) {
        error_log('Invalid response data');
        http_response_code(400);
        exit();
    }
    
    $merchant_transaction_id = $response_data['merchantTransactionId'] ?? null;
    $transaction_id = $response_data['transactionId'] ?? null;
    $code = $response_data['code'] ?? null;
    $state = $response_data['state'] ?? null;
    
    if (!$merchant_transaction_id) {
        error_log('Missing merchant transaction ID');
        http_response_code(400);
        exit();
    }
    
    // Verify callback (optional - for security)
    // You can verify the X-VERIFY header here
    
    // Update payment record
    $stmt = $conn->prepare("SELECT id, user_id, restaurant_id, amount, subscription_type FROM subscription_payments WHERE transaction_id = ? LIMIT 1");
    $stmt->execute([$merchant_transaction_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        error_log('Payment record not found: ' . $merchant_transaction_id);
        http_response_code(404);
        exit();
    }
    
    // Update payment status
    $payment_status = ($code === 'PAYMENT_SUCCESS' && $state === 'COMPLETED') ? 'success' : 'failed';
    $phonepe_transaction_id = $transaction_id;
    
    $update_stmt = $conn->prepare("UPDATE subscription_payments SET phonepe_transaction_id = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$phonepe_transaction_id, $payment_status, $payment['id']]);
    
    // If payment successful, activate subscription
    if ($payment_status === 'success') {
        // Update user subscription
        $renewal_date = date('Y-m-d', strtotime('+30 days')); // 30 days subscription
        $user_update_stmt = $conn->prepare("UPDATE users SET subscription_status = 'active', renewal_date = ?, is_active = 1 WHERE id = ?");
        $user_update_stmt->execute([$renewal_date, $payment['user_id']]);
        
        error_log('Subscription activated for user: ' . $payment['user_id']);
    }
    
    // Return success response to PhonePe
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

