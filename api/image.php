<?php
// Image serving script - supports both file-based and database-stored images
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start(); // Start fresh buffer

// Include database connection
if (file_exists(__DIR__ . '/../db_connection.php')) {
    require_once __DIR__ . '/../db_connection.php';
} else {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Database connection not available');
}

$imagePath = $_GET['path'] ?? '';
$imageType = $_GET['type'] ?? ''; // 'logo', 'item', 'banner', or 'business_qr'
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
            // Get website banner
            try {
                $stmt = $pdo->prepare("SELECT banner_data, banner_mime_type FROM website_banners WHERE id = ? LIMIT 1");
                $stmt->execute([$imageId]);
                $banner = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($banner && !empty($banner['banner_data'])) {
                    header('Content-Type: ' . ($banner['banner_mime_type'] ?? 'image/jpeg'));
                    header('Content-Length: ' . strlen($banner['banner_data']));
                    header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
                    echo $banner['banner_data'];
                    exit();
                } else {
                    // Banner not found in database, try file-based fallback
                    $stmt = $pdo->prepare("SELECT banner_path FROM website_banners WHERE id = ? LIMIT 1");
                    $stmt->execute([$imageId]);
                    $bannerPath = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($bannerPath && !empty($bannerPath['banner_path']) && strpos($bannerPath['banner_path'], 'db:') !== 0) {
                        // Use file-based path
                        $imagePath = $bannerPath['banner_path'];
                        // Fall through to file-based handling below
                    } else {
                        http_response_code(404);
                        header('Content-Type: text/plain');
                        exit('Banner not found');
                    }
                }
            } catch (PDOException $e) {
                // If banner_data column doesn't exist, try file-based fallback
                if (strpos($e->getMessage(), 'banner_data') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                    error_log("Banner data column not found, trying file-based: " . $e->getMessage());
                    try {
                        $stmt = $pdo->prepare("SELECT banner_path FROM website_banners WHERE id = ? LIMIT 1");
                        $stmt->execute([$imageId]);
                        $bannerPath = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($bannerPath && !empty($bannerPath['banner_path']) && strpos($bannerPath['banner_path'], 'db:') !== 0) {
                            $imagePath = $bannerPath['banner_path'];
                            // Fall through to file-based handling below
                        } else {
                            http_response_code(404);
                            header('Content-Type: text/plain');
                            exit('Banner not found');
                        }
                    } catch (PDOException $e2) {
                        http_response_code(404);
                        header('Content-Type: text/plain');
                        exit('Banner not found');
                    }
                } else {
                    throw $e;
                }
            }
        } elseif ($imageType === 'business_qr' && !empty($imageId)) {
            // Get business QR code from users table
            try {
                $stmt = $pdo->prepare("SELECT business_qr_code_data, business_qr_code_mime_type FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$imageId]);
                $qr = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($qr && !empty($qr['business_qr_code_data'])) {
                    header('Content-Type: ' . ($qr['business_qr_code_mime_type'] ?? 'image/jpeg'));
                    header('Content-Length: ' . strlen($qr['business_qr_code_data']));
                    header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
                    echo $qr['business_qr_code_data'];
                    exit();
                } else {
                    http_response_code(404);
                    header('Content-Type: text/plain');
                    exit('Business QR code not found');
                }
            } catch (PDOException $e) {
                // If business_qr_code_data column doesn't exist
                if (strpos($e->getMessage(), 'business_qr_code_data') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                    http_response_code(404);
                    header('Content-Type: text/plain');
                    exit('Business QR code not available');
                } else {
                    throw $e;
                }
            }
        } elseif (!empty($imagePath) && strpos($imagePath, 'db:') === 0) {
            // Extract ID from path (format: db:uniqueid)
            $dbId = str_replace('db:', '', $imagePath);
            
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
// Security check - only allow images from uploads directory
if (empty($imagePath) || (strpos($imagePath, 'uploads/') !== 0 && strpos($imagePath, '../uploads/') !== 0 && strpos($imagePath, '/uploads/') !== 0)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    error_log("Image path rejected (security): " . $imagePath);
    exit('Image not found');
}

// Normalize path - handle various path formats
$normalizedPath = $imagePath;
// Remove leading slashes and ../ prefixes
$normalizedPath = ltrim($normalizedPath, '/');
$normalizedPath = str_replace('../uploads/', 'uploads/', $normalizedPath);
$normalizedPath = str_replace('/uploads/', 'uploads/', $normalizedPath);

// Build full path from root - try multiple possible locations (Hostinger-friendly)
$rootDir = dirname(__DIR__);
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$scriptDir = __DIR__;

// Detect if running on Hostinger (check for common Hostinger paths)
$isHostinger = (
    strpos($docRoot, 'public_html') !== false ||
    strpos($docRoot, 'domains') !== false ||
    strpos($_SERVER['SERVER_NAME'] ?? '', 'hstgr.io') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', 'restrogrow.com') !== false
);

$possiblePaths = [];
if ($isHostinger) {
    // Hostinger-specific paths (usually public_html is document root)
    $possiblePaths = [
        $docRoot . '/' . $normalizedPath,  // From document root (most common on Hostinger)
        $rootDir . '/' . $normalizedPath,  // From project root
        $scriptDir . '/../' . $normalizedPath, // Relative from api/
        dirname($docRoot) . '/' . $normalizedPath, // One level up from doc root
        '/home/' . get_current_user() . '/public_html/' . $normalizedPath, // Absolute Hostinger path
    ];
} else {
    // Local development paths
    $possiblePaths = [
        $rootDir . '/' . $normalizedPath,  // Standard path
        $scriptDir . '/../' . $normalizedPath, // Relative from api/
        $docRoot . '/' . $normalizedPath, // From document root
        $normalizedPath, // Absolute path (if already full)
    ];
}

$fullPath = null;
foreach ($possiblePaths as $path) {
    // Normalize path separators
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $path = realpath($path); // Resolve any .. or . in path
    
    if ($path && file_exists($path) && is_readable($path)) {
        // Security: Ensure path is within allowed directory
        $allowedDir = realpath($rootDir . '/uploads') ?: realpath($docRoot . '/uploads');
        if ($allowedDir && strpos($path, $allowedDir) === 0) {
            $fullPath = $path;
            break;
        }
    }
}

// Check if file exists
if (!$fullPath || !file_exists($fullPath)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    
    // Enhanced logging for Hostinger debugging
    $debugInfo = [
        'environment' => $isHostinger ? 'Hostinger' : 'Local',
        'requested_path' => $imagePath,
        'normalized_path' => $normalizedPath,
        'root_dir' => $rootDir,
        'document_root' => $docRoot,
        'script_dir' => $scriptDir,
        'tried_paths' => $possiblePaths,
        'current_user' => get_current_user(),
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
    ];
    
    error_log("Image not found: " . json_encode($debugInfo));
    exit('Image not found');
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Validate MIME type is an image
if (strpos($mimeType, 'image/') !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    error_log("File is not an image. MIME type: " . $mimeType . ", Path: " . $fullPath);
    exit('Invalid image file');
}

// Clear any output buffer before sending image
ob_end_clean();

// Set appropriate headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Output the image
readfile($fullPath);
exit();
?>
