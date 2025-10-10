<?php
session_start();

// If not logged in, redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include the header and other files
require_once 'includes/header.php';
require_once 'includes/npms_dashboard.php';
require_once 'includes/malpay_dashboard.php';
require_once 'includes/footer.php';
?>