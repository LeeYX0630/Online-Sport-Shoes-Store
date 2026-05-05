<?php
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_pin'])) {
    $uid = $_SESSION['user_id'];
    $pin = $_POST['new_pin'];

    if (!preg_match('/^\d{6}$/', $pin)) {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN format.']);
        exit;
    }

    $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);

    // 3. 更新数据库
    $stmt = $conn->prepare("UPDATE `user` SET User_PIN = ? WHERE User_Id = ?");
    $stmt->bind_param("si", $hashed_pin, $uid);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
    $stmt->close();
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request.']);