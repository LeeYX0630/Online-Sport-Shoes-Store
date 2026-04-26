<?php
session_start();
require_once '../includes/db_connection.php';

// 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';

// 搜索与过滤逻辑 (匹配你的 SQL 字段)
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
    <title>User Management - Online Sports Shoes Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root { 
            --orange-main: #FF8C00; 
            --sidebar-width: 260px; /* 假设你的 sidebar 宽度是 260px */
        }
        
        body { background-color: #f4f7f6; margin: 0; }

        /* 关键：防止内容被盖住 */
        .wrapper { display: flex; width: 100%; }
        
        .main-content { 
            flex-grow: 1;
            margin-left: var(--sidebar-width); /* 留出侧边栏的空间 */
            min-height: 100vh;
            padding: 25px;
            transition: all 0.3s;
        }

        /* 模仿 Dashboard 的 Header */
        .header-container {
            background: #fff;
            padding: 15px 30px;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            margin-bottom: 30px;
        }

        .admin-info img {
            width: 40px; height: 40px;
            border-radius: 50%;
            border: 2px solid var(--orange-main);
            object-fit: cover;
        }

        /* 表格与 UI */
        .user-card {
            background: #fff;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .table thead { background-color: #fff9f2; }
        .table thead th { color: var(--orange-main); border: none; padding: 15px; }
        .btn-orange { background-color: var(--orange-main); color: white; }
        .btn-orange:hover { background-color: #e67e00; color: white; }
        
        /* 响应式：如果侧边栏在手机端隐藏，记得调整此处 */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="header-container">
            <div class="page-title">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none text-muted">Home</a></li>
                        <li class="breadcrumb-item active text-orange" aria-current="page">User Management</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Manage Users</h4>
            </div>
            
            <div class="admin-info d-flex align-items-center">
                <div class="text-end me-3 d-none d-sm-block">
                    <p class="mb-0 fw-bold"><?php echo $admin_name; ?></p>
                    <small class="text-muted">Administrator</small>
                </div>
                <img src="../assets/images/<?php echo $admin_image; ?>" alt="Admin">
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <form action="" method="GET" class="d-flex shadow-sm rounded-pill bg-white p-1">
                    <input type="text" name="search" class="form-control border-0 bg-transparent ps-3" placeholder="Search customer name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-orange rounded-pill px-4" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="user-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Customer</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; color: var(--orange-main); font-weight: bold; border: 1px solid #eee;">
                                        <?php echo strtoupper(substr($row['User_Name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['User_Name']); ?></div>
                                        <small class="text-muted">#ID-<?php echo $row['User_Id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small"><?php echo htmlspecialchars($row['User_Email']); ?></div>
                                <div class="text-muted small"><?php echo $row['User_Phone']; ?></div>
                            </td>
                            <td>
                                <?php if($row['User_Status'] == 'Active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                        <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="viewUser(<?php echo $row['User_Id']; ?>)"><i class="bi bi-eye me-2"></i> View</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <?php if($row['User_Status'] == 'Active'): ?>
                                                <a class="dropdown-item text-danger py-2" href="javascript:void(0)" onclick="changeStatus(<?php echo $row['User_Id']; ?>, 'Suspended')"><i class="bi bi-ban me-2"></i> Ban</a>
                                            <?php else: ?>
                                                <a class="dropdown-item text-success py-2" href="javascript:void(0)" onclick="changeStatus(<?php echo $row['User_Id']; ?>, 'Active')"><i class="bi bi-check-circle me-2"></i> Unban</a>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg border-0">
        <div class="modal-content rounded-4 border-0 shadow">
            <div id="modal_content_area"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function changeStatus(id, status) {
    Swal.fire({
        title: 'Confirm Action?',
        text: `Are you sure you want to set this user to ${status}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF8C00',
        confirmButtonText: 'Yes, proceed!'
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
    
    // 显示加载动画
    modalContent.innerHTML = '<div class="p-5 text-center"><div class="spinner-border text-warning"></div></div>';
    modal.show();

    // 获取数据
    fetch(`get_user_info.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            modalContent.innerHTML = html;
        });
}
</script>
</body>
</html>