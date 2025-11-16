<?php
/**
 * Database Configuration for Website
 * This file provides database connection for the website API
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Try to use the main db_connection.php first
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
    // The main db_connection.php may set up $pdo or getConnection()
    // We need to ensure $pdo is available
    if (!isset($pdo) && function_exists('getConnection')) {
        try {
            $pdo = getConnection();
        } catch (Exception $e) {
            error_log("Error getting connection: " . $e->getMessage());
        }
    }
}

// If $pdo is not set, create it directly
if (!isset($pdo)) {
    $host = 'localhost';
    $dbname = 'restro2';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Database connection failed. Please check your database configuration.'
            ]);
            exit();
        } else {
            die("Database connection failed. Please check your database configuration.");
        }
    }
}
?>

