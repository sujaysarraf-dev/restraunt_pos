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
// Security: Strict path validation to prevent path traversal attacks

// Whitelist of allowed image extensions
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

// Validate that path starts with uploads/
if (empty($imagePath) || strpos($imagePath, 'uploads/') !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found');
}

// Remove 'uploads/' prefix and get just the filename
$relativePath = substr($imagePath, 8); // Remove 'uploads/' (8 characters)

// Use basename to prevent directory traversal (removes any ../ or ./)
$filename = basename($relativePath);

// Validate filename is not empty and doesn't contain path separators
if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Invalid file path');
}

// Get file extension and validate
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('File type not allowed');
}

// Build full path - use realpath to resolve actual path
$rootDir = dirname(__DIR__);
$uploadsDir = realpath($rootDir . '/uploads');
$fullPath = realpath($uploadsDir . '/' . $filename);

// Critical security check: Ensure resolved path is within uploads directory
if ($fullPath === false || strpos($fullPath, $uploadsDir) !== 0) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Access denied');
}

// Check if file exists
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found');
}

// Get file info and validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Validate MIME type matches allowed types
if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Invalid file type');
}

// Set appropriate headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('X-Content-Type-Options: nosniff'); // Prevent MIME type sniffing

// Output the image
readfile($fullPath);
?>
