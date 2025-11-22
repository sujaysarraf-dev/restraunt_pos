<?php
/**
 * Centralized Error Handling System
 * Provides consistent error handling, logging, and monitoring
 */

// Error log directory
define('ERROR_LOG_DIR', __DIR__ . '/../logs');
define('SECURITY_LOG_FILE', ERROR_LOG_DIR . '/security.log');
define('ERROR_LOG_FILE', ERROR_LOG_DIR . '/errors.log');
define('GENERAL_LOG_FILE', ERROR_LOG_DIR . '/general.log');

// Create logs directory if it doesn't exist
if (!is_dir(ERROR_LOG_DIR)) {
    mkdir(ERROR_LOG_DIR, 0755, true);
}

/**
 * Log error with different severity levels
 */
function logError($message, $severity = 'ERROR', $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$severity}] {$message}";
    
    if (!empty($context)) {
        $logEntry .= " | Context: " . json_encode($context);
    }
    
    $logEntry .= PHP_EOL;
    
    // Determine log file based on severity
    $logFile = ERROR_LOG_FILE;
    if ($severity === 'SECURITY' || $severity === 'SECURITY_WARNING') {
        $logFile = SECURITY_LOG_FILE;
    }
    
    // Append to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also log to PHP error log
    error_log($logEntry);
}

/**
 * Log security-related errors separately
 */
function logSecurityError($message, $context = []) {
    logError($message, 'SECURITY', $context);
}

/**
 * Log general errors
 */
function logGeneralError($message, $context = []) {
    logError($message, 'ERROR', $context);
}

/**
 * Log warnings
 */
function logWarning($message, $context = []) {
    logError($message, 'WARNING', $context);
}

/**
 * Log info messages
 */
function logInfo($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [INFO] {$message}";
    
    if (!empty($context)) {
        $logEntry .= " | Context: " . json_encode($context);
    }
    
    $logEntry .= PHP_EOL;
    
    file_put_contents(GENERAL_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Handle and format errors for API responses
 */
function handleError($exception, $showDetails = false) {
    // Log the error
    $errorMessage = $exception->getMessage();
    $errorCode = $exception->getCode() ?: 500;
    $trace = $exception->getTraceAsString();
    
    // Determine if this is a security-related error
    $isSecurityError = (
        $exception instanceof PDOException ||
        strpos($errorMessage, 'SQL') !== false ||
        strpos($errorMessage, 'database') !== false ||
        strpos($errorMessage, 'authentication') !== false ||
        strpos($errorMessage, 'authorization') !== false ||
        strpos($errorMessage, 'unauthorized') !== false ||
        strpos($errorMessage, 'forbidden') !== false
    );
    
    if ($isSecurityError) {
        logSecurityError($errorMessage, [
            'code' => $errorCode,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'trace' => $trace
        ]);
    } else {
        logGeneralError($errorMessage, [
            'code' => $errorCode,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace
        ]);
    }
    
    // Set HTTP status code
    http_response_code($errorCode >= 400 && $errorCode < 600 ? $errorCode : 500);
    
    // Prepare response
    $response = [
        'success' => false,
        'message' => $showDetails ? $errorMessage : 'An error occurred. Please try again later.'
    ];
    
    // Add error code for debugging (only in development)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $response['error_code'] = $errorCode;
        $response['debug_message'] = $errorMessage;
    }
    
    return $response;
}

/**
 * Handle PDO exceptions specifically
 */
function handleDatabaseError($exception) {
    // Log security error for database issues
    logSecurityError('Database error occurred', [
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'anonymous'
    ]);
    
    http_response_code(500);
    
    return [
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ];
}

/**
 * Handle validation errors
 */
function handleValidationError($message, $field = null) {
    logWarning('Validation error', [
        'message' => $message,
        'field' => $field,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    http_response_code(400);
    
    return [
        'success' => false,
        'message' => $message,
        'field' => $field
    ];
}

/**
 * Handle authentication errors
 */
function handleAuthError($message = 'Authentication required') {
    logSecurityError('Authentication error', [
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(401);
    
    return [
        'success' => false,
        'message' => $message
    ];
}

/**
 * Handle authorization errors
 */
function handleAuthorizationError($message = 'Access denied') {
    logSecurityError('Authorization error', [
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'attempted_action' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
    
    http_response_code(403);
    
    return [
        'success' => false,
        'message' => $message
    ];
}

/**
 * Set up global error handler
 */
function setupErrorHandler() {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        logGeneralError($message, [
            'severity' => $severity,
            'file' => $file,
            'line' => $line
        ]);
        
        return true;
    });
    
    set_exception_handler(function($exception) {
        $response = handleError($exception);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    });
    
    // Log fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            logGeneralError($error['message'], [
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    });
}

/**
 * Clean old log files (older than specified days)
 */
function cleanOldLogs($daysToKeep = 30) {
    $logDir = ERROR_LOG_DIR;
    $files = glob($logDir . '/*.log');
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            @unlink($file);
        }
    }
}

// Auto-cleanup old logs (1% chance on each request to avoid overhead)
if (rand(1, 100) === 1) {
    cleanOldLogs(30);
}

