<?php
/**
 * Database Migration System
 * This file automatically runs SQL migration files
 * Place SQL files in migrations/ folder with numbered prefixes
 */

// Include database connection from root
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit();
}

header('Content-Type: application/json');

try {
    $conn = $pdo;
    
    // Create migrations table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_migration_name (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    // Get migration directory
    $migrationDir = __DIR__ . '/migrations';
    
    if (!is_dir($migrationDir)) {
        mkdir($migrationDir, 0755, true);
        echo json_encode([
            'success' => true,
            'message' => 'Migrations directory created. Place SQL files there.',
            'migrations_run' => 0
        ]);
        exit;
    }
    
    // Get all SQL files in migrations directory
    $files = glob($migrationDir . '/*.sql');
    natsort($files);
    
    $migrationsRun = 0;
    $errors = [];
    
    foreach ($files as $file) {
        $migrationName = basename($file);
        
        // Check if migration already run
        $stmt = $conn->prepare("SELECT id FROM schema_migrations WHERE migration_name = ?");
        $stmt->execute([$migrationName]);
        
        if ($stmt->fetch()) {
            continue; // Skip already executed migrations
        }
        
        // Read and execute SQL file
        $sqlContent = file_get_contents($file);
        
        // Remove comments and split by semicolon
        $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
        
        // Split into individual statements
        $statements = explode(';', $sqlContent);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            try {
                $conn->exec($statement);
            } catch (PDOException $e) {
                // Only log actual errors (not table already exists, etc)
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate column') === false &&
                    strpos($e->getMessage(), 'Unknown column') === false) {
                    $errors[] = "Error in $migrationName: " . $e->getMessage();
                }
            }
        }
        
        // Mark migration as executed
        $stmt = $conn->prepare("INSERT INTO schema_migrations (migration_name) VALUES (?)");
        $stmt->execute([$migrationName]);
        
        $migrationsRun++;
    }
    
    echo json_encode([
        'success' => true,
        'migrations_run' => $migrationsRun,
        'total_files' => count($files),
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

