<?php
session_start();

// Check if user is logged in (admin has user_id, staff has staff_id)
if ((isset($_SESSION['user_id']) || isset($_SESSION['staff_id'])) && isset($_SESSION['username']) && isset($_SESSION['restaurant_id'])) {
    // User is logged in, redirect to main dashboard
    header('Location: views/dashboard.php');
    exit();
} else {
    // User is not logged in, redirect to login page
    header('Location: admin/login.php');
    exit();
}
?>

