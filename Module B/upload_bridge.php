<?php
// Module B/upload_bridge.php
header('Content-Type: application/json'); // 强制返回 JSON 格式
require_once '../includes/db_connection.php'; // 包含数据库连接[cite: 1, 2]

$token = $_POST['token'] ?? '';
$success = false;
$error_msg = '';

if (!empty($token) && isset($_FILES['image'])) {
    
    // 1. 设置上传目录
    $target_dir = "../Module B/uploads/";
    // 如果目录不存在，自动创建
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // 2. 生成唯一的文件名 (例如: SS-fbh1vlr0h_capture.jpg)
    $filename = $token . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $filename;
    
    // 3. 将临时文件移动到最终目录
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // 4. 更新数据库中的 camera_bridge 表
        $file_path = "uploads/" . $filename; // 电脑端读取的路径
        $stmt = $conn->prepare("UPDATE camera_bridge SET image_path = ?, status = 'captured' WHERE session_token = ?");
        $stmt->bind_param("ss", $file_path, $token);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = true;
            } else {
                $error_msg = "Database update failed: No matching token found.";
            }
        } else {
            $error_msg = "Database query failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_msg = "Failed to move uploaded file.";
    }
} else {
    $error_msg = "Token or image data is missing in the request.";
}

// 5. 返回结果给手机端
echo json_encode(['success' => $success, 'error' => $error_msg]);
$conn->close();
?>