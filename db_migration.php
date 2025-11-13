<?php
/**
 * Database Migration System
 * This file automatically runs SQL migration files
 * Place SQL files in migrations/ folder with numbered prefixes
 */

// Suppress error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if (file_exists(__DIR__ . '/config/db_connection.php')) {
    require_once __DIR__ . '/config/db_connection.php';
} elseif (file_exists(__DIR__ . '/db_connection.php')) {
    require_once __DIR__ . '/db_connection.php';
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection file not found'
    ]);
    exit;
}

try {
    // Get connection
    if (isset($pdo) && $pdo instanceof PDO) {
        $conn = $pdo;
    } else {
        $conn = getConnection();
    }
    
    // Create migrations table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_migration_name (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    // Get migration directory (root migrations folder)
    $migrationDir = __DIR__ . '/migrations';
    
    if (!is_dir($migrationDir)) {
        mkdir($migrationDir, 0755, true);
        echo json_encode([
            'success' => true,
            'message' => 'Migrations directory created. Place SQL files there.',
            'migrations_run' => 0,
            'total_files' => 0,
            'errors' => []
        ]);
        exit;
    }
    
    // Get all SQL files in migrations directory
    $files = glob($migrationDir . '/*.sql');
    natsort($files);
    
    $migrationsRun = 0;
    $errors = [];
    $executedMigrations = [];
    
    foreach ($files as $file) {
        $migrationName = basename($file);
        
        // Check if migration already run
        try {
            $stmt = $conn->prepare("SELECT id FROM schema_migrations WHERE migration_name = ?");
            $stmt->execute([$migrationName]);
            
            if ($stmt->fetch()) {
                continue; // Skip already executed migrations
            }
        } catch (PDOException $e) {
            // If schema_migrations table doesn't exist yet, continue
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                // Table will be created above, so retry
                $stmt = $conn->prepare("SELECT id FROM schema_migrations WHERE migration_name = ?");
                $stmt->execute([$migrationName]);
                if ($stmt->fetch()) {
                    continue;
                }
            } else {
                $errors[] = "Error checking migration $migrationName: " . $e->getMessage();
                continue;
            }
        }
        
        // Read and execute SQL file
        $sqlContent = file_get_contents($file);
        
        if (empty(trim($sqlContent))) {
            continue;
        }
        
        // Remove comments
        $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
        
        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sqlContent)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^\/\*/', $stmt);
            }
        );
        
        $migrationErrors = [];
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            try {
                $conn->exec($statement);
            } catch (PDOException $e) {
                // Only log actual errors (not table/column already exists, etc)
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'already exists') === false && 
                    strpos($errorMsg, 'Duplicate column') === false &&
                    strpos($errorMsg, 'Unknown column') === false &&
                    strpos($errorMsg, 'Duplicate key') === false) {
                    $migrationErrors[] = $errorMsg;
                }
            }
        }
        
        // If there were real errors, don't mark as executed
        if (!empty($migrationErrors)) {
            $errors[] = "Error in $migrationName: " . implode('; ', $migrationErrors);
            continue;
        }
        
        // Mark migration as executed
        try {
            $stmt = $conn->prepare("INSERT INTO schema_migrations (migration_name) VALUES (?)");
            $stmt->execute([$migrationName]);
            $migrationsRun++;
            $executedMigrations[] = $migrationName;
        } catch (PDOException $e) {
            // If insert fails (duplicate), that's okay - migration was already run
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                $errors[] = "Error marking $migrationName as executed: " . $e->getMessage();
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'migrations_run' => $migrationsRun,
        'total_files' => count($files),
        'errors' => $errors,
        'executed_migrations' => $executedMigrations
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error in db_migration.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in db_migration.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

