<?php
/**
 * update_admin_process.php
 * 功能：处理 Edit Admin Details 表单提交（仅限 Super Admin）
 * 来源表单：admin_manage_admins.php -> #editAdminForm
 */
session_start();
require_once '../includes/db_connection.php';

// --- 1. 安全与权限检查 ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>
        alert('Permission Denied: Only Super Admins can access this page.');
        window.location.href = 'admin_login.php';
    </script>";
    exit();
}

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_manage_admins.php");
    exit();
}

/**
 * 统一的失败返回函数：把错误信息塞进 session，跳回管理页面，
 * 由 admin_manage_admins.php 用 SweetAlert2 弹出提示。
 */
function fail_redirect($title, $text) {
    $_SESSION['vendor_action_msg'] = [
        'type'  => 'reject', // 复用现有结构，'reject' 对应 warning 图标
        'title' => $title,
        'text'  => $text
    ];
    header("Location: admin_manage_admins.php");
    exit();
}

// --- 2. 接收并基本清理表单数据 ---
$admin_id        = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;
$admin_name      = trim($_POST['admin_name'] ?? '');
$admin_email     = trim($_POST['admin_email'] ?? '');
$admin_level     = isset($_POST['admin_level']) ? (int) $_POST['admin_level'] : 0;
$password        = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// --- 3. 基本验证 ---
if ($admin_id <= 0) {
    fail_redirect('Update Failed', 'Invalid admin ID.');
}

if ($admin_name === '' || $admin_email === '') {
    fail_redirect('Update Failed', 'Full name and email cannot be empty.');
}

if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
    fail_redirect('Update Failed', 'Please enter a valid email address.');
}

if (!in_array($admin_level, [0, 1], true)) {
    fail_redirect('Update Failed', 'Invalid admin level selected.');
}

// 密码：留空代表不修改；若填了，必须跟 confirm_password 一致
$update_password = false;
$hashed_password = null;

if ($password !== '' || $confirm_password !== '') {
    if ($password !== $confirm_password) {
        fail_redirect('Update Failed', 'Password and confirm password do not match.');
    }
    if (strlen($password) < 6) {
        fail_redirect('Update Failed', 'Password must be at least 6 characters long.');
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $update_password = true;
}

// --- 4. 确认该管理员存在 ---
$check_stmt = $conn->prepare("SELECT Admin_Id, Admin_Email FROM admin WHERE Admin_Id = ?");
$check_stmt->bind_param("i", $admin_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    fail_redirect('Update Failed', 'Admin not found.');
}
$current_admin = $check_result->fetch_assoc();
$check_stmt->close();

// --- 5. 确认 email 没有被其他管理员占用 ---
if (strcasecmp($current_admin['Admin_Email'], $admin_email) !== 0) {
    $email_check = $conn->prepare("SELECT Admin_Id FROM admin WHERE Admin_Email = ? AND Admin_Id != ?");
    $email_check->bind_param("si", $admin_email, $admin_id);
    $email_check->execute();
    $email_result = $email_check->get_result();

    if ($email_result->num_rows > 0) {
        $email_check->close();
        fail_redirect('Update Failed', 'This email is already used by another admin.');
    }
    $email_check->close();
}

// --- 6. 执行更新 ---
if ($update_password) {
    $update_stmt = $conn->prepare(
        "UPDATE admin SET Admin_Name = ?, Admin_Email = ?, Admin_Level = ?, Admin_Password = ? WHERE Admin_Id = ?"
    );
    $update_stmt->bind_param("ssisi", $admin_name, $admin_email, $admin_level, $hashed_password, $admin_id);
} else {
    $update_stmt = $conn->prepare(
        "UPDATE admin SET Admin_Name = ?, Admin_Email = ?, Admin_Level = ? WHERE Admin_Id = ?"
    );
    $update_stmt->bind_param("ssii", $admin_name, $admin_email, $admin_level, $admin_id);
}

$success = $update_stmt->execute();
$update_stmt->close();

if (!$success) {
    fail_redirect('Update Failed', 'Something went wrong while updating the admin. Please try again.');
}

// --- 7. 如果修改的是当前登录的这位管理员，同步更新 session 显示信息 ---
if (isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] === $admin_id) {
    $_SESSION['username'] = $admin_name;
}

// --- 8. 成功提示并跳转回管理页面 ---
$_SESSION['vendor_action_msg'] = [
    'type'  => 'approve', // 'approve' 对应 success 图标
    'title' => 'Admin Updated!',
    'text'  => htmlspecialchars($admin_name) . "'s details have been updated successfully."
];

header("Location: admin_manage_admins.php");
exit();