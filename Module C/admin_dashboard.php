<?php
// admin/admin_dashboard.php
session_start();
require_once '../includes/db_connection.php'; 

// 1. 安全检查[cite: 2]
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$admin_brand = $_SESSION['admin_brand'] ?? 'ALL';
$username = $_SESSION['username'] ?? 'Admin';
// 提取自 source 1 的头像变量逻辑[cite: 1]
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png'; 

// --- 2. 品牌过滤逻辑[cite: 2] ---
$brand_filter = "";
$brand_join = "";
if ($admin_role != 1) {
    $brand_join = " LEFT JOIN order_detail od_filter ON o.Order_Id = od_filter.Order_Id 
                    LEFT JOIN product p_filter ON od_filter.Pro_Id = p_filter.Pro_Id";
    $brand_filter = " AND p_filter.Product_Brand = '$admin_brand'";
}

// --- 3. 统计数据查询 (带周对比功能)[cite: 2] ---
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end   = date('Y-m-d', strtotime('sunday last week'));

function getStats($conn, $start_date, $end_date, $brand_join, $brand_filter) {
    $date_condition = " AND o.Order_Date BETWEEN '$start_date' AND '$end_date'";
    
    $res_count = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as total FROM `order` o $brand_join WHERE 1=1 $brand_filter $date_condition");
    $orders = $res_count->fetch_assoc()['total'] ?? 0;

    $res_revenue = $conn->query("SELECT SUM(o.Order_Amount) as revenue FROM `order` o $brand_join WHERE o.Order_Status != 'Cancelled' $brand_filter $date_condition");
    $revenue = $res_revenue->fetch_assoc()['revenue'] ?? 0;

    $res_pending = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as pending FROM `order` o $brand_join WHERE o.Order_Status = 'Pending' $brand_filter $date_condition");
    $pending = $res_pending->fetch_assoc()['pending'] ?? 0;

    return ['orders' => $orders, 'revenue' => $revenue, 'pending' => $pending];
}

$current_stats = getStats($conn, $this_week_start, date('Y-m-d'), $brand_join, $brand_filter);
$previous_stats = getStats($conn, $last_week_start, $last_week_end, $brand_join, $brand_filter);

$res_total_all = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as total, SUM(o.Order_Amount) as revenue FROM `order` o $brand_join WHERE 1=1 $brand_filter");
$row_all = $res_total_all->fetch_assoc();
$total_orders = $row_all['total'] ?? 0;
$total_revenue = $row_all['revenue'] ?? 0;

$res_pending_all = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as pending FROM `order` o $brand_join WHERE o.Order_Status = 'Pending' $brand_filter");
$pending_orders = $res_pending_all->fetch_assoc()['pending'] ?? 0;

function calculateGrowth($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

$order_growth = calculateGrowth($current_stats['orders'], $previous_stats['orders']);
$revenue_growth = calculateGrowth($current_stats['revenue'], $previous_stats['revenue']);
$pending_growth = calculateGrowth($current_stats['pending'], $previous_stats['pending']);

function renderGrowthUI($val, $reverse = false) {
    $abs_val = number_format(abs($val), 1);
    if ($val == 0) {
        return '<div class="growth-text text-muted"><i class="bi bi-dash"></i> 0.0% <span class="fw-normal ms-1">vs last week</span></div>';
    }
    $is_positive = $val > 0;
    $is_good = $reverse ? !$is_positive : $is_positive;
    $color_class = $is_good ? 'text-success-custom' : 'text-danger-custom';
    $icon_class = $is_positive ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
    return '<div class="growth-text ' . $color_class . '"><i class="bi ' . $icon_class . '"></i> ' . $abs_val . '% <span class="text-muted fw-normal ms-1">vs last week</span></div>';
}

// --- 4. 最近订单 & 5. 图表数据[cite: 2] ---
$sql_recent = "SELECT DISTINCT o.Order_Id, o.Order_Date, o.Order_Amount, o.Order_Status, u.User_Name 
               FROM `order` o 
               LEFT JOIN user u ON o.User_Id = u.User_Id 
               $brand_join
               WHERE 1=1 $brand_filter
               ORDER BY o.Order_Id DESC LIMIT 5";
$recent_orders = $conn->query($sql_recent);

$all_sales_data = [];
$sql_chart = "SELECT o.Order_Date, b.Brand_Name, SUM(od.Order_Qty) as brand_qty
              FROM `order` o 
              JOIN order_detail od ON o.Order_Id = od.Order_Id 
              JOIN product p ON od.Pro_Id = p.Pro_Id
              JOIN brand b ON p.Brand_Id = b.Brand_Id
              WHERE 1=1 $brand_filter 
              GROUP BY o.Order_Date, b.Brand_Name";
$res_chart = $conn->query($sql_chart);
if ($res_chart) {
    while($row = $res_chart->fetch_assoc()) { $all_sales_data[] = $row; }
}
$json_sales_data = json_encode($all_sales_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Online Sports Shoes Store</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
        /* 完全复刻自 admin_manage_promos.php 的全局样式[cite: 1] */
        :root { 
            --orange-primary: #FF8C00; 
            --sidebar-width: 260px; 
        }
        body { 
            background-color: #f8f9fa; 
            margin: 0; 
            font-family: 'Segoe UI', 'Inter', sans-serif; 
        }
        .wrapper { display: flex; }

        /* 统一内容区域布局[cite: 1] */
        .main-content { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
        }

        /* 统一 Header 的大小、内边距和投影[cite: 1] */
        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }

        /* 统一头像边框与大小[cite: 1] */
        .admin-profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

        /* Dashboard 特有卡片样式同步 Promo 页面的圆角和投影[cite: 1, 2] */
        .stat-card, .content-container-chart { 
            background: white; 
            border-radius: 15px; 
            padding: 24px; 
            border: none; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            height: 100%;
        }

        .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 15px; }
        .bg-light-orange { background: #FFF0E5; color: var(--orange-primary); }
        .bg-light-blue { background: #e0e7ff; color: #4338ca; }
        .bg-light-green { background: #dcfce7; color: #15803d; }
        
        .btn-orange { 
            background-color: var(--orange-primary); 
            color: white; border: none; 
            padding: 10px 18px; border-radius: 10px; 
            font-weight: 600; text-decoration: none; 
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-orange:hover { background-color: #e67e00; color: white; }

        .growth-text { font-size: 13px; font-weight: 600; margin-top: 8px; display: flex; align-items: center; gap: 4px; }
        .text-success-custom { color: #10b981; }
        .text-danger-custom { color: #ef4444; }

        .calendar-trigger { width: 38px; height: 38px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #e2e8f0; color: #64748b; }
        .calendar-trigger:hover { background: var(--orange-primary); color: white; }

        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <!-- 完美的 Header 布局同步[cite: 1] -->
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Admin Dashboard</h4>
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
            <!-- 标题栏[cite: 2] -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Analytics Overview</h5>
                    <p class="text-muted small mb-0">Managing <strong><?php echo $admin_brand; ?></strong> Store</p>
                </div>
                <a href="export_pdf_report.php" class="btn btn-orange shadow-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>

            <!-- 统计卡片区域[cite: 2] -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="icon-box bg-light-blue"><i class="bi bi-cart-check"></i></div>
                        <div class="text-muted small fw-bold text-uppercase">Total Orders</div>
                        <h3 class="fw-bold mt-1"><?php echo $total_orders; ?></h3>
                        <?php echo renderGrowthUI($order_growth); ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="icon-box bg-light-green"><i class="bi bi-currency-dollar"></i></div>
                        <div class="text-muted small fw-bold text-uppercase">Total Revenue</div>
                        <h3 class="fw-bold mt-1">RM <?php echo number_format($total_revenue, 2); ?></h3>
                        <?php echo renderGrowthUI($revenue_growth); ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="icon-box bg-light-orange"><i class="bi bi-clock-history"></i></div>
                        <div class="text-muted small fw-bold text-uppercase">Pending Orders</div>
                        <h3 class="fw-bold mt-1"><?php echo $pending_orders; ?></h3>
                        <?php echo renderGrowthUI($pending_growth, true); ?>
                    </div>
                </div>
            </div>

            <!-- 图表与最近订单[cite: 2] -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="content-container-chart">
                        <div class="d-flex align-items-center mb-4">
                            <h6 class="fw-bold mb-0 text-uppercase flex-grow-1" style="letter-spacing: 1px;">Weekly Sales</h6>
                            <div class="calendar-trigger" id="calendarBtn"><i class="bi bi-calendar3"></i></div>
                            <input type="text" id="weekPicker" style="display:none;">
                        </div>
                        <div style="height: 380px;"><canvas id="weeklyChart"></canvas></div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="content-container-chart">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Recent Orders</h6>
                            <a href="admin_manage_orders.php" class="small text-decoration-none fw-bold" style="color: var(--orange-primary);">View All</a>
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
                                                <span class="badge <?php echo ($row['Order_Status'] == 'Completed') ? 'bg-success' : 'bg-warning text-dark'; ?>" style="font-size: 9px;"><?php echo $row['Order_Status']; ?></span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center py-5 text-muted small">No orders recorded.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
    </div> 
</div>

<script>
    // 图表 JS 逻辑[cite: 2]
    const rawData = <?php echo $json_sales_data; ?>;
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    let myChart;

    function getMonday(d) {
        d = new Date(d);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(d.setDate(diff));
    }

    function renderChart(selectedDate) {
        const monday = getMonday(selectedDate);
        const weekDates = [];
        const labels = [];
        const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const dailyTotals = [0, 0, 0, 0, 0, 0, 0];

        for (let i = 0; i < 7; i++) {
            const tempDate = new Date(monday);
            tempDate.setDate(monday.getDate() + i);
            const dateStr = tempDate.toISOString().split('T')[0];
            weekDates.push(dateStr);
            labels.push([dayNames[i], (tempDate.getMonth() + 1) + '/' + tempDate.getDate()]);
        }

        rawData.forEach(item => {
            const dIdx = weekDates.indexOf(item.Order_Date);
            if (dIdx !== -1) dailyTotals[dIdx] += parseInt(item.brand_qty);
        });

        if (myChart) myChart.destroy();
        myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Items Sold',
                    data: dailyTotals,
                    fill: true,
                    backgroundColor: 'rgba(255, 140, 0, 0.1)',
                    borderColor: '#FF8C00',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    }

    const fp = flatpickr("#weekPicker", {
        maxDate: "today",
        defaultDate: "today",
        onChange: function(selectedDates) { renderChart(selectedDates[0]); }
    });

    document.getElementById('calendarBtn').addEventListener('click', () => fp.open());
    renderChart(new Date());
</script>
</body>
</html>