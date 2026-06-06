<?php
// admin_sidebar.php - Used in all admin pages for consistent navigation
include_once 'db_connection.php';
$current_page = basename($_SERVER['PHP_SELF']);
$admin_role = $_SESSION['role'] ?? ''; // 获取当前登录的角色
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<nav id="sidebarMenu" class="sidebar-container">
    
    <div class="sidebar-header">
        <div class="logo-icon">
            <img src="../images/picture/STRYDEX_Logo.jpeg" alt="Logo">
        </div>
        <div class="logo-text">
            <h5>STRYDEX Sport Shoe Store</h5>
            <p><?php echo (intval($admin_role) === 3) ? 'Vendor Panel' : 'Admin Panel'; ?></p>
        </div>
    </div>

<div class="sidebar-content">
        <small class="menu-label">MAIN MENU</small>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin_dashboard.php" class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                    <i class="bi bi-grid-fill"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_products.php" class="nav-link <?php echo ($current_page == 'admin_manage_products.php') ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam"></i> Products
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_orders.php" class="nav-link <?php echo ($current_page == 'admin_manage_orders.php') ? 'active' : ''; ?>">
                    <i class="bi bi-cart-check"></i> Orders
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_promos.php" class="nav-link <?php echo ($current_page == 'admin_manage_promos.php') ? 'active' : ''; ?>">
                    <i class="bi bi-megaphone"></i> Promo
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_notifications.php" class="nav-link <?php echo ($current_page == 'admin_notifications.php') ? 'active' : ''; ?>">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>

            <?php if ($admin_role === '1' || $admin_role === '2'): ?>
                            <li class="nav-item">
                                <a href="admin_manage_users.php" class="nav-link <?php echo ($current_page == 'admin_manage_users.php') ? 'active' : ''; ?>">
                                    <i class="bi bi-people"></i> Customers
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="admin_manage_admins.php" class="nav-link <?php echo ($current_page == 'admin_manage_admins.php') ? 'active' : ''; ?>">
                                    <i class="bi bi-shield-lock"></i> Admins
                                </a>
                            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a href="admin_profile.php" class="nav-link <?php echo ($current_page == 'admin_profile.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i> Profile
                </a>
            </li>
        </ul>
    </div>

    <a href="javascript:void(0);" class="sidebar-footer logout-link d-flex align-items-center" title="Sign out" onclick="confirmLogout()" style="text-decoration:none; gap:10px;">
        <i class="bi bi-door-open" style="font-size:18px; color:#FF6B00;"></i>
        <span style="font-weight:600; color:#212529;">Log out</span>
    </a>
</nav>

<style>
/* 导入谷歌字体 */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Bebas+Neue&display=swap');

/* --- 基础布局 --- */
.sidebar-container {
    width: 260px;
    height: 100vh;
    background-color: #FFFFFF; 
    color: #212529; 
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0; top: 0;
    border-right: 1px solid #f0f0f0;
    z-index: 1000; 
}

.sidebar-header { padding: 25px; display: flex; align-items: center; gap: 12px; }
.logo-icon img { max-width: 62px; object-fit: contain; border-radius: 10px; }
.logo-text h5 { 
    margin: 0; 
    font-size: 22px; 
    font-weight: 800;
    font-family: 'Bebas Neue', 'Poppins', sans-serif;
    letter-spacing: 1px;
    color: #FF6B00;
    text-transform: uppercase;
}
.logo-text p { margin: 0; font-size: 12px; color: #888; }

.sidebar-content { flex: 1; overflow-y: auto; padding: 10px 15px; }
.menu-label { color: #999; font-size: 11px; padding-left: 15px; letter-spacing: 1px; }

/* --- 导航链接 --- */
.nav-menu { list-style: none; padding: 0; margin-top: 15px; }
.nav-link {
    display: flex; align-items: center; padding: 12px 15px; color: #212529;
    text-decoration: none; border-radius: 8px; transition: 0.3s; cursor: pointer;
}
.nav-link i:first-child { margin-right: 12px; font-size: 18px; }

.nav-link:hover { background-color: #FFF0E5; }
.nav-link.active { background-color: #FF6B00; color: #ffffff; }

/* --- 底部 --- */
.sidebar-footer { padding: 20px; border-top: 1px solid #f0f0f0; display: flex; align-items: center; gap: 12px; background: #fff; transition: background 0.18s ease; cursor: pointer; }


/* 浅橙色 hover for full footer */
.sidebar-footer:hover { background-color: #FFF0E5; }
.sidebar-footer:focus { outline: none; box-shadow: inset 0 0 0 2px rgba(255,107,0,0.06); }

/* --- SweetAlert2 自定义样式 --- */
.swal2-actions button {
    margin: 0 10px !important;
    padding: 10px 25px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    transition: all 0.2s ease !important;
}
.btn-yes {
    background-color: transparent !important;
    color: #FF6B00 !important;
    border: 2px solid #FF6B00 !important;
}
.btn-yes:hover { background-color: #FF6B00 !important; color: #fff !important; }

.btn-no {
    background-color: transparent !important;
    color: #888 !important;
    border: 2px solid #ddd !important;
}
.btn-no:hover { border-color: #888 !important; color: #333 !important; }
</style>

<script>
// SweetAlert2 退出确认
function confirmLogout() {
    Swal.fire({
        title: 'Sign Out?',
        text: "Are you sure you want to exit the admin panel?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, sign out!',
        cancelButtonText: 'No, stay here',
        customClass: {
            confirmButton: 'btn-yes',
            cancelButton: 'btn-no'
        },
        buttonsStyling: false,
        background: '#fff',
        color: '#212529',
        iconColor: '#FF6B00'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin_logout.php';
        }
    });
}
</script>