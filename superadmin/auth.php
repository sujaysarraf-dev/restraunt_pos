<?php
session_start();

function require_superadmin() {
    if (!isset($_SESSION['superadmin_id'])) {
        header('Location: login.php');
        exit();
    }
}
?>


