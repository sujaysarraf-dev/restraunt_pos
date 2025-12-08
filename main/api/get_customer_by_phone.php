<?php
// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Include authorization configuration
require_once __DIR__ . '/../config/authorization_config.php';

header('Content-Type: application/json');

// Require permission to manage customers
requirePermission(PERMISSION_MANAGE_CUSTOMERS);

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

$restaurant_id = $_SESSION['restaurant_id'];
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';

// Search by phone (priority) or name
$searchByPhone = !empty($phone);
$searchByName = !empty($name) && empty($phone);

if (!$searchByPhone && !$searchByName) {
    echo json_encode(['success' => false, 'message' => 'Phone number or name is required']);
    exit();
}

try {
    $conn = $pdo;
    $customer = null;
    
    // First check customers table
    if ($searchByPhone) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE restaurant_id = ? AND phone = ? LIMIT 1");
        $stmt->execute([$restaurant_id, $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($searchByName) {
        // Search by name (partial match)
        $stmt = $conn->prepare("SELECT * FROM customers WHERE restaurant_id = ? AND customer_name LIKE ? LIMIT 5");
        $stmt->execute([$restaurant_id, '%' . $name . '%']);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($customers) === 1) {
            $customer = $customers[0];
        } elseif (count($customers) > 1) {
            // Multiple matches - return first one but indicate multiple found
            $customer = $customers[0];
            $customer['multiple_matches'] = true;
            $customer['matches'] = array_map(function($c) {
                return [
                    'id' => $c['id'],
                    'customer_name' => $c['customer_name'],
                    'phone' => $c['phone']
                ];
            }, $customers);
        }
    }
    
    // If not found in customers table, check orders table
    if (!$customer || (isset($customer['multiple_matches']) && !$customer['multiple_matches'])) {
        if ($searchByPhone) {
            $orderStmt = $conn->prepare("
                SELECT DISTINCT 
                    customer_name, 
                    customer_phone as phone, 
                    customer_email as email,
                    customer_address as address,
                    COUNT(*) as total_visits,
                    COALESCE(SUM(total), 0) as total_spent,
                    MAX(created_at) as last_visit
                FROM orders 
                WHERE restaurant_id = ? 
                AND customer_phone = ?
                AND customer_name IS NOT NULL 
                AND customer_name != '' 
                AND customer_name != 'Table Customer'
                AND customer_name != 'Takeaway'
                GROUP BY customer_name, customer_phone, customer_email, customer_address
                ORDER BY last_visit DESC
                LIMIT 1
            ");
            $orderStmt->execute([$restaurant_id, $phone]);
            $orderCustomer = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($orderCustomer) {
                $customer = [
                    'customer_name' => $orderCustomer['customer_name'],
                    'phone' => $orderCustomer['phone'],
                    'email' => $orderCustomer['email'] ?? '',
                    'address' => $orderCustomer['address'] ?? '',
                    'total_visits' => (int)$orderCustomer['total_visits'],
                    'total_spent' => (float)$orderCustomer['total_spent'],
                    'last_visit' => $orderCustomer['last_visit'],
                    'from_orders' => true // Flag to indicate we should save to customers table
                ];
            }
        } elseif ($searchByName) {
            $orderStmt = $conn->prepare("
                SELECT DISTINCT 
                    customer_name, 
                    customer_phone as phone, 
                    customer_email as email,
                    customer_address as address,
                    COUNT(*) as total_visits,
                    COALESCE(SUM(total), 0) as total_spent,
                    MAX(created_at) as last_visit
                FROM orders 
                WHERE restaurant_id = ? 
                AND customer_name LIKE ?
                AND customer_name IS NOT NULL 
                AND customer_name != '' 
                AND customer_name != 'Table Customer'
                AND customer_name != 'Takeaway'
                GROUP BY customer_name, customer_phone, customer_email, customer_address
                ORDER BY last_visit DESC
                LIMIT 5
            ");
            $orderStmt->execute([$restaurant_id, '%' . $name . '%']);
            $orderCustomers = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($orderCustomers) === 1) {
                $orderCustomer = $orderCustomers[0];
                $customer = [
                    'customer_name' => $orderCustomer['customer_name'],
                    'phone' => $orderCustomer['phone'],
                    'email' => $orderCustomer['email'] ?? '',
                    'address' => $orderCustomer['address'] ?? '',
                    'total_visits' => (int)$orderCustomer['total_visits'],
                    'total_spent' => (float)$orderCustomer['total_spent'],
                    'last_visit' => $orderCustomer['last_visit'],
                    'from_orders' => true
                ];
            } elseif (count($orderCustomers) > 1) {
                $customer = $orderCustomers[0];
                $customer['multiple_matches'] = true;
                $customer['matches'] = array_map(function($c) {
                    return [
                        'customer_name' => $c['customer_name'],
                        'phone' => $c['phone'],
                        'total_visits' => (int)$c['total_visits']
                    ];
                }, $orderCustomers);
            }
        }
    }
    
    // If customer found in orders but not in customers table, auto-save them
    if ($customer && isset($customer['from_orders']) && $customer['from_orders'] && !isset($customer['id'])) {
        try {
            // Check if already exists (by phone)
            $checkStmt = $conn->prepare("SELECT id FROM customers WHERE restaurant_id = ? AND phone = ?");
            $checkStmt->execute([$restaurant_id, $customer['phone']]);
            if (!$checkStmt->fetch()) {
                // Insert into customers table
                $insertStmt = $conn->prepare("INSERT INTO customers (restaurant_id, customer_name, phone, email, total_visits, total_spent, last_visit_date) VALUES (?, ?, ?, ?, ?, ?, DATE(?))");
                $insertStmt->execute([
                    $restaurant_id,
                    $customer['customer_name'],
                    $customer['phone'],
                    $customer['email'] ?? '',
                    $customer['total_visits'] ?? 0,
                    $customer['total_spent'] ?? 0,
                    $customer['last_visit'] ?? date('Y-m-d')
                ]);
                $customer['id'] = $conn->lastInsertId();
                $customer['auto_saved'] = true;
            }
        } catch (PDOException $e) {
            // If save fails, continue anyway - customer data is still valid
            error_log("Error auto-saving customer: " . $e->getMessage());
        }
    }
    
    // Get customer stats if not already included
    if ($customer && !isset($customer['total_visits'])) {
        $statsStmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_visits,
                COALESCE(SUM(total), 0) as total_spent,
                MAX(created_at) as last_visit
            FROM orders 
            WHERE restaurant_id = ? 
            AND customer_phone = ?
            AND customer_name = ?
        ");
        $statsStmt->execute([$restaurant_id, $customer['phone'] ?? '', $customer['customer_name'] ?? '']);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($stats) {
            $customer['total_visits'] = (int)$stats['total_visits'];
            $customer['total_spent'] = (float)$stats['total_spent'];
            $customer['last_visit'] = $stats['last_visit'];
        }
    }
    
    if ($customer) {
        $response = [
            'success' => true,
            'customer' => [
                'id' => $customer['id'] ?? null,
                'customer_name' => $customer['customer_name'] ?? $customer['name'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'email' => $customer['email'] ?? '',
                'address' => $customer['address'] ?? '',
                'total_visits' => $customer['total_visits'] ?? 0,
                'total_spent' => $customer['total_spent'] ?? 0,
                'last_visit' => $customer['last_visit'] ?? null
            ]
        ];
        
        if (isset($customer['multiple_matches']) && $customer['multiple_matches']) {
            $response['multiple_matches'] = true;
            $response['matches'] = $customer['matches'] ?? [];
        }
        
        if (isset($customer['auto_saved'])) {
            $response['auto_saved'] = true;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
    }
} catch (PDOException $e) {
    error_log("PDO Error in get_customer_by_phone.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_customer_by_phone.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit();
}

