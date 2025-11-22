<?php
/**
 * CSRF Protection System
 * Generates and validates CSRF tokens for all state-changing operations
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    if (file_exists(__DIR__ . '/session_config.php')) {
        require_once __DIR__ . '/session_config.php';
        configureSecureSession();
    } else {
        session_start();
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token
 */
function getCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        return generateCSRFToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Use hash_equals for timing-safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate CSRF token (call after successful operations)
 */
function regenerateCSRFToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token as hidden input HTML
 */
function getCSRFTokenInput() {
    $token = getCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Get CSRF token as meta tag (for JavaScript)
 */
function getCSRFTokenMeta() {
    $token = getCSRFToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST request
 */
function validateCSRFPost() {
    $token = $_POST['csrf_token'] ?? '';
    
    if (empty($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'CSRF token missing. Please refresh the page and try again.'
        ]);
        exit();
    }
    
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token. Please refresh the page and try again.'
        ]);
        exit();
    }
    
    return true;
}

/**
 * Validate CSRF token from GET request (for AJAX)
 */
function validateCSRFGet() {
    $token = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (empty($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'CSRF token missing. Please refresh the page and try again.'
        ]);
        exit();
    }
    
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token. Please refresh the page and try again.'
        ]);
        exit();
    }
    
    return true;
}

/**
 * Validate CSRF token from request (auto-detect method)
 */
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return validateCSRFPost();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // For PUT/DELETE/PATCH, check headers or request body
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        
        if (empty($token)) {
            // Try to get from request body for PUT/DELETE
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $token = $data['csrf_token'] ?? '';
        }
        
        if (empty($token) || !validateCSRFToken($token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token. Please refresh the page and try again.'
            ]);
            exit();
        }
        
        return true;
    }
    
    // GET requests don't need CSRF validation (idempotent)
    return true;
}

/**
 * Check if request needs CSRF validation (state-changing operations)
 */
function requiresCSRF($method) {
    $stateChangingMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
    return in_array(strtoupper($method), $stateChangingMethods);
}

