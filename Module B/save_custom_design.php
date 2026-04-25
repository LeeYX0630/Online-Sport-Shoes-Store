<?php
session_start();
require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_custom_cart'])) {

    $pro_id = intval($_POST['pro_id']);
    $design = $_POST['custom_design'];
    $custom_image = $_POST['custom_image'];
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $design_id = 'design_' . uniqid();

    if ($uid) {
        // --- 情况 A：用户已登录，存入数据库 ---
        require_once '../includes/db_connection.php';
        $stmt = $conn->prepare("INSERT INTO user_saved_designs (Design_Id, User_Id, Pro_Id, Design_JSON, Preview_Image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siiss", $design_id, $uid, $pro_id, $design, $custom_image);
        $stmt->execute();
    } else {
        // --- 情况 B：未登录，暂时存入 Session ---
        if (!isset($_SESSION['saved_designs'])) $_SESSION['saved_designs'] = [];
        $_SESSION['saved_designs'][$pro_id][$design_id] = [
            'design_id' => $design_id,
            'design_details' => $design,
            'custom_preview' => $custom_image
        ];
    }

    echo json_encode(['success' => true, 'design_id' => $design_id]);
    exit;
}