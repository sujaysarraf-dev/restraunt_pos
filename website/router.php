<?php
/**
 * Restaurant Router
 * Handles restaurant-specific URLs like website/restaurant-name
 * Usage: website/router.php?restaurant=restaurant-name
 * Or: website/restaurant-name (via .htaccess rewrite)
 */

// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Get restaurant identifier from URL
$restaurant_slug = isset($_GET['restaurant']) ? trim($_GET['restaurant']) : '';
$restaurant_id = null;
$restaurant_name = null;

if (empty($restaurant_slug)) {
    // Try to get from URL path (if using .htaccess rewrite)
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Extract restaurant name from URL path
    // Example: /test/restraunt_pos/website/restaurant-name
    $path = parse_url($request_uri, PHP_URL_PATH);
    $path_parts = explode('/', trim($path, '/'));
    
    // Find 'website' in path and get next part
    $website_index = array_search('website', $path_parts);
    if ($website_index !== false && isset($path_parts[$website_index + 1])) {
        $restaurant_slug = $path_parts[$website_index + 1];
    }
}

// If still no slug, check if it's index.php with restaurant parameter
if (empty($restaurant_slug) && isset($_GET['restaurant'])) {
    $restaurant_slug = trim($_GET['restaurant']);
}

// Include database connection
require_once __DIR__ . '/db_config.php';

// Get connection
if (isset($pdo) && $pdo instanceof PDO) {
    $conn = $pdo;
} else {
    if (function_exists('getConnection')) {
        $conn = getConnection();
    } else {
        $host = 'localhost';
        $dbname = 'restro2';
        $username = 'root';
        $password = '';
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
}

// Function to create URL-friendly slug from restaurant name
function createRestaurantSlug($name) {
    // Convert to lowercase
    $slug = strtolower($name);
    // Replace spaces and special characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    return $slug;
}

// Function to find restaurant by slug
function findRestaurantBySlug($conn, $slug) {
    // Get all restaurants and match by slug
    $stmt = $conn->prepare("SELECT restaurant_id, restaurant_name FROM users WHERE restaurant_name IS NOT NULL AND restaurant_name != ''");
    $stmt->execute();
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($restaurants as $restaurant) {
        $restaurant_slug = createRestaurantSlug($restaurant['restaurant_name']);
        if ($restaurant_slug === $slug) {
            return [
                'restaurant_id' => $restaurant['restaurant_id'],
                'restaurant_name' => $restaurant['restaurant_name']
            ];
        }
    }
    
    return null;
}

// If we have a restaurant slug, find the restaurant
if (!empty($restaurant_slug)) {
    $restaurant_info = findRestaurantBySlug($conn, $restaurant_slug);
    if ($restaurant_info) {
        $restaurant_id = $restaurant_info['restaurant_id'];
        $restaurant_name = $restaurant_info['restaurant_name'];
    }
}

// If restaurant found, redirect to index.php with restaurant_id
if ($restaurant_id) {
    header('Location: index.php?restaurant_id=' . urlencode($restaurant_id));
    exit();
} else {
    // Restaurant not found, show error or redirect to default
    http_response_code(404);
    die('Restaurant not found. Please check the URL.');
}
?>

