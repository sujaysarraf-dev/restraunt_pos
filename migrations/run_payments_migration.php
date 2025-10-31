<?php
header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$dbname = 'restro2';
$username = 'root';
$password = '';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Select database
    $pdo->exec("USE $dbname");
    
    // Read migration file
    $migrationFile = __DIR__ . '/002_create_payments_table.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception('Migration file not found: ' . $migrationFile);
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty($sql)) {
        throw new Exception('Migration file is empty');
    }
    
    // Execute migration
    $pdo->exec($sql);
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo json_encode([
            'success' => true,
            'message' => 'Payments table created successfully!',
            'details' => 'Table structure created with all required fields.'
        ]);
    } else {
        throw new Exception('Table creation failed - table does not exist after migration');
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'details' => 'Check your database connection and permissions.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
}
?>

