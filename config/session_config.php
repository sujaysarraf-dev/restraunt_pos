<?php
/**
 * Secure Session Configuration
 * Sets up secure session parameters and handles session timeout
 */

// Custom session name (prevents session fixation)
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'RESTAURANT_POS_SESSION');
}

// Session timeout in seconds (30 minutes)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800); // 30 minutes
}

/**
 * Configure secure session parameters
 */
function configureSecureSession() {
    // Set custom session name
    session_name(SESSION_NAME);
    
    // Configure secure session cookie parameters
    $cookieParams = [
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only in production
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Strict' // CSRF protection
    ];
    
    session_set_cookie_params($cookieParams);
    
    // Additional session security settings
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $cookieParams['secure'] ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Prevent session ID in URLs
    ini_set('session.use_trans_sid', 0);
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check session timeout
    checkSessionTimeout();
}

/**
 * Check if session has timed out
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        // Check if session has expired
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            // Session expired, destroy it
            session_unset();
            session_destroy();
            
            // Start new session
            session_start();
            
            // Set expired flag
            $_SESSION['session_expired'] = true;
        } else {
            // Update last activity time
            $_SESSION['last_activity'] = time();
        }
    } else {
        // First time, set last activity
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Regenerate session ID (call after successful login)
 */
function regenerateSessionId() {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Check if session is valid (user is logged in)
 */
function isSessionValid() {
    // Check if session exists and has required data
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check if session has timed out
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        return false;
    }
    
    // Check if user is logged in (admin or staff)
    return isset($_SESSION['user_id']) || isset($_SESSION['staff_id']) || isset($_SESSION['superadmin_id']);
}

/**
 * Destroy session securely
 */
function destroySession() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

