<?php
// init_bridge.php
require_once '../includes/db_connection.php';
$token = $_GET['token'] ?? '';
if ($token) {
    $conn->query("INSERT INTO camera_bridge (session_token, status) VALUES ('$token', 'waiting')");
    echo json_encode(['success' => true]);
}
?>