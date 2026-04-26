<?php
/**
 * update_admin_status.php
 * 功能：处理管理员状态更新逻辑 (含美化版告警)
 */
session_start();
require_once '../includes/db_connection.php';

// 只有超级管理员（Level 1）可以执行此操作
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    die("Unauthorized access.");
}

// 引入 SweetAlert2 所需的库（用于报错显示）
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
echo '<style>body { font-family: "Inter", sans-serif; }</style>';

if (isset($_GET['id']) && isset($_GET['status'])) {
    
    $adminId = intval($_GET['id']);
    $rawStatus = $_GET['status'];
    $newStatus = ($rawStatus === 'Banned') ? 'Banned' : 'Active';

    // --- 重点修改：美化版的“不能封禁自己”提示 ---
    if ($adminId == $_SESSION['admin_id'] && $newStatus === 'Banned') {
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Access Denied!',
                    text: 'You cannot ban your own account. This action is restricted to prevent system lockout.',
                    icon: 'error',
                    confirmButtonColor: '#FF6B00',
                    confirmButtonText: 'I Understand'
                }).then(() => {
                    window.location.href = 'admin_manage_admins.php';
                });
            }, 100);
        </script>";
        exit();
    }

    // 执行数据库更新
    $sql = "UPDATE admin SET Admin_Status = ? WHERE Admin_Id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("si", $newStatus, $adminId);
        
        if ($stmt->execute()) {
            // 更新成功直接跳转
            header("Location: admin_manage_admins.php");
            exit();
        } else {
            // 更新失败的美化提示
            echo "<script>
                setTimeout(function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update database status.',
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    }).then(() => {
                        window.location.href = 'admin_manage_admins.php';
                    });
                }, 100);
            </script>";
        }
        $stmt->close();
    }
} else {
    header("Location: admin_manage_admins.php");
    exit();
}

$conn->close();
?>