<?php
// check_bridge.php
require_once '../includes/db_connection.php';
$token = $_GET['token'] ?? '';
$res = $conn->query("SELECT * FROM camera_bridge WHERE session_token = '$token' AND status = 'captured'");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode(['status' => 'captured', 'image_url' => $row['image_path']]);
} else {
    echo json_encode(['status' => 'waiting']);
}
?>