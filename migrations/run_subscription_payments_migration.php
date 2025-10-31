<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connection.php';

try {
    $conn = getConnection();
    
    // Read SQL file
    $sql_file = __DIR__ . '/006_create_subscription_payments.sql';
    if (!file_exists($sql_file)) {
        throw new Exception('SQL file not found: ' . $sql_file);
    }
    
    $sql = file_get_contents($sql_file);
    
    if ($sql === false) {
        throw new Exception('Could not read SQL file');
    }
    
    // Remove comments and empty lines
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Subscription payments table created successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

