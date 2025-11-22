<?php
/**
 * Authorization and Access Control System
 * Provides role-based access control (RBAC) for API endpoints
 */

// Prevent direct access
if (!defined('AUTHORIZATION_LOADED')) {
    define('AUTHORIZATION_LOADED', true);
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) || isset($_SESSION['staff_id']) || isset($_SESSION['admin_id']);
}

/**
 * Get current user type
 * Returns: 'admin', 'staff', or null
 */
function getUserType() {
    if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
        return 'admin';
    } elseif (isset($_SESSION['staff_id'])) {
        return 'staff';
    }
    return null;
}

/**
 * Get current user role (for staff)
 * Returns: role string or 'Admin' for admin users
 */
function getUserRole() {
    if (isset($_SESSION['role'])) {
        return $_SESSION['role'];
    }
    if (getUserType() === 'admin') {
        return 'Admin';
    }
    return null;
}

/**
 * Get current restaurant ID
 */
function getRestaurantId() {
    return $_SESSION['restaurant_id'] ?? null;
}

/**
 * Get current user ID
 */
function getUserId() {
    return $_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? $_SESSION['admin_id'] ?? null;
}

/**
 * Check if user has required role
 * @param string|array $requiredRoles - Single role or array of allowed roles
 * @return bool
 */
function hasRole($requiredRoles) {
    $userRole = getUserRole();
    if (!$userRole) {
        return false;
    }
    
    // Normalize to array
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    
    // Admin has access to everything
    if ($userRole === 'Admin') {
        return true;
    }
    
    return in_array($userRole, $requiredRoles);
}

/**
 * Check if user can access restaurant data
 * @param int|null $restaurantId - Restaurant ID to check (null = current user's restaurant)
 * @return bool
 */
function canAccessRestaurant($restaurantId = null) {
    $userRestaurantId = getRestaurantId();
    if (!$userRestaurantId) {
        return false;
    }
    
    // If no specific restaurant ID provided, check current user's restaurant
    if ($restaurantId === null) {
        return true;
    }
    
    // User can only access their own restaurant's data
    return $userRestaurantId == $restaurantId;
}

/**
 * Require authentication - throws exception if not authenticated
 * @throws Exception
 */
function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required. Please login to continue.',
            'data' => []
        ]);
        exit();
    }
}

/**
 * Require specific role(s) - throws exception if user doesn't have required role
 * @param string|array $requiredRoles - Single role or array of allowed roles
 * @throws Exception
 */
function requireRole($requiredRoles) {
    requireAuth();
    
    if (!hasRole($requiredRoles)) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $roles = is_array($requiredRoles) ? implode(', ', $requiredRoles) : $requiredRoles;
        echo json_encode([
            'success' => false,
            'message' => "Access denied. Required role: {$roles}",
            'data' => []
        ]);
        exit();
    }
}

/**
 * Require admin access - only admin users can access
 * @throws Exception
 */
function requireAdmin() {
    requireAuth();
    
    if (getUserType() !== 'admin') {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Admin privileges required.',
            'data' => []
        ]);
        exit();
    }
}

/**
 * Require restaurant access - checks if user can access the restaurant
 * @param int|null $restaurantId - Restaurant ID to check
 * @throws Exception
 */
function requireRestaurantAccess($restaurantId = null) {
    requireAuth();
    
    $userRestaurantId = getRestaurantId();
    if (!$userRestaurantId) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Restaurant ID not found in session.',
            'data' => []
        ]);
        exit();
    }
    
    // If specific restaurant ID provided, verify access
    if ($restaurantId !== null && !canAccessRestaurant($restaurantId)) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. You do not have permission to access this restaurant\'s data.',
            'data' => []
        ]);
        exit();
    }
}

/**
 * Check if staff member can perform action
 * Some actions are restricted to specific roles
 * @param string $action - Action to check (e.g., 'manage_staff', 'manage_menu', 'view_reports')
 * @return bool
 */
function canPerformAction($action) {
    $userRole = getUserRole();
    if (!$userRole) {
        return false;
    }
    
    // Admin can do everything
    if ($userRole === 'Admin') {
        return true;
    }
    
    // Define role permissions
    $permissions = [
        'Manager' => [
            'manage_staff',
            'manage_menu',
            'manage_tables',
            'manage_areas',
            'view_reports',
            'manage_customers',
            'manage_reservations',
            'manage_orders',
            'manage_payments'
        ],
        'Chef' => [
            'manage_menu',
            'view_orders',
            'update_order_status'
        ],
        'Waiter' => [
            'view_menu',
            'create_orders',
            'view_orders',
            'manage_tables',
            'view_customers',
            'create_reservations'
        ],
        'Cashier' => [
            'view_orders',
            'manage_payments',
            'view_reports'
        ]
    ];
    
    // Check if role has permission
    if (isset($permissions[$userRole])) {
        return in_array($action, $permissions[$userRole]);
    }
    
    return false;
}

/**
 * Require specific action permission
 * @param string $action - Action to check
 * @throws Exception
 */
function requireAction($action) {
    requireAuth();
    
    if (!canPerformAction($action)) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => "Access denied. You do not have permission to perform this action: {$action}",
            'data' => []
        ]);
        exit();
    }
}

/**
 * Get authorization context for logging/auditing
 * @return array
 */
function getAuthContext() {
    return [
        'user_id' => getUserId(),
        'user_type' => getUserType(),
        'role' => getUserRole(),
        'restaurant_id' => getRestaurantId(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
?>

