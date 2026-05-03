<?php
// 检查 session 是否已启动，若未启动则启动（防止在 include 时报错）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取用户信息
$current_admin_level = $_SESSION['role'] ?? 2; 
$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';

/**
 * 变量说明：
 * $header_title: 当前页面的大标题
 * $breadcrumb_item: 面包屑导航的当前项名称
 */
$header_title = $header_title ?? 'Dashboard';
$breadcrumb_item = $breadcrumb_item ?? 'Overview';
?>

<!-- Header 组件样式[cite: 2] -->
<style>
    .admin-header { 
        background: white; 
        padding: 15px 30px; 
        border-radius: 15px; 
        margin-bottom: 25px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
    }
    .admin-profile-img { 
        width: 42px; 
        height: 42px; 
        border-radius: 50%; 
        border: 2px solid #FF8C00; /* 使用源文件中的橙色 */
        object-fit: cover; 
    }
    .breadcrumb-item a { color: #FF8C00; text-decoration: none; }
</style>

<header class="admin-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="admin_dashboard.php">Home</a></li>
                <li class="breadcrumb-item active"><?php echo $breadcrumb_item; ?></li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0"><?php echo $header_title; ?></h4>
    </div>
    <div class="d-flex align-items-center">
        <div class="text-end me-3 text-dark">
            <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
            <small class="text-muted">
                <?php echo ($current_admin_level == 1) ? 'Super Admin' : 'Manager'; ?>
            </small>
        </div>
        <!-- 自动添加时间戳防止头像缓存[cite: 2] -->
        <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
    </div>
</header>