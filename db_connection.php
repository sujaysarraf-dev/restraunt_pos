<?php
/**
 * Database Connection File
 * Creates PDO connection and getConnection() function
 */

// Prevent multiple includes
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

if (function_exists('getConnection')) {
    return;
}

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Hostinger Database
$host = 'auth-db1336.hstgr.io';
$dbname = 'u509616587_restrogrow';
$username = 'u509616587_restrogrow';
$password = 'Sujaysarraf@5569';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
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
    // Show actual error for debugging
    $error_msg = "Database Error: " . $e->getMessage();
    error_log($error_msg);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'host' => $host,
            'database' => $dbname
        ]);
        exit();
    } else {
        die($error_msg);
    }
}
?>
