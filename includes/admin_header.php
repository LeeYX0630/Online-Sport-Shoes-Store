<?php
// 检查 session 是否已启动，若未启动则启动（防止在 include 时报错）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取用户信息：优先从 DB 读取最新头像与权限，回退到 session 或默认图
$current_admin_level = $_SESSION['role'] ?? 2;
$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_image = 'default_admin.png';

// 确保有数据库连接可用（尝试包含同目录的 db_connection.php）
if (!isset($conn)) {
    $dbPath = __DIR__ . '/db_connection.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
    }
}

if (isset($_SESSION['admin_id']) && isset($conn) && $conn) {
    $admin_id = (int)$_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT Admin_Image, Admin_Level, Admin_Name FROM admin WHERE Admin_Id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $admin_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['Admin_Image'])) {
                    $admin_image = $row['Admin_Image'];
                } elseif (!empty($_SESSION['admin_image'])) {
                    $admin_image = $_SESSION['admin_image'];
                }
                if (!empty($row['Admin_Name'])) {
                    $admin_name = $row['Admin_Name'];
                }
                if (isset($row['Admin_Level'])) {
                    $current_admin_level = $row['Admin_Level'];
                }
            } else {
                // 无 DB 记录时回退到 session
                $admin_image = $_SESSION['admin_image'] ?? $admin_image;
            }
        }
        $stmt->close();
    } else {
        // 无法准备语句时回退到 session
        $admin_image = $_SESSION['admin_image'] ?? $admin_image;
    }
} else {
    $admin_image = $_SESSION['admin_image'] ?? $admin_image;
}

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