<?php
/**
 * Script to FORCE update ALL menu items with random placeholder images
 * This will replace ALL images with new random ones
 */

require_once 'db_connection.php';

try {
    $conn = getConnection();
    
    // Get all menu items
    $stmt = $conn->query("SELECT id, item_name_en, item_type FROM menu_items");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($menuItems) . " menu items\n";
    echo "Updating ALL items with random images...\n\n";
    
    $updated = 0;
    $errors = 0;
    
    foreach ($menuItems as $item) {
        $itemId = $item['id'];
        $itemType = $item['item_type'] ?? 'Veg';
        $itemName = $item['item_name_en'];
        
        // Get existing image path
        $checkStmt = $conn->prepare("SELECT item_image FROM menu_items WHERE id = ?");
        $checkStmt->execute([$itemId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete old image file if it exists
        if (!empty($existing['item_image']) && strpos($existing['item_image'], 'uploads/') === 0) {
            if (file_exists($existing['item_image'])) {
                @unlink($existing['item_image']);
            }
        }
        
        // Generate random image URL using Picsum Photos
        $width = 400;
        $height = 400;
        
        // Use different seeds based on item ID for variety
        $seed = $itemId * 7;
        
        // Use Picsum Photos with seed for consistent random images
        $imageUrl = "https://picsum.photos/seed/{$seed}/{$width}/{$height}";
        
        // Download and save image
        $imagePath = downloadAndSaveImage($imageUrl, $itemId, $itemName);
        
        if ($imagePath) {
            // Update database
            $updateStmt = $conn->prepare("UPDATE menu_items SET item_image = ? WHERE id = ?");
            $updateStmt->execute([$imagePath, $itemId]);
            $updated++;
            echo "✓ Updated item #{$itemId} ({$itemName}) with image: {$imagePath}\n";
        } else {
            $errors++;
            echo "✗ Failed to download image for item #{$itemId} ({$itemName})\n";
        }
        
        // Small delay to avoid overwhelming the server
        usleep(100000); // 0.1 second
    }
    
    echo "\n=== Summary ===\n";
    echo "Updated: {$updated} items\n";
    echo "Errors: {$errors} items\n";
    echo "Done!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Download image from URL and save to uploads directory
 */
function downloadAndSaveImage($url, $itemId, $itemName) {
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate filename
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', substr($itemName, 0, 30));
        $filename = 'item_' . $itemId . '_' . $safeName . '.jpg';
        $filepath = $uploadDir . '/' . $filename;
        
        // Download image with timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0'
            ]
        ]);
        
        $imageData = @file_get_contents($url, false, $context);
        
        if ($imageData === false || strlen($imageData) < 100) {
            // Fallback: use a placeholder service
            $width = 400;
            $height = 400;
            $placeholderUrl = "https://via.placeholder.com/{$width}x{$height}/E5E7EB/6B7280?text=" . urlencode(substr($itemName, 0, 20));
            $imageData = @file_get_contents($placeholderUrl, false, $context);
        }
        
        if ($imageData !== false && strlen($imageData) > 100) {
            file_put_contents($filepath, $imageData);
            return 'uploads/' . $filename;
        }
        
        return null;
    } catch (Exception $e) {
        echo "Error downloading image: " . $e->getMessage() . "\n";
        return null;
    }
}
?>

