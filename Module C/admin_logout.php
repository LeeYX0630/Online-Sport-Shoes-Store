<?php
// Module C/admin_logout.php
session_start();

// 1. 清空所有 Session 变量
$_SESSION = array();

// 2. 如果使用了 Cookie 存储 Session ID，则将其删除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 彻底销毁 Session
session_destroy();

// 4. 重定向到首页
header("Location: ../index.php");
exit();
?>