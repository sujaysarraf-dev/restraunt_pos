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
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if (file_exists(__DIR__ . '/../config/db_connection.php')) {
    require_once __DIR__ . '/../config/db_connection.php';
} elseif (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    throw new Exception('Database connection file not found');
}

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    // Get the action and data from POST
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    
    // Validate action
    if (empty($action)) {
        throw new Exception('Action is required');
    }
    
    switch ($action) {
        case 'login':
            handleLogin();
            break;
            
        case 'signup':
            handleSignup();
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    // Database error
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    
} catch (Exception $e) {
    // General error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleLogin() {
    global $pdo;
    
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    // First, try to find in users table (Admin)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Admin/User login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['restaurant_id'] = $user['restaurant_id'];
        $_SESSION['restaurant_name'] = $user['restaurant_name'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['role'] = 'Admin';
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => '../dashboard.php',
            'data' => [
                'username' => $user['username'],
                'restaurant_id' => $user['restaurant_id'],
                'restaurant_name' => $user['restaurant_name'],
                'user_type' => 'admin',
                'role' => 'Admin'
            ]
        ]);
        return;
    }
    
    // If not found in users table, check staff table
    // Try email first, then phone as username
    $staffStmt = $pdo->prepare("SELECT s.*, u.restaurant_name FROM staff s LEFT JOIN users u ON s.restaurant_id = u.restaurant_id WHERE (s.email = ? OR s.phone = ?) AND s.is_active = 1 LIMIT 1");
    $staffStmt->execute([$username, $username]);
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff && password_verify($password, $staff['password'])) {
        // Staff login
        $_SESSION['staff_id'] = $staff['id'];
        $_SESSION['username'] = $staff['member_name'];
        $_SESSION['email'] = $staff['email'];
        $_SESSION['restaurant_id'] = $staff['restaurant_id'];
        $_SESSION['restaurant_name'] = $staff['restaurant_name'] ?? 'Restaurant';
        $_SESSION['user_type'] = 'staff';
        $_SESSION['role'] = $staff['role'];
        
        // Determine redirect based on role
        $redirect = '../dashboard.php'; // Default
        if ($staff['role'] === 'Chef') {
            $redirect = '../chef_dashboard.php';
        } elseif ($staff['role'] === 'Waiter') {
            $redirect = '../waiter_dashboard.php';
        } elseif ($staff['role'] === 'Manager') {
            $redirect = '../manager_dashboard.php';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect,
            'data' => [
                'username' => $staff['member_name'],
                'restaurant_id' => $staff['restaurant_id'],
                'restaurant_name' => $staff['restaurant_name'] ?? 'Restaurant',
                'user_type' => 'staff',
                'role' => $staff['role']
            ]
        ]);
        return;
    }
    
    // If neither found, throw error
    throw new Exception('Invalid username or password');
}

function handleSignup() {
    global $pdo;
    
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $restaurantName = isset($_POST['restaurant_name']) ? trim($_POST['restaurant_name']) : '';
    
    // Validate input
    if (empty($username) || empty($password) || empty($restaurantName)) {
        throw new Exception('All fields are required');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('Username must be at least 3 characters long');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }
    
    if (strlen($restaurantName) < 2) {
        throw new Exception('Restaurant name must be at least 2 characters long');
    }
    
    // Check if username already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $checkStmt->execute([$username]);
    if ($checkStmt->fetchColumn() > 0) {
        throw new Exception('Username already exists');
    }
    
    // Generate restaurant ID
    $restaurantId = generateRestaurantId();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $insertStmt = $pdo->prepare("
        INSERT INTO users (username, password, restaurant_id, restaurant_name, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $insertStmt->execute([$username, $hashedPassword, $restaurantId, $restaurantName]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully',
            'data' => [
                'username' => $username,
                'restaurant_id' => $restaurantId,
                'restaurant_name' => $restaurantName
            ]
        ]);
    } else {
        throw new Exception('Failed to create account');
    }
}

function handleLogout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie if it exists
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

function generateRestaurantId() {
    global $pdo;
    
    // Get the highest existing restaurant ID
    $stmt = $pdo->prepare("SELECT restaurant_id FROM users ORDER BY restaurant_id DESC LIMIT 1");
    $stmt->execute();
    $lastId = $stmt->fetchColumn();
    
    if ($lastId) {
        // Extract number from last ID (e.g., RES001 -> 1)
        $lastNumber = (int)substr($lastId, 3);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    // Format as RES001, RES002, etc.
    return 'RES' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}
?>
