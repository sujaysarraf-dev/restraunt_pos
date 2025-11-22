<?php
/**
 * Secure Database Fallback Connection Helper
 * Use this instead of hardcoding credentials in fallback connection code
 */

// Prevent direct access
if (!defined('DB_FALLBACK_LOADED')) {
    define('DB_FALLBACK_LOADED', true);
}

/**
 * Get secure database connection for fallback scenarios
 * This function should be used when db_connection.php is not available
 */
function getSecureFallbackConnection() {
    // Try to use the secure database config
    if (file_exists(__DIR__ . '/database_config.php')) {
        require_once __DIR__ . '/database_config.php';
        if (function_exists('createSecurePDOConnection')) {
            return createSecurePDOConnection();
        }
    }
    
    // Fallback to environment variables
    $host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'localhost');
    $dbname = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'restro2');
    $username = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root');
    $password = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: '');
    
    // Check if we're in development mode
    $isDevelopment = getenv('APP_ENV') === 'development' || 
                     getenv('APP_ENV') === 'dev' ||
                     (defined('APP_ENV') && APP_ENV === 'development');
    
    // In production, require environment variables
    if (!$isDevelopment && ($username === 'root' && $password === '')) {
        throw new Exception('Database credentials must be set via environment variables in production. Set DB_HOST, DB_NAME, DB_USER, and DB_PASS.');
    }
    
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database fallback connection error: " . $e->getMessage());
        throw new Exception('Database connection failed. Please check your database settings.');
    }
}
?>

