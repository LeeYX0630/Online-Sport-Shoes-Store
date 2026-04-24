<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_custom_cart'])) {
    $pro_id = intval($_POST['pro_id']);
    $design = $_POST['custom_design'];
    $custom_image = $_POST['custom_image'];

    // 初始化设计库
    if (!isset($_SESSION['saved_designs'])) $_SESSION['saved_designs'] = [];
    if (!isset($_SESSION['saved_designs'][$pro_id])) $_SESSION['saved_designs'][$pro_id] = [];

    // 为每个设计生成唯一 ID
    $design_id = 'design_' . uniqid();

    // 存入设计库而不是购物车
    $_SESSION['saved_designs'][$pro_id][$design_id] = [
        'design_id' => $design_id,
        'design_details' => $design,
        'custom_preview' => $custom_image,
        'timestamp' => time()
    ];

    echo json_encode(['success' => true, 'design_id' => $design_id]);
    exit;
}