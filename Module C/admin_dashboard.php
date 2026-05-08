<?php
// admin/admin_dashboard.php
session_start();
require_once '../includes/db_connection.php'; 

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'];

// --- 辅助函数：计算并渲染增长率 UI ---
function calculateGrowth($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

function renderGrowthUI($growth, $inverse = false) {
    // 如果 inverse 为 true，则增长表示负面（如 Pending Orders 增加是坏事）
    $isPositive = $growth >= 0;
    $colorClass = $isPositive ? ($inverse ? 'text-danger-custom' : 'text-success-custom') : ($inverse ? 'text-success-custom' : 'text-danger-custom');
    $icon = $isPositive ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
    $sign = $isPositive ? '+' : '';
    return "<div class='growth-text {$colorClass}'><i class='bi {$icon}'></i> {$sign}" . number_format($growth, 1) . "% from last month</div>";
}

// --- 2. 品牌过滤逻辑 ---
$brand_filter = "";
$display_brand_name = "All Brands"; // 默认显示（Level 1 & 2）

if ($admin_role == '3') {
    $my_brand_id = 0;
    $brand_query = "SELECT Brand_Id, Brand_Name FROM brand WHERE Admin_Id = '$admin_id' LIMIT 1";
    $brand_res = $conn->query($brand_query);
    
    if ($brand_res && $brand_row = $brand_res->fetch_assoc()) {
        $my_brand_id = $brand_row['Brand_Id'];
        $display_brand_name = $brand_row['Brand_Name'];
    }
    
    $brand_filter = " AND p.Brand_Id = '$my_brand_id' ";
}
    
// --- 3. 统计查询 (修复了Order_Subtotal的重复乘法，并加入了本月/上月数据做对比) ---
$sql_stats = "SELECT 
    -- 总用户数
    (SELECT COUNT(*) FROM user) as total_users,
    
    -- 商品总数
    (SELECT COUNT(*) FROM product p WHERE 1=1 $brand_filter) as total_products,
    
    -- 已售出商品件数
    (SELECT IFNULL(SUM(Order_Qty), 0) FROM order_detail od 
     JOIN product p ON od.Pro_Id = p.Pro_Id 
     WHERE 1=1 $brand_filter) as total_sold,
     
    -- 总营业额 (修复：Order_Subtotal 本身已经是单品总价，不需要再乘 Order_Qty)
    (SELECT IFNULL(SUM(Order_Subtotal), 0) FROM order_detail od 
     JOIN product p ON od.Pro_Id = p.Pro_Id 
     WHERE 1=1 $brand_filter) as total_revenue,
     
    -- 历史总订单数
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE 1=1 $brand_filter) as total_orders,
     
    -- 待处理订单数
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE o.Order_Status = 'Pending' $brand_filter) as pending_orders,

    -- 当前月数据 (用于增长率对比)
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE MONTH(o.Order_Date) = MONTH(CURRENT_DATE()) AND YEAR(o.Order_Date) = YEAR(CURRENT_DATE()) $brand_filter) as current_month_orders,
     
    (SELECT IFNULL(SUM(Order_Subtotal), 0) FROM order_detail od
     JOIN product p ON od.Pro_Id = p.Pro_Id
     JOIN `order` o ON od.Order_Id = o.Order_Id
     WHERE MONTH(o.Order_Date) = MONTH(CURRENT_DATE()) AND YEAR(o.Order_Date) = YEAR(CURRENT_DATE()) $brand_filter) as current_month_revenue,
     
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE o.Order_Status = 'Pending' 
     AND MONTH(o.Order_Date) = MONTH(CURRENT_DATE()) AND YEAR(o.Order_Date) = YEAR(CURRENT_DATE()) $brand_filter) as current_month_pending,

    -- 上个月数据 (用于计算 Growth)
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE MONTH(o.Order_Date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(o.Order_Date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) $brand_filter) as prev_month_orders,
     
    (SELECT IFNULL(SUM(Order_Subtotal), 0) FROM order_detail od 
     JOIN product p ON od.Pro_Id = p.Pro_Id
     JOIN `order` o ON od.Order_Id = o.Order_Id
     WHERE MONTH(o.Order_Date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(o.Order_Date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) $brand_filter) as prev_month_revenue,
     
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE o.Order_Status = 'Pending' 
     AND MONTH(o.Order_Date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(o.Order_Date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) $brand_filter) as prev_month_pending
";

$result_stats = $conn->query($sql_stats);
$stats = ($result_stats) ? $result_stats->fetch_assoc() : [];

// 提取数据
$total_orders = $stats['total_orders'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$pending_orders = $stats['pending_orders'] ?? 0;

// 计算增长率
$order_growth = calculateGrowth($stats['current_month_orders'] ?? 0, $stats['prev_month_orders'] ?? 0);
$revenue_growth = calculateGrowth($stats['current_month_revenue'] ?? 0, $stats['prev_month_revenue'] ?? 0);
$pending_growth = calculateGrowth($stats['current_month_pending'] ?? 0, $stats['prev_month_pending'] ?? 0);

// --- 4. 获取图表销售数据 ---
$sql_chart = "SELECT DATE(o.Order_Date) as Order_Date, SUM(od.Order_Qty) as brand_qty 
              FROM `order` o
              JOIN order_detail od ON o.Order_Id = od.Order_Id
              JOIN product p ON od.Pro_Id = p.Pro_Id
              WHERE 1=1 $brand_filter
              GROUP BY DATE(o.Order_Date)";
$chart_res = $conn->query($sql_chart);
$chart_data = [];
if ($chart_res) {
    while ($row = $chart_res->fetch_assoc()) {
        $chart_data[] = $row;
    }
}
$json_sales_data = json_encode($chart_data);

// --- 5. 获取最近的订单 ---
// 为了保证给供应商看时，金额只显示他们自己商品的总金额，加入 IFNULL(SUM(od.Order_Subtotal), ...)
$sql_recent = "SELECT o.Order_Id, u.User_Name, 
               IFNULL(SUM(od.Order_Subtotal), o.Order_Amount) as Display_Order_Amount, 
               o.Order_Status, o.Order_Date 
               FROM `order` o
               LEFT JOIN user u ON o.User_Id = u.User_Id
               JOIN order_detail od ON o.Order_Id = od.Order_Id
               JOIN product p ON od.Pro_Id = p.Pro_Id
               WHERE 1=1 $brand_filter
               GROUP BY o.Order_Id, u.User_Name, o.Order_Status, o.Order_Date, o.Order_Amount
               ORDER BY o.Order_Date DESC 
               LIMIT 5";
$recent_orders = $conn->query($sql_recent);

// --- 6. 处理头像逻辑 ---
$admin_image = 'default_admin.png';
if ($admin_id) {
    $img_res = $conn->query("SELECT Admin_Image FROM admin WHERE Admin_Id = $admin_id");
    if ($img_res && $img_row = $img_res->fetch_assoc()) {
        $admin_image = !empty($img_row['Admin_Image']) ? $img_row['Admin_Image'] : 'default_admin.png';
    }
}
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

        .main-content { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
        }

        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }

        .admin-profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

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

        .calendar-trigger {position: relative; width: 38px; height: 38px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #e2e8f0; color: #64748b; }
        .calendar-trigger:hover { background: var(--orange-primary); color: white; }
        /* 强制日历在 static 模式下的定位 */
        .flatpickr-calendar.static {
            top: 100% !important;
            left: auto !important;
            right: 0 !important; /* 让日历向左展开，避免超出屏幕右侧 */
            margin-top: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        }

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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Analytics Overview</h5>
                    <p class="text-muted small mb-0">Managing <strong><?php echo $display_brand_name; ?></strong> Store</p>
                </div>
                <a href="export_pdf_report.php" class="btn btn-orange shadow-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>

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
                        <?php echo renderGrowthUI($pending_growth, true); // true 表示 pending 订单增长是负面信息 ?>
                    </div>
                </div>
            </div>

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
                                                <small class="text-muted"><?php echo htmlspecialchars($row['User_Name'] ?: 'Guest'); ?></small>
                                            </td>
                                            <td class="text-end pe-0 border-0">
                                                <div class="fw-bold text-dark" style="font-size: 14px;">RM <?php echo number_format($row['Display_Order_Amount'], 2); ?></div>
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

    // 找到 script 标签中的 flatpickr 初始化部分
    const fp = flatpickr("#weekPicker", {
        maxDate: "today",
        defaultDate: "today",
        // 关键修改点：
        appendTo: document.getElementById('calendarBtn'), // 挂载到按钮本身
        static: true,                                    // 开启静态定位模式
        position: "below right",                         // 强制在下方，并对齐右侧
        onChange: function(selectedDates) { 
            renderChart(selectedDates[0]); 
        }
    });

    document.getElementById('calendarBtn').addEventListener('click', () => fp.open());
    renderChart(new Date());
</script>
</body>
</html>