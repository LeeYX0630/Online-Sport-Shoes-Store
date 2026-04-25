<?php
/**
 * STEALTH SPORT SHOES - USER LOGOUT
 * Module A: User Permissions & Profile
 */

// Start session to access existing data
session_start();

// Determine the destination after logout
// Default is to stay within Module A's login page
$target_page = "login.php"; 

// If a specific logout source is detected (like from the Home page nav)
if (isset($_GET['action']) && $_GET['action'] === 'exit_to_home') {
    // Navigate up one level to reach the root index.php 
    $target_page = "../index.php?status=success_logout";
}

// 1. Clear all session variables
$_SESSION = [];

// 2. Invalidate the session cookie in the browser
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 86400, '/');
}

// 3. Completely destroy the session on the server
session_destroy();

// 4. Redirect the user to the decided target page
header("Location: " . $target_page);
exit();
?>