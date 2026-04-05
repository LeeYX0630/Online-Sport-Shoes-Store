<?php
// for admin to delete user
session_start();
require_once '../includes/db_connection.php';
// 1. Safety check: must be Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. check if user ID is provided
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Prevent admin from deleting themselves
    if ($id == $_SESSION['user_id']) {
        header("Location: admin_manage_users.php?error=Cannot delete your own admin account!");
        exit();
    }

    // 3. Delete user from database
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Successful deletion, redirect back to list with success message
        header("Location: admin_manage_users.php?msg=User deleted successfully.");
    } else {
        // Failure, redirect back with error message
        header("Location: admin_manage_users.php?error=Error deleting user.");
    }
    $stmt->close();
} else {
    header("Location: admin_manage_users.php");
}
?>