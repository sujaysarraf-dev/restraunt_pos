<?php
/**
 * Database Connection Wrapper
 * This file redirects to the actual db_connection.php in config folder
 */
if (file_exists(__DIR__ . '/config/db_connection.php')) {
    require_once __DIR__ . '/config/db_connection.php';
} else {
    // Fallback - try to create connection directly
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
        
        function getConnection() {
            global $pdo;
            if (!$pdo) {
                throw new Exception('Database connection not initialized');
            }
            return $pdo;
        }
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please try again later.'
            ]);
            exit();
        } else {
            die("Database connection failed. Please try again later.");
        }
    }
}
?>

