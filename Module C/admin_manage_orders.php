<?php
// 强制把 PHP 时区设置为马来西亚（吉隆坡）时间
date_default_timezone_set('Asia/Kuala_Lumpur');
// admin_manage_orders.php
session_start();
require_once '../includes/db_connection.php'; 

// 1. 安全检查[cite: 2]
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';
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

// 获取当前登录管理员 ID (假设你存放在 session 中)
$current_admin_id = $_SESSION['admin_id']; 
$admin_level = $_SESSION['role']; // 或者是 $_SESSION['role']，取决于你的命名



// 1. 先定义基础 SQL（注意：这里不要加分号结束，我们要根据条件拼接）
$sql = "SELECT o.*, u.User_Name, u.User_Email
        FROM `order` o 
        JOIN `user` u ON o.User_Id = u.User_Id";

// 2. 判断是否为 Level 3 品牌管理员
if ($_SESSION['role'] == 3) {
    $current_admin_id = $_SESSION['admin_id']; 
    // 增加过滤条件：该订单必须包含属于该 Admin 管理的品牌的商品
    $sql .= " WHERE EXISTS (
        SELECT 1 FROM order_detail od
        JOIN product p ON od.Pro_Id = p.Pro_Id
        JOIN brand b ON p.Brand_Id = b.Brand_Id
        WHERE od.Order_Id = o.Order_Id 
        AND b.Admin_Id = '$current_admin_id'
    )";
}

// 🌟【新增修复代码】执行查询，生成 $result 结果集供下方表格循环使用
$result = mysqli_query($conn, $sql);

// --- 处理 AJAX 请求：更新状态、记录各自的时间节点 ---
if (isset($_POST['update_status'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $current_time = date('Y-m-d H:i:s'); // 获取当前系统时间
    
    $extra_query = ""; // 用于动态拼接 order 表的更新字段

    // 为了在通知中显示订单编号，先查出该订单的 Tracking Number
    $order_info_res = mysqli_query($conn, "SELECT Order_Tracking_Num FROM `order` WHERE Order_Id = '$order_id'");
    $order_tracking_num = '';
    if ($order_info_res && $order_row = mysqli_fetch_assoc($order_info_res)) {
        $order_tracking_num = $order_row['Order_Tracking_Num'];
    }

    if ($new_status == 'Processing') {
        // Processing 时，订单处理时间留在 order 表
        $extra_query = ", Order_Processing_Date = '$current_time'";
        
        include 'generate_estimated_arrival_date.php';
        
        // 预计到达时间写入 shipment 表
        $check_shipment = mysqli_query($conn, "SELECT * FROM `shipment` WHERE Order_Id = '$order_id'");
        if (mysqli_num_rows($check_shipment) > 0) {
            $shipment_sql = "UPDATE `shipment` SET Estimated_Arrival_Date = '$estimated_arrival_date' WHERE Order_Id = '$order_id'";
        } else {
            $shipment_sql = "INSERT INTO `shipment` (Order_Id, Estimated_Arrival_Date) VALUES ('$order_id', '$estimated_arrival_date')";
        }
        mysqli_query($conn, $shipment_sql);
    } 
    elseif ($new_status == 'Shipped') {
        // Shipped 时不更新 order 表其他字段
        $extra_query = "";
        
        $month_day = date('md');
        $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_str = substr(str_shuffle($permitted_chars), 0, 2);
        $tracking_number = "SSPMY" . $month_day . $random_str;

        // 追踪单号(Ship_Tracking_Number) 和 发货时间(Shipped_Date) 存入 shipment 表
        $check_shipment = mysqli_query($conn, "SELECT * FROM `shipment` WHERE Order_Id = '$order_id'");
        if (mysqli_num_rows($check_shipment) > 0) {
            $shipment_sql = "UPDATE `shipment` 
                             SET Ship_Tracking_num = '$tracking_number', 
                                 Shipped_Date = '$current_time' 
                             WHERE Order_Id = '$order_id'";
        } else {
            $shipment_sql = "INSERT INTO `shipment` (Order_Id, Ship_Tracking_num, Shipped_Date) 
                             VALUES ('$order_id', '$tracking_number', '$current_time')";
        }
        mysqli_query($conn, $shipment_sql);
    } 
    elseif ($new_status == 'Delivered') {
        // Delivered 时不更新 order 表其他字段
        $extra_query = "";
        
        // 将 Delivered_Date 存入 shipment 表
        $check_shipment = mysqli_query($conn, "SELECT * FROM `shipment` WHERE Order_Id = '$order_id'");
        if (mysqli_num_rows($check_shipment) > 0) {
            $shipment_sql = "UPDATE `shipment` SET Delivered_Date = '$current_time' WHERE Order_Id = '$order_id'";
        } else {
            $shipment_sql = "INSERT INTO `shipment` (Order_Id, Delivered_Date) VALUES ('$order_id', '$current_time')";
        }
        mysqli_query($conn, $shipment_sql);
    }

    // 最后，只更新 order 表中的 Status（以及 Processing 时的处理时间）
    $update_sql = "UPDATE `order` SET Order_Status = '$new_status' $extra_query WHERE Order_Id = '$order_id'";
    
if (mysqli_query($conn, $update_sql)) {
        
        // ── 🌟 自动触发状态变更通知（精准路由给对应品牌的 Level 3 管理员） ──
        $notif_type = 'status_change';
        $notif_title = "Order Status Updated";
        
        // 根据变更为何种状态，定制人性化的通知内容
        switch ($new_status) {
            case 'Processing':
                $notif_msg = "Order #ODR{$order_tracking_num} is now being processed and prepared.";
                break;
            case 'Shipped':
                $notif_msg = "Order #ODR{$order_tracking_num} has been shipped out.";
                break;
            case 'Delivered':
                $notif_msg = "Order #ODR{$order_tracking_num} has been successfully delivered.";
                break;
            default:
                $notif_msg = "Order #ODR{$order_tracking_num} status changed to {$new_status}.";
                break;
        }
        
        $notif_link = "admin_manage_orders.php"; 

        // 【核心修改】：通过当前 Order_Id 查出这个订单里包含了哪些 Level 3 品牌管理员的产品
        $brand_admin_sql = "
            SELECT DISTINCT b.Admin_Id 
            FROM order_detail od
            JOIN product p ON od.Pro_Id = p.Pro_Id
            JOIN brand b ON p.Brand_Id = b.Brand_Id
            WHERE od.Order_Id = '$order_id' AND b.Admin_Id IS NOT NULL
        ";
        $brand_admin_res = mysqli_query($conn, $brand_admin_sql);
        
        // 用于记录是否成功发给了特定品牌管理员
        $has_sent_to_brand_admin = false;

        if ($brand_admin_res && mysqli_num_rows($brand_admin_res) > 0) {
            // 遍历所有涉及到的 Level 3 品牌管理员，给他们每个人单独插入一条带有个体 Admin_Id 的通知
            $stmt_notif = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, ?, ?)");
            
            while ($brand_admin_row = mysqli_fetch_assoc($brand_admin_res)) {
                $target_admin_id = intval($brand_admin_row['Admin_Id']);
                $stmt_notif->bind_param("ssssii", $notif_type, $notif_title, $notif_msg, $notif_link, $target_admin_id, $order_id);
                $stmt_notif->execute();
                $has_sent_notif = true;
            }
            $stmt_notif->close();
        }

        // 【兜底机制】：如果这个订单里没有查到任何 Level 3 管理员的产品（比如普通全站产品），则以 NULL 形式发给 Level 1 & 2 系统大管理员
        if (!$has_sent_notif) {
            $stmt_global = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, NULL, ?)");
            $stmt_global->bind_param("ssssi", $notif_type, $notif_title, $notif_msg, $notif_link, $order_id);
            $stmt_global->execute();
            $stmt_global->close();
        }
        // ──────────────────────────────────────────────

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit();
}

// --- 统计订单状态数量 (为顶部的4个卡片提供数据) ---
$stats = [
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0
];

$stat_sql = "SELECT Order_Status, COUNT(*) as count FROM `order` o";

// 如果是 Level 3 品牌管理员，统计时也必须过滤出该管理员的订单
if ($_SESSION['role'] == 3) {
    $stat_sql .= " WHERE EXISTS (
        SELECT 1 FROM order_detail od
        JOIN product p ON od.Pro_Id = p.Pro_Id
        JOIN brand b ON p.Brand_Id = b.Brand_Id
        WHERE od.Order_Id = o.Order_Id 
        AND b.Admin_Id = '$current_admin_id'
    )";
}

$stat_sql .= " GROUP BY Order_Status";
$stat_result = mysqli_query($conn, $stat_sql);

if ($stat_result) {
    while ($row = mysqli_fetch_assoc($stat_result)) {
        // 将数据库里的状态 (如 'Pending') 转成小写 ('pending') 以匹配我们的数组键名
        $status = strtolower($row['Order_Status']); 
        if (isset($stats[$status])) {
            $stats[$status] = $row['count'];
        }
    }
}



// --- 修改后的逻辑 ---

// --- 处理 AJAX 请求：获取订单内的商品列表 (整合颜色图片逻辑) ---
if (isset($_GET['ajax_get_items'])) {
    $order_id = mysqli_real_escape_string($conn, $_GET['ajax_get_items']);
    
    // 增加查询 Pro_Colour 和 Pro_Size (从 order_detail 表)
    $sql = "SELECT od.*, p.Pro_Name, p.Pro_Image, b.Brand_Name, p.Pro_Id
            FROM order_detail od 
            JOIN product p ON od.Pro_Id = p.Pro_Id 
            JOIN brand b ON p.Brand_Id = b.Brand_Id 
            WHERE od.Order_Id = '$order_id'";

    if ($_SESSION['role'] == 3) {
        $current_admin_id = $_SESSION['admin_id'];
        $sql .= " AND b.Admin_Id = '$current_admin_id'";
    }

    $result = mysqli_query($conn, $sql);
    
    echo '<div class="list-group">';
    while ($item = mysqli_fetch_assoc($result)) {
        // --- 1. 从 order_detail 中拿出 color 和 size ---
        $item_color = trim($item['Pro_Colour'] ?? ''); 
        $item_size = trim($item['Pro_Size'] ?? '');
        
        // --- 2. 拼接图片逻辑 ---
        $base_img = $item['Pro_Image'];
        $display_img = "../uploads/" . $base_img; // 默认图片路径

        // 检查是否是 Custom Design 并且有预览图 (兼容你数据库里的 Custom_Preview)
        if ($item_color === 'Custom Design' && !empty($item['Custom_Preview'])) {
            $display_img = $item['Custom_Preview'];
        } 
        else if (!empty($item_color)) {
            $path_info = pathinfo($base_img);
            // 提取前面的文件名 (例如: pegasus42)
            $base_name = preg_replace('/_\d+$/', '', $path_info['filename']); 
            $ext = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
            
            // 将颜色转成小写，并将空格替换为下划线 (例如 "Laksa Pruple" 变成 "laksa_pruple")
            $formatted_color = strtolower(str_replace(' ', '_', $item_color));
            
            // 组装格式: [pro_image]_[pro_color]_1
            $target_img_name = $base_name . '_' . $formatted_color . '_1' . $ext;
            $display_img = "../uploads/" . $target_img_name;
        }
        // ----------------------------

        $detail_link = "order_details.php?id=$order_id&pro_id=" . $item['Pro_Id'];
        
        echo '
        <a href="'.$detail_link.'" class="list-group-item list-group-item-action d-flex align-items-center p-3 mb-2 order-item-hover" style="border-radius:10px;">
        <img src="'.$display_img.'" class="rounded me-3 shadow-sm" style="width:80px; height:80px; object-fit:contain; background-color: #fff; padding: 4px; border: 1px solid #eee;" onerror="this.src=\'../assets/no-image.png\'">            <div class="flex-grow-1 text-start">
                <div class="d-flex justify-content-between">
                    <h6 class="mb-0 fw-bold">'.$item['Pro_Name'].'</h6>
                    <span class="badge bg-light text-dark border">'.$item['Brand_Name'].'</span>
                </div>
                <small class="text-muted">Color: <b>'.($item_color ?: 'N/A').'</b> | Size: <b>'.($item_size ?: 'N/A').'</b></small>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <small class="text-muted">RM '.number_format($item['Order_Subtotal'], 2).' x '.$item['Order_Qty'].'</small>
                    <div class="fw-bold text-orange">Subtotal: RM '.number_format($item['Order_Subtotal'] * $item['Order_Qty'], 2).'</div>
                </div>
            </div>
            <i class="bi bi-chevron-right ms-2 text-muted"></i>
        </a>';
    }
    echo '</div>';
    exit();
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Admin</title>
    
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

        /* 统一 Header 样式[cite: 1] */
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

        /* 统一卡片样式[cite: 1] */
        .table-card { 
            background: white; 
            border-radius: 15px; 
            padding: 24px; 
            border: none; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            margin-bottom: 25px;
        }
        
        .badge-status { 
            width: 100px; /* 固定宽度确保格子长度相同 */
            height: 32px;
            justify-content: center; /* 文字居中 */
            padding: 0; 
            border-radius: 8px; 
            font-size: 11px; 
            font-weight: 700; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center;
            border: none;
            transition: all 0.3s ease;
        }

        /* 状态对应颜色方案 */
        .bg-pending { background-color: #FFF4E5; color: #FF8C00; border: 1px solid #FFE0B2; }    /* 橙色/警告 */
        .bg-processing { background-color: #E8EAF6; color: #3F51B5; border: 1px solid #C5CAE9; } /* 紫色/处理 */
        .bg-shipped { background-color: #E3F2FD; color: #1976D2; border: 1px solid #BBDEFB; }    /* 蓝色/运输 */
        .bg-delivered { background-color: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }  /* 绿色/完成 */

        /* 移除下拉箭头的默认边距 */
        .dropdown-toggle::after {
            margin-left: 8px;
        }

        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .icon-pending { background: #fff7ed; color: #f59e0b; }
        .icon-processing { background: #eef2ff; color: #6366f1; }
        .icon-shipped { background: #eff6ff; color: #3b82f6; }
        .icon-delivered { background: #f0fdf4; color: #22c55e; }

        .btn-orange-outline { border: 1px solid var(--orange-primary); color: var(--orange-primary); transition: 0.3s; }
        .btn-orange-outline:hover { background: var(--orange-primary); color: white; }

        
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }

        /* --- 订单商品列表 Hover 效果 --- */
                .order-item-hover {
                    border: 1px solid #eee !important;
                    transition: all 0.2s ease-in-out;
                }
                .order-item-hover:hover {
                    border-color: rgba(255, 140, 0, 0.6) !important; /* 浅橙色边框 */
                    background-color: #fffbf5; /* 加一点极浅的橙色背景，效果更好看 */
                    box-shadow: 0 4px 8px rgba(255, 140, 0, 0.05); /* 微微的阴影 */
                }

                /* 让 4 个数据框可点击并带有悬浮动画 */
.summary-card {
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}
/* 增加一个隐形的内边框，为“变厚”做准备 */
.summary-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    border: 3px solid transparent;
    border-radius: inherit;
    transition: all 0.3s ease;
    pointer-events: none;
}
.summary-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1) !important;
}

/* 点击激活后的“变厚”与高亮状态 */
.summary-card.active-filter {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(255, 140, 0, 0.2) !important;
}
.summary-card.active-filter::after {
    border-color: var(--orange-primary); /* 出现橙色加粗边框，看起来变厚了 */
}

    </style>
</head>
<body>
<?php include_once '../includes/admin_sidebar.php'; ?>
<div class="wrapper">
    

    <div class="main-content">
        <!-- 完美的 Header 布局同步[cite: 1] -->
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <!-- 已更新为带链接的 Home -->
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #6c757d;">Orders</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Manage Orders</h4>
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
            <!-- 概述卡片 -->
            <div class="row g-4 mb-4">
            <?php 
            $cards = [
                ['Pending', $stats['pending'], 'bi-clock-history', 'icon-pending'], 
                ['Processing', $stats['processing'], 'bi-gear-wide-connected', 'icon-processing'], 
                ['Shipped', $stats['shipped'], 'bi-truck', 'icon-shipped'], 
                ['Delivered', $stats['delivered'], 'bi-check2-circle', 'icon-delivered']
            ];
            
            foreach($cards as $c): ?>
            <div class="col-md-3">
                <div class="table-card summary-card d-flex align-items-center justify-content-between p-4 mb-0" 
                    onclick="filterOrders('<?php echo $c[0]; ?>', this)">
                    <div>
                        <p class="text-muted small fw-bold mb-1 text-uppercase"><?php echo $c[0]; ?></p>
                        <h3 class="fw-bold mb-0"><?php echo $c[1]; ?></h3>
                    </div>
                    <div class="stat-icon <?php echo $c[3]; ?>"><i class="bi <?php echo $c[2]; ?>"></i></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

            <!-- 订单表格区域 -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Order Transactions</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light small">
                            <tr>
                                <th>ORDER ID</th>
                                <th>CUSTOMER</th>
                                <th>TRANSACTION DATE</th> <th>TRANSACTION TIME</th> <th>STATUS</th>
                                <th>AMOUNT</th>
                                <th class="text-end">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // 定义严格的状态流转顺序
                            $status_flow = ['Pending', 'Processing', 'Shipped', 'Delivered'];

                            while($row = mysqli_fetch_assoc($result)): 
                                // 拆分日期与时间
                                $order_timestamp = strtotime($row['Order_Date']);
                                $display_date = date('d M Y', $order_timestamp);
                                $display_time = date('h:i A', $order_timestamp);
                                
                                // 计算当前状态在流转链条中的位置
                                $current_status = $row['Order_Status'];
                                $current_index = array_search($current_status, $status_flow);
                            ?>
                            <tr class="order-row" data-status="<?php echo ucfirst(strtolower($row['Order_Status'])); ?>">
                                <td class="fw-bold">ODR<?php echo htmlspecialchars($row['Order_Tracking_Num']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['User_Name']); ?></div>
                                    <div class="small text-muted"><?php echo $row['User_Email']; ?></div>
                                </td>
                                <td><?php echo $display_date; ?></td> <td><?php echo $display_time; ?></td> 
                                <td>
                                    <div class="dropdown">
                                        <?php 
                                        // 判断是否已经是最后一个状态 (Delivered)
                                        $is_last_status = ($current_index === count($status_flow) - 1);
                                        ?>
                                        
                                        <span class="badge-status bg-<?php echo strtolower($current_status); ?> <?php echo !$is_last_status ? 'dropdown-toggle' : ''; ?>" 
                                            <?php echo !$is_last_status ? 'data-bs-toggle="dropdown" aria-expanded="false"' : ''; ?>>
                                            <?php echo $current_status; ?>
                                        </span>
                                        
                                        <?php if (!$is_last_status): ?>
                                        <ul class="dropdown-menu border-0 shadow-lg p-2" style="border-radius: 12px;">
                                            <li class="dropdown-header small text-uppercase fw-bold pb-2">Change Progress</li>
                                            <?php 
                                            foreach ($status_flow as $index => $step) {
                                                // 【核心修改】：只允许显示索引正好比当前大 1 的选项（只能前进1步，绝不后退）
                                                if ($index === $current_index + 1) {
                                                    echo '<li><a class="dropdown-item d-flex align-items-center rounded-3 py-2" href="javascript:void(0)" 
                                                        onclick="updateStatus('.$row['Order_Id'].', \''.$step.'\')">
                                                        <i class="bi bi-arrow-right-circle text-primary me-2"></i>
                                                        <span>Move to '.$step.'</span></a></li>';
                                                }
                                            }
                                            ?>
                                        </ul>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="fw-bold text-dark">RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                                <td class="text-end">
                                <button onclick="showItemPopup('<?php echo $row['Order_Id']; ?>', '<?php echo htmlspecialchars($row['Order_Tracking_Num']); ?>')" class="btn btn-sm btn-outline-dark rounded-pill px-3">Details</button>                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 直接更新状态的函数
function updateStatus(orderId, newStatus) {
    let formData = new FormData();
    formData.append('update_status', true);
    formData.append('order_id', orderId);
    formData.append('new_status', newStatus);

    fetch('admin_manage_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
if (data.status === 'success') {
            // 使用标准的屏幕居中 SweetAlert
            Swal.fire({
                icon: 'success', // 自动自带 ✔ 图标
                title: 'Status Updated to ' + newStatus,
                position: 'center', // 显示在屏幕正中心
                showConfirmButton: false, // 隐藏底部的 OK 按钮
                timer: 2000, // 2000 毫秒 (2秒) 后自动关闭
                timerProgressBar: true // 显示底部的时间进度条（视觉效果更好，不需要可以删掉这行）
            }).then(() => {
                location.reload(); // 2秒后弹窗关闭，自动刷新页面更新数据
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to update status. Please try again.'
            });
        }
    });
}

function showItemPopup(orderId) {
    let paddedOrderId = String(orderId).padStart(5, '0');
    Swal.fire({
        title: 'Order Items (ID: #ORD-' + paddedOrderId + ')',
        // --- 加入 customClass 控制标题样式 ---
        customClass: {
            title: 'text-start w-100 fs-5 mt-2 ms-2' 
        },
        html: '<div id="popup-loading" class="py-4"><div class="spinner-border text-primary"></div><p>Loading items...</p></div>',
        showConfirmButton: false,
        showCloseButton: true,
        width: '600px', // 保持之前的 600px
        didOpen: () => {
            // 通过 AJAX 获取商品列表 HTML
            fetch('admin_manage_orders.php?ajax_get_items=' + orderId)
                .then(response => response.text())
                .then(html => {
                    Swal.update({
                        html: html
                    });
                });
        }
    });
}

let currentFilter = '';

function filterOrders(status, cardElement) {
    const rows = document.querySelectorAll('.order-row');
    
    // 1. 如果点击的是已经“变厚”的框，代表取消过滤，显示所有订单
    if (currentFilter === status) {
        currentFilter = '';
        cardElement.classList.remove('active-filter');
        rows.forEach(row => row.style.display = ''); // 恢复显示所有行
        return;
    }

    // 2. 移除所有框的“变厚”状态，只给当前点击的框加厚
    document.querySelectorAll('.summary-card').forEach(card => card.classList.remove('active-filter'));
    cardElement.classList.add('active-filter');
    currentFilter = status;

    // 3. 秒速过滤下面的表格 List
    rows.forEach(row => {
        if (row.getAttribute('data-status') === status) {
            row.style.display = ''; // 状态吻合，显示
        } else {
            row.style.display = 'none'; // 状态不吻合，隐藏
        }
    });
}

</script>
</body>
</html>