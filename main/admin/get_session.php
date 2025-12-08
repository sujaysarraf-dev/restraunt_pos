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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Check if user is logged in and session is valid
    // Support both admin users (user_id) and staff members (staff_id)
    $isAdmin = isset($_SESSION['user_id']);
    $isStaff = isset($_SESSION['staff_id']);
    
    if (!isSessionValid() || (!$isAdmin && !$isStaff) || !isset($_SESSION['username']) || !isset($_SESSION['restaurant_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please login again.'
        ]);
        exit();
    }
    
    // Load subscription info from DB
    if (file_exists(__DIR__ . '/../db_connection.php')) {
        require_once __DIR__ . '/../db_connection.php';
    } else {
        throw new Exception('Database connection file not found');
    }
    
    // Get connection using getConnection() for lazy connection support
    if (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback to $pdo if getConnection() doesn't exist (backward compatibility)
        global $pdo;
        $conn = $pdo ?? null;
        if (!$conn) {
            throw new Exception('Database connection not available');
        }
    }
    
    // Initialize row array
    $row = [];
    
    // For admin users, get data from users table
    if ($isAdmin) {
        // Try to get all fields, handle missing columns gracefully
        try {
            $stmt = $conn->prepare("SELECT id, subscription_status, trial_end_date, renewal_date, created_at, email, role, phone, address, currency_symbol, timezone, restaurant_logo, business_qr_code_path FROM users WHERE id = :id LIMIT 1");
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
    } else if ($isStaff) {
        // For staff members, get data from staff table
        try {
            $stmt = $conn->prepare("SELECT id, member_name, email, role, restaurant_id FROM staff WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['staff_id']]);
            $staffRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
            // Get restaurant info for staff
            if (!empty($staffRow['restaurant_id'])) {
                $restStmt = $conn->prepare("SELECT restaurant_name, currency_symbol, restaurant_logo, business_qr_code_path FROM users WHERE restaurant_id = :restaurant_id LIMIT 1");
                $restStmt->execute([':restaurant_id' => $staffRow['restaurant_id']]);
                $restRow = $restStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                
                $row = array_merge($staffRow, $restRow);
            } else {
                $row = $staffRow;
            }
        } catch (PDOException $e) {
            // If staff table query fails, use session data only
            error_log("PDO Error fetching staff data: " . $e->getMessage());
            $row = [];
        }
    }
    
    // Check and update trial expiry automatically
    if ($isAdmin && !empty($row['subscription_status']) && !empty($row['trial_end_date'])) {
        $subscriptionStatus = $row['subscription_status'];
        $trialEndDate = $row['trial_end_date'];
        $today = date('Y-m-d');
        
        // If trial status and trial_end_date has passed, automatically expire
        if ($subscriptionStatus === 'trial' && $trialEndDate < $today) {
            try {
                $updateStmt = $conn->prepare("UPDATE users SET subscription_status = 'expired', is_active = 0 WHERE id = :id");
                $updateStmt->execute([':id' => $_SESSION['user_id']]);
                // Update the row data for response
                $row['subscription_status'] = 'expired';
                $row['is_active'] = 0;
            } catch (PDOException $e) {
                error_log("Error updating trial expiry: " . $e->getMessage());
            }
        }
    }
    
    // Get remaining session time
    $remainingTime = getRemainingSessionTime();
    $remainingMinutes = floor($remainingTime / 60);
    $remainingSeconds = $remainingTime % 60;
    
    // Build response data
    $responseData = [
        'id' => $row['id'] ?? ($_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? null),
        'user_id' => $_SESSION['user_id'] ?? null,
        'staff_id' => $_SESSION['staff_id'] ?? null,
        'username' => $_SESSION['username'] ?? ($row['member_name'] ?? null),
        'restaurant_id' => $_SESSION['restaurant_id'],
        'restaurant_name' => $_SESSION['restaurant_name'] ?? ($row['restaurant_name'] ?? 'Restaurant'),
        'email' => $row['email'] ?? $_SESSION['email'] ?? null,
        'phone' => $row['phone'] ?? null,
        'address' => $row['address'] ?? null,
        'currency_symbol' => isset($row['currency_symbol']) ? (function() use ($row) {
            require_once __DIR__ . '/../config/unicode_utils.php';
            return fixCurrencySymbol($row['currency_symbol']);
        })() : ($_SESSION['currency_symbol'] ?? null),
        'timezone' => $row['timezone'] ?? null,
        'restaurant_logo' => $row['restaurant_logo'] ?? null,
        'business_qr_code_path' => $row['business_qr_code_path'] ?? null,
        'role' => $row['role'] ?? $_SESSION['role'] ?? 'Administrator',
        'user_type' => $_SESSION['user_type'] ?? ($isAdmin ? 'admin' : 'staff'),
        'subscription_status' => $row['subscription_status'] ?? null,
        'trial_end_date' => $row['trial_end_date'] ?? null,
        'renewal_date' => $row['renewal_date'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'is_active' => $row['is_active'] ?? 1,
        'session_remaining_time' => $remainingTime,
        'session_remaining_minutes' => $remainingMinutes,
        'session_remaining_seconds' => $remainingSeconds
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $responseData
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
