<?php
session_start();
require_once '../includes/db_connection.php';

// 1. Gatekeeper: Only Admins allowed
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. Get User ID and Current Status
if (isset($_GET['id']) && isset($_GET['current_status'])) {
    $user_id = intval($_GET['id']);
    $current_status = $_GET['current_status'];

    // 3. Change Account Status
    $new_status = ($current_status === 'Active') ? 'Blocked' : 'Active';

    // 4. Update database
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_status, $user_id);

    if ($stmt->execute()) {
        //Sucess redirect back to manage users with a success message
        header("Location: admin_manage_users.php?msg=Status updated");
    } else {
        echo "Error updating record: " . $conn->error;
    }
} else {
    header("Location: admin_manage_users.php");
}
?>