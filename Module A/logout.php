<?php
// for user logout
session_start();

$redirect_to = "login.php"; 

if (isset($_GET['redirect']) && $_GET['redirect'] == 'home') {
    // 【关键修复】加上 ../ 回到根目录的 index.php
    $redirect_to = "../index.php?msg=logged_out";
}
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: " . $redirect_to);
exit();
?>