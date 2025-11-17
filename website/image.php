<?php
// Simple image serving script
$imagePath = $_GET['path'] ?? '';

// Normalize path to ensure it points inside uploads directory
if (strpos($imagePath, '../uploads/') === 0) {
    $imagePath = substr($imagePath, 3); // Remove "../"
}

// Security check - only allow images from uploads directory
if (strpos($imagePath, 'uploads/') !== 0) {
    http_response_code(403);
    exit('Access denied');
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
