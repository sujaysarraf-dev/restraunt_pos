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
            
        case 'uploadBusinessQR':
            handleUploadBusinessQR();
            break;
            
        case 'removeBusinessQR':
            handleRemoveBusinessQR();
            break;
            
        case 'forgotPassword':
            handleForgotPassword();
            break;
            
        case 'resetPassword':
            handleResetPassword();
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
    
    // Check if input looks like an email
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
    
    // First, try to find in users table (Admin)
    // Try username first, then email if input looks like email
    if ($isEmail) {
        // If input is email, try to find by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found by email, try username as fallback
        if (!$user) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // If not email, try username first
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found by username and input contains @, try email as fallback
        if (!$user && strpos($username, '@') !== false) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if ($user && password_verify($password, $user['password'])) {
        // Admin/User login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['restaurant_id'] = $user['restaurant_id'];
        $_SESSION['restaurant_name'] = $user['restaurant_name'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['role'] = 'Admin';
        
        // Regenerate session ID after successful login for security
        regenerateSessionAfterLogin();
        
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
        
        // Regenerate session ID after successful login for security
        regenerateSessionAfterLogin();
        
        // Redirect based on role to appropriate dashboard
        $role = $staff['role'];
        switch ($role) {
            case 'Waiter':
                $redirect = '../views/waiter_dashboard.php';
                break;
            case 'Chef':
                $redirect = '../views/chef_dashboard.php';
                break;
            case 'Manager':
                $redirect = '../views/manager_dashboard.php';
                break;
            case 'Admin':
            default:
                $redirect = '../views/dashboard.php';
                break;
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
    throw new Exception('Invalid username, email or password');
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
    // Use secure session destruction
    destroySession();
    
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
    
    // Read image data for database storage
    $logoData = file_get_contents($file['tmp_name']);
    if ($logoData === false) {
        throw new Exception('Failed to read image file');
    }
    
    $logoPath = 'db:' . uniqid(); // Reference ID for database storage
    $userId = $_SESSION['user_id'];
    
    // Delete old logo file if exists (for backward compatibility)
    try {
        $oldLogoStmt = $pdo->prepare("SELECT restaurant_logo FROM users WHERE id = ?");
        $oldLogoStmt->execute([$userId]);
        $oldLogo = $oldLogoStmt->fetchColumn();
        
        if ($oldLogo && strpos($oldLogo, 'db:') !== 0 && file_exists(__DIR__ . '/../' . $oldLogo)) {
            @unlink(__DIR__ . '/../' . $oldLogo);
        }
    } catch (PDOException $e) {
        // Ignore if column doesn't exist yet
    }
    
    // Ensure logo_data and logo_mime_type columns exist
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'logo_data'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN logo_data LONGBLOB NULL AFTER restaurant_logo");
            $pdo->exec("ALTER TABLE users ADD COLUMN logo_mime_type VARCHAR(50) NULL AFTER logo_data");
        }
    } catch (PDOException $e) {
        // Columns might already exist, continue
    }
    
    // Update restaurant logo - handle column existence gracefully
    try {
        // First ensure restaurant_logo column exists
        try {
            $checkLogoCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'restaurant_logo'");
            if ($checkLogoCol->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN restaurant_logo VARCHAR(255) NULL AFTER restaurant_name");
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        // Update with logo data in database
        $updateStmt = $pdo->prepare("UPDATE users SET restaurant_logo = ?, logo_data = ?, logo_mime_type = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$logoPath, $logoData, $mimeType, $userId]);
    } catch (PDOException $e) {
        // Fallback: try without logo_data columns
        try {
            $updateStmt = $pdo->prepare("UPDATE users SET restaurant_logo = ?, updated_at = NOW() WHERE id = ?");
            $result = $updateStmt->execute([$logoPath, $userId]);
        } catch (PDOException $e2) {
            throw new Exception('Failed to update restaurant logo: ' . $e2->getMessage());
        }
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Restaurant logo uploaded successfully',
            'data' => [
                'restaurant_logo' => $logoPath,
                'logo_url' => 'api/image.php?type=logo&id=' . $userId
            ]
        ]);
    } else {
        throw new Exception('Failed to update restaurant logo');
    }
}

function handleUploadBusinessQR() {
    global $pdo;
    
    if (!isset($_FILES['business_qr']) || $_FILES['business_qr']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No QR code file uploaded');
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file = $_FILES['business_qr'];
    
    // Verify MIME type
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensionMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $actualMimeType = $extensionMap[$extension] ?? 'application/octet-stream';
    }
    
    if (!in_array($actualMimeType, $allowedTypes)) {
        throw new Exception('Invalid QR code file type. Allowed: JPEG, PNG, GIF, WebP');
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('QR code file too large (max 5MB)');
    }
    
    // Read image data for database storage
    $qrData = file_get_contents($file['tmp_name']);
    if ($qrData === false) {
        throw new Exception('Failed to read QR code file');
    }
    
    $qrPath = 'db:' . uniqid();
    $userId = $_SESSION['user_id'];
    
    // Ensure business_qr_code columns exist
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'business_qr_code_path'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN business_qr_code_path VARCHAR(500) DEFAULT NULL AFTER timezone");
            $pdo->exec("ALTER TABLE users ADD COLUMN business_qr_code_data LONGBLOB NULL AFTER business_qr_code_path");
            $pdo->exec("ALTER TABLE users ADD COLUMN business_qr_code_mime_type VARCHAR(50) NULL AFTER business_qr_code_data");
        }
    } catch (PDOException $e) {
        // Columns might already exist, continue
    }
    
    // Update business QR code
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET business_qr_code_path = ?, business_qr_code_data = ?, business_qr_code_mime_type = ?, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$qrPath, $qrData, $actualMimeType, $userId]);
    } catch (PDOException $e) {
        throw new Exception('Failed to update business QR code: ' . $e->getMessage());
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Business QR code uploaded successfully',
            'data' => [
                'business_qr_code_path' => $qrPath,
                'qr_code_url' => 'api/image.php?type=business_qr&id=' . $userId
            ]
        ]);
    } else {
        throw new Exception('Failed to update business QR code');
    }
}

function handleRemoveBusinessQR() {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET business_qr_code_path = NULL, business_qr_code_data = NULL, business_qr_code_mime_type = NULL, updated_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$userId]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Business QR code removed successfully'
            ]);
        } else {
            throw new Exception('Failed to remove business QR code');
        }
    } catch (PDOException $e) {
        throw new Exception('Failed to remove business QR code: ' . $e->getMessage());
    }
}

function handleForgotPassword() {
    global $pdo;
    
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Validate input
    if (empty($email)) {
        throw new Exception('Email address is required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }
    
    // Check if email exists in users table
    $stmt = $pdo->prepare("SELECT id, username, restaurant_name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Always return success message (security: don't reveal if email exists)
    if (!$user) {
        // Still return success to prevent email enumeration
        echo json_encode([
            'success' => true,
            'message' => 'If the email exists in our system, a password reset link has been sent.'
        ]);
        return;
    }
    
    // Create password reset token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes')); // Token expires in 5 minutes
    
    // Create password_reset_tokens table if it doesn't exist
    try {
        // Check if table exists first
        $checkTable = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        if ($checkTable->rowCount() == 0) {
            // Create table without foreign key first (to avoid constraint issues)
            $createTableStmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires_at (expires_at)
                )
            ");
            $createTableStmt->execute();
        }
    } catch (PDOException $e) {
        error_log("Error creating password_reset_tokens table: " . $e->getMessage());
        // Continue anyway - table might already exist or we'll handle it in the insert
    }
    
    // Invalidate any existing tokens for this user
    try {
        $invalidateStmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE user_id = ? AND used = FALSE");
        $invalidateStmt->execute([$user['id']]);
    } catch (PDOException $e) {
        // Ignore if table doesn't exist yet
    }
    
    // Insert new token
    try {
        $insertStmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $insertStmt->execute([$user['id'], $token, $expiresAt]);
    } catch (PDOException $e) {
        error_log("Error creating reset token: " . $e->getMessage());
        throw new Exception('Failed to create reset token. Please try again later.');
    }
    
    // Generate reset link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
    
    // Include email configuration if available
    if (file_exists(__DIR__ . '/../config/email_config.php')) {
        require_once __DIR__ . '/../config/email_config.php';
    }
    
    // Send email
    $subject = 'Password Reset Request - ' . $user['restaurant_name'];
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #151A2D; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background: #151A2D; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Password Reset Request</h2>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>We received a request to reset your password for your restaurant account: <strong>" . htmlspecialchars($user['restaurant_name']) . "</strong></p>
                <p>Click the button below to reset your password:</p>
                <p style='text-align: center;'>
                    <a href='" . $resetLink . "' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; color: #666;'>" . $resetLink . "</p>
                <p><strong>This link will expire in 5 minutes.</strong></p>
                <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Try to send email using the email helper function if available
    $mailSent = false;
    $mailError = '';
    
    if (function_exists('sendEmail')) {
        // Use SMTP email function
        $emailResult = sendEmail($email, $subject, $message);
        if (is_array($emailResult)) {
            $mailSent = $emailResult['success'] ?? false;
            $mailError = $emailResult['error'] ?? '';
        } else {
            // Legacy boolean return
            $mailSent = $emailResult;
        }
        
        if (!$mailSent) {
            if (empty($mailError)) {
                $mailError = 'SMTP email sending failed';
            }
            error_log("Failed to send password reset email via SMTP to: " . $email . " - Error: " . $mailError);
        }
    } else {
        // Fallback to PHP mail() function
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Restaurant POS System <noreply@" . $host . ">" . "\r\n";
        
        $mailSent = @mail($email, $subject, $message, $headers);
        
        if (!$mailSent) {
            $lastError = error_get_last();
            $mailError = $lastError ? ($lastError['message'] ?? 'Mail function failed') : 'Mail function returned false';
            error_log("Failed to send password reset email to: " . $email . " - Error: " . $mailError);
        }
    }
    
    // Check if we're in development mode (localhost or local IP)
    $isDevelopment = (
        in_array($host, ['localhost', '127.0.0.1']) || 
        strpos($host, 'localhost') !== false || 
        strpos($host, '127.0.0.1') !== false ||
        strpos($host, '192.168.') !== false ||
        strpos($host, '10.0.') !== false ||
        (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') ||
        (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')
    );
    
    // Always return the reset link (especially useful when email doesn't work)
    // This ensures users can still reset their password even if email fails
    $responseMessage = 'Password reset link generated successfully!';
    
    if ($isDevelopment) {
        $responseMessage .= ' (Development Mode: Email may not be configured, use link below)';
    } elseif (!$mailSent) {
        $responseMessage .= ' (Email sending failed, use link below)';
    } else {
        $responseMessage .= ' (Check your email, or use link below if email not received)';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $responseMessage,
        'reset_link' => $resetLink, // Always include reset link
        'development_mode' => $isDevelopment,
        'email_sent' => $mailSent,
        'email_error' => $mailError,
        'expires_in' => '5 minutes'
    ]);
}

function handleResetPassword() {
    global $pdo;
    
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    
    // Validate input
    if (empty($token)) {
        throw new Exception('Reset token is required');
    }
    
    if (empty($newPassword)) {
        throw new Exception('New password is required');
    }
    
    if (strlen($newPassword) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }
    
    if ($newPassword !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }
    
    // Ensure password_reset_tokens table exists
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        if ($checkTable->rowCount() == 0) {
            // Create table if it doesn't exist
            $createTableStmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires_at (expires_at)
                )
            ");
            $createTableStmt->execute();
        }
    } catch (PDOException $e) {
        error_log("Error checking/creating password_reset_tokens table: " . $e->getMessage());
        // Continue anyway, might be a permission issue
    }
    
    // Verify token
    try {
        // First check if token exists and get expiration info
        $stmt = $pdo->prepare("
            SELECT prt.user_id, prt.expires_at, prt.used, u.id, u.username,
                   NOW() as server_time,
                   TIMESTAMPDIFF(SECOND, NOW(), prt.expires_at) as seconds_remaining
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            throw new Exception('Invalid reset token. Please request a new password reset.');
        }
        
        // Check if token is already used
        if ($tokenData['used']) {
            throw new Exception('This reset token has already been used. Please request a new password reset.');
        }
        
        // Check if token is expired (using server time)
        $currentTime = new DateTime();
        $expiresAt = new DateTime($tokenData['expires_at']);
        
        if ($currentTime > $expiresAt) {
            $minutesExpired = round(($currentTime->getTimestamp() - $expiresAt->getTimestamp()) / 60);
            throw new Exception('Reset token has expired. Please request a new password reset. (Expired ' . $minutesExpired . ' minute(s) ago)');
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $updateResult = $updateStmt->execute([$hashedPassword, $tokenData['user_id']]);
        
        if (!$updateResult) {
            throw new Exception('Failed to update password. Please try again.');
        }
        
        // Mark token as used
        $markUsedStmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
        $markUsedStmt->execute([$token]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now login with your new password.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Error resetting password: " . $e->getMessage());
        // Check if it's a table doesn't exist error
        if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
            throw new Exception('Password reset system not properly initialized. Please contact support.');
        }
        // For other database errors, provide more helpful message
        throw new Exception('Database error: ' . $e->getMessage());
    }
}
?>
