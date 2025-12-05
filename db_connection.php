<?php
/**
 * Database Connection File
 * Creates PDO connection and getConnection() function
 * Auto-detects environment (local vs Hostinger server)
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
     strpos($_SERVER['HTTP_HOST'] ?? '', 'hstgr.io') !== false)
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

// Use persistent connection to reuse connections and avoid max_connections limit
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_PERSISTENT => true,  // Reuse connections
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,  // Use native prepared statements
    PDO::ATTR_TIMEOUT => 5,  // Connection timeout
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Force UTF-8 encoding for all queries
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    
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
        error_log("Database connection limit exceeded. Waiting and retrying...");
        
        // Wait a bit and try once more with non-persistent connection
        sleep(1);
        try {
            $options[PDO::ATTR_PERSISTENT] = false;  // Try non-persistent as fallback
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET CHARACTER SET utf8mb4");
            
            if (!function_exists('getConnection')) {
                function getConnection() {
                    global $pdo;
                    if (!isset($pdo) || !($pdo instanceof PDO)) {
                        throw new Exception('Database connection not initialized');
                    }
                    return $pdo;
                }
            }
            return;  // Success, exit early
        } catch (PDOException $e2) {
            // Still failed, show error
            $error_msg = "Database Error: Too many connections. Please try again in a moment.";
            error_log("Database connection failed after retry: " . $e2->getMessage());
        }
    } else {
        $error_msg = "Database Error: " . $error_message;
    }
    
    error_log($error_msg);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
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
