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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Require permission to manage reservations
requirePermission(PERMISSION_MANAGE_RESERVATIONS);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

// Include validation and rate limiting
if (file_exists(__DIR__ . '/../config/validation.php')) {
    require_once __DIR__ . '/../config/validation.php';
}
if (file_exists(__DIR__ . '/../config/rate_limit.php')) {
    require_once __DIR__ . '/../config/rate_limit.php';
    // Apply rate limiting: 30 requests per minute
    applyRateLimit(30, 60);
}

// Check request size (max 5MB for POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sizeCheck = checkRequestSize(5);
    if (!$sizeCheck['valid']) {
        http_response_code(413);
        echo json_encode(['success' => false, 'message' => $sizeCheck['message']]);
        exit();
    }
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback connection
        $host = 'localhost';
        $dbname = 'restro2';
        $username = 'root';
        $password = '';
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    
    // Get the action and data from POST
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $reservationId = isset($_POST['reservationId']) ? (int)$_POST['reservationId'] : 0;
    $reservationDate = isset($_POST['reservationDate']) ? trim($_POST['reservationDate']) : '';
    $timeSlot = isset($_POST['timeSlot']) ? trim($_POST['timeSlot']) : '';
    $noOfGuests = isset($_POST['noOfGuests']) ? (int)$_POST['noOfGuests'] : 1;
    $mealType = isset($_POST['mealType']) ? trim($_POST['mealType']) : 'Lunch';
    $customerName = isset($_POST['customerName']) ? trim($_POST['customerName']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $specialRequest = isset($_POST['specialRequest']) ? trim($_POST['specialRequest']) : '';
    $tableId = isset($_POST['selectTable']) ? (int)$_POST['selectTable'] : NULL;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Pending';
    
    // Validate action
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    // Validate required fields for add and update actions using validation functions
    if (in_array($action, ['add', 'update'])) {
        // Validate date
        $dateValidation = validateDate($reservationDate, 'Y-m-d');
        if (!$dateValidation['valid']) {
            throw new Exception($dateValidation['message']);
        }
        $reservationDate = $dateValidation['value'];
        
        // Validate time slot
        $timeValidation = validateTime($timeSlot);
        if (!$timeValidation['valid']) {
            throw new Exception($timeValidation['message']);
        }
        $timeSlot = $timeValidation['value'];
        
        // Validate customer name
        $nameValidation = validateString($customerName, 2, 100, true);
        if (!$nameValidation['valid']) {
            throw new Exception($nameValidation['message']);
        }
        $customerName = sanitizeString($nameValidation['value']);
        
        // Validate phone number
        $phoneValidation = validatePhone($phone);
        if (!$phoneValidation['valid']) {
            throw new Exception($phoneValidation['message']);
        }
        $phone = $phoneValidation['value'];
        
        // Validate email if provided
        if (!empty($email)) {
            $emailValidation = validateEmail($email);
            if (!$emailValidation['valid']) {
                throw new Exception($emailValidation['message']);
            }
            $email = $emailValidation['value'];
        }
        
        // Validate number of guests
        $guestsValidation = validateInteger($noOfGuests, 1, 50);
        if (!$guestsValidation['valid']) {
            throw new Exception($guestsValidation['message']);
        }
        $noOfGuests = $guestsValidation['value'];
        
        // Sanitize special request
        if (!empty($specialRequest)) {
            $specialRequest = sanitizeString($specialRequest);
            if (strlen($specialRequest) > 500) {
                throw new Exception('Special request must be less than 500 characters');
            }
        }
    }
    
    // Validate reservation ID for update and delete actions
    if (in_array($action, ['update', 'delete']) && $reservationId <= 0) {
        throw new Exception('Invalid reservation ID');
    }
    
    switch ($action) {
        case 'add':
            // Verify table belongs to this restaurant if specified
            if ($tableId && $tableId > 0) {
                $tableCheckStmt = $conn->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
                $tableCheckStmt->execute([$tableId, $restaurant_id]);
                if (!$tableCheckStmt->fetch()) {
                    throw new Exception('Invalid table selection');
                }
            }
            
            // Insert new reservation
            $insertStmt = $conn->prepare("INSERT INTO reservations (restaurant_id, table_id, reservation_date, time_slot, no_of_guests, meal_type, customer_name, phone, email, special_request, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $result = $insertStmt->execute([$restaurant_id, $tableId, $reservationDate, $timeSlot, $noOfGuests, $mealType, $customerName, $phone, $email, $specialRequest, $status]);
            
            if ($result) {
                $newReservationId = $conn->lastInsertId();
                
                // Auto-add customer if not exists
                if (!empty($phone)) {
                    // Check if customer already exists
                    $customerCheckStmt = $conn->prepare("SELECT id FROM customers WHERE restaurant_id = ? AND phone = ?");
                    $customerCheckStmt->execute([$restaurant_id, $phone]);
                    
                    if (!$customerCheckStmt->fetch()) {
                        // Customer doesn't exist, add them
                        $addCustomerStmt = $conn->prepare("INSERT INTO customers (restaurant_id, customer_name, phone, email, total_visits, last_visit_date) VALUES (?, ?, ?, ?, 1, ?)");
                        $addCustomerStmt->execute([$restaurant_id, $customerName, $phone, $email, $reservationDate]);
                    } else {
                        // Customer exists, update their visit count and last visit date
                        $updateCustomerStmt = $conn->prepare("UPDATE customers SET total_visits = total_visits + 1, last_visit_date = ? WHERE restaurant_id = ? AND phone = ?");
                        $updateCustomerStmt->execute([$reservationDate, $restaurant_id, $phone]);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Reservation added successfully',
                    'data' => [
                        'id' => $newReservationId,
                        'customer_name' => $customerName,
                        'reservation_date' => $reservationDate,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                throw new Exception('Failed to add reservation');
            }
            break;
            
        case 'update':
            // Check if reservation exists and belongs to this restaurant
            $checkStmt = $conn->prepare("SELECT id FROM reservations WHERE id = ? AND restaurant_id = ?");
            $checkStmt->execute([$reservationId, $restaurant_id]);
            if (!$checkStmt->fetch()) {
                throw new Exception('Reservation not found');
            }
            
            // Verify table belongs to this restaurant if specified
            if ($tableId && $tableId > 0) {
                $tableCheckStmt = $conn->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
                $tableCheckStmt->execute([$tableId, $restaurant_id]);
                if (!$tableCheckStmt->fetch()) {
                    throw new Exception('Invalid table selection');
                }
            }
            
            // Update reservation
            $updateStmt = $conn->prepare("UPDATE reservations SET table_id = ?, reservation_date = ?, time_slot = ?, no_of_guests = ?, meal_type = ?, customer_name = ?, phone = ?, email = ?, special_request = ?, status = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?");
            $result = $updateStmt->execute([$tableId, $reservationDate, $timeSlot, $noOfGuests, $mealType, $customerName, $phone, $email, $specialRequest, $status, $reservationId, $restaurant_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reservation updated successfully',
                    'data' => [
                        'id' => $reservationId,
                        'customer_name' => $customerName,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                throw new Exception('Failed to update reservation');
            }
            break;
            
        case 'delete':
            // Check if reservation exists and belongs to this restaurant
            $checkStmt = $conn->prepare("SELECT customer_name FROM reservations WHERE id = ? AND restaurant_id = ?");
            $checkStmt->execute([$reservationId, $restaurant_id]);
            $reservation = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reservation) {
                throw new Exception('Reservation not found');
            }
            
            // Delete reservation
            $deleteStmt = $conn->prepare("DELETE FROM reservations WHERE id = ? AND restaurant_id = ?");
            $result = $deleteStmt->execute([$reservationId, $restaurant_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reservation deleted successfully',
                    'data' => [
                        'id' => $reservationId,
                        'customer_name' => $reservation['customer_name']
                    ]
                ]);
            } else {
                throw new Exception('Failed to delete reservation');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("PDO Error in reservation_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in reservation_operations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}
?>

