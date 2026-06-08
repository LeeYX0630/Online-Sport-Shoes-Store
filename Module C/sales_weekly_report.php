<?php
// export_pdf_report.php
session_start();
require_once '../includes/db_connection.php';

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id    = $_SESSION['admin_id'];
$admin_level = $_SESSION['admin_level'] ?? $_SESSION['role'];
$username    = $_SESSION['username'] ?? 'Admin';

// 2. Date & Weekly Logic
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_date  = new DateTime($selected_date);
$day_of_week   = $current_date->format('N');

$monday = clone $current_date;
$monday->modify("-" . ($day_of_week - 1) . " days");
$start_of_week = $monday->format('Y-m-d');

$sunday = clone $monday;
$sunday->modify("+6 days");
$end_of_week = $sunday->format('Y-m-d');

// 3. Permission Logic
$display_brand = "All Brands";
$brand_filter  = "";
$my_brand_id   = null;

if ($admin_level == '3') {
    $brand_query = "SELECT Brand_Id, Brand_Name FROM brand WHERE Admin_Id = '$admin_id' LIMIT 1";
    $brand_res   = $conn->query($brand_query);
    if ($brand_res && $brand_row = $brand_res->fetch_assoc()) {
        $my_brand_id   = $brand_row['Brand_Id'];
        $display_brand = $brand_row['Brand_Name'];
        $brand_filter  = " AND p.Brand_Id = '$my_brand_id' ";
    } else {
        $brand_filter  = " AND 1=0 ";
        $display_brand = "No Brand Assigned";
    }
}

// 4. Main Orders Query — uses Order_Tracking_Num for display
$sql = "SELECT 
            o.Order_Id,
            o.Order_Tracking_Num,
            o.Order_Date,
            u.User_Name,
            o.Order_Status,
            SUM(od.Order_Subtotal) as Display_Amount
        FROM `order` o
        LEFT JOIN user u ON o.User_Id = u.User_Id
        JOIN order_detail od ON o.Order_Id = od.Order_Id
        JOIN product p ON od.Pro_Id = p.Pro_Id
        WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
        $brand_filter
        GROUP BY o.Order_Id
        ORDER BY o.Order_Date DESC";

$result             = $conn->query($sql);
$total_orders_count = ($result) ? $result->num_rows : 0;

// ────────────────────────────────────────────────────────────
// 5a. Chart data – Level 3: daily sales count + amount
// ────────────────────────────────────────────────────────────
$daily_labels  = [];
$daily_counts  = [];
$daily_amounts = [];

if ($admin_level == '3') {

    // 1. 先生成完整的 7 天基础数据 (全部设为 0)
    $week_data = [];
    $temp_date = clone $monday;
    for ($i = 0; $i < 7; $i++) {
        $d_str = $temp_date->format('Y-m-d');
        $week_data[$d_str] = [
            'label'  => $temp_date->format('D d/m'), // 例如 Mon 01/06
            'count'  => 0,
            'amount' => 0.0
        ];
        $temp_date->modify('+1 day');
    }

    // Taken from main query's date range, but re-aggregated by day for charting
    $daily_sql = "SELECT
                      DATE(o.Order_Date) as day,
                      COUNT(DISTINCT o.Order_Id) as cnt,
                      SUM(od.Order_Subtotal) as total
                  FROM `order` o
                  JOIN order_detail od ON o.Order_Id = od.Order_Id
                  JOIN product p ON od.Pro_Id = p.Pro_Id
                  WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
                  $brand_filter
                  GROUP BY DATE(o.Order_Date)
                  ORDER BY day";
    $daily_res = $conn->query($daily_sql);

    // 3. 将数据库里实际有的销量覆盖到对应的日期上
    if ($daily_res) {
        while ($row = $daily_res->fetch_assoc()) {
            $d = $row['day'];
            if (isset($week_data[$d])) {
                $week_data[$d]['count']  = (int)$row['cnt'];
                $week_data[$d]['amount'] = (float)$row['total'];
            }
        }
    }

    // 4. 将整理好的数据分配给图表变量
    if ($daily_res) {
        foreach ($week_data as $data) {
            $daily_labels[]  = $data['label'];
            $daily_counts[]  = $data['count'];
            $daily_amounts[] = $data['amount'];
        }
    }
}

// ────────────────────────────────────────────────────────────
// 5b. Chart data – Level 3: top products by quantity sold
// ────────────────────────────────────────────────────────────
$product_labels  = [];
$product_qtys    = [];
$top_product_name = '';

if ($admin_level == '3') {
    $product_sql = "SELECT
                        p.Pro_Name,
                        SUM(od.Order_Qty) as total_qty
                    FROM order_detail od
                    JOIN product p ON od.Pro_Id = p.Pro_Id
                    JOIN `order` o ON od.Order_Id = o.Order_Id
                    WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
                    $brand_filter
                    GROUP BY p.Pro_Id
                    ORDER BY total_qty DESC
                    LIMIT 5";
    $product_res = $conn->query($product_sql);
    if ($product_res) {
        $first = true;
        while ($row = $product_res->fetch_assoc()) {
            $product_labels[] = $row['Pro_Name'];
            $product_qtys[]   = (int)$row['total_qty'];
            if ($first) {
                $top_product_name = $row['Pro_Name'];
                $first = false;
            }
        }
    }
}

// ────────────────────────────────────────────────────────────
// 5c. Chart data – Level 1/2: daily sales across ALL brands
// ────────────────────────────────────────────────────────────
$all_daily_labels  = [];
$all_daily_counts  = [];
$all_daily_amounts = [];

if ($admin_level != '3') {

    // 1. 先生成完整的 7 天基础数据 (全部设为 0)
    $all_week_data = [];
    $temp_date = clone $monday;
    for ($i = 0; $i < 7; $i++) {
        $d_str = $temp_date->format('Y-m-d');
        $all_week_data[$d_str] = [
            'label'  => $temp_date->format('D d/m'),
            'count'  => 0,
            'amount' => 0.0
        ];
        $temp_date->modify('+1 day');
    }

    $all_daily_sql = "SELECT
                          DATE(o.Order_Date) as day,
                          COUNT(DISTINCT o.Order_Id) as cnt,
                          SUM(od.Order_Subtotal) as total
                      FROM `order` o
                      JOIN order_detail od ON o.Order_Id = od.Order_Id
                      WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
                      GROUP BY DATE(o.Order_Date)
                      ORDER BY day";
    $all_daily_res = $conn->query($all_daily_sql);

    // 3. 覆盖实际销量数据
    if ($all_daily_res) {
        while ($row = $all_daily_res->fetch_assoc()) {
            $d = $row['day'];
            if (isset($all_week_data[$d])) {
                $all_week_data[$d]['count']  = (int)$row['cnt'];
                $all_week_data[$d]['amount'] = (float)$row['total'];
            }
        }
    }

    // 4. 将数据填充给前端 Chart.js
        foreach ($all_week_data as $wd) {
            $all_daily_labels[]  = $wd['label'];
            $all_daily_counts[]  = $wd['count'];
            $all_daily_amounts[] = $wd['amount'];
        }
}

// ────────────────────────────────────────────────────────────
// 5d. Chart data – Level 1/2: brand revenue ranking
// ────────────────────────────────────────────────────────────
$brand_labels     = [];
$brand_amounts    = [];
$top_brand_name   = '';
$top_brand_product = '';

if ($admin_level != '3') {
    $brand_sql = "SELECT
                      b.Brand_Name,
                      SUM(od.Order_Subtotal) as total_amount
                  FROM order_detail od
                  JOIN product p ON od.Pro_Id = p.Pro_Id
                  JOIN brand b ON p.Brand_Id = b.Brand_Id
                  JOIN `order` o ON od.Order_Id = o.Order_Id
                  WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
                  GROUP BY b.Brand_Id
                  ORDER BY total_amount DESC";
    $brand_res = $conn->query($brand_sql);
    if ($brand_res) {
        $first = true;
        while ($row = $brand_res->fetch_assoc()) {
            $brand_labels[]  = $row['Brand_Name'];
            $brand_amounts[] = (float)$row['total_amount'];
            if ($first) {
                $top_brand_name = $row['Brand_Name'];
                $first = false;
            }
        }
    }

    // Top-selling product of the top brand
    if ($top_brand_name) {
        $top_prod_sql = "SELECT p.Pro_Name, SUM(od.Order_Qty) as qty
                         FROM order_detail od
                         JOIN product p ON od.Pro_Id = p.Pro_Id
                         JOIN brand b ON p.Brand_Id = b.Brand_Id
                         JOIN `order` o ON od.Order_Id = o.Order_Id
                         WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
                           AND b.Brand_Name = '" . $conn->real_escape_string($top_brand_name) . "'
                         GROUP BY p.Pro_Id
                         ORDER BY qty DESC
                         LIMIT 1";
        $top_prod_res = $conn->query($top_prod_sql);
        if ($top_prod_res && $tp = $top_prod_res->fetch_assoc()) {
            $top_brand_product = $tp['Pro_Name'];
        }
    }
}

// JSON encode chart data for JS
$j_daily_labels    = json_encode($daily_labels);
$j_daily_counts    = json_encode($daily_counts);
$j_daily_amounts   = json_encode($daily_amounts);
$j_product_labels  = json_encode($product_labels);
$j_product_qtys    = json_encode($product_qtys);
$j_all_daily_labels  = json_encode($all_daily_labels);
$j_all_daily_counts  = json_encode($all_daily_counts);
$j_all_daily_amounts = json_encode($all_daily_amounts);
$j_brand_labels    = json_encode($brand_labels);
$j_brand_amounts   = json_encode($brand_amounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Weekly Report – <?php echo htmlspecialchars($display_brand); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* ── Base ─────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', 'Inter', sans-serif;
            padding: 40px;
            color: #333;
            line-height: 1.6;
            background: #f5f5f5;
        }

        /* ── Toolbar (no-print) ────────────────────────────────── */
        .no-print-area {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
        }
        .back-button-container {
            display: flex;
            justify-content: flex-end;
        }
        .btn-back-header {
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            transition: all 0.2s;
        }
        .btn-back-header:hover { color: #FA8A34; border-color: #FA8A34; transform: translateX(-3px); }

        .calendar-trigger {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: #fff;
            border: 2px solid #eee;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-height: 48px;
        }
        .calendar-trigger:hover { border-color: #FA8A34; color: #FA8A34; }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: #FA8A34;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            min-height: 48px;
            box-shadow: 0 4px 10px rgba(250,138,52,.25);
            transition: all 0.3s;
        }
        .btn-print:hover { background: #e67e22; transform: translateY(-2px); }

        /* ── Report card ───────────────────────────────────────── */
        #report-content {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }

        .report-header {
            text-align: center;
            border-bottom: 3px solid #FA8A34;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .report-header img { height: 75px; margin-bottom: 12px; }
        .report-header h1 {
            margin: 0;
            color: #FA8A34;
            font-size: 26px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .report-header p { color: #888; font-size: 14px; margin-top: 4px; }

        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
            font-size: 14px;
            color: #555;
        }

        /* ── KPI summary ───────────────────────────────────────── */
        .kpi-row {
            display: flex;
            gap: 16px;
            margin-bottom: 30px;
        }
        .kpi-card {
            flex: 1;
            background: linear-gradient(135deg, #fff8f2, #fff);
            border: 1px solid #ffe4cc;
            border-radius: 12px;
            padding: 18px 22px;
        }
        .kpi-card .kpi-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: .5px; }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 800; color: #FA8A34; margin-top: 4px; }
        .kpi-card .kpi-sub   { font-size: 12px; color: #aaa; margin-top: 2px; }

        /* ── Chart section ─────────────────────────────────────── */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr; /* 把原来的 1fr 1fr 改成 1fr，强制上下排列 */
            gap: 30px; /* 稍微增加一点上下间距，让排版更透气 */
            margin-bottom: 32px;
        }
        .chart-card {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
        }
        .chart-card h3 {
            font-size: 14px;
            font-weight: 700;
            color: #555;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .chart-card h3 .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #FA8A34;
            display: inline-block;
        }
        .top-sales-badge {
            margin-top: 10px;
            padding: 8px 12px;
            background: #fff8f2;
            border-left: 3px solid #FA8A34;
            border-radius: 4px;
            font-size: 12px;
            color: #888;
        }

        /* 找到并更新或添加以下代码 */
        .chart-card canvas {
            max-height: 300px !important; /* 强制缩小图表高度，如果还嫌大可以改成 150px */
            width: 100% !important;
        }

        .top-sales-badge strong { color: #FA8A34; }

        /* ── Orders table ──────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead th {
            background: #FA8A34;
            color: #fff;
            padding: 13px 12px;
            text-align: left;
            font-weight: 600;
        }
        tbody td { padding: 13px 12px; border-bottom: 1px solid #f0f0f0; }
        tbody tr:hover { background: #fff8f2; }
        .amount { font-weight: 700; text-align: right; }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-delivered  { background:#d1fae5; color:#065f46; }
        .status-processing { background:#dbeafe; color:#1e40af; }
        .status-shipped    { background:#fef9c3; color:#92400e; }
        .status-other      { background:#f3f4f6; color:#374151; }

        .grand-total td {
            background: #fff8f2;
            font-weight: 700;
            padding: 16px 12px;
            border-top: 2px solid #FA8A34;
        }
        .total-count-box { color: #FA8A34; font-weight: 800; border-right: 2px solid #ffe4cc; }

        /* ── Print ─────────────────────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .no-print-area { display: none; }
            #report-content { box-shadow: none; border-radius: 0; padding: 0; }
            
            /* 添加这两行：防止图表在生成 PDF 时从中间被切断 */
            .charts-section, .chart-card, table, tr { 
                page-break-inside: avoid; 
                break-inside: avoid; 
            }
        }
    </style>
</head>
<body>

<!-- ════════════════ TOOLBAR ════════════════ -->
<div class="no-print-area">
    <div class="back-button-container">
        <a href="admin_dashboard.php" class="btn-back-header">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <div class="action-row">
        <div class="calendar-trigger" id="datePickerBtn">
            <i class="bi bi-calendar-event"></i>
            <span>Week: <?php echo date('d M', strtotime($start_of_week)) . ' – ' . date('d M Y', strtotime($end_of_week)); ?></span>
        </div>
        <input type="text" id="weekPicker" style="display:none;">
        <button onclick="downloadPDF()" class="btn-print" id="dlBtn">
            <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF Report
        </button>
    </div>
</div>

<!-- ════════════════ REPORT ════════════════ -->
<div id="report-content">

    <!-- Header -->
    <div class="report-header">
        <img src="../images/picture/Logo 2.png" alt="Store Logo" onerror="this.style.display='none'">
        <h1>Sales Weekly Report</h1>
        <p>Online Sport Shoes Store</p>
    </div>

    <!-- Meta info -->
    <div class="info-section">
        <div>
            <strong>Generated By:</strong> <?php echo htmlspecialchars($username); ?><br>
            <strong>Report Scope:</strong> <?php echo htmlspecialchars($display_brand); ?><br>
            <strong>Period:</strong> <?php echo date('d/m/Y', strtotime($start_of_week)); ?> to <?php echo date('d/m/Y', strtotime($end_of_week)); ?>
        </div>
        <div style="text-align:right;">
            <strong>Print Date:</strong> <?php echo date('Y-m-d H:i'); ?><br>
            <strong>Admin Level:</strong> <?php echo htmlspecialchars($admin_level); ?>
        </div>
    </div>

    <!-- KPI summary cards -->
    <?php
    // Re-fetch totals for KPI
    $kpi_total = 0;
    $kpi_count = $total_orders_count;
    $kpi_sql   = "SELECT SUM(od.Order_Subtotal) as grand
                  FROM `order` o
                  JOIN order_detail od ON o.Order_Id = od.Order_Id
                  JOIN product p ON od.Pro_Id = p.Pro_Id
                  WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
                  $brand_filter";
    $kpi_res = $conn->query($kpi_sql);
    if ($kpi_res && $kpi_row = $kpi_res->fetch_assoc()) {
        $kpi_total = (float)$kpi_row['grand'];
    }
    $avg_order = ($kpi_count > 0) ? $kpi_total / $kpi_count : 0;
    ?>
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-value">RM <?php echo number_format($kpi_total, 2); ?></div>
            <div class="kpi-sub">This week</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value"><?php echo $kpi_count; ?></div>
            <div class="kpi-sub">Completed transactions</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Avg Order Value</div>
            <div class="kpi-value">RM <?php echo number_format($avg_order, 2); ?></div>
            <div class="kpi-sub">Per transaction</div>
        </div>
    </div>

    <!-- ════ CHARTS – LEVEL 3 (Brand Admin) ════ -->
    <?php if ($admin_level == '3'): ?>
    <div class="charts-section">

        <!-- Chart 1: Daily Sales (orders count + amount) -->
        <div class="chart-card">
            <h3><span class="dot"></span>Daily Sales Overview</h3>
            <canvas id="chartDailySales" height="220"></canvas>
        </div>

        <!-- Chart 2: Top Shoes by Qty -->
        <div class="chart-card">
            <h3><span class="dot"></span>Top Shoes Sold (by Quantity)</h3>
            <canvas id="chartTopShoes" height="200"></canvas>
            <?php if ($top_product_name): ?>
            <div class="top-sales-badge">
                🏆 Top Sales: <strong><?php echo htmlspecialchars($top_product_name); ?></strong>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ════ CHARTS – LEVEL 1/2 (Super / Manager) ════ -->
    <?php else: ?>
    <div class="charts-section">

        <!-- Chart 1: Daily Sales all brands -->
        <div class="chart-card">
            <h3><span class="dot"></span>Daily Sales Overview (All Brands)</h3>
            <canvas id="chartAllDailySales" height="220"></canvas>
        </div>

        <!-- Chart 2: Brand Revenue -->
        <div class="chart-card">
            <h3><span class="dot"></span>Revenue by Brand</h3>
            <canvas id="chartBrandRevenue" height="200"></canvas>
            <?php if ($top_brand_name): ?>
            <div class="top-sales-badge">
                🏆 Top Brand: <strong><?php echo htmlspecialchars($top_brand_name); ?></strong>
                <?php if ($top_brand_product): ?>
                &nbsp;|&nbsp; Best Seller: <strong><?php echo htmlspecialchars($top_brand_product); ?></strong>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>

    <!-- ════ ORDERS TABLE ════ -->
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Status</th>
                <th style="text-align:right;">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $total_sum = 0;
        // Reset result pointer (already fetched rows for charts via separate queries)
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $total_sum += $row['Display_Amount'];
                // Format: ODR + Order_Tracking_Num
                $tracking = $row['Order_Tracking_Num'] ?? str_pad($row['Order_Id'], 6, '0', STR_PAD_LEFT);
                $display_id = 'ODR' . $tracking;

                // Status badge class
                $status_raw   = strtolower(trim($row['Order_Status']));
                $status_class = 'status-other';
                if (strpos($status_raw, 'deliver') !== false) $status_class = 'status-delivered';
                elseif (strpos($status_raw, 'process') !== false) $status_class = 'status-processing';
                elseif (strpos($status_raw, 'ship') !== false) $status_class = 'status-shipped';
        ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($display_id); ?></strong></td>
                <td><?php echo date('d/m/Y', strtotime($row['Order_Date'])); ?></td>
                <td><?php echo htmlspecialchars($row['User_Name'] ?? 'Guest'); ?></td>
                <td>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($row['Order_Status'] ?: '—'); ?>
                    </span>
                </td>
                <td class="amount"><?php echo number_format($row['Display_Amount'], 2); ?></td>
            </tr>
        <?php endwhile; ?>

            <tr class="grand-total">
                <td class="total-count-box">Total Orders: <?php echo $total_orders_count; ?></td>
                <td colspan="3" style="text-align:right; text-transform:uppercase; letter-spacing:.5px;">
                    Weekly Grand Total:
                </td>
                <td class="amount" style="color:#FA8A34; font-size:18px;">
                    RM <?php echo number_format($total_sum, 2); ?>
                </td>
            </tr>

        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align:center; padding:60px; color:#999;">
                    No sales records found for this week.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div><!-- end #report-content -->


<!-- ════════════════ SCRIPTS ════════════════ -->
<script>
/* ─── Flatpickr date picker ─── */
document.getElementById('datePickerBtn').addEventListener('click', () => fp.open());
const fp = flatpickr("#weekPicker", {
    defaultDate: "<?php echo $selected_date; ?>",
    maxDate: "today",
    appendTo: document.getElementById('datePickerBtn'),
    static: true,
    position: "below",
    onChange: function(selectedDates, dateStr) {
        window.location.href = "sales_weekly_report.php?date=" + dateStr;
    }
});

/* ─── Palette ─── */
const ORANGE  = '#FA8A34';
const ORANGE_T = 'rgba(250,138,52,.15)';
const palette  = ['#FA8A34','#f97316','#fb923c','#fdba74','#fed7aa','#fde68a','#fbbf24','#f59e0b'];

/* ─── Chart.js defaults ─── */
Chart.defaults.font.family = "'Segoe UI', 'Inter', sans-serif";
Chart.defaults.color       = '#666';

<?php if ($admin_level == '3'): ?>
/* ══════════ LEVEL 3 CHARTS ══════════ */

/* Chart 1 – Daily line (orders) + bar (amount) */
(function() {
    const labels  = <?php echo $j_daily_labels; ?>;
    const counts  = <?php echo $j_daily_counts; ?>;
    const amounts = <?php echo $j_daily_amounts; ?>;
    if (!labels.length) return;

    new Chart(document.getElementById('chartDailySales'), {
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Revenue (RM)',
                    data: amounts,
                    backgroundColor: ORANGE_T,
                    borderColor: ORANGE,
                    borderWidth: 2,
                    borderRadius: 6,
                    yAxisID: 'yAmount'
                },
                {
                    type: 'line',
                    label: 'No. of Orders',
                    data: counts,
                    borderColor: '#f97316',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#f97316',
                    yAxisID: 'yCount'
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
            scales: {
                yAmount: {
                    type: 'linear',
                    position: 'left',
                    grid: { color: '#f0f0f0' },
                    ticks: { callback: v => 'RM ' + v.toLocaleString() }
                },
                yCount: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
})();

/* Chart 2 – Top Shoes horizontal bar */
(function() {
    const labels = <?php echo $j_product_labels; ?>;
    const qtys   = <?php echo $j_product_qtys; ?>;
    if (!labels.length) return;

    new Chart(document.getElementById('chartTopShoes'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Units Sold',
                data: qtys,
                backgroundColor: palette.slice(0, labels.length),
                borderRadius: 6,
                maxBarThickness: 40
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.x + ' units' } }
            },
            scales: {
                x: {
                    grid: { color: '#f0f0f0' },
                    ticks: { stepSize: 1 }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        // Shorten long names for display
                        callback: function(val, idx) {
                            const s = this.getLabelForValue(val);
                            return s.length > 20 ? s.substr(0, 18) + '…' : s;
                        }
                    }
                }
            }
        }
    });
})();

<?php else: ?>
/* ══════════ LEVEL 1/2 CHARTS ══════════ */

/* Chart 1 – All-brands daily sales */
(function() {
    const labels  = <?php echo $j_all_daily_labels; ?>;
    const counts  = <?php echo $j_all_daily_counts; ?>;
    const amounts = <?php echo $j_all_daily_amounts; ?>;
    if (!labels.length) return;

    new Chart(document.getElementById('chartAllDailySales'), {
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Revenue (RM)',
                    data: amounts,
                    backgroundColor: ORANGE_T,
                    borderColor: ORANGE,
                    borderWidth: 2,
                    borderRadius: 6,
                    yAxisID: 'yAmount'
                },
                {
                    type: 'line',
                    label: 'No. of Orders',
                    data: counts,
                    borderColor: '#f97316',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#f97316',
                    yAxisID: 'yCount'
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
            scales: {
                yAmount: {
                    type: 'linear',
                    position: 'left',
                    grid: { color: '#f0f0f0' },
                    ticks: { callback: v => 'RM ' + v.toLocaleString() }
                },
                yCount: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
})();

/* Chart 2 – Brand Revenue Bar */
(function() {
    const labels  = <?php echo $j_brand_labels; ?>;
    const amounts = <?php echo $j_brand_amounts; ?>;
    if (!labels.length) return;

    new Chart(document.getElementById('chartBrandRevenue'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (RM)',
                data: amounts,
                backgroundColor: palette.slice(0, labels.length),
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' RM ' + ctx.parsed.x.toLocaleString(undefined, {minimumFractionDigits:2})
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: '#f0f0f0' },
                    ticks: { callback: v => 'RM ' + v.toLocaleString() }
                },
                y: { grid: { display: false } }
            }
        }
    });
})();

<?php endif; ?>

/* ─── PDF Download ─── */
function downloadPDF() {
    const element = document.getElementById('report-content');
    const btn     = document.getElementById('dlBtn');
    const orig    = btn.innerHTML;
    
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';

    // 1. 备份网页原始样式状态
    const originalTransform = element.style.transform;
    const originalTransformOrigin = element.style.transformOrigin;
    const originalWidth = element.style.width;
    const originalMargin = element.style.margin;

    // 2. 注入 80% 缩放样式 (等比例缩小)
    // 将 width 设为 125% 是为了补偿整体缩放 0.8 后的空间流失，确保报表数据完美铺满 PDF 的左右两侧
    element.style.transform = 'scale(0.90)';
    element.style.transformOrigin = 'top left';
    element.style.width = '110%'; 
    element.style.margin = '0 ';

    const opt = {
        margin:   0.3,
        filename: 'Weekly_Report_<?php echo $start_of_week; ?>.pdf',
        image:    { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF:    { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // 3. 执行 html2pdf 转换
    html2pdf().set(opt).from(element).save().then(() => {
        // 4. 导出成功后，立刻将屏幕网页上的布局恢复成原本的 100% 状态
        element.style.transform = originalTransform;
        element.style.transformOrigin = originalTransformOrigin;
        element.style.width = originalWidth;
        
        btn.disabled  = false;
        btn.innerHTML = orig;
    }).catch(err => {
        // 异常处理：即使报错也必须恢复原网页排版
        element.style.transform = originalTransform;
        element.style.transformOrigin = originalTransformOrigin;
        element.style.width = originalWidth;
        element.style.margin = originalMargin;
        
        btn.disabled  = false;
        btn.innerHTML = orig;
        console.error(err);
    });
}
</script>

</body>
</html>