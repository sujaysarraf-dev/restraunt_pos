<?php
/**
 * Create payment_methods table
 * Run this file once to create the payment methods table
 */

require_once __DIR__ . '/../db_connection.php';

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } elseif (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        // Fallback connection
        $host = 'localhost';
        $dbname = 'restro2';
        $username = 'root';
        $password = '';
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    // Create payment_methods table
    $sql = "CREATE TABLE IF NOT EXISTS payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        restaurant_id VARCHAR(50) NOT NULL,
        method_name VARCHAR(100) NOT NULL,
        emoji VARCHAR(10) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_restaurant_method (restaurant_id, method_name),
        INDEX idx_restaurant_id (restaurant_id),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->exec($sql);
    echo "✓ Table 'payment_methods' created successfully!\n";

    // Insert default payment methods with emojis
    $defaultMethods = [
        ['Cash', '💵'],
        ['Card', '💳'],
        ['UPI', '📱'],
        ['Online', '🌐'],
        ['Wallet', '👛'],
        ['Bank Transfer', '🏦'],
        ['Cheque', '📝'],
        ['Cryptocurrency', '₿']
    ];

    // Get all restaurant IDs
    $restaurants = $conn->query("SELECT DISTINCT restaurant_id FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($restaurants)) {
        // If no restaurants table, use a default
        $restaurants = ['RES001'];
    }

    $insertStmt = $conn->prepare("
        INSERT IGNORE INTO payment_methods (restaurant_id, method_name, emoji, display_order) 
        VALUES (?, ?, ?, ?)
    ");

    foreach ($restaurants as $restaurantId) {
        foreach ($defaultMethods as $index => $method) {
            $insertStmt->execute([$restaurantId, $method[0], $method[1], $index]);
        }
    }

    echo "✓ Default payment methods inserted successfully!\n";
    echo "\nPayment methods table setup complete!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>



