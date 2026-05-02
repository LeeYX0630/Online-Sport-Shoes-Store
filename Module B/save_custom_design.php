<?php
// save_custom_design.php
session_start();
require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_custom_cart'])) {
    $pro_id = intval($_POST['pro_id']);
    $design = $_POST['custom_design']; // JSON 字符串
    $custom_image = $_POST['custom_image'];
    $uid = $_SESSION['user_id'] ?? null;
    $update_id = $_POST['update_design_id'] ?? '';

    if ($uid) {
        // --- 【核心修复：查重逻辑】 ---
        // 检查用户是否已存在一模一样的设计方案
        $stmt_dup = $conn->prepare("SELECT Design_Id FROM user_saved_designs WHERE User_Id = ? AND Pro_Id = ? AND Design_JSON = ? LIMIT 1");
        $stmt_dup->bind_param("iis", $uid, $pro_id, $design);
        $stmt_dup->execute();
        $res_dup = $stmt_dup->get_result();

        if ($res_dup->num_rows > 0) {
            // 如果找到完全一致的设计，不再保存，直接返回已有 ID
            $existing_design = $res_dup->fetch_assoc();
            echo json_encode([
                'success' => true, 
                'design_id' => $existing_design['Design_Id'], 
                'is_duplicate' => true 
            ]);
            exit;
        }

        // --- 继续原有的保存/更新逻辑 ---
        if (!empty($update_id)) {
            $stmt = $conn->prepare("UPDATE user_saved_designs SET Design_JSON = ?, Preview_Image = ? WHERE Design_Id = ? AND User_Id = ?");
            $stmt->bind_param("sssi", $design, $custom_image, $update_id, $uid);
            $stmt->execute();
            $final_id = $update_id;
        } else {
            $new_id = 'design_' . uniqid();
            $stmt = $conn->prepare("INSERT INTO user_saved_designs (Design_Id, User_Id, Pro_Id, Design_JSON, Preview_Image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("siiss", $new_id, $uid, $pro_id, $design, $custom_image);
            $stmt->execute();
            $final_id = $new_id;
        }
    } else {
        // 未登录用户 Session 查重（可选实现，逻辑同上）
        // ... (此处省略 Session 查重代码，结构类似)
        $final_id = (!empty($update_id)) ? $update_id : ('design_' . uniqid());
        $_SESSION['saved_designs'][$pro_id][$final_id] = [
            'design_id' => $final_id, 'design_details' => $design, 'custom_preview' => $custom_image
        ];
    }

    echo json_encode(['success' => true, 'design_id' => $final_id, 'is_duplicate' => false]);
    exit;
}