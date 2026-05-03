<?php
/**
 * admin_manage_admins.php 
 * 功能：管理员管理页面（仅限 Super Admin）
 */
session_start();
require_once '../includes/db_connection.php'; 

// --- 1. 安全与权限检查[cite: 2, 6] ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>
        alert('Permission Denied: Only Super Admins can access this page.');
        window.location.href = 'admin_dashboard.php';
    </script>";
    exit();
}

$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png'; // 提取自 source 1[cite: 1]

// --- 2. 获取管理员列表[cite: 6] ---
$sql = "SELECT a.*, IFNULL(b.Brand_Name, 'Super Admin') AS Display_Brand 
        FROM admin a 
        LEFT JOIN brand b ON a.Admin_Id = b.Admin_Id 
        ORDER BY a.Admin_Level ASC";
$result = $conn->query($sql);

// 定义图片根路径[cite: 6]
$admin_img_path = "../uploads/admin/";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | Online Sport Shoes</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* 统一全局 CSS 变量 (同步自 source 1 & 2)[cite: 1, 2] */
        :root { 
            --orange-primary: #FF8C00; 
            --sidebar-width: 260px; 
        }
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', 'Inter', sans-serif; 
            margin: 0;
        }
        .wrapper { display: flex; }

        /* 统一内容区域布局[cite: 1] */
        .main-content { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
        }

        /* 统一 Header 样式[cite: 1, 2] */
        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }

        /* 统一头像边框[cite: 1] */
        .admin-profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

        /* 管理员列表特有样式[cite: 6] */
        .action-bar { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 30px; }
        .search-wrapper { position: relative; flex: 1; max-width: 400px; }
        .search-wrapper i.bi-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
        .search-input { padding: 12px 40px; border-radius: 12px; border: 1px solid #e2e8f0; width: 100%; transition: 0.3s; }
        .search-input:focus { outline: none; border-color: var(--orange-primary); box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1); }

        .admin-card {
            background: white; border-radius: 20px; border: none;
            transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            overflow: hidden; text-align: center; padding: 30px 20px;
            height: 100%; display: flex; flex-direction: column; justify-content: space-between;
        }
        .admin-card:hover { transform: translateY(-10px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); }
        .avatar-container { width: 90px; height: 90px; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 3px solid #f1f5f9; }
        .avatar-container img { width: 100%; height: 100%; object-fit: cover; }
        
        .badge-role { font-size: 11px; padding: 5px 12px; border-radius: 50px; font-weight: 600; text-transform: uppercase; }
        .role-super { background: #fee2e2; color: #ef4444; }
        .btn-add-admin {
            background-color: var(--orange-primary); color: white; border: none;
            padding: 12px 25px; border-radius: 12px; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center;
        }
        .btn-add-admin:hover { background-color: #e67e00; color: white; }

        .strength-meter { height: 4px; width: 100%; background-color: #e2e8f0; margin-top: 10px; border-radius: 2px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0; transition: 0.3s; }
        .strength-weak { background-color: #ef4444; }
        .strength-medium { background-color: #f59e0b; }
        .strength-strong { background-color: #10b981; }

        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <!-- 统一 Header 布局[cite: 1, 2] -->
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                        <a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #6c757d;">Admin Manage</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Admin Overview</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                    <small class="text-muted">Super Admin</small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="container-fluid p-0">
            <!-- 操作栏[cite: 6] -->
            <div class="action-bar">
                <div class="search-wrapper">
                    <i class="bi bi-search"></i>
                    <input type="text" id="adminSearch" class="search-input shadow-sm" placeholder="Search admin by name..." onkeyup="filterAdmins()">
                </div>
                <a href="add_admin.php" class="btn-add-admin shadow-sm">
                    <i class="bi bi-person-plus-fill me-2"></i> Add New Admin
                </a>
            </div>

            <!-- 管理员列表[cite: 6] -->
            <div class="row g-4" id="adminList">
                <?php if($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $isBanned = (isset($row['Admin_Status']) && $row['Admin_Status'] == 'Banned');
                        if (!empty($row['Admin_Image'])) {
                            $displayImg = $admin_img_path . $row['Admin_Image'];
                        } else {
                            $displayImg = 'https://ui-avatars.com/api/?name='.urlencode($row['Admin_Name']).'&background=random';
                        }
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 admin-item" data-name="<?php echo strtolower(htmlspecialchars($row['Admin_Name'])); ?>">
                        <div class="admin-card">
                            <div>
                                <div class="avatar-container">
                                    <img src="<?php echo $displayImg; ?>" alt="admin">
                                </div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($row['Admin_Name']); ?></h5>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($row['Admin_Email']); ?></p>
                                <div class="mb-3">
                                    <?php if($row['Admin_Level'] == 1): ?>
                                        <span class="badge-role role-super">SUPER ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border px-3 py-1 rounded-pill small">
                                            <?php echo htmlspecialchars($row['Display_Brand']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if($isBanned): ?>
                                        <div class="mt-2"><span class="badge bg-danger rounded-pill">BANNED</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row g-2 mt-auto">
                                <div class="col-6">
                                    <button class="btn btn-light btn-sm w-100 border py-2" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                </div>
                                <div class="col-6">
                                    <?php if($isBanned): ?>
                                        <button class="btn btn-outline-success btn-sm w-100 py-2" onclick="toggleStatus(<?php echo $row['Admin_Id']; ?>, 'Active', '<?php echo $row['Admin_Name']; ?>')">
                                            <i class="bi bi-check-circle me-1"></i> Active
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-danger btn-sm w-100 py-2" onclick="toggleStatus(<?php echo $row['Admin_Id']; ?>, 'Banned', '<?php echo $row['Admin_Name']; ?>')">
                                            <i class="bi bi-slash-circle me-1"></i> Ban
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 编辑管理员 Modal[cite: 6] -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">Edit Admin Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAdminForm" action="update_admin_process.php" method="POST">
                <div class="modal-body px-4 pb-4">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <div class="mb-3 text-center">
                                <div class="avatar-container mb-2">
                                    <img id="edit_preview_img" src="" alt="preview">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Full Name</label>
                                <input type="text" name="admin_name" id="edit_admin_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                                <input type="email" name="admin_email" id="edit_admin_email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Admin Level</label>
                                <select name="admin_level" id="edit_admin_level" class="form-select">
                                    <option value="0">Normal Admin</option>
                                    <option value="1">Super Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Security</h6>
                            <p class="text-muted small">Leave blank if you don't want to change the password.</p>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">New Password</label>
                                <input type="password" name="password" id="passwordInput" class="form-control">
                                <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light px-4 border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange px-4 text-white" style="background-color: var(--orange-primary);">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function filterAdmins() {
        const input = document.getElementById('adminSearch');
        const filter = input.value.toLowerCase();
        const adminItems = document.querySelectorAll('.admin-item');
        adminItems.forEach(item => {
            const name = item.getAttribute('data-name');
            item.style.display = name.includes(filter) ? "" : "none";
        });
    }

    function toggleStatus(adminId, newStatus, adminName) {
        const isBanning = newStatus === 'Banned';
        Swal.fire({
            title: isBanning ? 'Are you sure?' : 'Confirm Activation',
            text: isBanning ? `Do you want to ban ${adminName}?` : `Activate ${adminName}'s account?`,
            icon: isBanning ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isBanning ? '#d33' : '#198754',
            confirmButtonText: isBanning ? 'Yes, Ban' : 'Yes, Activate'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `update_admin_status.php?id=${adminId}&status=${newStatus}`;
            }
        })
    }

    function openEditModal(adminData) {
        document.getElementById('edit_admin_id').value = adminData.Admin_Id;
        document.getElementById('edit_admin_name').value = adminData.Admin_Name;
        document.getElementById('edit_admin_email').value = adminData.Admin_Email;
        document.getElementById('edit_admin_level').value = adminData.Admin_Level;
        
        const uploadPath = '../uploads/admin/';
        document.getElementById('edit_preview_img').src = adminData.Admin_Image ? (uploadPath + adminData.Admin_Image) : ('https://ui-avatars.com/api/?name=' + encodeURIComponent(adminData.Admin_Name));
        
        document.getElementById('passwordInput').value = '';
        document.getElementById('strengthBar').style.width = '0%';
        new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    }

    // 密码强度检测[cite: 6]
    document.getElementById('passwordInput').addEventListener('input', function() {
        const val = this.value;
        let strength = 0;
        if (val.length > 0) {
            if (/\d/.test(val)) strength += 25;
            if (/[a-z]/.test(val)) strength += 25;
            if (/[A-Z]/.test(val)) strength += 25;
            if (/[^A-Za-z0-9]/.test(val)) strength += 25;
        }
        const bar = document.getElementById('strengthBar');
        bar.style.width = strength + '%';
        bar.className = 'strength-bar ' + (strength <= 25 ? 'strength-weak' : (strength <= 75 ? 'strength-medium' : 'strength-strong'));
    });
</script>
</body>
</html>