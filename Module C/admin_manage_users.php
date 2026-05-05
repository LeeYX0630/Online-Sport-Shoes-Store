<?php
// admin/admin_manage_users.php
session_start();
require_once '../includes/db_connection.php';

// 1. 安全检查[cite: 2]
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';

// 确保头像来源：优先使用 DB 中的图片，其次使用 session，最后回退到默认图
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

// 2. 搜索与过滤逻辑[cite: 5]
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";
$sql = "SELECT * FROM user WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (User_Name LIKE '%$search%' OR User_Email LIKE '%$search%')";
}
$sql .= " ORDER BY User_Id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Online Sports Shoes Store</title>
    
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

        /* 用户列表卡片样式[cite: 5] */
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .search-bar-container {
            max-width: 500px;
            margin-bottom: 25px;
        }

        .btn-orange { 
            background-color: var(--orange-primary); color: white; border: none; 
            padding: 10px 20px; border-radius: 10px; font-weight: 600; transition: 0.3s;
        }
        .btn-orange:hover { background-color: #e67e00; color: white; }

        .table thead th { 
            background-color: #f8fafc; 
            color: #64748b; 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 1px; 
            padding: 15px;
        }

        /* 状态标签样式[cite: 5] */
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }

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
                        <li class="breadcrumb-item active" aria-current="page" style="color: #6c757d;">Users Manage</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">User Management</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                    <small class="text-muted"><?php echo ($admin_role == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="container-fluid p-0">
            <!-- 搜索栏[cite: 5] -->
            <div class="search-bar-container">
                <form action="" method="GET" class="d-flex shadow-sm rounded-pill bg-white p-1 border">
                    <input type="text" name="search" class="form-control border-0 bg-transparent ps-3" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-orange rounded-pill px-4" type="submit">Search</button>
                </form>
            </div>

            <!-- 用户列表[cite: 5] -->
            <div class="user-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">CUSTOMER</th>
                                <th>CONTACT INFO</th>
                                <th>STATUS</th>
                                <th class="text-end pe-4">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-3 bg-light d-flex align-items-center justify-content-center me-3 border" style="width: 45px; height: 45px; color: var(--orange-primary); font-weight: bold;">
                                                <?php echo strtoupper(substr($row['User_Name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['User_Name']); ?></div>
                                                <small class="text-muted">#ID-<?php echo $row['User_Id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark small"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($row['User_Email']); ?></div>
                                        <div class="text-muted small"><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($row['User_Phone']); ?></div>
                                    </td>
                                    <td>
                                        <?php if($row['User_Status'] == 'Active'): ?>
                                            <span class="badge badge-active rounded-pill px-3 py-2">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-suspended rounded-pill px-3 py-2">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle border shadow-sm" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                                                <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="viewUser(<?php echo $row['User_Id']; ?>)"><i class="bi bi-eye me-2"></i> View Profile</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <?php if($row['User_Status'] == 'Active'): ?>
                                                        <a class="dropdown-item text-danger py-2" href="javascript:void(0)" onclick="changeStatus(<?php echo $row['User_Id']; ?>, 'Suspended')"><i class="bi bi-ban me-2"></i> Ban User</a>
                                                    <?php else: ?>
                                                        <a class="dropdown-item text-success py-2" href="javascript:void(0)" onclick="changeStatus(<?php echo $row['User_Id']; ?>, 'Active')"><i class="bi bi-check-circle me-2"></i> Unban User</a>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 详情 Modal[cite: 5] -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg border-0">
        <div class="modal-content rounded-4 border-0 shadow">
            <div id="modal_content_area"></div>
        </div>
    </div>
</div>

<script>
function changeStatus(id, status) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to change this user status to ${status}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF8C00',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, confirm!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `admin_process_user.php?id=${id}&status=${status}`;
        }
    });
}

function viewUser(id) {
    const modalElement = document.getElementById('userModal');
    const modalContent = document.getElementById('modal_content_area');
    const modal = new bootstrap.Modal(modalElement);
    
    modalContent.innerHTML = '<div class="p-5 text-center"><div class="spinner-border text-warning"></div><p class="mt-2 text-muted">Loading user profile...</p></div>';
    modal.show();

    fetch(`get_user_info.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            modalContent.innerHTML = html;
        });
}
</script>
</body>
</html>