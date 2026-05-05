<?php
/**
 * update_admin_status.php
 * 功能：处理管理员状态更新逻辑 (含美化版告警及智能重定向)
 */
session_start();
require_once '../includes/db_connection.php';

// --- 1. 权限检查 ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    die("Unauthorized access.");
}

// We'll only output SweetAlert HTML when needed (after headers). Do not echo here to keep header() usable.

if (isset($_GET['id']) && isset($_GET['status'])) {
    
    $adminId = intval($_GET['id']);
    $rawStatus = $_GET['status']; // 这里的 status 是我们想要改变的目标状态
    $newStatus = ($rawStatus === 'Banned') ? 'Banned' : 'Active';
    
    // 决定操作完成后应该跳到哪个 Tab
    // 逻辑：如果你把人 Ban 了，应该跳到 Banned 页看结果；如果你激活了人，跳回 Active 页
    $targetView = $newStatus; 

    // --- 2. 安全检查：不能封禁自己[cite: 3] ---
    if ($adminId == $_SESSION['admin_id'] && $newStatus === 'Banned') {
        $returnView = isset($_GET['view']) && in_array($_GET['view'], ['Active','Banned']) ? $_GET['view'] : 'Active';
        // emit a small HTML page that shows SweetAlert then redirects (we already sent no output)
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Action Blocked</title>'; 
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo '</head><body><script>
            setTimeout(function(){
                Swal.fire({
                    title: "Access Denied!",
                    text: "You cannot ban your own account. This action is restricted to prevent system lockout.",
                    icon: "error",
                    confirmButtonColor: "#FF6B00",
                    confirmButtonText: "I Understand"
                }).then(function(){
                    window.location.href = "admin_manage_admins.php?status=' . $returnView . '&view=' . $returnView . '";
                });
            },100);
        </script></body></html>';
        exit();
    }

    // --- 3. 执行数据库更新[cite: 3] ---
    $sql = "UPDATE admin SET Admin_Status = ? WHERE Admin_Id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("si", $newStatus, $adminId);
        
        if ($stmt->execute()) {
            // Redirect to admin_manage_admins.php with both status and view params
            header("Location: admin_manage_admins.php?status=" . urlencode($targetView) . "&view=" . urlencode($targetView));
            exit();
        } else {
            $returnView = isset($_GET['view']) && in_array($_GET['view'], ['Active','Banned']) ? $_GET['view'] : $targetView;
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>';
            echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
            echo '</head><body><script>
                setTimeout(function(){
                    Swal.fire({
                        title: "Error!",
                        text: "Failed to update database status.",
                        icon: "error",
                        confirmButtonColor: "#d33"
                    }).then(function(){
                        window.location.href = "admin_manage_admins.php?status=' . $returnView . '&view=' . $returnView . '";
                    });
                },100);
            </script></body></html>';
        }
        $stmt->close();
    }
} else {
    header("Location: admin_manage_admins.php");
    exit();
}

$conn->close();
?>