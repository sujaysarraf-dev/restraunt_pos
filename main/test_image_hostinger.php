<?php
/**
 * Test Image Loading on Hostinger - Detailed Diagnostics
 * This will help identify why images aren't loading
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/db_connection.php';

echo "<h2>Image Loading Diagnostics - Hostinger</h2>";
echo "<pre style='font-family: Consolas, monospace; background: #1e1e1e; color: #0f0; padding: 20px; white-space: pre-wrap;'>";

try {
    // Test 1: Database Connection
    echo "=== Test 1: Database Connection ===\n";
    echo "Database: " . $dbname . "\n";
    echo "Host: " . $host . "\n";
    echo "Connection: " . ($pdo ? "✓ Connected" : "✗ Failed") . "\n\n";
    
    // Test 2: Check if image_data column exists
    echo "=== Test 2: Database Schema Check ===\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_data'");
        $hasImageData = $stmt->rowCount() > 0;
        echo "image_data column: " . ($hasImageData ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
        
        $stmt = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_mime_type'");
        $hasMimeType = $stmt->rowCount() > 0;
        echo "image_mime_type column: " . ($hasMimeType ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
        
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'logo_data'");
        $hasLogoData = $stmt->rowCount() > 0;
        echo "logo_data column: " . ($hasLogoData ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
    } catch (PDOException $e) {
        echo "Error checking schema: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 3: Check menu items with images
    echo "=== Test 3: Menu Items with Images ===\n";
    $stmt = $pdo->query("SELECT id, item_name_en, item_image FROM menu_items WHERE item_image IS NOT NULL AND item_image != '' LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        echo "Item #{$item['id']} ({$item['item_name_en']}):\n";
        echo "  item_image: {$item['item_image']}\n";
        
        // Check for BLOB data
        try {
            $blobStmt = $pdo->prepare("SELECT 
                CASE WHEN image_data IS NOT NULL THEN LENGTH(image_data) ELSE 0 END as blob_size,
                image_mime_type 
                FROM menu_items WHERE id = ?");
            $blobStmt->execute([$item['id']]);
            $blobInfo = $blobStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($blobInfo && $blobInfo['blob_size'] > 0) {
                echo "  ✓ BLOB EXISTS: " . number_format($blobInfo['blob_size']) . " bytes, MIME: " . ($blobInfo['image_mime_type'] ?? 'unknown') . "\n";
                echo "  API URL: /api/image.php?path=" . urlencode($item['item_image']) . "\n";
                echo "  Expected: Should serve from BLOB\n";
            } else {
                echo "  ✗ No BLOB data\n";
                echo "  API URL: /api/image.php?path=" . urlencode($item['item_image']) . "\n";
                echo "  Expected: Should serve from file system\n";
            }
        } catch (PDOException $e) {
            echo "  ⚠ Error checking BLOB: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    // Test 4: Test actual API call simulation
    echo "=== Test 4: Simulating API Call ===\n";
    if (!empty($items)) {
        $testItem = $items[0];
        $testPath = $testItem['item_image'];
        echo "Testing path: {$testPath}\n";
        
        // Simulate the BLOB check query from image.php
        try {
            $testStmt = $pdo->prepare("SELECT image_data, image_mime_type FROM menu_items WHERE item_image = ? AND image_data IS NOT NULL LIMIT 1");
            $testStmt->execute([$testPath]);
            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($testResult && !empty($testResult['image_data'])) {
                $blobSize = strlen($testResult['image_data']);
                echo "  ✓ BLOB Query SUCCESS: Found " . number_format($blobSize) . " bytes\n";
                echo "  MIME Type: " . ($testResult['image_mime_type'] ?? 'unknown') . "\n";
                echo "  This should work on Hostinger!\n";
            } else {
                echo "  ✗ BLOB Query: No data found with path '{$testPath}'\n";
                echo "  Trying alternative: Check by ID instead...\n";
                
                // Try checking by ID
                $testStmt2 = $pdo->prepare("SELECT image_data, image_mime_type FROM menu_items WHERE id = ? AND image_data IS NOT NULL LIMIT 1");
                $testStmt2->execute([$testItem['id']]);
                $testResult2 = $testStmt2->fetch(PDO::FETCH_ASSOC);
                
                if ($testResult2 && !empty($testResult2['image_data'])) {
                    $blobSize = strlen($testResult2['image_data']);
                    echo "  ✓ Found BLOB by ID: " . number_format($blobSize) . " bytes\n";
                    echo "  ⚠ ISSUE: item_image path doesn't match, but BLOB exists!\n";
                    echo "  This might be why images aren't loading - the query needs to check by ID too.\n";
                } else {
                    echo "  ✗ No BLOB found by ID either\n";
                }
            }
        } catch (PDOException $e) {
            echo "  ✗ Query Error: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
    
    // Test 5: Check file paths
    echo "=== Test 5: File System Paths ===\n";
    $rootDir = dirname(__DIR__);
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    echo "Root dir: $rootDir\n";
    echo "Document root: $docRoot\n";
    echo "Script dir: " . __DIR__ . "\n";
    
    $uploadsDirs = [
        $rootDir . '/uploads',
        $docRoot . '/uploads',
        dirname($docRoot) . '/uploads',
    ];
    
    foreach ($uploadsDirs as $dir) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
        $exists = file_exists($normalized);
        $readable = is_readable($normalized);
        echo "  $normalized: " . ($exists ? "EXISTS" : "NOT FOUND") . " / " . ($readable ? "READABLE" : "NOT READABLE") . "\n";
    }
    echo "\n";
    
    // Test 6: Environment detection
    echo "=== Test 6: Environment Detection ===\n";
    $isHostinger = (
        strpos($docRoot, 'public_html') !== false ||
        strpos($docRoot, 'domains') !== false ||
        strpos($_SERVER['SERVER_NAME'] ?? '', 'hstgr.io') !== false ||
        strpos($_SERVER['HTTP_HOST'] ?? '', 'restrogrow.com') !== false
    );
    echo "Detected as: " . ($isHostinger ? "Hostinger" : "Local") . "\n";
    echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n";
    echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
    echo "\n";
    
    echo "===========================================\n";
    echo "   DIAGNOSTICS COMPLETE\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>

