<?php
// order_details.php
session_start();
require_once '../includes/db_connection.php'; 

// 强制设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;

// 获取头像
$admin_image = 'default_admin.png';
if ($admin_id) {
    $img_res = $conn->query("SELECT Admin_Image FROM admin WHERE Admin_Id = $admin_id");
    if ($img_res && $img_row = $img_res->fetch_assoc()) {
        $admin_image = !empty($img_row['Admin_Image']) ? $img_row['Admin_Image'] : 'default_admin.png';
    }
}

// 2. 接收并验证 Order ID
$order_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;

if (!$order_id) {
    echo "<script>alert('Invalid Order ID!'); window.location.href='admin_manage_orders.php';</script>";
    exit();
}

// 3. 查询订单基础信息、客户信息与物流信息
$order_sql = "SELECT o.*, u.User_Name, u.User_Email, u.User_Phone,
                     s.Ship_Tracking_num, s.Estimated_Arrival_Date, s.Shipped_Date, s.Delivered_Date 
              FROM `order` o 
              JOIN `user` u ON o.User_Id = u.User_Id 
              LEFT JOIN `shipment` s ON o.Order_Id = s.Order_Id 
              WHERE o.Order_Id = '$order_id'";
$order_result = mysqli_query($conn, $order_sql);
$order_data = mysqli_fetch_assoc($order_result);

if (!$order_data) {
    echo "<script>alert('Order not found!'); window.location.href='admin_manage_orders.php';</script>";
    exit();
}

// 格式化订单编号
$display_order_id = "#ORD-" . str_pad($order_id, 5, '0', STR_PAD_LEFT);

// ==========================================
// 🚨 严格的状态高亮逻辑
// ==========================================
$current_status = strtolower($order_data['Order_Status']);
$status_level = 1; 

// 根据当前状态，生成规范的展示文本和下方的小注释
$display_status_text = "Order Placed";
$status_note = "Your order has been logged successfully and is currently awaiting review.";

if ($current_status == 'processing') {
    $status_level = 2; 
    $display_status_text = "Processing";
    $status_note = "The warehouse is carefully inspecting, packing, and preparing your parcel for dispatch.";
} elseif ($current_status == 'shipped') {
    $status_level = 4; 
    $display_status_text = "In Transit";
    $status_note = "Your package has been handed over to our logistics partner and is making its way to you.";
} elseif ($current_status == 'delivered') {
    $status_level = 5; 
    $display_status_text = "Delivered";
    $status_note = "The shipment has arrived safely at the designated address. Thank you for shopping with us!";
}

// 精准对应 4 条间距线段的百分比
$progress_width = "0%";
if ($status_level == 2) $progress_width = "25%";
if ($status_level == 4) $progress_width = "75%";
if ($status_level == 5) $progress_width = "100%";

// 【新增控制】格式化右侧的 Estimated Arrival Time
$eta_display = "Pending Assignment";
if (!empty($order_data['Estimated_Arrival_Date'])) {
    $eta_display = date('d M Y', strtotime($order_data['Estimated_Arrival_Date']));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root { 
            --orange-primary: #FF8C00; 
            --sidebar-width: 260px; 
            --step-grey: #e0e0e0;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .wrapper { display: flex; }
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; }
        
        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--orange-primary); object-fit: cover; }
        
        .content-card { background: white; border-radius: 15px; padding: 24px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-title { font-size: 1.05rem; font-weight: 700; color: #333; margin-bottom: 1.2rem; display: flex; align-items: center; }
        
        .back-button-container { display: flex; justify-content: flex-end; margin-bottom: 15px; padding: 0 10px; }
        .btn-back-header { text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; transition: all 0.2s; border: 1px solid #e2e8f0; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .btn-back-header:hover { background-color: #fff; color: #FA8A34; border-color: #FA8A34; transform: translateX(-3px); }

        /* --- Stepper 进度条轨道机制 --- */
        .order-tracking { display: flex; justify-content: space-between; position: relative; padding: 10px 0; }
        .step-progress-bar-container { 
            position: absolute; 
            top: 27px; 
            left: 60px; 
            right: 60px; 
            height: 3px; 
            background-color: var(--step-grey); 
            z-index: 0; 
        }
        .step-line-progress { height: 100%; background-color: var(--orange-primary); transition: width 0.5s ease; }
        
        .track-step { position: relative; z-index: 2; text-align: center; width: 120px; }
        .track-step .icon-circle { 
            width: 54px; height: 54px; border-radius: 50%; 
            background: white; 
            border: 3px solid var(--step-grey); 
            display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 10px; transition: all 0.3s; font-size: 20px; 
            color: var(--step-grey); 
        }
        .track-step.active .icon-circle { 
            border-color: var(--orange-primary); 
            background-color: var(--orange-primary); 
            color: white; 
        }
        .track-step.active .step-label { color: #333; font-weight: 700; }
        .track-step .step-label { font-size: 14px; color: #888; margin-bottom: 0; }
        .track-step .step-date { font-size: 11px; color: #aaa; }

        /* 商品属性格子 */
        .attribute-badge {
            background-color: #fafafa;
            color: #555;
            border: 1px solid #e5e5e5;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 500;
            display: inline-block;
        }

        .info-label { font-size: 0.8rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; font-weight: 600; }
        .info-value { font-size: 0.95rem; color: #333; font-weight: 600; }

        .product-img { width: 80px; height: 80px; object-fit: contain; background-color: #fff; padding: 4px; border: 1px solid #eee; border-radius: 10px; }
        .text-orange { color: var(--orange-primary) !important; }
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
                        <li class="breadcrumb-item"><a href="admin_manage_orders.php" class="text-decoration-none" style="color: var(--orange-primary);">Orders</a></li>
                        <li class="breadcrumb-item active">Order Details</li>
                    </ol>
                </nav>
                
                <h4 class="fw-bold mb-0">Order <?php echo $display_order_id; ?></h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                    <small class="text-muted"><?php echo ($admin_role == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>" class="admin-profile-img" onerror="this.src='../assets/default_admin.png'">
            </div>
        </header>

            <div class="back-button-container">
                <a href="admin_manage_orders.php" class="btn-back-header"><i class="bi bi-arrow-left"></i> Back to Orders</a>
            </div>

        <div class="row mb-3">
            <div class="col-12">
                <div class="content-card shadow-sm" style="background-color: var(--orange-primary); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(255, 140, 0, 0.15); height: auto; padding: 24px;">
                    
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1 text-white-50" style="font-size: 0.82rem; letter-spacing: 0.5px; text-transform: uppercase;">Current Status</h6>
                            <h3 class="fw-bold text-white mb-2" style="font-size: 1.6rem;"><?php echo $display_status_text; ?></h3>
                            <p class="mb-0 text-white-50" style="font-size: 0.85rem; max-width: 500px; font-weight: 400; line-height: 1.4;">
                                <?php echo $status_note; ?>
                            </p>
                        </div>

                        <div class="text-end">
                            <h6 class="fw-bold mb-1 text-white-50" style="font-size: 0.82rem; letter-spacing: 0.5px; text-transform: uppercase;">Estimated Arrival Time</h6>
                            <h3 class="fw-bold text-white mb-0" style="font-size: 1.6rem; letter-spacing: -0.5px;">
                                <?php echo $eta_display; ?>
                            </h3>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="row">
            
            <div class="col-lg-8">
                
                <div class="content-card">
                    <div class="order-tracking">
                        <div class="step-progress-bar-container">
                            <div class="step-line-progress" style="width: <?php echo $progress_width; ?>;"></div>
                        </div>

                        <div class="track-step active">
                            <div class="icon-circle"><i class="bi bi-file-earmark-text"></i></div>
                            <p class="step-label">Order Placed</p>
                            <span class="step-date"><?php echo date('d M Y, H:i', strtotime($order_data['Order_Date'])); ?></span>
                        </div>

                        <div class="track-step <?php echo ($status_level >= 2) ? 'active' : ''; ?>">
                            <div class="icon-circle"><i class="bi bi-credit-card"></i></div>
                            <p class="step-label">Processing</p>
                            <span class="step-date"><?php echo $order_data['Order_Processing_Date'] ? date('d M Y, H:i', strtotime($order_data['Order_Processing_Date'])) : '--'; ?></span>
                        </div>

                        <div class="track-step <?php echo ($status_level >= 3) ? 'active' : ''; ?>">
                            <div class="icon-circle"><i class="bi bi-box-seam"></i></div>
                            <p class="step-label">Shipped Out</p>
                            <span class="step-date"><?php echo $order_data['Shipped_Date'] ? date('d M Y, H:i', strtotime($order_data['Shipped_Date'])) : '--'; ?></span>
                        </div>

                        <div class="track-step <?php echo ($status_level >= 4) ? 'active' : ''; ?>">
                            <div class="icon-circle"><i class="bi bi-truck"></i></div>
                            <p class="step-label">In Transit</p>
                            <span class="step-date"><?php echo $order_data['Ship_Tracking_num'] ?: '--'; ?></span>
                        </div>

                        <div class="track-step <?php echo ($status_level >= 5) ? 'active' : ''; ?>">
                            <div class="icon-circle"><i class="bi bi-check2-circle"></i></div>
                            <p class="step-label">Delivered</p>
                            <span class="step-date"><?php echo $order_data['Delivered_Date'] ? date('d M Y, H:i', strtotime($order_data['Delivered_Date'])) : '--'; ?></span>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h5 class="fw-bold mb-0">Items in Order</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Details</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $items_sql = "SELECT od.*, p.Pro_Name, p.Pro_Image, b.Brand_Name 
                                              FROM order_detail od 
                                              JOIN product p ON od.Pro_Id = p.Pro_Id 
                                              JOIN brand b ON p.Brand_Id = b.Brand_Id 
                                              WHERE od.Order_Id = '$order_id'";
                                if ($_SESSION['role'] == 3) $items_sql .= " AND b.Admin_Id = '$admin_id'";
                                
                                $items_result = mysqli_query($conn, $items_sql);
                                $total_final = 0;

                                while ($item = mysqli_fetch_assoc($items_result)) {
                                    $item_color = $item['Pro_Colour'];
                                    $base_img = $item['Pro_Image'];
                                    $display_img = "../uploads/" . $base_img; 
                                    
                                    if ($item_color === 'Custom Design' && !empty($item['Custom_Preview'])) {
                                        $display_img = $item['Custom_Preview'];
                                    } else if (!empty($item_color)) {
                                        $path_info = pathinfo($base_img);
                                        $base_name = preg_replace('/_\d+$/', '', $path_info['filename']); 
                                        $formatted_color = strtolower(str_replace(' ', '_', $item_color));
                                        $display_img = "../uploads/" . $base_name . '_' . $formatted_color . '_1.' . $path_info['extension'];
                                    }

                                    $subtotal = $item['Order_Subtotal'] * $item['Order_Qty'];
                                    $total_final += $subtotal;
                                ?>
                                    <tr>
                                        <td><img src="<?php echo $display_img; ?>" class="product-img shadow-sm" onerror="this.src='../assets/no-image.png'"></td>
                                        <td>
                                            <h6 class="mb-2 fw-bold text-dark"><?php echo $item['Pro_Name']; ?></h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="attribute-badge"><?php echo $item['Brand_Name']; ?></span>
                                                <span class="attribute-badge">Color: <b><?php echo $item_color ?: 'N/A'; ?></b></span>
                                                <span class="attribute-badge">Size: <b><?php echo $item['Pro_Size'] ?: 'N/A'; ?></b></span>
                                            </div>
                                        </td>
                                        <td>RM <?php echo number_format($item['Order_Subtotal'], 2); ?></td>
                                        <td><?php echo $item['Order_Qty']; ?></td>
                                        <td class="text-end fw-bold text-orange">RM <?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <h5 class="fw-bold">Total: <span class="text-orange">RM <?php echo number_format($total_final, 2); ?></span></h5>
                    </div>
                </div>

                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">
                            <i class="bi bi-credit-card text-orange me-2"></i>Payment Details
                        </h5>
                        <span class="badge bg-light text-success border px-2 py-1 fw-bold" style="font-size: 0.85rem;">Paid</span>
                    </div>
                    <hr class="mt-0 mb-4" style="color: #eee;">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-label text-muted mb-1">Payment Method</div>
                            <div class="info-value text-dark" style="font-size: 1rem; font-weight: 600;">
                                <?php echo htmlspecialchars($order_data['Payment_Method'] ?? 'Online Payment'); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 border-start border-light ps-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted" style="font-size: 0.9rem;">Subtotal</span>
                                <span class="text-dark fw-semibold">RM <?php echo number_format($total_final, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted" style="font-size: 0.9rem;">Shipping Fee</span>
                                <span class="text-dark fw-semibold">RM 0.00</span>
                            </div>
                            <hr class="my-2" style="color: #eee;">
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="fw-bold text-dark" style="font-size: 1rem;">Total Amount</span>
                                <span class="fw-bold text-orange" style="font-size: 1.3rem;">RM <?php echo number_format($total_final, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                
                <div class="content-card">
                    <h5 class="card-title"><i class="bi bi-person-badge text-orange me-2"></i>Customer Information</h5>
                    <div class="info-label text-muted mb-1">Customer Name</div>
                    <div class="info-value text-dark mb-3"><?php echo htmlspecialchars($order_data['User_Name']); ?></div>
                    
                    <div class="info-label text-muted mb-1">Phone Number</div>
                    <div class="info-value text-dark mb-3"><?php echo htmlspecialchars($order_data['User_Phone'] ?: 'N/A'); ?></div>
                    
                    <div class="info-label text-muted mb-1">Email Address</div>
                    <div class="info-value text-dark mb-3" style="font-weight: 500; font-size: 0.9rem Tao;"><?php echo htmlspecialchars($order_data['User_Email']); ?></div>
                    
                    <div class="info-label text-muted mb-1">Shipping Address</div>
                    <div class="info-value text-dark" style="font-weight: 500; font-size: 0.9rem; line-height: 1.4;"><?php echo htmlspecialchars($order_data['Order_Shipping_Addr'] ?? 'N/A'); ?></div>
                </div>

                <div class="content-card">
                    <h5 class="card-title"><i class="bi bi-truck text-orange me-2"></i>Shipping Info</h5>
                    
                    <div class="info-label text-muted mb-1">Tracking Number</div>
                    <div class="info-value text-primary fw-bold mb-3" style="font-size: 1.05rem;">
                        <?php echo !empty($order_data['Ship_Tracking_num']) ? htmlspecialchars($order_data['Ship_Tracking_num']) : 'Not Available'; ?>
                    </div>
                    
                    <div class="info-label text-muted mb-1">Shipped Date</div>
                    <div class="info-value text-dark fw-medium mb-3" style="font-size: 0.95rem;">
                        <?php echo !empty($order_data['Shipped_Date']) ? date('d M Y, h:i A', strtotime($order_data['Shipped_Date'])) : 'Not Shipped Yet'; ?>
                    </div>
                    
                    <div class="info-label text-muted mb-1">Delivered Date</div>
                    <div class="info-value text-success fw-bold" style="font-size: 0.95rem;">
                        <?php echo !empty($order_data['Delivered_Date']) ? date('d M Y, h:i A', strtotime($order_data['Delivered_Date'])) : 'Not Delivered Yet'; ?>
                    </div>
                </div>

            </div>
            
        </div>
    </div>
</div>

</body>
</html>