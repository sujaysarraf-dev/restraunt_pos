<?php
// Simple image serving script
$imagePath = $_GET['path'] ?? '';

// Security check - only allow images from uploads directory
if (strpos($imagePath, 'uploads/') !== 0) {
    http_response_code(403);
    exit('Access denied');
}

// Check if file exists (look in parent directory)
$fullPath = '../' . $imagePath;
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
