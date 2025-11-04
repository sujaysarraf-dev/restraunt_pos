<?php
/**
 * Script to update menu items with random placeholder images
 * This will add random food images to all menu items that don't have images
 */

require_once 'db_connection.php';

try {
    $conn = getConnection();
    
    // Get all menu items
    $stmt = $conn->query("SELECT id, item_name_en, item_type FROM menu_items");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($menuItems) . " menu items\n";
    
    // Food image categories based on item type
    $imageCategories = [
        'Veg' => ['vegetable', 'salad', 'pasta', 'pizza', 'bread'],
        'Non Veg' => ['chicken', 'meat', 'barbecue', 'steak', 'seafood'],
        'Egg' => ['egg', 'breakfast', 'omelette'],
        'Drink' => ['drink', 'juice', 'coffee', 'tea'],
        'Other' => ['food', 'restaurant', 'dish']
    ];
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($menuItems as $item) {
        $itemId = $item['id'];
        $itemType = $item['item_type'] ?? 'Veg';
        $itemName = $item['item_name_en'];
        
        // Check if item already has an image
        $checkStmt = $conn->prepare("SELECT item_image FROM menu_items WHERE id = ?");
        $checkStmt->execute([$itemId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Only skip if image exists, is valid, AND file actually exists
        if (!empty($existing['item_image']) && strpos($existing['item_image'], 'uploads/') === 0) {
            // Check if file actually exists
            if (file_exists($existing['item_image'])) {
                $fileSize = filesize($existing['item_image']);
                // Only skip if file exists and has reasonable size (>1KB)
                if ($fileSize > 1024) {
                    $skipped++;
                    echo "Skipping item #{$itemId} ({$itemName}) - already has valid image\n";
                    continue;
                }
            }
        }
        
        // Delete old image file if it exists but is broken
        if (!empty($existing['item_image']) && strpos($existing['item_image'], 'uploads/') === 0) {
            if (file_exists($existing['item_image'])) {
                @unlink($existing['item_image']);
            }
        }
        
        // Generate random image URL using Picsum Photos (random images)
        $width = 400;
        $height = 400;
        
        // Use different seeds based on item ID for variety
        $seed = $itemId * 7;
        
        // Use Picsum Photos with seed for consistent random images
        // Alternative: Use food image API
        $imageUrl = "https://picsum.photos/seed/{$seed}/{$width}/{$height}";
        
        // Alternative: Use food-specific placeholder
        // $imageUrl = "https://source.unsplash.com/{$width}x{$height}/?food";
        
        // Store as a placeholder path (we'll use external URLs directly)
        // Or download and save locally
        $imagePath = downloadAndSaveImage($imageUrl, $itemId, $itemName);
        
        if ($imagePath) {
            // Update database
            $updateStmt = $conn->prepare("UPDATE menu_items SET item_image = ? WHERE id = ?");
            $updateStmt->execute([$imagePath, $itemId]);
            $updated++;
            echo "Updated item #{$itemId} ({$itemName}) with image: {$imagePath}\n";
        } else {
            echo "Failed to download image for item #{$itemId} ({$itemName})\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Updated: {$updated} items\n";
    echo "Skipped: {$skipped} items\n";
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
        
        if ($imageData === false) {
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

