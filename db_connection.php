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

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$host = 'localhost';
$dbname = 'restro2';
$username = 'root';
$password = '';

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
    error_log("Database connection error: " . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed. Please check your database settings.'
        ]);
        exit();
    } else {
        die("Database connection failed. Please check your database settings.");
    }
}
?>
