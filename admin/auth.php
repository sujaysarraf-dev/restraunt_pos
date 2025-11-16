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
if (file_exists(__DIR__ . '/../db_connection.php')) {
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
            
        case 'updateProfile':
            handleUpdateProfile();
            break;
            
        case 'changePassword':
            handleChangePassword();
            break;
            
        case 'updateRestaurantSettings':
            handleUpdateRestaurantSettings();
            break;
            
        case 'updateSystemSettings':
            handleUpdateSystemSettings();
            break;
            
        case 'uploadRestaurantLogo':
            handleUploadRestaurantLogo();
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
            'redirect' => '../views/dashboard.php',
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
        $redirect = '../views/dashboard.php'; // Default
        if ($staff['role'] === 'Chef') {
            $redirect = '../views/chef_dashboard.php';
        } elseif ($staff['role'] === 'Waiter') {
            $redirect = '../views/waiter_dashboard.php';
        } elseif ($staff['role'] === 'Manager') {
            $redirect = '../views/manager_dashboard.php';
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

function handleUpdateProfile() {
    global $pdo;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to update your profile');
    }
    
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Validate input
    if (empty($username)) {
        throw new Exception('Username is required');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email address is required');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('Username must be at least 3 characters long');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Check if username is already taken by another user
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $checkStmt->execute([$username, $userId]);
    if ($checkStmt->fetch()) {
        throw new Exception('Username already exists');
    }
    
    // Check if email is already taken by another user
    $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkEmailStmt->execute([$email, $userId]);
    if ($checkEmailStmt->fetch()) {
        throw new Exception('Email already exists');
    }
    
    // Update user profile - handle email column existence
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$username, $email, $userId]);
    } catch (PDOException $e) {
        // If email column doesn't exist, update without it
        if (strpos($e->getMessage(), 'email') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            $updateStmt = $pdo->prepare("UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$username, $userId]);
        } else {
            throw $e;
        }
    }
    
    if ($result) {
        // Update session
        $_SESSION['username'] = $username;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'username' => $username,
                'email' => $email
            ]
        ]);
    } else {
        throw new Exception('Failed to update profile');
    }
}

function handleChangePassword() {
    global $pdo;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to change your password');
    }
    
    $currentPassword = isset($_POST['currentPassword']) ? $_POST['currentPassword'] : '';
    $newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';
    
    // Validate input
    if (empty($currentPassword)) {
        throw new Exception('Current password is required');
    }
    
    if (empty($newPassword)) {
        throw new Exception('New password is required');
    }
    
    if (strlen($newPassword) < 6) {
        throw new Exception('New password must be at least 6 characters long');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Get current user password - check both users and staff tables
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found in users table, check staff table
    if (!$user && isset($_SESSION['staff_id'])) {
        $staffStmt = $pdo->prepare("SELECT password FROM staff WHERE id = ?");
        $staffStmt->execute([$_SESSION['staff_id']]);
        $user = $staffStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        throw new Exception('Current password is incorrect');
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password in appropriate table
    if (isset($_SESSION['staff_id'])) {
        $updateStmt = $pdo->prepare("UPDATE staff SET password = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$hashedPassword, $_SESSION['staff_id']]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$hashedPassword, $userId]);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    } else {
        throw new Exception('Failed to change password');
    }
}

function handleUpdateRestaurantSettings() {
    global $pdo;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
        throw new Exception('You must be logged in to update restaurant settings');
    }
    
    $restaurantName = isset($_POST['restaurant_name']) ? trim($_POST['restaurant_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    
    // Validate input
    if (empty($restaurantName)) {
        throw new Exception('Restaurant name is required');
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email address is required');
    }
    
    $userId = $_SESSION['user_id'];
    $restaurantId = $_SESSION['restaurant_id'];
    
    // Check if email is already taken by another user
    if (!empty($email)) {
        $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmailStmt->execute([$email, $userId]);
        if ($checkEmailStmt->fetch()) {
            throw new Exception('Email already exists');
        }
    }
    
    // Update restaurant settings - handle column existence gracefully
    try {
        // Try to update with all fields (email, phone, address)
        $updateStmt = $pdo->prepare("UPDATE users SET restaurant_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$restaurantName, $email, $phone, $address, $userId]);
    } catch (PDOException $e) {
        // If some columns don't exist, try with fewer fields
        if (strpos($e->getMessage(), 'phone') !== false || strpos($e->getMessage(), 'address') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            try {
                // Try with email only
                $updateStmt = $pdo->prepare("UPDATE users SET restaurant_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $result = $updateStmt->execute([$restaurantName, $email, $userId]);
            } catch (PDOException $e2) {
                // If email column doesn't exist either, update without it
                if (strpos($e2->getMessage(), 'email') !== false || strpos($e2->getMessage(), 'Unknown column') !== false) {
                    $updateStmt = $pdo->prepare("UPDATE users SET restaurant_name = ?, updated_at = NOW() WHERE id = ?");
                    $result = $updateStmt->execute([$restaurantName, $userId]);
                } else {
                    throw $e2;
                }
            }
        } else {
            throw $e;
        }
    }
    
    if ($result) {
        // Update session
        $_SESSION['restaurant_name'] = $restaurantName;
        if (!empty($email)) {
            $_SESSION['email'] = $email;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Restaurant settings updated successfully',
            'data' => [
                'restaurant_name' => $restaurantName,
                'email' => $email,
                'phone' => $phone,
                'address' => $address
            ]
        ]);
    } else {
        throw new Exception('Failed to update restaurant settings');
    }
}

function handleUpdateSystemSettings() {
    global $pdo;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
        throw new Exception('You must be logged in to update system settings');
    }
    
    $currencySymbol = isset($_POST['currency_symbol']) ? trim($_POST['currency_symbol']) : '₹';
    $timezone = isset($_POST['timezone']) ? trim($_POST['timezone']) : 'Asia/Kolkata';
    $autoSync = isset($_POST['auto_sync']) ? (int)$_POST['auto_sync'] : 0;
    $notifications = isset($_POST['notifications']) ? (int)$_POST['notifications'] : 0;
    
    // Validate input
    if (empty($currencySymbol)) {
        $currencySymbol = '₹';
    }
    
    if (empty($timezone)) {
        $timezone = 'Asia/Kolkata';
    }
    
    $userId = $_SESSION['user_id'];
    
    // Update system settings - handle column existence gracefully
    try {
        // Try to update with all fields (currency_symbol, timezone)
        $updateStmt = $pdo->prepare("UPDATE users SET currency_symbol = ?, timezone = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$currencySymbol, $timezone, $userId]);
    } catch (PDOException $e) {
        // If columns don't exist, try without them
        if (strpos($e->getMessage(), 'currency_symbol') !== false || 
            strpos($e->getMessage(), 'timezone') !== false || 
            strpos($e->getMessage(), 'Unknown column') !== false) {
            // Columns don't exist, just update timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$userId]);
        } else {
            throw $e;
        }
    }
    
    if ($result) {
        // Save currency to session so it loads immediately on next page load (no flash)
        $_SESSION['currency_symbol'] = $currencySymbol;
        
        echo json_encode([
            'success' => true,
            'message' => 'System settings updated successfully',
            'data' => [
                'currency_symbol' => $currencySymbol,
                'timezone' => $timezone
            ]
        ]);
    } else {
        throw new Exception('Failed to update system settings');
    }
}

function handleUploadRestaurantLogo() {
    global $pdo;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['restaurant_id'])) {
        throw new Exception('You must be logged in to upload restaurant logo');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }
    
    $file = $_FILES['logo'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }
    
    // Validate file size (2MB max)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size is 2MB.');
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = '';
    switch ($mimeType) {
        case 'image/jpeg':
            $extension = '.jpg';
            break;
        case 'image/png':
            $extension = '.png';
            break;
        case 'image/gif':
            $extension = '.gif';
            break;
        case 'image/webp':
            $extension = '.webp';
            break;
    }
    
    $filename = 'logo_' . $_SESSION['restaurant_id'] . '_' . uniqid() . '_' . time() . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    $logoPath = 'uploads/' . $filename;
    
    $userId = $_SESSION['user_id'];
    
    // Delete old logo if exists
    try {
        $oldLogoStmt = $pdo->prepare("SELECT restaurant_logo FROM users WHERE id = ?");
        $oldLogoStmt->execute([$userId]);
        $oldLogo = $oldLogoStmt->fetchColumn();
        
        if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
            @unlink(__DIR__ . '/../' . $oldLogo);
        }
    } catch (PDOException $e) {
        // Ignore if column doesn't exist yet
    }
    
    // Update restaurant logo - handle column existence gracefully
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET restaurant_logo = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$logoPath, $userId]);
    } catch (PDOException $e) {
        // If column doesn't exist, we need to add it first
        if (strpos($e->getMessage(), 'restaurant_logo') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            // Add column if it doesn't exist
            try {
                $alterStmt = $pdo->prepare("ALTER TABLE users ADD COLUMN restaurant_logo VARCHAR(255) NULL AFTER restaurant_name");
                $alterStmt->execute();
                
                // Try update again
                $updateStmt = $pdo->prepare("UPDATE users SET restaurant_logo = ?, updated_at = NOW() WHERE id = ?");
                $result = $updateStmt->execute([$logoPath, $userId]);
            } catch (PDOException $e2) {
                // If alter fails (column might already exist), try update again
                $updateStmt = $pdo->prepare("UPDATE users SET restaurant_logo = ?, updated_at = NOW() WHERE id = ?");
                $result = $updateStmt->execute([$logoPath, $userId]);
            }
        } else {
            throw $e;
        }
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Restaurant logo uploaded successfully',
            'data' => [
                'restaurant_logo' => $logoPath
            ]
        ]);
    } else {
        throw new Exception('Failed to update restaurant logo');
    }
}
?>
