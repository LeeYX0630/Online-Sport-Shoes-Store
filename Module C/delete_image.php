<?php
// delete_image.php
session_start();
if (!isset($_SESSION['role'])) { exit("Unauthorized"); }

if (isset($_POST['file_path'])) {
    $file = $_POST['file_path'];
    
    // 安全检查：确保路径在 uploads 目录下，防止路径穿越攻击
    $real_path = realpath($file);
    $upload_dir = realpath('../uploads/');

    if ($real_path && strpos($real_path, $upload_dir) === 0 && file_exists($real_path)) {
        if (unlink($real_path)) {
            echo "success";
        } else {
            echo "error";
        }
    } else {
        echo "invalid_path";
    }
}
?>