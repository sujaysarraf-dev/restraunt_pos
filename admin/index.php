<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['restaurant_id'])) {
    header('Location: /admin/login.php');
    exit();
}

// Get the requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Define allowed pages
$allowedPages = ['dashboard', 'menus', 'menu-items', 'settings'];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Include the main application
include 'index.html';
?>
