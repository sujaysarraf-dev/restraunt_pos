<?php
/**
 * Session Configuration with Timeout
 * 
 * This file configures secure session settings including:
 * - Secure cookie parameters (HttpOnly, Secure, SameSite)
 * - Session timeout (default: 30 minutes of inactivity)
 * - Session ID regeneration
 * - Session validation
 */

// Prevent multiple includes
if (defined('SESSION_CONFIG_LOADED')) {
    return;
}
define('SESSION_CONFIG_LOADED', true);

// Set UTF-8 encoding for all output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Session timeout in seconds (60 minutes default - increased from 30 to prevent premature timeouts)
define('SESSION_TIMEOUT', 60 * 60);

// Configure secure session settings BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0); // Session cookie expires when browser closes
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT); // Garbage collection timeout

/**
 * Start secure session with timeout checking
 * 
 * @return void
 */
function startSecureSession($skipTimeoutValidation = false) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Session already started, just validate timeout if not skipped
        if (!$skipTimeoutValidation) {
            validateSessionTimeout();
        }
        return;
    }
    
    session_start();
    
    // Initialize session timeout tracking
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
    
    // Validate session timeout only if not skipped (for public pages like customer website)
    if (!$skipTimeoutValidation) {
        validateSessionTimeout();
    }
    
    // Regenerate session ID periodically (every 5 minutes) to prevent session fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 5 * 60) {
        // Regenerate session ID every 5 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Validate session timeout
 * Destroys session if timeout exceeded
 * 
 * @return bool True if session is valid, false if expired
 */
function validateSessionTimeout() {
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check if session has timed out
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // Session expired
        destroySession();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Check if session is valid (not expired)
 * Also updates last_activity if session is valid to prevent premature timeout
 * 
 * @return bool True if session is valid
 */
function isSessionValid() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    $isValid = (time() - $_SESSION['last_activity']) <= SESSION_TIMEOUT;
    
    // Update last activity if session is still valid (prevents premature timeout)
    if ($isValid) {
        $_SESSION['last_activity'] = time();
    }
    
    return $isValid;
}

/**
 * Destroy session securely
 * 
 * @return void
 */
function destroySession() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Regenerate session ID after login (call this after successful authentication)
 * 
 * @return void
 */
function regenerateSessionAfterLogin() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Get remaining session time in seconds
 * 
 * @return int Remaining seconds, or 0 if expired
 */
function getRemainingSessionTime() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

// Auto-start session if not already started (for backward compatibility)
// Note: Individual files should call startSecureSession() explicitly
// This is here as a fallback
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}
?>

