<?php
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
$sql = "SELECT o.*, u.User_Name 
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

// 3. 最后才加上排序，并执行查询（全页面只能有一个 $result = mysqli_query...）
$sql .= " ORDER BY o.Order_Date DESC";
$result = mysqli_query($conn, $sql);

// --- 处理 AJAX 请求：更新状态 ---
if (isset($_POST['update_status'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    $update_sql = "UPDATE `order` SET Order_Status = '$new_status' WHERE Order_Id = '$order_id'";
    if (mysqli_query($conn, $update_sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}



// --- 修改后的逻辑 ---

// 1. 获取统计数据 (第 103 行左右的统计逻辑也建议加上过滤，否则 Level 3 看到的总数会是全站的)
$stats_where = "";
if ($_SESSION['role'] == 3) {
    $stats_where = " WHERE EXISTS (
        SELECT 1 FROM order_detail od 
        JOIN product p ON od.Pro_Id = p.Pro_Id 
        JOIN brand b ON p.Brand_Id = b.Brand_Id 
        WHERE od.Order_Id = `order`.Order_Id AND b.Admin_Id = '".$_SESSION['admin_id']."'
    )";
}
$stats_query = "SELECT 
    COUNT(CASE WHEN Order_Status = 'Pending' THEN 1 END) as pending,
    COUNT(CASE WHEN Order_Status = 'Processing' THEN 1 END) as processing,
    COUNT(CASE WHEN Order_Status = 'Shipped' THEN 1 END) as shipped,
    COUNT(CASE WHEN Order_Status = 'Delivered' THEN 1 END) as delivered,
    COUNT(*) as total FROM `order` $stats_where";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);


// 2. 修改主表格查询 (第 115 行左右)
$filter = $_GET['status'] ?? 'All';

// 基础条件：如果是 Level 3，必须只看自己品牌
if ($_SESSION['role'] == 3) {
    $base_condition = "EXISTS (
        SELECT 1 FROM order_detail od2 
        JOIN product p2 ON od2.Pro_Id = p2.Pro_Id 
        JOIN brand b2 ON p2.Brand_Id = b2.Brand_Id 
        WHERE od2.Order_Id = o.Order_Id AND b2.Admin_Id = '".$_SESSION['admin_id']."'
    )";
    
    // 如果有状态筛选，则用 AND 连接
    $where_clause = ($filter != 'All') ? "WHERE ($base_condition) AND o.Order_Status = '$filter'" : "WHERE $base_condition";
} else {
    // Level 1 或 2 的原有逻辑
    $where_clause = ($filter != 'All') ? "WHERE o.Order_Status = '$filter'" : "";
}

// 最终执行的 SQL
$sql = "SELECT o.*, u.User_Name, u.User_Email, GROUP_CONCAT(p.Pro_Name SEPARATOR ', ') as products, SUM(od.Order_Qty) as total_qty
        FROM `order` o
        JOIN `user` u ON o.User_Id = u.User_Id
        JOIN `order_detail` od ON o.Order_Id = od.Order_Id
        JOIN `product` p ON od.Pro_Id = p.Pro_Id
        $where_clause 
        GROUP BY o.Order_Id 
        ORDER BY o.Order_Date DESC";
        
$result = mysqli_query($conn, $sql);

// --- 处理 AJAX 请求：获取订单内的商品列表 (整合颜色图片逻辑) ---
if (isset($_GET['ajax_get_items'])) {
    $order_id = mysqli_real_escape_string($conn, $_GET['ajax_get_items']);
    
    // 增加查询 Pro_Colour (订单时的颜色)
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
        // --- 核心整合：拿颜色的 Code ---
        $item_color = trim($item['Order_Colour'] ?? ''); // 订单里记录的颜色
        $base_img = $item['Pro_Image'];
        $display_img = "../uploads/" . $base_img; // 默认图片路径

        if (!empty($item_color)) {
            // 这里的逻辑参考自 admin_manage_products.php
            $base_name = preg_replace('/_\d+$/', '', pathinfo($base_img, PATHINFO_FILENAME));
            $slug = strtolower(str_replace(' ', '_', $item_color));
            
            // 搜索匹配该颜色的物理文件
            $files = glob("../uploads/{$base_name}*{$slug}*.*");
            if (!empty($files)) {
                $display_img = "../uploads/" . basename($files[0]); // 取该颜色第一张
            }
        }
        // ----------------------------

        $detail_link = "admin_order_details.php?id=$order_id&pro_id=" . $item['Pro_Id'];
        
        echo '
        <a href="'.$detail_link.'" class="list-group-item list-group-item-action d-flex align-items-center p-3 mb-2" style="border-radius:10px; border:1px solid #eee;">
            <img src="'.$display_img.'" class="rounded me-3" style="width:50px; height:50px; object-fit:cover;" onerror="this.src=\'../assets/no-image.png\'">
            <div class="flex-grow-1 text-start">
                <div class="d-flex justify-content-between">
                    <h6 class="mb-0 fw-bold">'.$item['Pro_Name'].'</h6>
                    <span class="badge bg-light text-dark border">'.$item['Brand_Name'].'</span>
                </div>
                <small class="text-muted">Color: <b>'.$item_color.'</b> | Size: '.$item['Pro_Size'].'</small>
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
            <!-- 统计卡片区域 -->
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
                    <div class="table-card d-flex align-items-center justify-content-between p-4 mb-0">
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
                            <tr>
                                <td class="fw-bold">#ORD-<?php echo $row['Order_Id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['User_Name']); ?></div>
                                    <div class="small text-muted"><?php echo $row['User_Email']; ?></div>
                                </td>
                                <td><?php echo $display_date; ?></td> <td><?php echo $display_time; ?></td> <td>
                                    <div class="dropdown">
    <span class="badge-status bg-<?php echo strtolower($current_status); ?> dropdown-toggle" 
          data-bs-toggle="dropdown" aria-expanded="false">
        <?php echo $current_status; ?>
    </span>
    <ul class="dropdown-menu border-0 shadow-lg p-2" style="border-radius: 12px;">
        <li class="dropdown-header small text-uppercase fw-bold pb-2">Change Progress</li>
        <?php 
        foreach ($status_flow as $index => $step) {
            if ($index === $current_index - 1 || $index === $current_index + 1) {
                // 根据步骤类型显示不同的图标前缀
                $icon = ($index > $current_index) ? 'bi-arrow-right-circle' : 'bi-arrow-left-circle';
                $color_class = ($index > $current_index) ? 'text-primary' : 'text-muted';
                
                echo '<li><a class="dropdown-item d-flex align-items-center rounded-3 py-2" href="javascript:void(0)" 
                      onclick="updateStatus('.$row['Order_Id'].', \''.$step.'\')">
                      <i class="bi '.$icon.' '.$color_class.' me-2"></i>
                      <span>Move to '.$step.'</span></a></li>';
            }
        }
        ?>
    </ul>
</div>
                                </td>
                                <td class="fw-bold text-dark">RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                                <td class="text-end">
                                    <button onclick="showItemPopup('<?php echo $row['Order_Id']; ?>')" class="btn btn-sm btn-outline-dark rounded-pill px-3">Details    </button>
                                </td>
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
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Status updated to ' + newStatus
            }).then(() => {
                location.reload(); 
            });
        }
    });
}

function showItemPopup(orderId) {
    Swal.fire({
        title: 'Order Items (ID: #ORD-' + orderId + ')',
        html: '<div id="popup-loading" class="py-4"><div class="spinner-border text-primary"></div><p>Loading items...</p></div>',
        showConfirmButton: false,
        showCloseButton: true,
        width: '500px',
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

</script>
</body>
</html>