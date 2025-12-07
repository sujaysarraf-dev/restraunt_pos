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

try {
    $conn = $pdo;
    
    // Get customers from customers table
    $stmt = $conn->prepare("SELECT * FROM customers WHERE restaurant_id = ? ORDER BY customer_name ASC");
    $stmt->execute([$restaurant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get unique customers from orders table (for takeaway orders with customer details)
    $orderCustomersStmt = $conn->prepare("
        SELECT DISTINCT 
            customer_name, 
            customer_phone as phone, 
            customer_email as email,
            customer_address as address,
            COALESCE(SUM(total), 0) as total_spent,
            COUNT(*) as total_visits,
            MAX(created_at) as last_visit_date
        FROM orders 
        WHERE restaurant_id = ? 
        AND customer_name IS NOT NULL 
        AND customer_name != '' 
        AND customer_name != 'Table Customer'
        AND customer_name != 'Takeaway'
        GROUP BY customer_name, customer_phone, customer_email, customer_address
    ");
    $orderCustomersStmt->execute([$restaurant_id]);
    $orderCustomers = $orderCustomersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge customers - use customers table as base, update with order data
    $customerMap = [];
    foreach ($customers as $c) {
        $key = strtolower(trim($c['customer_name']));
        $customerMap[$key] = $c;
    }
    
    // Add/update customers from orders
    foreach ($orderCustomers as $oc) {
        $key = strtolower(trim($oc['customer_name']));
        if (isset($customerMap[$key])) {
            // Update existing customer with order data if missing
            if (empty($customerMap[$key]['phone']) && !empty($oc['phone'])) {
                $customerMap[$key]['phone'] = $oc['phone'];
            }
            if (empty($customerMap[$key]['email']) && !empty($oc['email'])) {
                $customerMap[$key]['email'] = $oc['email'];
            }
            if (empty($customerMap[$key]['address']) && !empty($oc['address'])) {
                $customerMap[$key]['address'] = $oc['address'];
            }
            // Update spending/visits from orders
            $customerMap[$key]['total_spent'] = (float)$oc['total_spent'];
            $customerMap[$key]['total_visits'] = (int)$oc['total_visits'];
            $customerMap[$key]['last_visit_date'] = $oc['last_visit_date'] ? substr($oc['last_visit_date'], 0, 10) : null;
        } else {
            // Add new customer from orders
            $customerMap[$key] = [
                'id' => null,
                'restaurant_id' => $restaurant_id,
                'customer_name' => $oc['customer_name'],
                'phone' => $oc['phone'] ?? '',
                'email' => $oc['email'] ?? '',
                'address' => $oc['address'] ?? '',
                'total_spent' => (float)$oc['total_spent'],
                'total_visits' => (int)$oc['total_visits'],
                'last_visit_date' => $oc['last_visit_date'] ? substr($oc['last_visit_date'], 0, 10) : null,
                'created_at' => null,
                'updated_at' => null
            ];
        }
    }
    
    // Convert back to array and sort
    $allCustomers = array_values($customerMap);
    usort($allCustomers, function($a, $b) {
        return strcasecmp($a['customer_name'], $b['customer_name']);
    });

    // Recalculate spending and visits from orders table for accuracy (for customers table entries)
    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(total),0) AS spent, COUNT(*) AS orders, MAX(created_at) AS last_order
                               FROM orders WHERE restaurant_id = ? AND customer_name = ?");
    $updStmt = $conn->prepare("UPDATE customers SET total_spent = ?, total_visits = ?, last_visit_date = DATE(?) WHERE id = ?");

    foreach ($allCustomers as &$c) {
        if ($c['id']) { // Only update if exists in customers table
            $name = $c['customer_name'];
            $sumStmt->execute([$restaurant_id, $name]);
            $row = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['spent'=>0,'orders'=>0,'last_order'=>null];
            $calcSpent = (float)$row['spent'];
            $calcVisits = (int)$row['orders'];
            $lastOrder = $row['last_order'];

            // If values differ, update DB so future fetches are fast
            if ((float)$c['total_spent'] !== $calcSpent || (int)$c['total_visits'] !== $calcVisits || ($lastOrder && $c['last_visit_date'] != substr($lastOrder,0,10))) {
                $updStmt->execute([$calcSpent, $calcVisits, $lastOrder, $c['id']]);
                $c['total_spent'] = $calcSpent;
                $c['total_visits'] = $calcVisits;
                $c['last_visit_date'] = $lastOrder ? substr($lastOrder,0,10) : $c['last_visit_date'];
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $allCustomers]);
} catch (PDOException $e) {
    error_log("PDO Error in get_customers.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("Error in get_customers.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}

