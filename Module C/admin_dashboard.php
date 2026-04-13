<?php
// admin/admin_dashboard.php
session_start();
require_once '../includes/db_connection.php';

// 1. 安全检查：如果未登录，跳回登录页
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$verify_msg = "";

// 2. 处理超级管理员二级验证 (Modal 提交)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_super'])) {
    $sa_email = trim($_POST['sa_email']); 
    $sa_password = $_POST['sa_password'];

    $sql = "SELECT * FROM admin WHERE Admin_Email = ? AND Admin_Level = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sa_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($sa_password, $row['Admin_Password'])) {
            $_SESSION['role'] = $row['Admin_Level']; 
            $_SESSION['admin_id'] = $row['Admin_Id'];
            $_SESSION['username'] = $row['Admin_Name'];
            header("Location: add_admin.php");
            exit();
        } else {
            $verify_msg = "Wrong password!";
        }
    } else {
        $verify_msg = "Super Admin not found or access denied!";
    }
}

// 3. 数据统计逻辑 (适配 db_online_shoes.sql)
$res_count = $conn->query("SELECT COUNT(*) as total FROM `order`");
$total_orders = $res_count ? $res_count->fetch_assoc()['total'] : 0;

$res_revenue = $conn->query("SELECT SUM(Order_Amount) as revenue FROM `order` WHERE Order_Status != 'Cancelled'");
$total_revenue = ($res_revenue && $res_revenue->num_rows > 0) ? $res_revenue->fetch_assoc()['revenue'] : 0;
$total_revenue = $total_revenue ?? 0;

$res_pending = $conn->query("SELECT COUNT(*) as pending FROM `order` WHERE Order_Status = 'Pending'");
$pending_orders = $res_pending ? $res_pending->fetch_assoc()['pending'] : 0;

$sql_recent = "SELECT o.Order_Id, o.Order_Date, o.Order_Amount, o.Order_Status, u.User_Name 
               FROM `order` o 
               LEFT JOIN user u ON o.User_Id = u.User_Id 
               ORDER BY o.Order_Id DESC LIMIT 5";
$recent_orders = $conn->query($sql_recent);

// 图表数据处理
$days_data = [
    'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 
    'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0
];

$sql_chart = "SELECT DAYNAME(Order_Date) as day_name, COUNT(*) as cnt 
              FROM `order` 
              GROUP BY day_name";
$res_chart = $conn->query($sql_chart);

if ($res_chart) {
    while ($row = $res_chart->fetch_assoc()) {
        $day = trim($row['day_name']);
        if (isset($days_data[$day])) {
            $days_data[$day] = (int)$row['cnt'];
        }
    }
}

$chartConfig = [
    'type' => 'bar',
    'data' => [
        'labels' => array_keys($days_data),
        'datasets' => [[
            'label' => 'Orders',
            'data' => array_values($days_data),
            'backgroundColor' => 'rgba(13, 110, 253, 0.6)',
            'borderColor' => 'rgba(13, 110, 253, 1)',
            'borderWidth' => 1,
            'borderRadius' => 5
        ]]
    ]
];
$chartUrl = "https://quickchart.io/chart?c=" . rawurlencode(json_encode($chartConfig));

// ===== 引入公共 Header =====
$page_title = "Admin Dashboard | Shoe Store";
include_once '../includes/header.php'; 
?>

<style>
    .sidebar { min-height: 100vh; box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1); padding-top: 20px; }
    .stat-card { transition: transform 0.2s; cursor: pointer; }
    .stat-card:hover { transform: translateY(-5px); }
    main { padding-top: 20px; }
</style>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky">
                <div class="px-3 mb-4">
                    <h5 class="fw-bold">Shoe Store Admin 👟</h5>
                </div>
                
                <ul class="nav flex-column mb-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="admin_manage_orders.php"><i class="bi bi-cart-check me-2"></i> Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="admin_manage_products.php"><i class="bi bi-box-seam me-2"></i> Products</a>
                    </li>
                </ul>

                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted text-uppercase"><span>System</span></h6>
                <ul class="nav flex-column mb-2">
                    <?php if ($_SESSION['role'] == 1): ?>
                        <li class="nav-item">
                            <a class="nav-link text-success" href="admin_manage_admins.php"><i class="bi bi-person-gear me-2"></i> Manage Admins</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-success" href="add_admin.php"><i class="bi bi-person-plus-fill me-2"></i> Add New Admin</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link text-secondary" href="#" data-bs-toggle="modal" data-bs-target="#superAdminModal">
                                <i class="bi bi-lock-fill me-2"></i> Manage Admins <small>(Locked)</small>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../Module%20A/logout.php" onclick="return confirm('Sign out?');">
                            <i class="bi bi-box-arrow-right me-2"></i> Sign out
                        </a>
                    </li>
                </ul>

                <hr>
                <div class="px-3">
                    <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong><br>
                    <small class="text-muted"><?php echo ($_SESSION['role'] == 1) ? 'Super Admin' : 'Admin'; ?></small>
                </div>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php if ($verify_msg): ?>
                <div class="alert alert-danger mt-3"><?php echo $verify_msg; ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Overview</h1>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white stat-card p-3 shadow-sm border-0">
                        <h6>Total Orders</h6>
                        <h2 class="fw-bold"><?php echo $total_orders; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white stat-card p-3 shadow-sm border-0">
                        <h6>Total Revenue</h6>
                        <h2 class="fw-bold">RM <?php echo number_format($total_revenue, 2); ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-dark stat-card p-3 shadow-sm border-0">
                        <h6>Pending Orders</h6>
                        <h2 class="fw-bold"><?php echo $pending_orders; ?></h2>
                    </div>
                </div>
            </div>

            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body text-center">
                    <h5 class="text-start mb-3">Order Trends</h5>
                    <img src="<?php echo $chartUrl; ?>" class="img-fluid" style="max-height:300px;">
                </div>
            </div>

            <h3>Recent Orders</h3>
            <div class="table-responsive bg-white shadow-sm rounded p-3 mb-5">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_orders && $recent_orders->num_rows > 0): ?>
                            <?php while($row = $recent_orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['Order_Id']; ?></td>
                                <td><?php echo htmlspecialchars($row['User_Name'] ?? 'Guest'); ?></td>
                                <td><?php echo $row['Order_Date']; ?></td>
                                <td>RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                                <td>
                                    <?php 
                                        $status = $row['Order_Status'];
                                        $badge = 'bg-secondary';
                                        if ($status == 'Completed') $badge = 'bg-success';
                                        elseif ($status == 'Pending') $badge = 'bg-warning text-dark';
                                        elseif ($status == 'Cancelled') $badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($status ?: 'N/A'); ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No recent orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="superAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">🔐 Super Admin Verification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="verify_super" value="1">
                    <div class="mb-3">
                        <label class="form-label">Super Admin Email</label>
                        <input type="email" name="sa_email" class="form-control" required placeholder="superadmin@shoestore.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="sa_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Verify & Unlock</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>