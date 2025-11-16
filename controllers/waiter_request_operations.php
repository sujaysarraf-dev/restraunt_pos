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
        case 'add':
            handleAddRequest($conn, $restaurant_id);
            break;
            
        case 'mark_attended':
            handleMarkAttended($conn, $restaurant_id);
            break;
            
        case 'delete':
            handleDeleteRequest($conn, $restaurant_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("PDO Error in waiter_request_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
    exit();
} catch (Exception $e) {
    error_log("Error in waiter_request_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}

function handleAddRequest($conn, $restaurant_id) {
    $tableId = $_POST['tableId'] ?? '';
    $requestType = $_POST['requestType'] ?? 'General';
    $notes = $_POST['notes'] ?? '';
    
    // Validation
    if (empty($tableId)) {
        echo json_encode(['success' => false, 'message' => 'Table ID is required']);
        return;
    }
    
    // Check if table belongs to restaurant
    $checkStmt = $conn->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$tableId, $restaurant_id]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid table']);
        return;
    }
    
    // Insert waiter request
    $stmt = $conn->prepare("INSERT INTO waiter_requests (restaurant_id, table_id, request_type, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$restaurant_id, $tableId, $requestType, $notes]);
    
    echo json_encode(['success' => true, 'message' => 'Request added successfully']);
}

function handleMarkAttended($conn, $restaurant_id) {
    $requestId = $_POST['requestId'] ?? '';
    
    if (empty($requestId)) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        return;
    }
    
    // Check if request exists and belongs to restaurant
    $checkStmt = $conn->prepare("SELECT id FROM waiter_requests WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$requestId, $restaurant_id]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        return;
    }
    
    // Update request status
    $stmt = $conn->prepare("UPDATE waiter_requests SET status = 'Attended', attended_at = NOW() WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$requestId, $restaurant_id]);
    
    echo json_encode(['success' => true, 'message' => 'Request marked as attended']);
}

function handleDeleteRequest($conn, $restaurant_id) {
    $requestId = $_POST['requestId'] ?? '';
    
    if (empty($requestId)) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        return;
    }
    
    // Check if request exists and belongs to restaurant
    $checkStmt = $conn->prepare("SELECT id FROM waiter_requests WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$requestId, $restaurant_id]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        return;
    }
    
    // Delete request
    $stmt = $conn->prepare("DELETE FROM waiter_requests WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$requestId, $restaurant_id]);
    
    echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
}

