<?php
// Image serving script - supports both file-based and database-stored images
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Database connection not available');
}

$imagePath = $_GET['path'] ?? '';
$imageType = $_GET['type'] ?? ''; // 'logo', 'item', or 'banner'
$imageId = $_GET['id'] ?? '';

// Check if this is a database-stored image (starts with 'db:')
if (strpos($imagePath, 'db:') === 0 || !empty($imageType)) {
    // Retrieve image from database
    try {
        if ($imageType === 'logo' && !empty($imageId)) {
            // Get restaurant logo
            try {
                $stmt = $pdo->prepare("SELECT logo_data, logo_mime_type FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$imageId]);
                $logo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($logo && !empty($logo['logo_data'])) {
                    header('Content-Type: ' . ($logo['logo_mime_type'] ?? 'image/jpeg'));
                    header('Content-Length: ' . strlen($logo['logo_data']));
                    header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
                    echo $logo['logo_data'];
                    exit();
                }
            } catch (PDOException $e) {
                // If logo_data column doesn't exist, fall through to file-based handling
                if (strpos($e->getMessage(), 'logo_data') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                    error_log("Logo data column not found, falling back to file-based: " . $e->getMessage());
                } else {
                    throw $e;
                }
            }
        } elseif ($imageType === 'banner' && !empty($imageId)) {
            // Get website banner by ID
            $stmt = $pdo->prepare("SELECT banner_data, banner_mime_type FROM website_banners WHERE id = ? LIMIT 1");
            $stmt->execute([$imageId]);
            $banner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($banner && !empty($banner['banner_data'])) {
                header('Content-Type: ' . ($banner['banner_mime_type'] ?? 'image/jpeg'));
                header('Content-Length: ' . strlen($banner['banner_data']));
                header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
                echo $banner['banner_data'];
                exit();
            }
        } elseif (!empty($imagePath) && strpos($imagePath, 'db:') === 0) {
            // Try to find menu item by item_image reference
            $stmt = $pdo->prepare("SELECT image_data, image_mime_type FROM menu_items WHERE item_image = ? LIMIT 1");
            $stmt->execute([$imagePath]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item && !empty($item['image_data'])) {
                header('Content-Type: ' . ($item['image_mime_type'] ?? 'image/jpeg'));
                header('Content-Length: ' . strlen($item['image_data']));
                header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
                echo $item['image_data'];
                exit();
            }
            
            // Try to find banner by banner_path reference
            $stmt = $pdo->prepare("SELECT banner_data, banner_mime_type FROM website_banners WHERE banner_path = ? LIMIT 1");
            $stmt->execute([$imagePath]);
            $banner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($banner && !empty($banner['banner_data'])) {
                header('Content-Type: ' . ($banner['banner_mime_type'] ?? 'image/jpeg'));
                header('Content-Length: ' . strlen($banner['banner_data']));
                header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
                echo $banner['banner_data'];
                exit();
            }
        }
        
        // If not found in database and no file path provided, return 404
        if (empty($imagePath)) {
            http_response_code(404);
            header('Content-Type: text/plain');
            exit('Image not found');
        }
        // Otherwise, fall through to file-based handling
        
    } catch (PDOException $e) {
        error_log("Error retrieving image from database: " . $e->getMessage());
        // If columns don't exist, try to fall back to file-based
        if (strpos($e->getMessage(), 'logo_data') !== false || strpos($e->getMessage(), 'image_data') !== false || strpos($e->getMessage(), 'banner_data') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            // Columns don't exist yet, fall through to file-based handling
        } else {
            http_response_code(500);
            header('Content-Type: text/plain');
            exit('Error loading image');
        }
    }
}

// Fallback: File-based image (backward compatibility)
// Normalize path to ensure it points inside uploads directory
if (strpos($imagePath, '../uploads/') === 0) {
    $imagePath = substr($imagePath, 3); // Remove "../"
}

// Security check - only allow images from uploads directory
if (empty($imagePath) || strpos($imagePath, 'uploads/') !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found');
}

// Build full path relative to project root
$fullPath = dirname(__DIR__) . '/' . $imagePath;
if (!file_exists($fullPath)) {
    http_response_code(404);
    exit('Image not found');
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Set appropriate headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

// Output the image
readfile($fullPath);
?>
