<?php
// active_account.php
session_start();
require_once '../includes/db_connection.php';

// 1. 接收 token
$token = $_GET['token'] ?? null;
$message = "";
$messageType = ""; // 用于区分成功或错误提示框颜色

if (!$token) { 
    die("<div style='text-align:center; padding:50px; font-family: Arial, sans-serif; color: #d33;'><h2>Invalid request. No token provided.</h2></div>"); 
}

// 2. 验证 Token 是否存在且未过期
$check_token = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
$check_token->bind_param("s", $token);
$check_token->execute();
$result = $check_token->get_result();

if ($result->num_rows === 0) {
    die("<div style='text-align:center; padding:50px; font-family: Arial, sans-serif; color: #d33;'><h2>This activation link is invalid or has expired.</h2></div>");
}

// 获取该 token 对应的邮箱
$row = $result->fetch_assoc();
$email = $row['email'];
$check_token->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
        $messageType = "error";
    } else {
        // 3. 密码加密 (Hash)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 4. 更新 admin 表：设置新密码并将状态更新为 'Active'
        $stmt = $conn->prepare("UPDATE admin SET Admin_Password = ?, Admin_Status = 'Active' WHERE Admin_Email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            // 5. 激活成功后，删除这个 token，防止重复使用
            $del_token = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $del_token->bind_param("s", $token);
            $del_token->execute();
            $del_token->close();

            $message = "Account activated successfully! You can now <a href='admin_login.php' style='color:#FF8C00; font-weight:bold;'>Login Here</a>.";
            $messageType = "success";
        } else {
            $message = "Error updating account.";
            $messageType = "error";
        }
        $stmt->close();
    }
}

// 引入网页头部 (Header)
include_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* 全局字体设置 */
    body {
        font-family: Arial, "Segoe UI", sans-serif;
        background-color: #f8fafc; /* 淡灰背景，凸显中间的白色卡片 */
        margin: 0;
        padding: 0;
    }

    /* 居中卡片容器 */
    .activation-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 70vh; /* 确保在屏幕正中 */
        padding: 20px;
    }

    .activation-card {
        background: #ffffff;
        width: 100%;
        max-width: 450px;
        padding: 40px 50px;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        text-align: center; /* 内容居中 */
    }

    /* 主色调标题 */
    .activation-card h2 {
        color: #FF8C00; 
        font-size: 26px;
        font-weight: bold;
        margin-bottom: 10px;
        margin-top: 0;
    }

    .activation-card p.subtitle {
        color: #64748B;
        font-size: 14px;
        margin-bottom: 30px;
    }

    /* 输入框样式 */
    .form-group {
        position: relative;
        margin-bottom: 20px;
    }

    .form-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #94A3B8;
        font-size: 18px;
    }

    .form-control {
        width: 100%;
        padding: 14px 15px 14px 45px; /* 左侧留出图标空间 */
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        font-size: 15px;
        box-sizing: border-box;
        transition: all 0.3s ease;
        background-color: #F8FAFC;
    }

    /* 聚焦时显示主色调 */
    .form-control:focus {
        background-color: #FFFFFF;
        border-color: #FF8C00;
        outline: none;
        box-shadow: 0 0 0 4px rgba(255, 140, 0, 0.1);
    }

    /* 主色调按钮 */
    .btn-activate {
        background-color: #FF8C00;
        color: white;
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 10px;
    }

    .btn-activate:hover {
        background-color: #e67e00; /* 悬停时稍微加深 */
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 140, 0, 0.2);
    }

    /* 消息提示框 */
    .alert {
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 20px;
        text-align: center;
    }
    .alert-error {
        background-color: #FEE2E2;
        color: #DC2626;
        border: 1px solid #FCA5A5;
    }
    .alert-success {
        background-color: #DCFCE7;
        color: #166534;
        border: 1px solid #86EFAC;
    }
</style>

<div class="activation-wrapper">
    <div class="activation-card">
        <h2>Set Your Admin Password</h2>
        <p class="subtitle">Secure your newly activated account.</p>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($messageType !== 'success'): ?>
        <form method="POST" action="active_account.php?token=<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="New Password" required minlength="6">
            </div>

            <div class="form-group">
                <i class="bi bi-shield-check"></i>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New Password" required minlength="6">
            </div>

            <button type="submit" class="btn-activate">Save & Activate</button>
            
        </form>
        <?php endif; ?>
    </div>
</div>

<?php 
// 引入网页底部 (Footer)
include_once '../includes/footer.php'; 
?>