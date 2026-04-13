<?php
// 包含数据库连接文件
// 请确保路径指向你真正的 db_connection.php
require_once '../includes/db_connection.php';

// --- 这里修改你想要的账号信息 ---
$admin_name     = "SuperAdmin";
$admin_email    = "admin@sport.com";
$admin_password = "password123"; // 这是你以后登录要输入的明文
$admin_level    = "1";           // 1 通常代表最高权限
// -----------------------------

// 使用 password_hash 进行加密（这非常重要，否则登录会失败）
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// 准备 SQL 语句，严格对应你截图中的字段名
$sql = "INSERT INTO admin (Admin_Name, Admin_Email, Admin_Password, Admin_Level) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // 绑定参数 (s 代表 string 字符串)
    $stmt->bind_param("ssss", $admin_name, $admin_email, $hashed_password, $admin_level);
    
    if ($stmt->execute()) {
        echo "<h3>✅ 管理员创建成功！</h3>";
        echo "<b>Email:</b> " . $admin_email . "<br>";
        echo "<b>Password:</b> " . $admin_password . "<br>";
        echo "<p style='color:red;'>请立即删除此文件 (create_first_admin.php) 以保安全。</p>";
    } else {
        echo "❌ 插入失败: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "❌ SQL 准备失败: " . $conn->error;
}
?>