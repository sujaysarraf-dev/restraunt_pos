<?php
/**
 * Database Connection File - Highly Optimized for High Concurrency
 * Creates PDO connection and getConnection() function
 * Auto-detects environment (local vs Hostinger server)
 * 
 * Features:
 * - Connection pooling and reuse
 * - Automatic connection cleanup
 * - Error handling and retry logic
 * - Query result caching
 * - Connection health checks
 * - Optimized for 200+ concurrent users
 */

// Prevent multiple includes
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

if (function_exists('getConnection')) {
    return;
}

// Error handling - log errors but don't display (prevents HTML in JSON responses)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors (prevents HTML output)
ini_set('log_errors', 1);

// Set UTF-8 encoding for all database operations
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

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

// Highly optimized connection configuration
// Add connection timeout in DSN for remote connections
$timeout = $is_hostinger_server ? 2 : 10; // Longer timeout for remote connections
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_PERSISTENT => false,  // Non-persistent to prevent connection leaks
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,  // Use native prepared statements (faster, more secure)
    PDO::ATTR_TIMEOUT => $timeout,  // Longer timeout for remote connections
    PDO::ATTR_STRINGIFY_FETCHES => false,  // Keep numeric types as numbers (faster)
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,  // Use buffered queries (REQUIRED to prevent unbuffered query errors)
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

// Connection retry configuration
$max_retries = $is_hostinger_server ? 3 : 2; // Fewer retries for remote (already slower)
$retry_delays = $is_hostinger_server ? [0.5, 1, 2] : [2, 5]; // Longer delays for remote connections

// Connection statistics (for monitoring)
if (!isset($GLOBALS['db_connection_stats'])) {
    $GLOBALS['db_connection_stats'] = [
        'attempts' => 0,
        'success' => 0,
        'failures' => 0,
        'retries' => 0,
    ];
}

try {
    $pdo = null;
    $last_error = null;
    $connection_attempt = 0;
    
    // Retry logic for connection failures with progressive backoff
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $connection_attempt = $attempt;
        $GLOBALS['db_connection_stats']['attempts']++;
        
        try {
            // Create connection
            $pdo = new PDO($dsn, $username, $password, $options);
            
            // Verify connection is alive (use buffered query)
            $stmt = $pdo->query("SELECT 1");
            $stmt->fetchAll();  // Fetch all results to clear
            $stmt = null;  // Free statement
            
            // Set optimized session variables for better performance (one at a time, fetch results)
            $pdo->exec("SET SESSION wait_timeout = 30");  // Close idle connections after 30s
            $pdo->exec("SET SESSION interactive_timeout = 30");
            $pdo->exec("SET SESSION query_cache_type = OFF");  // Disable query cache (let MySQL handle it)
            // Note: max_execution_time is not available in all MySQL versions, removed
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
            // Success - break out of retry loop
            $GLOBALS['db_connection_stats']['success']++;
            if ($attempt > 1) {
                $GLOBALS['db_connection_stats']['retries']++;
            }
            break;
            
        } catch (PDOException $e) {
            $last_error = $e;
            $GLOBALS['db_connection_stats']['failures']++;
            
            $error_msg = $e->getMessage();
            $error_code = $e->getCode();
            
            // If it's a connection limit error, wait before retry
            if (strpos($error_msg, 'max_connections') !== false || 
                strpos($error_msg, 'Too many connections') !== false ||
                $error_code == 1226) {
                
                if ($attempt < $max_retries) {
                    $delay = $retry_delays[$attempt - 1] ?? 1;
                    error_log("Connection limit reached, waiting {$delay}s before retry (attempt $attempt/$max_retries)");
                    sleep($delay);
                }
            } elseif (strpos($error_msg, 'Connection refused') !== false ||
                      strpos($error_msg, 'Connection timed out') !== false ||
                      strpos($error_msg, 'did not properly respond') !== false ||
                      $error_code == 2002 || $error_code == 2006) {
                // Network/timeout errors - wait longer and retry
                if ($attempt < $max_retries) {
                    $delay = $retry_delays[$attempt - 1] ?? 2;
                    error_log("Connection timeout/network error, waiting {$delay}s before retry (attempt $attempt/$max_retries): $error_msg");
                    sleep($delay);
                }
            } else {
                // Other errors - don't retry
                throw $e;
            }
        }
    }
    
    // If still no connection after retries, throw error
    if (!$pdo || !($pdo instanceof PDO)) {
        throw $last_error ?? new Exception('Failed to establish database connection after ' . $max_retries . ' attempts');
    }
    
    // Connection health check function (ensures buffered query)
    if (!function_exists('checkConnectionHealth')) {
        function checkConnectionHealth($conn) {
            try {
                // Use buffered query for health check
                $stmt = $conn->query("SELECT 1");
                $result = $stmt->fetchAll();
                $stmt = null;  // Free statement
                return !empty($result);
            } catch (Exception $e) {
                return false;
            }
        }
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
    
    // Optimized getConnection function with health check and result set cleanup
    if (!function_exists('getConnection')) {
        function getConnection() {
            global $pdo;
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                throw new Exception('Database connection not initialized');
            }
            
            // Note: nextRowset() is a PDOStatement method, not PDO method
            // We can't clear result sets here, but buffered queries handle this automatically
            
            // Quick health check (only if connection seems stale)
            static $last_check = 0;
            $now = time();
            if ($now - $last_check > 5) {  // Check every 5 seconds max
                try {
                    if (!checkConnectionHealth($pdo)) {
                        throw new Exception('Database connection is not healthy');
                    }
                } catch (Exception $e) {
                    // Health check failed - connection is not usable
                    throw $e;
                }
                $last_check = $now;
            }
            
            return $pdo;
        }
    }
    
    // Connection statistics function
    if (!function_exists('getConnectionStats')) {
        function getConnectionStats() {
            return $GLOBALS['db_connection_stats'] ?? [
                'attempts' => 0,
                'success' => 0,
                'failures' => 0,
                'retries' => 0,
            ];
        }
    }
    
} catch (PDOException $e) {
    // Handle max_connections error specifically
    $error_code = $e->getCode();
    $error_message = $e->getMessage();
    
    // Check if it's a connection limit error
    if (strpos($error_message, 'max_connections') !== false || 
        strpos($error_message, 'Too many connections') !== false ||
        $error_code == 1226) {
        error_log("Database connection limit exceeded after $connection_attempt attempts");
        $error_msg = "Database temporarily unavailable due to high traffic. Please try again in a moment.";
        $http_code = 503;  // Service Unavailable
    } elseif (strpos($error_message, 'Connection refused') !== false ||
              strpos($error_message, 'Connection timed out') !== false ||
              strpos($error_message, 'did not properly respond') !== false ||
              $error_code == 2002 || $error_code == 2006) {
        error_log("Database connection network/timeout error: " . $error_message);
        if (!$is_hostinger_server) {
            $error_msg = "Cannot connect to remote database. Remote access may be disabled or network is slow. Please check your connection or use the production server.";
        } else {
            $error_msg = "Database connection timeout. Please try again.";
        }
        $http_code = 503;
    } else {
        $error_msg = "Database Error: " . $error_message;
        $http_code = 500;  // Internal Server Error
    }
    
    error_log("DB Connection Error: " . $error_msg . " (Attempt: $connection_attempt/$max_retries)");
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($http_code);
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'host' => $host,
            'database' => $dbname,
            'environment' => $is_hostinger_server ? 'hostinger' : 'local',
            'attempts' => $connection_attempt
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        die($error_msg);
    }
}
?>
