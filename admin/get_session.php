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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['restaurant_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
        exit();
    }
    
    // Load subscription info from DB
    if (file_exists(__DIR__ . '/../db_connection.php')) {
        require_once __DIR__ . '/../db_connection.php';
    } else {
        throw new Exception('Database connection file not found');
    }
    
    // Get connection
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } else {
        $conn = getConnection();
    }
    // Try to get all fields, handle missing columns gracefully
    try {
        $stmt = $conn->prepare("SELECT subscription_status, trial_end_date, renewal_date, created_at, email, role, phone, address, currency_symbol, timezone, restaurant_logo FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // If some columns don't exist, try without them
        if (strpos($e->getMessage(), 'currency_symbol') !== false || strpos($e->getMessage(), 'timezone') !== false || strpos($e->getMessage(), 'phone') !== false || strpos($e->getMessage(), 'address') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            try {
                $stmt = $conn->prepare("SELECT subscription_status, trial_end_date, renewal_date, created_at, email, role, phone, address FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e2) {
                // If phone/address columns don't exist, try without them
                if (strpos($e2->getMessage(), 'phone') !== false || strpos($e2->getMessage(), 'address') !== false || strpos($e2->getMessage(), 'Unknown column') !== false) {
                    try {
                        $stmt = $conn->prepare("SELECT subscription_status, trial_end_date, renewal_date, created_at, email, role FROM users WHERE id = :id LIMIT 1");
                        $stmt->execute([':id' => $_SESSION['user_id']]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    } catch (PDOException $e3) {
                        // If email/role columns don't exist either
                        if (strpos($e3->getMessage(), 'email') !== false || strpos($e3->getMessage(), 'role') !== false || strpos($e3->getMessage(), 'Unknown column') !== false) {
                            $stmt = $conn->prepare("SELECT subscription_status, trial_end_date, renewal_date, created_at FROM users WHERE id = :id LIMIT 1");
                            $stmt->execute([':id' => $_SESSION['user_id']]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        } else {
                            throw $e3;
                        }
                    }
                } else {
                    throw $e2;
                }
            }
        } else {
            throw $e;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'restaurant_id' => $_SESSION['restaurant_id'],
            'restaurant_name' => $_SESSION['restaurant_name'],
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'currency_symbol' => $row['currency_symbol'] ?? null,
            'timezone' => $row['timezone'] ?? null,
            'restaurant_logo' => $row['restaurant_logo'] ?? null,
            'role' => $row['role'] ?? 'Administrator',
            'subscription_status' => $row['subscription_status'] ?? null,
            'trial_end_date' => $row['trial_end_date'] ?? null,
            'renewal_date' => $row['renewal_date'] ?? null,
            'created_at' => $row['created_at'] ?? null
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}
?>
