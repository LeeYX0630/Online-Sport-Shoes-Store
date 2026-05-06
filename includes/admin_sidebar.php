<?php
// admin_sidebar.php - Used in all admin pages for consistent navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<nav id="sidebarMenu" class="sidebar-container">
    
    <div class="sidebar-header">
        <div class="logo-icon">
            <img src="../images/picture/Logo 2.png" alt="Logo">
        </div>
        <div class="logo-text">
            <h5>SS Sport</h5>
            <p>Admin Panel</p>
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
                    <i class="bi bi-cart"></i> Orders
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_promos.php" class="nav-link <?php echo ($current_page == 'admin_manage_promos.php') ? 'active' : ''; ?>">
                    <i class="bi bi-ticket-perforated"></i> Promo
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_users.php" class="nav-link <?php echo ($current_page == 'admin_manage_users.php') ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Users
                </a>
            </li>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
            <li class="nav-item">
                <a href="admin_manage_admins.php" class="nav-link <?php echo ($current_page == 'admin_manage_admins.php') ? 'active' : ''; ?>">
                    <i class="bi bi-shield-check"></i> Admins
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a href="admin_profile.php" class="nav-link <?php echo ($current_page == 'admin_profile.php') ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-avatar">
            <?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 2)) : 'AD'; ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            <span class="user-email"><?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 1) ? 'Super Admin' : 'Brand Admin'; ?></span>
        </div>
        <a href="javascript:void(0);" class="logout-btn" title="Sign out" onclick="confirmLogout()">
            <i class="bi bi-box-arrow-right"></i>
        </a>    
    </div>
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
.logo-icon img { max-width: 55px; object-fit: contain; }
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
.sidebar-footer { padding: 20px; border-top: 1px solid #f0f0f0; display: flex; align-items: center; gap: 12px; background: #fff; }
.user-avatar { width: 40px; height: 40px; background: #FF6B00; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.user-info { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.user-name { font-size: 14px; font-weight: 600; }
.logout-btn { color: #888; font-size: 20px; transition: 0.3s; cursor: pointer; }
.logout-btn:hover { color: #FF6B00; }

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