<?php
/**
 * admin_manage_admins.php 
 * 功能：管理员管理页面（仅限 Super Admin）
 */
session_start();
require_once '../includes/db_connection.php'; 

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

// --- 1. 安全与权限检查（已彻底移除反斜杠 \） ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>
        alert('Permission Denied: Only Super Admins can access this page.');
        window.location.href = 'admin_dashboard.php';
    </script>";
    exit();
}

$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';

// 确保头像来源
$admin_image = 'default_admin.png';
$admin_id = $_SESSION['admin_id'] ?? null;
if ($admin_id) {
    $img_res = $conn->query("SELECT Admin_Image FROM admin WHERE Admin_Id = $admin_id");
    if ($img_res && $img_row = $img_res->fetch_assoc()) {
        $admin_image = !empty($img_row['Admin_Image']) ? $img_row['Admin_Image'] : ($_SESSION['admin_image'] ?? 'default_admin.png');
    } else {
        $admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';
    }
} else {
    $admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';
}

// --- 2. 获取已激活或禁用的管理员列表 (主卡片渲染) ---
$sql = "SELECT a.*, IFNULL(b.Brand_Name, 'Super Admin') AS Display_Brand 
        FROM admin a 
        LEFT JOIN brand b ON a.Admin_Id = b.Admin_Id 
        WHERE a.Admin_Status != 'Pending'
        ORDER BY a.Admin_Level ASC";
$result = $conn->query($sql);

$admins_active_banned = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admins_active_banned[] = $row;
    }
}

// --- 3. 从 vendors 表中读取所有字段资料，完美映射 ---
$admins_pending = [];

// 自动侦测并兼容 vendors 表中的状态字段名
$check_col = $conn->query("SHOW COLUMNS FROM `vendors` LIKE 'Vendor_Status'");
$status_field = ($check_col && $check_col->num_rows > 0) ? 'Vendor_Status' : 'Status';

$vendor_sql = "SELECT * FROM `vendors` WHERE `$status_field` = 'Pending'";
$vendor_res = $conn->query($vendor_sql);

if ($vendor_res && $vendor_res->num_rows > 0) {
    while ($v_row = $vendor_res->fetch_assoc()) {
        $admins_pending[] = [
            'Id'               => $v_row['vendor_id'] ?? $v_row['id'] ?? 0,
            'Name'             => $v_row['business_name'] ?? $v_row['name'] ?? 'Unknown Partner',
            'Brand'            => $v_row['brand'] ?? 'No Brand', 
            'Email'            => $v_row['email'] ?? 'N/A',
            'Phone'            => $v_row['phone'] ?? 'N/A',
            'SSM'              => $v_row['reg_number'] ?? 'N/A', 
            'VerificationDoc'  => $v_row['auth_doc_path'] ?? '', 
            'BankName'         => $v_row['bank_name'] ?? 'N/A',
            'BankAccNo'        => $v_row['bank_acc_no'] ?? 'N/A', 
            'BankStatement'    => $v_row['bank_statement_path'] ?? '', 
            'WarehouseAddress' => $v_row['warehouse_address'] ?? 'N/A',
            'Image'            => $v_row['Vendor_Image'] ?? $v_row['image'] ?? ''
        ];
    }
}

$pending_count = count($admins_pending);
$admin_img_path = "../uploads/admin/";

// --- 修复点：确保 PHP 逻辑正确闭合 ---
if (isset($_SESSION['new_admin_data'])): 
    $data = $_SESSION['new_admin_data'];
?> 
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Admin Created Successfully!',
                html: 'Email: <b><?php echo addslashes($data['email']); ?></b><br>' +
                      'Password: <b><?php echo addslashes($data['password']); ?></b><br><br>' +
                      '<small>Please copy this password immediately.</small>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#FF6B00'
            });
        });
    </script>
<?php 
    // 这里重新开启 PHP 模式以执行 unset
    unset($_SESSION['new_admin_data']);
endif; // 结束 if 块
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
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; }
        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--orange-primary); object-fit: cover; }

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

        .status-tabs { display: flex; gap: 10px; background: #eee; padding: 5px; border-radius: 12px; width: fit-content; }
        .tab-btn { padding: 8px 25px; border-radius: 10px; border: none; background: transparent; font-weight: 600; color: #64748b; transition: all 0.3s ease; cursor: pointer; }
        .tab-btn.active { background: white; color: var(--orange-primary); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .tab-btn:hover:not(.active) { background: rgba(255, 255, 255, 0.5); }

        .partner-request-btn {
            background: white; color: var(--orange-primary); border: 1px solid #e2e8f0;
            width: 44px; height: 44px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.25rem;
            transition: all 0.3s ease; cursor: pointer; position: relative; padding: 0;
        }
        .partner-request-btn:hover {
            background: var(--orange-primary); color: white; border-color: var(--orange-primary);
            transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 140, 0, 0.2);
        }
        .partner-badge-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background-color: #ef4444; border-radius: 50%; border: 2px solid white; }

        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="status-tabs shadow-sm">
                <button class="tab-btn active" data-status="Active" onclick="filterByStatus('Active', this)">
                    <i class="bi bi-person-check-fill me-2"></i> Active Admins
                </button>
                <button class="tab-btn" data-status="Banned" onclick="filterByStatus('Banned', this)">
                    <i class="bi bi-person-x-fill me-2"></i> Banned Admins
                </button>
            </div>

            <button type="button" class="partner-request-btn shadow-sm" title="Receive Partner Requests" onclick="showPartnerRequestsPopup()">
                <i class="bi bi-people-fill"></i>
                <?php if ($pending_count > 0): ?>
                    <span class="partner-badge-dot"></span>
                <?php endif; ?>
            </button>
        </div>

        <div class="container-fluid p-0">
            <div class="action-bar">
                <div class="search-wrapper">
                    <i class="bi bi-search"></i>
                    <input type="text" id="adminSearch" class="search-input shadow-sm" placeholder="Search admin by name..." onkeyup="filterAdmins()">
                </div>
                <a href="add_admin.php" class="btn-add-admin shadow-sm">
                    <i class="bi bi-person-plus-fill me-2"></i> Add New Admin
                </a>
            </div>

            <div class="row g-4" id="adminList">
                <?php if(!empty($admins_active_banned)): ?>
                    <?php foreach($admins_active_banned as $row): 
                            $isBanned = (isset($row['Admin_Status']) && $row['Admin_Status'] == 'Banned');
                            $status = $isBanned ? 'Banned' : 'Active';

                            if (!empty($row['Admin_Image'])) {
                                $displayImg = $admin_img_path . $row['Admin_Image'];
                            } else {
                                $displayImg = 'https://ui-avatars.com/api/?name='.urlencode($row['Admin_Name']).'&background=random';
                            }
                        ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 admin-item" data-name="<?php echo strtolower(htmlspecialchars($row['Admin_Name'])); ?>" data-status="<?php echo $status; ?>">
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
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
                                tap chi</div>
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
    // 注入提取自 vendors 表的完整核心队列
    const pendingPartners = <?php echo json_encode($admins_pending); ?>;

    // 展示流式丰富信息卡片弹窗
    function showPartnerRequestsPopup() {
        if (pendingPartners.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No Pending Requests',
                text: 'There are currently no partner requests to review.',
                confirmButtonColor: '#FF8C00'
            });
            return;
        }

        let htmlContent = `<div style="max-height: 520px; overflow-y: auto; padding-right: 5px;">`;

        pendingPartners.forEach(partner => {
            let avatar = partner.Image ? `../uploads/vendor/${partner.Image}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(partner.Name)}&background=random`;
            
            // 💡 彻底清理了容易引发意外中断的行尾反斜杠 \
            let verifyDoc = partner.VerificationDoc 
                ? `<a href="../uploads/vendors/${partner.VerificationDoc}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:0.75rem;"><i class="bi bi-file-earmark-pdf"></i> View Document</a>` 
                : `<span class="text-muted small">Not Uploaded</span>`;
                
            let bankStatement = partner.BankStatement 
                ? `<a href="../uploads/vendors/${partner.BankStatement}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:0.75rem;"><i class="bi bi-file-earmark-richtext"></i> View Statement</a>` 
                : `<span class="text-muted small">Not Uploaded</span>`;

            htmlContent += `
                <div class="card mb-3 border text-start shadow-sm" style="border-radius: 12px; border-color: #e2e8f0 !important;">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <img src="${avatar}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1;" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(partner.Name)}'">
                            <span class="fw-bold text-dark" style="font-size: 0.9rem;">${partner.Name}</span>
                        </div>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill small fw-bold">New Apply</span>
                    </div>
                    
                    <div class="card-body p-3 bg-white" style="font-size: 0.83rem; line-height: 1.6; color: #475569;">
                        <div class="row g-3">
                            
                            <div class="col-md-4 border-end pe-3">
                                <div class="mb-3">
                                    <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Brand</span>
                                    <span class="text-dark fw-bold" style="color: var(--orange-primary) !important; font-size: 0.95rem;">${partner.Brand}</span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Email Address</span>
                                    <span class="text-dark fw-medium" style="word-break: break-all;">${partner.Email}</span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Phone</span>
                                    <span class="text-dark fw-medium">${partner.Phone}</span>
                                </div>
                                <div>
                                    <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">SSM (Reg Number)</span>
                                    <span class="text-dark fw-semibold">${partner.SSM}</span>
                                </div>
                            </div>
                            
                            <div class="col-md-8 ps-3">
                                <div class="mb-3">
                                    <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Verification Document</span>
                                    <div class="mt-1">${verifyDoc}</div>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Bank Name</span>
                                        <span class="text-dark fw-semibold">${partner.BankName}</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Bank Account Number</span>
                                        <span class="text-dark fw-bold text-secondary">${partner.BankAccNo}</span>
                                    </div>
                                </div>
                                
                                <div class="mb-1">
                                    <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Bank Statement</span>
                                    <div class="mt-1">${bankStatement}</div>
                                </div>
                            </div>

                            <div class="col-12 mt-2">
                                <span class="text-muted d-block small text-uppercase fw-semibold" style="font-size:0.72rem;">Warehouse Address</span>
                                <span class="text-dark bg-light d-block p-2 rounded mt-1 border" style="font-size:0.8rem; min-height:34px;">${partner.WarehouseAddress}</span>
                            </div>
                        </div>

                        <hr class="my-3" style="color: #e2e8f0; opacity: 0.6;">
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-success btn-sm px-3 fw-bold" onclick="handlePartnerReview(${partner.Id}, 'Active', '${partner.Name}')">
                                <i class="bi bi-check-lg me-1"></i> Approve Partner
                            </button>
                            <button class="btn btn-danger btn-sm px-3 fw-bold" onclick="handlePartnerReview(${partner.Id}, 'Banned', '${partner.Name}')">
                                <i class="bi bi-x-lg me-1"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        htmlContent += `</div>`;

        Swal.fire({
            title: 'Partner Onboarding Requests (Pending Vendors)',
            html: htmlContent,
            showConfirmButton: false,
            showCloseButton: true,
            width: '820px', 
            customClass: {
                title: 'fw-bold fs-5 text-start border-bottom pb-3 mt-2 ms-2'
            }
        });
    }

    // 二级审核处理流
    function handlePartnerReview(vendorId, action, partnerName) {
        const isActive = (action === 'Active');
        Swal.fire({
            title: isActive ? 'Approve Account?' : 'Reject Request?',
            text: isActive ? `Authorize ${partnerName} to access the system dashboard?` : `Deny and ban ${partnerName}?`,
            icon: isActive ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isActive ? '#198754' : '#d33',
            confirmButtonText: isActive ? 'Yes, Approve' : 'Yes, Reject'
        }).then((result) => {
        let action = result.isConfirmed ? 'approve' : 'reject';
        
        // 跳转到处理文件，带上 action 参数
        window.location.href = `update_vendors_status.php?id=${vendorId}&action=${action}`;
    });
    }

    function filterAdmins() {
        const input = document.getElementById('adminSearch');
        const filter = input.value.toLowerCase();
        const adminItems = document.querySelectorAll('.admin-item');
        adminItems.forEach(item => {
            const name = item.getAttribute('data-name');
            item.style.display = name.includes(filter) ? "" : "none";
        });
    }

    function filterByStatus(status, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const adminItems = document.querySelectorAll('.admin-item');
        adminItems.forEach(item => {
            const itemStatus = item.getAttribute('data-status');
            const searchTerm = document.getElementById('adminSearch').value.toLowerCase();
            const matchesSearch = (item.getAttribute('data-name') || '').includes(searchTerm);
            item.style.display = (itemStatus === status && matchesSearch) ? "" : "none";
        });
    }

    (function() {
        const params = new URLSearchParams(window.location.search);
        const s = params.get('status') || params.get('view');
        if (s && (s === 'Active' || s === 'Banned')) {
            const btns = document.querySelectorAll('.tab-btn');
                btns.forEach(b => {
                    if (b.dataset.status === s) {
                        filterByStatus(s, b);
                    }
                });
        } else {
            const activeBtn = document.querySelector('.tab-btn');
            if (activeBtn) filterByStatus('Active', activeBtn);
        }
    })();

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