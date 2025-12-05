<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

// Require admin permission to manage staff
requireAdmin();

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];
$action = $_POST['action'] ?? '';

try {
    $conn = $pdo;
    
    switch ($action) {
        case 'add':
            handleAddStaff($conn, $restaurant_id);
            break;
            
        case 'update':
            handleUpdateStaff($conn, $restaurant_id);
            break;
            
        case 'delete':
            handleDeleteStaff($conn, $restaurant_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("PDO Error in staff_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
    exit();
} catch (Exception $e) {
    error_log("Error in staff_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

function handleAddStaff($conn, $restaurant_id) {
    $memberName = trim($_POST['memberName'] ?? '');
    $email = trim($_POST['memberEmail'] ?? '');
    $countryCode = trim($_POST['countryCode'] ?? '');
    $phone = trim($_POST['restaurantPhone'] ?? '');
    $password = $_POST['memberPassword'] ?? '';
    $role = trim($_POST['memberRole'] ?? 'Waiter');
    
    // Validation
    if (empty($memberName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member name is required']);
        return;
    }
    
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email address is required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }
    
    if (empty($phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Phone number is required']);
        return;
    }
    
    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        return;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }
    
    // Combine country code with phone
    $fullPhone = $countryCode . '-' . $phone;
    
    // Check if staff with this email already exists
    $checkStmt = $conn->prepare("SELECT id FROM staff WHERE restaurant_id = ? AND email = ?");
    $checkStmt->execute([$restaurant_id, $email]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Staff member with this email already exists']);
        return;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert staff
    $stmt = $conn->prepare("INSERT INTO staff (restaurant_id, member_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$restaurant_id, $memberName, $email, $fullPhone, $hashedPassword, $role]);
    
    echo json_encode(['success' => true, 'message' => 'Staff member added successfully']);
}

function handleUpdateStaff($conn, $restaurant_id) {
    $staffId = $_POST['staffId'] ?? '';
    $memberName = trim($_POST['memberName'] ?? '');
    $email = trim($_POST['memberEmail'] ?? '');
    $countryCode = trim($_POST['countryCode'] ?? '');
    $phone = trim($_POST['restaurantPhone'] ?? '');
    $password = $_POST['memberPassword'] ?? '';
    $role = trim($_POST['memberRole'] ?? 'Waiter');
    
    // Validation
    if (empty($memberName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member name is required']);
        return;
    }
    
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email address is required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }
    
    if (empty($phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Phone number is required']);
        return;
    }
    
    // Check if staff exists and belongs to restaurant
    $checkStmt = $conn->prepare("SELECT id FROM staff WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$staffId, $restaurant_id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Staff member not found']);
        return;
    }
    
    // Check if email already exists for another staff member
    $emailCheckStmt = $conn->prepare("SELECT id FROM staff WHERE restaurant_id = ? AND email = ? AND id != ?");
    $emailCheckStmt->execute([$restaurant_id, $email, $staffId]);
    
    if ($emailCheckStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists for another staff member']);
        return;
    }
    
    // Combine country code with phone
    $fullPhone = $countryCode . '-' . $phone;
    
    // Update staff
    if (!empty($password)) {
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            return;
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE staff SET member_name = ?, email = ?, phone = ?, password = ?, role = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$memberName, $email, $fullPhone, $hashedPassword, $role, $staffId, $restaurant_id]);
    } else {
        $stmt = $conn->prepare("UPDATE staff SET member_name = ?, email = ?, phone = ?, role = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$memberName, $email, $fullPhone, $role, $staffId, $restaurant_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Staff member updated successfully']);
}

function handleDeleteStaff($conn, $restaurant_id) {
    $staffId = $_POST['staffId'] ?? '';
    
    if (empty($staffId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
        return;
    }
    
    // Check if staff exists and belongs to restaurant
    $checkStmt = $conn->prepare("SELECT id FROM staff WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$staffId, $restaurant_id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Staff member not found']);
        return;
    }
    
    // Delete staff
    $stmt = $conn->prepare("DELETE FROM staff WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$staffId, $restaurant_id]);
    
    echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
}
?>

