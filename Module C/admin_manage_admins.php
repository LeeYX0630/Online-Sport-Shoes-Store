<?php
/**
 * admin_manage_admins.php 
 * 功能：管理员管理页面（仅限 Super Admin）
 */
session_start();
require_once '../includes/db_connection.php'; 

// --- 1. 安全与权限检查 ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>
        alert('Permission Denied: Only Super Admins can access this page.');
        window.location.href = 'admin_dashboard.php';
    </script>";
    exit();
}

$username = $_SESSION['username'] ?? 'Admin';

// --- 2. 获取管理员列表 ---
$sql = "SELECT a.*, IFNULL(b.Brand_Name, 'Super Admin') AS Display_Brand 
        FROM admin a 
        LEFT JOIN brand b ON a.Admin_Id = b.Admin_Id 
        ORDER BY a.Admin_Level ASC";
$result = $conn->query($sql);

// 定义图片根路径
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-orange: #FF6B00; 
        }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }
        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 40px; background-color: #FFFFFF; border-bottom: 1px solid #edf2f7;
            position: sticky; top: 0; z-index: 100;
        }
        .top-bar-left h2 { margin: 0; font-size: 22px; color: #212529; font-weight: 700; }
        
        .action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 30px;
        }
        .search-wrapper {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        .search-wrapper i.bi-search {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        .search-wrapper .btn-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            display: none;
        }
        .search-wrapper .btn-clear:hover { color: #ef4444; }
        .search-input {
            padding: 12px 40px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            width: 100%;
            transition: 0.3s;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
        }

        .admin-card {
            background: white; border-radius: 20px; border: none;
            transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            overflow: hidden; text-align: center; padding: 30px 20px;
            height: 100%; display: flex; flex-direction: column; justify-content: space-between;
        }
        .admin-card:hover { transform: translateY(-10px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); }
        .avatar-container {
            width: 90px; height: 90px; margin: 0 auto 15px;
            border-radius: 50%; overflow: hidden; border: 3px solid #f1f5f9;
        }
        .avatar-container img { width: 100%; height: 100%; object-fit: cover; }
        .badge-role { font-size: 11px; padding: 5px 12px; border-radius: 50px; font-weight: 600; text-transform: uppercase; }
        .role-super { background: #fee2e2; color: #ef4444; }
        .role-brand { background: #e0e7ff; color: #4338ca; }
        .btn-action { border-radius: 12px; font-weight: 600; padding: 10px; transition: 0.2s; height: 45px; display: flex; align-items: center; justify-content: center; }
        
        .btn-add-admin {
            background-color: var(--primary-orange); color: white; border: none;
            padding: 12px 25px; border-radius: 12px; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center;
            white-space: nowrap;
        }
        .btn-add-admin:hover { background-color: #e66000; color: white; }

        .strength-meter { height: 4px; width: 100%; background-color: #e2e8f0; margin-top: 10px; border-radius: 2px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0; transition: 0.3s; }
        .strength-weak { background-color: #ef4444; }
        .strength-medium { background-color: #f59e0b; }
        .strength-strong { background-color: #10b981; }
        .password-hints { font-size: 12px; color: #94a3b8; margin-top: 5px; }
        .hint-item.met { display: none; }

        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="top-bar">
            <div class="top-bar-left">
                <h2>Admin Overview</h2>
                <p class="text-muted small mb-0">System access control and personnel management</p>
            </div>
        </header>

        <div class="p-4 p-lg-5">
            <div class="action-bar">
                <div class="search-wrapper">
                    <i class="bi bi-search"></i>
                    <input type="text" id="adminSearch" class="search-input" placeholder="Search admin by name..." onkeyup="filterAdmins()">
                    <button type="button" id="clearSearch" class="btn-clear" onclick="clearSearchInput()">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <a href="add_admin.php" class="btn-add-admin shadow-sm">
                    <i class="bi bi-person-plus-fill me-2"></i> Add New Admin
                </a>
            </div>

            <div class="row g-4" id="adminList">
                <?php if($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $isBanned = (isset($row['Admin_Status']) && $row['Admin_Status'] == 'Banned');
                        
                        // --- 核心修改：处理 PHP 图片路径 ---
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
                                
                                <h5 class="fw-bold mb-1 admin-name-text"><?php echo htmlspecialchars($row['Admin_Name']); ?></h5>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($row['Admin_Email']); ?></p>
                                
                                <div class="mb-3">
                                    <?php if($row['Admin_Level'] == 1): ?>
                                        <span class="badge-role role-super">SUPER ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo htmlspecialchars($row['Display_Brand']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if($isBanned): ?>
                                        <div class="mt-2"><span class="badge bg-danger">BANNED</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row g-2 mt-auto">
                                <div class="col-6">
                                    <button class="btn btn-light btn-action w-100" 
                                            onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                </div>
                                <div class="col-6">
                                    <?php if($isBanned): ?>
                                        <button class="btn btn-outline-success btn-action w-100" onclick="toggleStatus(<?php echo $row['Admin_Id']; ?>, 'Active', '<?php echo $row['Admin_Name']; ?>')">
                                            <i class="bi bi-check-circle me-1"></i> Active
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-danger btn-action w-100" onclick="toggleStatus(<?php echo $row['Admin_Id']; ?>, 'Banned', '<?php echo $row['Admin_Name']; ?>')">
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

    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="editAdminModalLabel">Edit Admin Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                    <small class="text-muted">Profile Preview</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">Full Name</label>
                                    <input type="text" name="admin_name" id="edit_admin_name" class="form-control" style="border-radius: 10px;" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">Email Address</label>
                                    <input type="email" name="admin_email" id="edit_admin_email" class="form-control" style="border-radius: 10px;" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">Admin Level</label>
                                    <select name="admin_level" id="edit_admin_level" class="form-select" style="border-radius: 10px;">
                                        <option value="0">Normal Admin</option>
                                        <option value="1">Super Admin</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3 mt-md-0 mt-3">Change Password</h6>
                                <p class="text-muted small mb-3">Leave blank if you don't want to change the password.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">New Password</label>
                                    <input type="password" name="password" id="passwordInput" class="form-control" style="border-radius: 10px;">
                                    
                                    <div class="strength-meter">
                                        <div id="strengthBar" class="strength-bar"></div>
                                    </div>
                                    <div class="password-hints" id="passwordHints">
                                        <span id="hint-num">*number</span>
                                        <span id="hint-low">, *lowercase</span>
                                        <span id="hint-up">, *uppercase</span>
                                        <span id="hint-sym">, *Symbols</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" style="border-radius: 10px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-light px-4" style="border-radius: 10px;" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4" style="border-radius: 10px; background-color: var(--primary-orange); border: none;">Save Changes</button>
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
            const clearBtn = document.getElementById('clearSearch');

            clearBtn.style.display = input.value.length > 0 ? 'block' : 'none';

            adminItems.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(filter)) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        }

        function clearSearchInput() {
            const input = document.getElementById('adminSearch');
            input.value = '';
            filterAdmins();
            input.focus();
        }

        function toggleStatus(adminId, newStatus, adminName) {
            const isBanning = newStatus === 'Banned';
            Swal.fire({
                title: isBanning ? 'Are you sure?' : 'Confirm Activation',
                text: isBanning ? `Do you want to ban ${adminName}? They will lose system access.` : `Do you want to activate ${adminName}'s account?`,
                icon: isBanning ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: isBanning ? '#d33' : '#198754',
                cancelButtonColor: '#64748b',
                confirmButtonText: isBanning ? 'Yes, Ban' : 'Yes, Activate',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `update_admin_status.php?id=${adminId}&status=${newStatus}`;
                }
            })
        }

        // --- 核心修改：处理 JS 图片路径预览 ---
        function openEditModal(adminData) {
            document.getElementById('edit_admin_id').value = adminData.Admin_Id;
            document.getElementById('edit_admin_name').value = adminData.Admin_Name;
            document.getElementById('edit_admin_email').value = adminData.Admin_Email;
            document.getElementById('edit_admin_level').value = adminData.Admin_Level;
            
            const uploadPath = '../uploads/admin/';
            let imgSrc;
            
            if (adminData.Admin_Image) {
                imgSrc = uploadPath + adminData.Admin_Image;
            } else {
                imgSrc = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(adminData.Admin_Name) + '&background=random';
            }
            
            document.getElementById('edit_preview_img').src = imgSrc;

            // 重置密码
            document.getElementById('passwordInput').value = '';
            document.querySelector('[name="confirm_password"]').value = '';
            document.getElementById('strengthBar').style.width = '0%';

            const editModal = new bootstrap.Modal(document.getElementById('editAdminModal'));
            editModal.show();
        }

        // 密码强度
        const passwordInput = document.getElementById('passwordInput');
        const strengthBar = document.getElementById('strengthBar');
        const hints = {
            num: document.getElementById('hint-num'),
            low: document.getElementById('hint-low'),
            up: document.getElementById('hint-up'),
            sym: document.getElementById('hint-sym')
        };

        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let strength = 0;

            if (val.length === 0) {
                strengthBar.style.width = '0%';
                Object.values(hints).forEach(h => h.classList.remove('met'));
                return;
            }

            const hasNum = /\d/.test(val);
            const hasLow = /[a-z]/.test(val);
            const hasUp = /[A-Z]/.test(val);
            const hasSym = /[^A-Za-z0-9]/.test(val);

            hasNum ? hints.num.classList.add('met') : hints.num.classList.remove('met');
            hasLow ? hints.low.classList.add('met') : hints.low.classList.remove('met');
            hasUp ? hints.up.classList.add('met') : hints.up.classList.remove('met');
            hasSym ? hints.sym.classList.add('met') : hints.sym.classList.remove('met');

            if(hasNum) strength += 25;
            if(hasLow) strength += 25;
            if(hasUp) strength += 25;
            if(hasSym) strength += 25;

            strengthBar.style.width = strength + '%';
            strengthBar.className = 'strength-bar';
            if (strength <= 25) strengthBar.classList.add('strength-weak');
            else if (strength <= 75) strengthBar.classList.add('strength-medium');
            else strengthBar.classList.add('strength-strong');
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>