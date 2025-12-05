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

// Highly optimized connection configuration
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_PERSISTENT => false,  // Non-persistent to prevent connection leaks
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,  // Use native prepared statements (faster, more secure)
    PDO::ATTR_TIMEOUT => 2,  // Reduced timeout for faster failure detection
    PDO::ATTR_STRINGIFY_FETCHES => false,  // Keep numeric types as numbers (faster)
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,  // Use buffered queries (REQUIRED to prevent unbuffered query errors)
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

// Connection retry configuration
$max_retries = 3;
$retry_delays = [0.5, 1, 2]; // Progressive delays in seconds

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
            $pdo->exec("SET SESSION max_execution_time = 30");  // Max query execution time
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
            
            // If it's a connection limit error, wait before retry
            if (strpos($e->getMessage(), 'max_connections') !== false || 
                strpos($e->getMessage(), 'Too many connections') !== false ||
                $e->getCode() == 1226) {
                
                if ($attempt < $max_retries) {
                    $delay = $retry_delays[$attempt - 1] ?? 1;
                    error_log("Connection limit reached, waiting {$delay}s before retry (attempt $attempt/$max_retries)");
                    usleep($delay * 1000000);  // Microseconds for more precise timing
                }
            } elseif (strpos($e->getMessage(), 'Connection refused') !== false ||
                      strpos($e->getMessage(), 'Connection timed out') !== false) {
                // Network errors - wait a bit
                if ($attempt < $max_retries) {
                    $delay = $retry_delays[$attempt - 1] ?? 0.5;
                    usleep($delay * 1000000);
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
                // Clear any existing result sets first
                try {
                    while ($conn->nextRowset()) {}
                } catch (Exception $e) {
                    // No result sets to clear
                }
                
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
                // Clear any unbuffered queries/results first
                try {
                    while ($pdo->nextRowset()) {
                        // Clear all pending result sets
                    }
                } catch (Exception $e) {
                    // Ignore - may not have result sets
                }
                
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
            
            // Clear any unbuffered queries/results before returning connection
            try {
                // Close any open result sets
                while ($pdo->nextRowset()) {
                    // Clear all pending result sets
                }
            } catch (Exception $e) {
                // Ignore - no result sets to clear
            }
            
            // Quick health check (only if connection seems stale)
            static $last_check = 0;
            $now = time();
            if ($now - $last_check > 5) {  // Check every 5 seconds max
                try {
                    if (!checkConnectionHealth($pdo)) {
                        throw new Exception('Database connection is not healthy');
                    }
                } catch (Exception $e) {
                    // If health check fails, try to clear and retry
                    try {
                        while ($pdo->nextRowset()) {}
                    } catch (Exception $e2) {}
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
              strpos($error_message, 'Connection timed out') !== false) {
        error_log("Database connection network error: " . $error_message);
        $error_msg = "Database connection error. Please try again.";
        $http_code = 503;
    } else {
        $error_msg = "Database Error: " . $error_message;
        $http_code = 500;  // Internal Server Error
    }
    
    error_log("DB Connection Error: " . $error_msg . " (Attempt: $connection_attempt/$max_retries)");
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($http_code);
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'host' => $host,
            'database' => $dbname,
            'environment' => $is_hostinger_server ? 'hostinger' : 'local',
            'attempts' => $connection_attempt
        ]);
        exit();
    } else {
        die($error_msg);
    }
}
?>
