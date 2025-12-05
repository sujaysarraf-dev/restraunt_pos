<?php
/**
 * Database Connection File - Optimized for High Concurrency
 * Creates PDO connection and getConnection() function
 * Auto-detects environment (local vs Hostinger server)
 * 
 * Features:
 * - Connection pooling and reuse
 * - Automatic connection cleanup
 * - Error handling and retry logic
 * - Optimized for 100+ concurrent users
 */

// Prevent multiple includes
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

if (function_exists('getConnection')) {
    return;
}

// Enable error display for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Auto-detect if running on Hostinger server
// On Hostinger, use localhost; from local machine, use remote host
$is_hostinger_server = (
    isset($_SERVER['SERVER_NAME']) && 
    (strpos($_SERVER['SERVER_NAME'], 'hstgr.io') !== false || 
     strpos($_SERVER['SERVER_NAME'], 'hostinger') !== false ||
     strpos($_SERVER['SERVER_NAME'], 'restrogrow.com') !== false ||
     strpos($_SERVER['HTTP_HOST'] ?? '', 'hstgr.io') !== false ||
     strpos($_SERVER['HTTP_HOST'] ?? '', 'restrogrow.com') !== false)
);

if ($is_hostinger_server) {
    // Running ON Hostinger server - use localhost
    $host = 'localhost';
} else {
    // Running from local machine - use remote host (if remote access enabled)
    $host = 'auth-db1336.hstgr.io';
}

$dbname = 'u509616587_restrogrow';
$username = 'u509616587_restrogrow';
$password = 'Sujaysarraf@5569';

// Connection configuration optimized for high concurrency
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_PERSISTENT => false,  // Non-persistent to prevent connection leaks
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,  // Use native prepared statements (faster)
    PDO::ATTR_TIMEOUT => 3,  // Reduced timeout for faster failure detection
    PDO::ATTR_STRINGIFY_FETCHES => false,  // Keep numeric types as numbers
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,  // Use buffered queries
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",  // Set charset immediately
];

// Connection retry configuration
$max_retries = 2;
$retry_delay = 1; // seconds

try {
    $pdo = null;
    $last_error = null;
    
    // Retry logic for connection failures
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            
            // Force UTF-8 encoding for all queries
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET CHARACTER SET utf8mb4");
            
            // Set connection timeout for queries (prevent hanging)
            $pdo->exec("SET SESSION wait_timeout = 60");  // Close idle connections after 60s
            $pdo->exec("SET SESSION interactive_timeout = 60");
            
            // Success - break out of retry loop
            break;
            
        } catch (PDOException $e) {
            $last_error = $e;
            
            // If it's a connection limit error, wait longer before retry
            if (strpos($e->getMessage(), 'max_connections') !== false || $e->getCode() == 1226) {
                if ($attempt < $max_retries) {
                    error_log("Connection limit reached, waiting before retry (attempt $attempt/$max_retries)");
                    sleep($retry_delay * $attempt);  // Exponential backoff
                }
            } else {
                // Other errors - don't retry
                throw $e;
            }
        }
    }
    
    // If still no connection after retries, throw error
    if (!$pdo || !($pdo instanceof PDO)) {
        throw $last_error ?? new Exception('Failed to establish database connection');
    }
    
    // Set connection to close automatically when script ends
    register_shutdown_function(function() use (&$pdo) {
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                // Close any open transactions
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                // Ignore errors during cleanup
            }
            $pdo = null;  // Close connection
        }
    });
    
    if (!function_exists('getConnection')) {
        function getConnection() {
            global $pdo;
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                throw new Exception('Database connection not initialized');
            }
            return $pdo;
        }
    }
    
} catch (PDOException $e) {
    // Handle max_connections error specifically
    $error_code = $e->getCode();
    $error_message = $e->getMessage();
    
    // Check if it's a connection limit error
    if (strpos($error_message, 'max_connections') !== false || $error_code == 1226) {
        error_log("Database connection limit exceeded after all retries");
        $error_msg = "Database temporarily unavailable due to high traffic. Please try again in a moment.";
    } else {
        $error_msg = "Database Error: " . $error_message;
    }
    
    error_log($error_msg);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(503);  // Service Unavailable
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'host' => $host,
            'database' => $dbname,
            'environment' => $is_hostinger_server ? 'hostinger' : 'local'
        ]);
        exit();
    } else {
        die($error_msg);
    }
}
?>
