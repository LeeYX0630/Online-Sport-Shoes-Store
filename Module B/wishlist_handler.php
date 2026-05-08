<?php
// Module B/wishlist_handler.php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$pro_id = intval($input['pro_id']);
$uid = $_SESSION['user_id'] ?? null;

if (!$uid || !$pro_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 检查是否存在记录 (对应表结构: Wishlist_Id, User_Id, Pro_Id, Added_Date)
$check = $conn->prepare("SELECT Wishlist_Id FROM wishlist WHERE User_Id = ? AND Pro_Id = ?");
$check->bind_param("ii", $uid, $pro_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // 如果已存在，则取消收藏（删除记录）
    $del = $conn->prepare("DELETE FROM wishlist WHERE User_Id = ? AND Pro_Id = ?");
    $del->bind_param("ii", $uid, $pro_id);
    $del->execute();
    echo json_encode(['status' => 'removed']);
} else {
    // 如果不存在，则添加收藏（插入记录）
    $ins = $conn->prepare("INSERT INTO wishlist (User_Id, Pro_Id, Added_Date) VALUES (?, ?, NOW())");
    $ins->bind_param("ii", $uid, $pro_id);
    $ins->execute();
    echo json_encode(['status' => 'added']);
}

$conn->close();
?>