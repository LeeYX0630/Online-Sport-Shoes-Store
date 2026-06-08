<?php
// admin/admin_dashboard.php
session_start();
require_once '../includes/db_connection.php'; 


// 1. 安全检查
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['is_admin'])) {
    header("Location: admin_login.php");
    exit();
}

loadEnv(__DIR__ . '/../includes/Tung_Gemini_API.env');

$admin_role = $_SESSION['role'] ?? '1';
$username = $_SESSION['username'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;

// ── 读取 .env 文件 ────────────────────────────────────────────────────────────
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // 跳过注释行
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!empty($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// --- 辅助函数：计算并渲染增长率 UI ---
function calculateGrowth($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

function renderGrowthUI($growth, $inverse = false) {
    $isPositive = $growth >= 0;
    $colorClass = $isPositive ? ($inverse ? 'text-danger-custom' : 'text-success-custom') : ($inverse ? 'text-success-custom' : 'text-danger-custom');
    $icon = $isPositive ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
    $sign = $isPositive ? '+' : '';
    return "<div class='growth-text {$colorClass}'><i class='bi {$icon}'></i> {$sign}" . number_format($growth, 1) . "% from last month</div>";
}

// --- 2. 品牌过滤逻辑 ---
$brand_filter = "";
$display_brand_name = "All Brands";

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
    (SELECT COUNT(*) FROM user) as total_users,
    (SELECT COUNT(*) FROM product p WHERE 1=1 $brand_filter) as total_products,
    (SELECT IFNULL(SUM(Order_Qty), 0) FROM order_detail od 
     JOIN product p ON od.Pro_Id = p.Pro_Id 
     WHERE 1=1 $brand_filter) as total_sold,
    (SELECT IFNULL(SUM(Order_Subtotal), 0) FROM order_detail od 
     JOIN product p ON od.Pro_Id = p.Pro_Id 
     WHERE 1=1 $brand_filter) as total_revenue,
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE 1=1 $brand_filter) as total_orders,
    (SELECT COUNT(DISTINCT o.Order_Id) FROM `order` o
     JOIN order_detail od ON o.Order_Id = od.Order_Id
     JOIN product p ON od.Pro_Id = p.Pro_Id
     WHERE o.Order_Status = 'Pending' $brand_filter) as pending_orders,
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

$total_orders = $stats['total_orders'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$pending_orders = $stats['pending_orders'] ?? 0;

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

// --- 7. 获取 AI 预测所需数据 ---
$sql_ai_data = "SELECT 
    p.Pro_Id, p.Pro_Name, p.Pro_Price,
    b.Brand_Name,
    c.Cat_Name,
    IFNULL(SUM(od.Order_Qty), 0) as total_sold,
    IFNULL(SUM(od.Order_Subtotal), 0) as total_revenue,
    COUNT(DISTINCT od.Order_Id) as order_count,
    IFNULL(SUM(CASE WHEN MONTH(o.Order_Date) = MONTH(CURRENT_DATE()) THEN od.Order_Qty ELSE 0 END), 0) as this_month_sold,
    IFNULL(SUM(CASE WHEN MONTH(o.Order_Date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) THEN od.Order_Qty ELSE 0 END), 0) as last_month_sold
    FROM product p
    LEFT JOIN order_detail od ON p.Pro_Id = od.Pro_Id
    LEFT JOIN `order` o ON od.Order_Id = o.Order_Id
    LEFT JOIN brand b ON p.Brand_Id = b.Brand_Id
    LEFT JOIN category c ON p.Cat_Id = c.Cat_Id
    WHERE 1=1 $brand_filter
    GROUP BY p.Pro_Id, p.Pro_Name, p.Pro_Price, b.Brand_Name, c.Cat_Name
    ORDER BY total_sold DESC
    LIMIT 15";

$ai_data_res = $conn->query($sql_ai_data);
$ai_products_data = [];
if ($ai_data_res) {
    while ($row = $ai_data_res->fetch_assoc()) {
        $ai_products_data[] = $row;
    }
}
$json_ai_products = json_encode($ai_products_data);
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
            --ai-purple: #6C47FF;
            --ai-purple-light: #F0ECFF;
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

        /* === AI PREDICT BUTTON (new) === */
        .btn-ai { 
            background: linear-gradient(135deg, var(--ai-purple), #9B5DE5);
            color: white; 
            border: none; 
            padding: 10px 18px; 
            border-radius: 10px; 
            font-weight: 600;
            font-size: 14px;
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-ai:hover { opacity: 0.88; transform: translateY(-1px); color: white; }

        .growth-text { font-size: 13px; font-weight: 600; margin-top: 8px; display: flex; align-items: center; gap: 4px; }
        .text-success-custom { color: #10b981; }
        .text-danger-custom { color: #ef4444; }

        .calendar-trigger { position: relative; width: 38px; height: 38px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #e2e8f0; color: #64748b; }
        .calendar-trigger:hover { background: var(--orange-primary); color: white; }
        .flatpickr-calendar.static {
            top: 100% !important;
            left: auto !important;
            right: 0 !important;
            margin-top: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        }

        /* ===================== AI MODAL (new) ===================== */
        .ai-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10, 10, 30, 0.5);
            backdrop-filter: blur(3px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .ai-modal-overlay.active { display: flex; }

        .ai-modal {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 780px;
            max-height: 88vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,0.18);
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to   { opacity: 1; transform: none; }
        }

        .ai-modal-header {
            padding: 22px 28px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(135deg, #F0ECFF 0%, #FFF5EB 100%);
        }
        .ai-header-icon {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--ai-purple), #9B5DE5);
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: white; flex-shrink: 0;
        }
        .ai-modal-title { font-size: 17px; font-weight: 700; color: #1e293b; margin: 0; }
        .ai-modal-subtitle { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .ai-modal-close {
            margin-left: auto;
            width: 34px; height: 34px;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #64748b;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .ai-modal-close:hover { background: #f1f5f9; }

        .ai-modal-body { padding: 24px 28px; overflow-y: auto; flex: 1; }

        /* Loading */
        .ai-loading { text-align: center; padding: 60px 20px; }
        .ai-spinner {
            width: 52px; height: 52px;
            border: 3px solid #EDE9FF;
            border-top-color: var(--ai-purple);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .ai-loading-text { color: #64748b; font-size: 14px; }
        .ai-loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }
        @keyframes dots { 0%,20%{content:''} 40%{content:'.'} 60%{content:'..'} 80%,100%{content:'...'} }

        /* Summary box */
        .ai-summary-box {
            background: linear-gradient(135deg, #F0ECFF, #FFF5EB);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(108,71,255,0.15);
        }
        .ai-summary-label { font-size: 11px; font-weight: 700; color: var(--ai-purple); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
        .ai-summary-text { font-size: 13px; color: #475569; line-height: 1.7; }

        /* Prediction grid */
        .prediction-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media(max-width: 600px) { .prediction-grid { grid-template-columns: 1fr; } }

        .prediction-card {
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .prediction-card:hover { border-color: var(--ai-purple); box-shadow: 0 4px 16px rgba(108,71,255,0.1); }
        .prediction-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--ai-purple), #9B5DE5);
        }

        .pred-rank { 
            position: absolute; top: 14px; right: 14px;
            font-size: 10px; font-weight: 700;
            color: var(--ai-purple);
            background: var(--ai-purple-light);
            padding: 3px 8px; border-radius: 20px;
        }
        .pred-product-name { font-weight: 700; font-size: 14px; color: #1e293b; padding-right: 60px; margin-bottom: 3px; }
        .pred-brand { font-size: 11px; color: #94a3b8; margin-bottom: 12px; }

        .pred-score-bar { height: 5px; background: #f1f5f9; border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
        .pred-score-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, var(--ai-purple), #9B5DE5); }

        .pred-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px; }
        .pred-tag { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
        .tag-hot     { background: #FFF0E5; color: var(--orange-primary); }
        .tag-trend   { background: #dcfce7; color: #15803d; }
        .tag-growing { background: var(--ai-purple-light); color: var(--ai-purple); }
        .tag-stable  { background: #f1f5f9; color: #64748b; }

        .pred-reason { font-size: 12px; color: #64748b; line-height: 1.6; border-top: 1px solid #f1f5f9; padding-top: 10px; }

        .ai-disclaimer { font-size: 11px; color: #94a3b8; text-align: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .btn-regen { background: none; border: none; color: var(--ai-purple); font-size: 11px; cursor: pointer; margin-top: 6px; font-weight: 600; }
        .btn-regen:hover { text-decoration: underline; }

        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <!-- Original Header -->
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
                    <small class="text-muted"><?php 
                        if($admin_role == 1) echo 'Super Admin';
                        elseif($admin_role == 2) echo 'Admin';
                        else echo 'Brand Manager'; 
                    ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="container-fluid p-0">
            <!-- Original Analytics Overview row — with AI button added -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Analytics Overview</h5>
                    <p class="text-muted small mb-0">Managing <strong><?php echo $display_brand_name; ?></strong> Store</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <!-- NEW: AI Predict Button -->
                    <button class="btn-ai" onclick="openAIPrediction()">
                        <i class="bi bi-stars"></i> AI Predict Hot Products
                    </button>
                    <!-- Original: Sales Report Button -->
                    <a href="sales_weekly_report.php" class="btn btn-orange shadow-sm" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Sales Report
                    </a>
                </div>
            </div>

            <!-- Original Stat Cards (3 cards) -->
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

            <!-- Original Chart + Recent Orders layout -->
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
                                        <tr style="cursor: pointer;" onclick="window.location='order_details.php?id=<?php echo $row['Order_Id']; ?>'">
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

<!-- ===================== AI PREDICTION MODAL ===================== -->
<div class="ai-modal-overlay" id="aiModalOverlay" onclick="handleOverlayClick(event)">
    <div class="ai-modal" id="aiModal">
        <div class="ai-modal-header">
            <div class="ai-header-icon"><i class="bi bi-stars"></i></div>
            <div>
                <div class="ai-modal-title">AI Hot Product Prediction</div>
                <div class="ai-modal-subtitle">Powered by Gemini AI &middot; Based on your store's sales data</div>
            </div>
            <button class="ai-modal-close" onclick="closeAIPrediction()"><i class="bi bi-x"></i></button>
        </div>
        <div class="ai-modal-body" id="aiModalBody"></div>
    </div>
</div>

<script>
// ===================== ORIGINAL SALES CHART =====================
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
    appendTo: document.getElementById('calendarBtn'),
    static: true,
    position: "below right",
    onChange: function(selectedDates) { renderChart(selectedDates[0]); }
});

document.getElementById('calendarBtn').addEventListener('click', () => fp.open());
renderChart(new Date());


// ===================== AI PREDICTION =====================
const aiProductsData = <?php echo $json_ai_products; ?>;
let predictionGenerated = false;

function openAIPrediction() {
    document.getElementById('aiModalOverlay').classList.add('active');
    if (!predictionGenerated) generateAIPrediction();
}

function closeAIPrediction() {
    document.getElementById('aiModalOverlay').classList.remove('active');
}

function handleOverlayClick(e) {
    if (e.target === document.getElementById('aiModalOverlay')) closeAIPrediction();
}

async function generateAIPrediction() {
    predictionGenerated = true;
    const body = document.getElementById('aiModalBody');
    body.innerHTML = `
        <div class="ai-loading">
            <div class="ai-spinner"></div>
            <div class="ai-loading-text">AI is analysing your sales data<span class="ai-loading-dots"></span></div>
            <div style="font-size: 12px; color: #cbd5e1; margin-top: 8px;">Studying trends, growth rates &amp; demand patterns</div>
        </div>`;

    const dataStr = aiProductsData.map(p =>
        `- ${p.Pro_Name} (${p.Brand_Name}, Category: ${p.Cat_Name}, Price: RM${p.Pro_Price}, Total Sold: ${p.total_sold}, This Month: ${p.this_month_sold}, Last Month: ${p.last_month_sold}, Orders: ${p.order_count})`
    ).join('\n');

    const prompt = `You are a retail analytics AI for a Malaysian sports shoes online store. Based on the following product sales data, predict the TOP 4 hottest-selling products for the coming month and explain why.

PRODUCT SALES DATA:
${dataStr}

Instructions:
- Analyse growth trends (compare this_month_sold vs last_month_sold)
- Consider total popularity (total_sold, order_count)
- Factor in price point and category demand
- Return ONLY valid JSON (no markdown, no explanation outside JSON)

Return this exact JSON structure:
{
  "summary": "2-3 sentence overall market insight for this store",
  "predictions": [
    {
      "rank": 1,
      "product_name": "exact product name",
      "brand": "brand name",
      "category": "category",
      "confidence_score": 87,
      "tags": ["Hot Seller", "Growing"],
      "reason": "Detailed 2-3 sentence explanation why this product will be hot next month. Include specific numbers from the data."
    }
  ]
}

Tags must be chosen from: Hot Seller, Growing, Trending, Stable, Rising Star, High Demand, Top Rated. Pick 1-2 most relevant.
Confidence score is a number between 60 and 98.`;

const GEMINI_KEY = '<?php echo htmlspecialchars(getenv("GEMINI_API_KEY")); ?>';

    try {
        const response = await fetch(
            `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=${GEMINI_KEY}`,
            {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    contents: [{ parts: [{ text: prompt }] }],
                    generationConfig: { temperature: 0.7, maxOutputTokens: 1000 }
                })
            }
        );

        const data = await response.json();
        const rawText = data.candidates[0].content.parts[0].text;
        const cleaned = rawText.replace(/```json|```/g, '').trim();
        renderPredictions(JSON.parse(cleaned));

    } catch (err) {
        body.innerHTML = `
            <div style="text-align:center; padding:50px 20px;">
                <div style="font-size:36px; margin-bottom:16px;">⚠️</div>
                <div style="font-size:15px; font-weight:700; color:#1e293b; margin-bottom:8px;">Unable to generate prediction</div>
                <div style="font-size:13px; color:#94a3b8; margin-bottom:20px;">Please check your API key and try again.</div>
                <button onclick="predictionGenerated=false; generateAIPrediction();" class="btn-ai" style="font-size:13px; padding:9px 20px;">
                    <i class="bi bi-arrow-clockwise"></i> Try Again
                </button>
            </div>`;
    }
}

function getTagClass(tag) {
    const map = { 'Hot Seller':'tag-hot','High Demand':'tag-hot','Trending':'tag-trend','Top Rated':'tag-trend','Growing':'tag-growing','Rising Star':'tag-growing','Stable':'tag-stable' };
    return map[tag] || 'tag-stable';
}

function renderPredictions(data) {
    const body = document.getElementById('aiModalBody');
    const cardsHTML = data.predictions.map(p => `
        <div class="prediction-card">
            <span class="pred-rank">#${p.rank} Predicted</span>
            <div class="pred-product-name">${p.product_name}</div>
            <div class="pred-brand">${p.brand} &middot; ${p.category}</div>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <div class="pred-score-bar" style="flex:1;">
                    <div class="pred-score-fill" style="width:${p.confidence_score}%;"></div>
                </div>
                <span style="font-size:11px; font-weight:700; color:var(--ai-purple); white-space:nowrap;">${p.confidence_score}% confident</span>
            </div>
            <div class="pred-tags">${(p.tags||[]).map(t=>`<span class="pred-tag ${getTagClass(t)}">${t}</span>`).join('')}</div>
            <div class="pred-reason">${p.reason}</div>
        </div>`).join('');

    body.innerHTML = `
        <div class="ai-summary-box">
            <div class="ai-summary-label"><i class="bi bi-graph-up-arrow"></i> Market Insight</div>
            <div class="ai-summary-text">${data.summary}</div>
        </div>
        <div class="prediction-grid">${cardsHTML}</div>
        <div class="ai-disclaimer">
            <i class="bi bi-info-circle"></i>
            Predictions are based on historical sales data and AI trend analysis. Use as a guide, not a guarantee.
            <br>
            <button class="btn-regen" onclick="predictionGenerated=false; generateAIPrediction();">
                <i class="bi bi-arrow-clockwise"></i> Regenerate Prediction
            </button>
        </div>`;
}
</script>
</body>
</html>