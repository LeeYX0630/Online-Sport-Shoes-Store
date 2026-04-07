<?php
// for admin to reset user password
session_start();
require_once '../includes/db_connection.php';


if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Set default password
    $default_pass = "abc123";
    $hashed_pass = password_hash($default_pass, PASSWORD_DEFAULT);

    // Update password in database
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashed_pass, $id);

    if ($stmt->execute()) {
        header("Location: admin_manage_users.php?msg=Password reset to 'abc123' successfully.");
    } else {
        header("Location: admin_manage_users.php?error=Failed to reset password.");
    }
    $stmt->close();
} else {
    header("Location: admin_manage_users.php");
}
?>