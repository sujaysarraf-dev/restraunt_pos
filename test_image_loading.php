<?php
/**
 * Test Image Loading - Debug script for Hostinger
 * Run this to check image loading issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/db_connection.php';

echo "<h2>Image Loading Test</h2>";
echo "<pre style='font-family: Consolas, monospace; background: #1e1e1e; color: #0f0; padding: 20px;'>";

try {
    global $pdo;
    $conn = $pdo;
    
    // Test 1: Check uploads directory
    echo "=== Test 1: Uploads Directory ===\n";
    $rootDir = dirname(__DIR__);
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    
    // Check multiple possible locations (especially for Hostinger)
    $possibleUploadDirs = [
        $rootDir . '/uploads',
        $docRoot . '/uploads',
        dirname($docRoot) . '/uploads',
        $rootDir . '/public_html/uploads',
    ];
    
    echo "Root dir: $rootDir\n";
    echo "Document root: $docRoot\n";
    echo "\nChecking possible uploads locations:\n";
    
    $foundUploadsDir = null;
    foreach ($possibleUploadDirs as $uploadsDir) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadsDir);
        $exists = file_exists($normalized);
        $readable = is_readable($normalized);
        echo "  - $normalized\n";
        echo "    Exists: " . ($exists ? 'YES' : 'NO') . "\n";
        echo "    Readable: " . ($readable ? 'YES' : 'NO') . "\n";
        
        if ($exists && $readable && $foundUploadsDir === null) {
            $foundUploadsDir = $normalized;
            echo "    ✓ Using this directory\n";
            
            // List files
            $files = scandir($normalized);
            $fileCount = 0;
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $fileCount++;
                    if ($fileCount <= 10) { // Show first 10 files
                        echo "      - $file\n";
                    }
                }
            }
            if ($fileCount > 10) {
                echo "      ... and " . ($fileCount - 10) . " more files\n";
            }
        }
        echo "\n";
    }
    
    if ($foundUploadsDir === null) {
        echo "⚠️  WARNING: No uploads directory found!\n";
        echo "   You may need to create: " . $docRoot . "/uploads\n";
        echo "   Make sure it has proper permissions (755 or 775)\n";
    }
    echo "\n";
    
    // Test 2: Check restaurant logos
    echo "=== Test 2: Restaurant Logos ===\n";
    $stmt = $conn->query("SELECT id, username, restaurant_name, restaurant_logo FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "User #{$user['id']} ({$user['username']}):\n";
        echo "  Logo path: " . ($user['restaurant_logo'] ?? 'NULL') . "\n";
        
        if (!empty($user['restaurant_logo'])) {
            $logoPath = $user['restaurant_logo'];
            
            // Check if database-stored
            if (strpos($logoPath, 'db:') === 0) {
                echo "  Type: Database-stored\n";
                echo "  API URL: /api/image.php?type=logo&id={$user['id']}\n";
            } else {
                echo "  Type: File-based\n";
                echo "  API URL: /api/image.php?path=" . urlencode($logoPath) . "\n";
                
                // Use the found uploads directory if available, otherwise try common locations
                $uploadsBase = $foundUploadsDir ?? $docRoot . '/uploads';
                
                // Check if file exists
                $possiblePaths = [
                    $rootDir . '/' . $logoPath,
                    __DIR__ . '/../' . $logoPath,
                    $docRoot . '/' . $logoPath,
                    $uploadsBase . '/' . basename($logoPath),
                    $rootDir . '/uploads/' . basename($logoPath),
                    __DIR__ . '/../uploads/' . basename($logoPath),
                ];
                
                // If path already contains uploads/, use it directly
                if (strpos($logoPath, 'uploads/') === 0) {
                    $possiblePaths[] = $uploadsBase . '/' . substr($logoPath, 8); // Remove 'uploads/' prefix
                    $possiblePaths[] = $docRoot . '/' . $logoPath;
                    $possiblePaths[] = $rootDir . '/' . $logoPath;
                } else {
                    // Also check normalized paths
                    $possiblePaths[] = $uploadsBase . '/' . $logoPath;
                    $possiblePaths[] = $rootDir . '/uploads/' . $logoPath;
                    $possiblePaths[] = __DIR__ . '/../uploads/' . $logoPath;
                }
                
                $found = false;
                foreach ($possiblePaths as $path) {
                    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
                    if (file_exists($normalized)) {
                        echo "  ✓ Found at: $normalized\n";
                        echo "  Size: " . filesize($normalized) . " bytes\n";
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    echo "  ❌ NOT FOUND in any location\n";
                    echo "  Tried:\n";
                    foreach ($possiblePaths as $path) {
                        echo "    - $path\n";
                    }
                }
            }
        }
        echo "\n";
    }
    
    // Test 3: Check menu item images
    echo "=== Test 3: Menu Item Images ===\n";
    $stmt = $conn->query("SELECT id, item_name_en, item_image FROM menu_items WHERE item_image IS NOT NULL AND item_image != '' LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $item) {
        echo "Item #{$item['id']} ({$item['item_name_en']}):\n";
        echo "  Image path: {$item['item_image']}\n";
        
        if (strpos($item['item_image'], 'db:') === 0) {
            echo "  Type: Database-stored\n";
            echo "  API URL: /api/image.php?path=" . urlencode($item['item_image']) . "\n";
        } else {
            echo "  Type: File-based\n";
            echo "  API URL: /api/image.php?path=" . urlencode($item['item_image']) . "\n";
            
            // Use the found uploads directory if available, otherwise try common locations
            $uploadsBase = $foundUploadsDir ?? $docRoot . '/uploads';
            
            $possiblePaths = [
                $rootDir . '/' . $item['item_image'],
                __DIR__ . '/../' . $item['item_image'],
                $uploadsBase . '/' . basename($item['item_image']),
                $rootDir . '/uploads/' . basename($item['item_image']),
                __DIR__ . '/../uploads/' . basename($item['item_image']),
            ];
            
            // If path already contains uploads/, use it directly
            if (strpos($item['item_image'], 'uploads/') === 0) {
                $possiblePaths[] = $uploadsBase . '/' . substr($item['item_image'], 8); // Remove 'uploads/' prefix
                $possiblePaths[] = $docRoot . '/' . $item['item_image'];
                $possiblePaths[] = $rootDir . '/' . $item['item_image'];
            } else {
                // Also check normalized paths
                $possiblePaths[] = $uploadsBase . '/' . $item['item_image'];
                $possiblePaths[] = $rootDir . '/uploads/' . $item['item_image'];
                $possiblePaths[] = __DIR__ . '/../uploads/' . $item['item_image'];
            }
            
            $found = false;
            foreach ($possiblePaths as $path) {
                $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
                if (file_exists($normalized)) {
                    echo "  ✓ Found at: $normalized\n";
                    echo "  Size: " . filesize($normalized) . " bytes\n";
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                echo "  ❌ NOT FOUND in any location\n";
                echo "  Tried:\n";
                foreach ($possiblePaths as $path) {
                    echo "    - $path\n";
                }
            }
        }
        echo "\n";
    }
    
    echo "===========================================\n";
    echo "   TEST COMPLETE\n";
    echo "===========================================\n";
    echo "\n⚠️  DELETE THIS FILE AFTER TESTING!\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>

