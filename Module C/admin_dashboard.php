<?php
// admin/admin_dashboard.php
session_start();
require_once '../includes/db_connection.php'; // 确保路径正确 [cite: 32, 36]

// 1. 安全检查 [cite: 18]
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$admin_brand = $_SESSION['admin_brand'] ?? 'ALL';
$username = $_SESSION['username'] ?? 'Admin';

// --- 2. 品牌过滤逻辑 ---
$brand_filter = "";
$brand_join = "";
if ($admin_role != 1) {
    $brand_join = " LEFT JOIN order_details od ON o.Order_Id = od.Order_Id 
                    LEFT JOIN product p ON od.Product_Id = p.Product_Id";
    $brand_filter = " AND p.Product_Brand = '$admin_brand'";
}

// --- 3. 数据统计查询 ---
$res_count = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as total FROM `order` o $brand_join WHERE 1=1 $brand_filter");
$total_orders = ($res_count) ? $res_count->fetch_assoc()['total'] : 0;

$res_revenue = $conn->query("SELECT SUM(o.Order_Amount) as revenue FROM `order` o $brand_join WHERE o.Order_Status != 'Cancelled' $brand_filter");
$total_revenue = ($res_revenue) ? $res_revenue->fetch_assoc()['revenue'] : 0;

$res_pending = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as pending FROM `order` o $brand_join WHERE o.Order_Status = 'Pending' $brand_filter");
$pending_orders = ($res_pending) ? $res_pending->fetch_assoc()['pending'] : 0;

$sql_recent = "SELECT DISTINCT o.Order_Id, o.Order_Date, o.Order_Amount, o.Order_Status, u.User_Name 
               FROM `order` o 
               LEFT JOIN user u ON o.User_Id = u.User_Id 
               $brand_join
               WHERE 1=1 $brand_filter
               ORDER BY o.Order_Id DESC LIMIT 5";
$recent_orders = $conn->query($sql_recent);

// --- 4. 图表数据 ---
$days_data = ['Mon' => 0, 'Tue' => 0, 'Wed' => 0, 'Thu' => 0, 'Fri' => 0, 'Sat' => 0, 'Sun' => 0];
$sql_chart = "SELECT DAYNAME(Order_Date) as day_name, COUNT(DISTINCT o.Order_Id) as cnt 
              FROM `order` o $brand_join 
              WHERE 1=1 $brand_filter 
              GROUP BY day_name";
$res_chart = $conn->query($sql_chart);
if ($res_chart) {
    while ($row = $res_chart->fetch_assoc()) {
        $short_day = substr($row['day_name'], 0, 3);
        if (isset($days_data[$short_day])) $days_data[$short_day] = (int)$row['cnt'];
    }
}
$chartConfig = [
    'type' => 'line',
    'data' => [
        'labels' => array_keys($days_data),
        'datasets' => [[
            'label' => 'Weekly Orders',
            'data' => array_values($days_data),
            'fill' => true,
            'backgroundColor' => 'rgba(250, 138, 52, 0.1)',
            'borderColor' => '#FA8A34',
            'borderWidth' => 3,
            'pointRadius' => 4
        ]]
    ],
    'options' => [ 'scales' => [ 'yAxes' => [[ 'ticks' => [ 'beginAtZero' => true, 'precision' => 0 ] ]] ] ]
];
$chartUrl = "https://quickchart.io/chart?c=" . rawurlencode(json_encode($chartConfig));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Online Sport Shoes</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-orange: #FF6B00; /* 符合 Guideline [cite: 29] */
        }

        body { 
            background-color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            margin: 0;
            overflow-x: hidden;
        }

        /* 主包装容器：处理侧边栏占位 */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }

        /* Top Bar：白色背景，横跨顶部 */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #FFFFFF; /* 符合 Guideline [cite: 29] */
            border-bottom: 1px solid #edf2f7;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar-left h2 {
            margin: 0;
            font-size: 22px;
            color: #212529; /* 符合 Guideline [cite: 29] */
            font-weight: 700;
        }

        .top-bar-left p {
            margin: 2px 0 0;
            color: #8b949e;
            font-size: 13px;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .action-icon {
            position: relative;
            font-size: 18px;
            color: #64748b;
            cursor: pointer;
            background: #f1f5f9;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .action-icon:hover { background: #e2e8f0; }

        .notification-dot {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 7px;
            height: 7px;
            background-color: var(--primary-orange);
            border-radius: 50%;
            border: 1.5px solid #fff;
        }

        .user-profile-circle {
            width: 40px;
            height: 40px;
            background-color: #6366f1; /* 图片中的紫色头像 */
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 600;
            font-size: 13px;
        }

        /* 实际内容区域的间距 */
        .dashboard-content-area {
            padding: 30px 40px;
        }

        /* 统计卡片样式 */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }

        .icon-box {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-bottom: 15px;
        }
        .bg-light-orange { background: #FFF0E5; color: var(--primary-orange); }
        .bg-light-blue { background: #e0e7ff; color: #4338ca; }
        .bg-light-green { background: #dcfce7; color: #15803d; }

        .btn-export {
            background-color: var(--primary-orange);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-export:hover {
            background-color: #e66000;
            color: white;
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.2);
        }

        .content-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        @media (max-width: 991px) {
            .main-wrapper { margin-left: 0; width: 100%; }
            .top-bar { padding: 15px 20px; }
            .dashboard-content-area { padding: 20px; }
        }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        
        <header class="top-bar">
            <div class="top-bar-left">
                <h2>Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($username); ?> 👋</p>
            </div>
            
            <div class="top-bar-right">
                <div class="action-icon">
                    <i class="bi bi-bell"></i>
                    <span class="notification-dot"></span>
                </div>
                
                <div class="user-profile-circle">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
            </div>
        </header>

        <div class="dashboard-content-area">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1">Overview</h4>
                    <p class="text-muted small mb-0">Managing <strong><?php echo $admin_brand; ?></strong> Store</p>
                </div>
                <a href="export_pdf_report.php" class="btn-export shadow-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="icon-box bg-light-blue"><i class="bi bi-cart-check"></i></div>
                        <div class="text-muted small fw-bold">TOTAL ORDERS</div>
                        <h3 class="fw-bold mt-1"><?php echo $total_orders; ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="icon-box bg-light-green"><i class="bi bi-currency-dollar"></i></div>
                        <div class="text-muted small fw-bold">TOTAL REVENUE</div>
                        <h3 class="fw-bold mt-1">RM <?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="icon-box bg-light-orange"><i class="bi bi-clock-history"></i></div>
                        <div class="text-muted small fw-bold">PENDING ORDERS</div>
                        <h3 class="fw-bold mt-1"><?php echo $pending_orders; ?></h3>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="content-container">
                        <h6 class="fw-bold mb-4 text-uppercase" style="letter-spacing: 1px;">Weekly Order Trends</h6>
                        <img src="<?php echo $chartUrl; ?>" class="img-fluid w-100" style="max-height: 380px; object-fit: contain;">
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="content-container h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Recent Orders</h6>
                            <a href="admin_manage_orders.php" class="small text-primary text-decoration-none fw-bold">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <tbody>
                                    <?php if($recent_orders && $recent_orders->num_rows > 0): ?>
                                        <?php while($row = $recent_orders->fetch_assoc()): ?>
                                        <tr style="cursor: pointer;" onclick="window.location='admin_order_details.php?id=<?php echo $row['Order_Id']; ?>'">
                                            <td class="ps-0 border-0">
                                                <div class="fw-bold text-dark" style="font-size: 14px;">#<?php echo $row['Order_Id']; ?></div>
                                                <small class="text-muted"><?php echo $row['User_Name'] ?: 'Guest'; ?></small>
                                            </td>
                                            <td class="text-end pe-0 border-0">
                                                <div class="fw-bold text-dark" style="font-size: 14px;">RM <?php echo number_format($row['Order_Amount'], 2); ?></div>
                                                <?php 
                                                    $status = $row['Order_Status'];
                                                    $badge = ($status == 'Completed') ? 'bg-success' : (($status == 'Pending') ? 'bg-warning text-dark' : 'bg-danger');
                                                ?>
                                                <span class="badge <?php echo $badge; ?>" style="font-size: 9px; padding: 4px 8px;"><?php echo $status; ?></span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center py-5 text-muted small">No orders recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>