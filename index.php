<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['restaurant_id'])) {
    // User is logged in - redirect to their restaurant's website
    require_once __DIR__ . '/db_connection.php';
    
    // Get restaurant name to create URL slug
    $restaurant_name = $_SESSION['restaurant_name'] ?? 'Restaurant';
    $restaurant_id = $_SESSION['restaurant_id'];
    
    // Create URL-friendly slug from restaurant name
    function createRestaurantSlug($name) {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
    
    $restaurant_slug = createRestaurantSlug($restaurant_name);
    
    // Redirect to restaurant's website
    header('Location: website/index.php?restaurant_id=' . urlencode($restaurant_id) . '&restaurant=' . urlencode($restaurant_slug));
    exit();
} else {
    // User is not logged in, redirect to login page
    header('Location: admin/login.php');
    exit();
}
?>

