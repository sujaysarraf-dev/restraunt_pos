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
    $uploadsDir = $rootDir . '/uploads';
    echo "Root dir: $rootDir\n";
    echo "Uploads dir: $uploadsDir\n";
    echo "Exists: " . (file_exists($uploadsDir) ? 'YES' : 'NO') . "\n";
    echo "Readable: " . (is_readable($uploadsDir) ? 'YES' : 'NO') . "\n";
    if (file_exists($uploadsDir)) {
        echo "Files in uploads:\n";
        $files = scandir($uploadsDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - $file\n";
            }
        }
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
                
                // Check if file exists
                $possiblePaths = [
                    $rootDir . '/' . $logoPath,
                    __DIR__ . '/../' . $logoPath,
                    $_SERVER['DOCUMENT_ROOT'] . '/' . $logoPath,
                    $rootDir . '/uploads/' . basename($logoPath),
                    __DIR__ . '/../uploads/' . basename($logoPath),
                ];
                
                // Also check normalized paths
                if (strpos($logoPath, 'uploads/') === false) {
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
            
            $possiblePaths = [
                $rootDir . '/' . $item['item_image'],
                __DIR__ . '/../' . $item['item_image'],
                $rootDir . '/uploads/' . basename($item['item_image']),
                __DIR__ . '/../uploads/' . basename($item['item_image']),
            ];
            
            // Also check normalized paths
            if (strpos($item['item_image'], 'uploads/') === false) {
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

