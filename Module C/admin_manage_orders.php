<?php
// admin_manage_orders.php
session_start();
require_once '../includes/db_connection.php'; 

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

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

// 处理获取详情 (保持 SweetAlert 弹窗，因为详情信息多，弹窗比较好看)
if (isset($_GET['ajax_order_id'])) {
    $order_id = mysqli_real_escape_string($conn, $_GET['ajax_order_id']);
    $sql = "SELECT o.*, u.User_Name, u.User_Email FROM `order` o JOIN `user` u ON o.User_Id = u.User_Id WHERE o.Order_Id = '$order_id'";
    $res = mysqli_query($conn, $sql);
    $order = mysqli_fetch_assoc($res);
    if ($order) {
        $items_sql = "SELECT od.*, p.Pro_Name FROM order_detail od JOIN product p ON od.Pro_Id = p.Pro_Id WHERE od.Order_Id = '$order_id'";
        $items_res = mysqli_query($conn, $items_sql);
        $items_html = '<table class="table table-sm text-start" style="font-size:13px;"><thead><tr><th>Product</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
        while ($item = mysqli_fetch_assoc($items_res)) {
            $items_html .= "<tr><td>{$item['Pro_Name']}</td><td>{$item['Order_Qty']}</td><td>RM {$item['Order_Subtotal']}</td></tr>";
        }
        $items_html .= '</tbody></table>';
        echo '<div class="text-start small"><p><strong>Customer:</strong> '.$order['User_Name'].'</p><p><strong>Address:</strong> '.$order['Order_Shipping_Addr'].'</p>'.$items_html.'<h5 class="text-end fw-bold mt-2" style="color:#FF6B00">Total: RM '.number_format($order['Order_Amount'], 2).'</h5></div>';
    }
    exit();
}

// 2. 获取统计数据
$stats_query = "SELECT 
    COUNT(CASE WHEN Order_Status = 'Pending' THEN 1 END) as pending,
    COUNT(CASE WHEN Order_Status = 'Processing' THEN 1 END) as processing,
    COUNT(CASE WHEN Order_Status = 'Shipped' THEN 1 END) as shipped,
    COUNT(CASE WHEN Order_Status = 'Delivered' THEN 1 END) as delivered,
    COUNT(*) as total FROM `order` ";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$filter = $_GET['status'] ?? 'All';
$where_clause = ($filter != 'All') ? "WHERE o.Order_Status = '$filter'" : "";

$sql = "SELECT o.*, u.User_Name, u.User_Email, GROUP_CONCAT(p.Pro_Name SEPARATOR ', ') as products, SUM(od.Order_Qty) as total_qty
        FROM `order` o
        JOIN `user` u ON o.User_Id = u.User_Id
        JOIN `order_detail` od ON o.Order_Id = od.Order_Id
        JOIN `product` p ON od.Pro_Id = p.Pro_Id
        $where_clause GROUP BY o.Order_Id ORDER BY o.Order_Date DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --sidebar-width: 260px; --primary-orange: #FF6B00; }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
        .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); min-height: 100vh; }
        .content-area { padding: 30px 40px; }
        .table-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        
        /* 状态标签样式 */
        .badge-status { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; }
        .bg-pending { background: #fef3c7; color: #92400e; }
        .bg-processing { background: #e0e7ff; color: #3730a3; }
        .bg-shipped { background: #dbeafe; color: #1e40af; }
        .bg-delivered { background: #dcfce7; color: #166534; }

        /* 下拉菜单微调 */
        .dropdown-menu { font-size: 13px; border-radius: 10px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .dropdown-item:active { background-color: var(--primary-orange); }

        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .icon-pending { background: #fff7ed; color: #f59e0b; }
        .icon-processing { background: #eef2ff; color: #6366f1; }
        .icon-shipped { background: #eff6ff; color: #3b82f6; }
        .icon-delivered { background: #f0fdf4; color: #22c55e; }
        .filter-btn { padding: 8px 18px; border-radius: 10px; text-decoration: none; background: white; color: #64748b; border: 1px solid #e2e8f0; font-size: 14px; }
        .filter-btn.active { background: var(--primary-orange); color: white; border-color: var(--primary-orange); }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="content-area">
            <h2 class="fw-bold mb-4">Manage Orders</h2>

            <div class="row g-4 mb-4">
                <?php 
                $cards = [['Pending', $stats['pending'], 'bi-clock-history', 'icon-pending'], ['Processing', $stats['processing'], 'bi-gear-wide-connected', 'icon-processing'], ['Shipped', $stats['shipped'], 'bi-truck', 'icon-shipped'], ['Delivered', $stats['delivered'], 'bi-check2-circle', 'icon-delivered']];
                foreach($cards as $c): ?>
                <div class="col-md-3">
                    <div class="table-card d-flex align-items-center justify-content-between p-4">
                        <div><p class="text-muted small fw-bold mb-1"><?php echo $c[0]; ?></p><h3 class="fw-bold mb-0"><?php echo $c[1]; ?></h3></div>
                        <div class="stat-icon <?php echo $c[3]; ?>"><i class="bi <?php echo $c[2]; ?>"></i></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="table-card">
                <table class="table align-middle">
                    <thead class="table-light small">
                        <tr><th>Order ID</th><th>Customer</th><th>Products</th><th>Status</th><th>Amount</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="fw-bold">#ORD-<?php echo $row['Order_Id']; ?></td>
                            <td><?php echo $row['User_Name']; ?></td>
                            <td class="small text-truncate" style="max-width: 150px;"><?php echo $row['products']; ?></td>
                            <td>
                                <div class="dropdown">
                                    <span class="badge-status bg-<?php echo strtolower($row['Order_Status']); ?> dropdown-toggle" 
                                          data-bs-toggle="dropdown" aria-expanded="false">
                                        <?php echo $row['Order_Status']; ?>
                                    </span>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatus(<?php echo $row['Order_Id']; ?>, 'Pending')">Pending</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatus(<?php echo $row['Order_Id']; ?>, 'Processing')">Processing</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatus(<?php echo $row['Order_Id']; ?>, 'Shipped')">Shipped</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatus(<?php echo $row['Order_Id']; ?>, 'Delivered')">Delivered</a></li>
                                    </ul>
                                </div>
                            </td>
                            <td class="fw-bold">RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                            <td class="text-end">
                                <button onclick="showDetail(<?php echo $row['Order_Id']; ?>)" class="btn btn-sm btn-outline-dark rounded-pill px-3">Details</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
                // 成功后使用 SweetAlert 的小提示（Toast），不遮挡屏幕
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
                    location.reload(); // 刷新页面同步统计
                });
            }
        });
    }

    // 详情依然使用 SweetAlert 弹窗
    function showDetail(orderId) {
        Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading() } });
        fetch('admin_manage_orders.php?ajax_order_id=' + orderId)
            .then(res => res.text())
            .then(html => {
                Swal.fire({
                    title: 'Order Details #ORD-' + orderId,
                    html: html,
                    width: '600px',
                    confirmButtonColor: '#FF6B00'
                });
            });
    }
    </script>
</body>
</html>