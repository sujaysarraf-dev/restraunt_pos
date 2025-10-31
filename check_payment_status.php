<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'db_connection.php';

try {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    $transaction_id = $_GET['transaction_id'] ?? null;
    
    if (!$transaction_id) {
        // Get latest payment for user
        $stmt = $conn->prepare("SELECT * FROM subscription_payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM subscription_payments WHERE user_id = ? AND transaction_id = ? LIMIT 1");
        $stmt->execute([$user_id, $transaction_id]);
    }
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode([
            'success' => false,
            'message' => 'Payment record not found'
        ]);
        exit();
    }
    
    // Get user subscription status
    $user_stmt = $conn->prepare("SELECT subscription_status, renewal_date, is_active FROM users WHERE id = ? LIMIT 1");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'payment' => [
            'transaction_id' => $payment['transaction_id'],
            'amount' => $payment['amount'],
            'payment_status' => $payment['payment_status'],
            'created_at' => $payment['created_at']
        ],
        'subscription' => [
            'status' => $user['subscription_status'] ?? null,
            'renewal_date' => $user['renewal_date'] ?? null,
            'is_active' => (bool)($user['is_active'] ?? 0)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

