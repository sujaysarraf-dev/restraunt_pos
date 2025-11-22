<?php
/**
 * Secure Database Configuration
 * Reads credentials from environment variables or .env file
 * Falls back to secure defaults only in development
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
}

/**
 * Get database configuration
 * Priority: Environment variables > .env file > secure defaults (dev only)
 */
function getDatabaseConfig() {
    // Try environment variables first (production)
    $host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: null);
    $dbname = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: null);
    $username = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: null);
    $password = getenv('DB_PASS');
    if ($password === false) {
        $password = getenv('MYSQL_PASSWORD');
        if ($password === false) {
            $password = null;
        }
    }
    
    // If not in environment, try .env file
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile) && is_readable($envFile)) {
        $envVars = parse_ini_file($envFile);
        if ($envVars) {
            // Only use .env values if environment variables are not set
            if ($host === null) {
                $host = $envVars['DB_HOST'] ?? null;
            }
            if ($dbname === null) {
                $dbname = $envVars['DB_NAME'] ?? null;
            }
            if ($username === null) {
                $username = $envVars['DB_USER'] ?? null;
            }
            if ($password === null) {
                $password = $envVars['DB_PASS'] ?? null;
            }
        }
    }
    
    // Development fallback (only if explicitly in development mode)
    // In production, this should never be used
    $isDevelopment = getenv('APP_ENV') === 'development' || 
                     getenv('APP_ENV') === 'dev' ||
                     (defined('APP_ENV') && APP_ENV === 'development');
    
    // Validate required fields
    if ($host === null || $dbname === null || $username === null) {
        if ($isDevelopment) {
            // Development defaults (XAMPP)
            $host = $host ?? 'localhost';
            $dbname = $dbname ?? 'restro2';
            $username = $username ?? 'root';
            $password = $password ?? '';
            
            // Log warning in development
            error_log("WARNING: Using development database defaults. Set DB_HOST, DB_NAME, DB_USER, DB_PASS environment variables for production.");
        } else {
            // Production: throw error if credentials not set
            throw new Exception('Database credentials not configured. Please set DB_HOST, DB_NAME, DB_USER, and DB_PASS environment variables.');
        }
    } else {
        // If all required fields are set, ensure password is at least an empty string
        if ($password === null) {
            $password = '';
        }
    }
    
    return [
        'host' => $host,
        'dbname' => $dbname,
        'username' => $username,
        'password' => $password
    ];
}

/**
 * Create PDO connection using secure configuration
 */
function createSecurePDOConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception('Database connection failed. Please check your database settings.');
    }
}
?>

