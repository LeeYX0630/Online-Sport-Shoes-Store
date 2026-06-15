<?php
// 强制把 PHP 时区设置为马来西亚（吉隆坡）时间
date_default_timezone_set('Asia/Kuala_Lumpur');
// admin_manage_orders.php
session_start();
require_once '../includes/db_connection.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../includes/PHPMailer/Exception.php';
require_once '../includes/PHPMailer/PHPMailer.php';
require_once '../includes/PHPMailer/SMTP.php';
require_once '../includes/mail_config.php';

// 发送发货通知邮件给用户
function sendShipmentNotificationEmail($user_email, $user_name, $order_id, $tracking_number, $shipped_date) {
    $mail = new PHPMailer(true);
    try {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $order_link = "http://{$host}/Online-Sport-Shoes-Store/Module A/order_view.php?order_id={$order_id}";

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('sportshoes.system@gmail.com', 'STRYDEX SPORT SHOES STORE');
        $mail->addAddress($user_email, $user_name);

        $mail->isHTML(true);
        $mail->Subject = "Your order #{$order_id} has been shipped";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 680px; margin: auto; border: 1px solid #e5e5e5; border-radius: 14px; overflow: hidden;'>
                <div style='background: #111; color: #fff; padding: 24px; text-align: center;'>
                    <h1 style='margin:0; font-size: 24px;'>Your order is on the way</h1>
                    <p style='margin:8px 0 0; color:#ddd;'>Order #{$order_id} has been shipped.</p>
                </div>
                <div style='padding: 28px;'>
                    <p style='font-size: 16px; color: #333;'>Hello {$user_name},</p>
                    <p style='color: #555; line-height: 1.7;'>Good news! Your order has been shipped and is now on its way to you. Please keep the tracking number below for your reference.</p>
                    <div style='background:#f8f9ff; border:1px solid #dbe4ff; border-radius:12px; padding:18px; margin:20px 0;'>
                        <p style='margin:0 0 8px; font-size:14px; color:#717171;'>Tracking Number</p>
                        <h2 style='margin:0; color:#111;'>$tracking_number</h2>
                        <p style='margin:16px 0 0; color:#555;'>Shipped on: $shipped_date</p>
                    </div>
                    <p style='color: #555; line-height: 1.7;'>You can view your order status and delivery details by visiting your account.</p>
                    <div style='text-align:center; margin-top: 24px;'>
                        <a href='$order_link' style='display:inline-block; padding: 14px 26px; border-radius: 8px; background:#FF6B00; color:#fff; text-decoration:none; font-weight:700;'>View Order Details</a>
                    </div>
                </div>
                <div style='background:#f2f2f2; padding:18px; text-align:center; color:#777; font-size:12px;'>If you did not request this order or believe this is an error, please contact our support team immediately.</div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Shipment email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// ─────────────────────────────────────────────────────────────
// 发送 Issue / Canceled / Resumed-Processing 通知邮件给客户
// ─────────────────────────────────────────────────────────────
function sendOrderStatusEmail($type, $user_email, $user_name, $order_tracking_num, $reason, $superadmin_email, $brand_admin_email) {
    $mail = new PHPMailer(true);
    try {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $order_link = "http://{$host}/Online-Sport-Shoes-Store/Module A/order_view.php?order_id={$order_tracking_num}";

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('sportshoes.system@gmail.com', 'STRYDEX SPORT SHOES STORE');
        $mail->addAddress($user_email, $user_name);
        $mail->isHTML(true);

        // ── 共用 header HTML (匹配 send_receipt_handler.php 样式) ──
        $header = "
            <div style='background-color:#000; padding:30px; text-align:center;'>
                <h1 style='color:#FF6B00; margin:0; font-size:24px; text-transform:uppercase; letter-spacing:2px;'>STRYDEX Sport Shoes Store</h1>
                <p style='color:#fff; margin:5px 0 0; font-size:12px; opacity:0.8;'>Multimedia University, Melaka, Malaysia | +60 12-345 6789</p>
            </div>";

        // ── 共用 contact footer ──
        $contact_block = "";
        if (!empty($superadmin_email) || !empty($brand_admin_email)) {
            $contact_block .= "<div style='margin-top:28px; padding:16px 20px; background:#fff8f0; border-left:4px solid #FF6B00; border-radius:6px;'>";
            $contact_block .= "<p style='margin:0 0 6px; font-size:13px; color:#555; font-weight:600;'>If you have any questions, please contact us:</p>";
            if (!empty($superadmin_email)) {
                $contact_block .= "<p style='margin:2px 0; font-size:13px; color:#333;'>📧 Support: <a href='mailto:{$superadmin_email}' style='color:#FF6B00;'>{$superadmin_email}</a></p>";
            }
            if (!empty($brand_admin_email) && $brand_admin_email !== $superadmin_email) {
                $contact_block .= "<p style='margin:2px 0; font-size:13px; color:#333;'>📧 Brand Manager: <a href='mailto:{$brand_admin_email}' style='color:#FF6B00;'>{$brand_admin_email}</a></p>";
            }
            $contact_block .= "</div>";
        }

        $footer = "<div style='background-color:#f4f4f4; padding:20px; text-align:center; font-size:12px;'>
                        <p style='margin:0; color:#999;'>&copy; " . date('Y') . " STRYDEX Sport Shoes Store. All Rights Reserved.</p>
                   </div>";

        // ── 根据类型构建邮件内容 ──
        if ($type === 'Issue') {
            $mail->Subject = "Your Order #ODR{$order_tracking_num} — Action Required";
            $icon  = "⚠️";
            $title = "Your Order Has Been Flagged";
            $accent = "#f59e0b";
            $body_content = "
                <p style='font-size:15px; color:#333;'>Hello <strong>{$user_name}</strong>,</p>
                <p style='color:#555; line-height:1.8;'>
                    We would like to inform you that your order <strong>#ODR{$order_tracking_num}</strong> has been temporarily placed on hold due to an issue that requires our attention.
                </p>
                <div style='background:#fffbea; border:1px solid #fde68a; border-radius:10px; padding:16px 20px; margin:20px 0;'>
                    <p style='margin:0 0 6px; font-size:13px; color:#92400e; font-weight:700; text-transform:uppercase; letter-spacing:.5px;'>Reason</p>
                    <p style='margin:0; font-size:15px; color:#111; font-weight:600;'>{$reason}</p>
                </div>
                <p style='color:#555; line-height:1.8;'>
                    Our team is actively working to resolve this as quickly as possible. Please allow <strong>1–3 working days</strong> for us to investigate and get back to you.
                    We apologise for any inconvenience caused and appreciate your patience.
                </p>
                {$contact_block}
                <div style='text-align:center; margin-top:24px;'>
                    <a href='{$order_link}' style='display:inline-block; padding:12px 28px; border-radius:8px; background:#FF8C00; color:#fff; text-decoration:none; font-weight:700; font-size:14px;'>View My Order</a>
                </div>";

        } elseif ($type === 'Canceled') {
            $mail->Subject = "Your Order #ODR{$order_tracking_num} Has Been Cancelled";
            $icon  = "❌";
            $title = "Your Order Has Been Cancelled";
            $accent = "#ef4444";
            $body_content = "
                <p style='font-size:15px; color:#333;'>Hello <strong>{$user_name}</strong>,</p>
                <p style='color:#555; line-height:1.8;'>
                    We regret to inform you that your order <strong>#ODR{$order_tracking_num}</strong> has been cancelled. Below is the reason provided by our team:
                </p>
                <div style='background:#fff5f5; border:1px solid #fecaca; border-radius:10px; padding:16px 20px; margin:20px 0;'>
                    <p style='margin:0 0 6px; font-size:13px; color:#b91c1c; font-weight:700; text-transform:uppercase; letter-spacing:.5px;'>Reason</p>
                    <p style='margin:0; font-size:15px; color:#111; font-weight:600;'>{$reason}</p>
                </div>
                <p style='color:#555; line-height:1.8;'>
                    If you believe this is a mistake or would like to place a new order, please don't hesitate to reach out to us. We're here to help.
                </p>
                {$contact_block}
                <div style='text-align:center; margin-top:24px;'>
                    <a href='{$order_link}' style='display:inline-block; padding:12px 28px; border-radius:8px; background:#FF8C00; color:#fff; text-decoration:none; font-weight:700; font-size:14px;'>View My Order</a>
                </div>";

        } elseif ($type === 'IssueResolved') {
            $mail->Subject = "Good News! Your Order #ODR{$order_tracking_num} Is Back On Track";
            $icon  = "✅";
            $title = "Issue Resolved — Order Resumed";
            $accent = "#10b981";
            $body_content = "
                <p style='font-size:15px; color:#333;'>Hello <strong>{$user_name}</strong>,</p>
                <p style='color:#555; line-height:1.8;'>
                    Great news! The issue with your order <strong>#ODR{$order_tracking_num}</strong> has been resolved and your order is now back in processing. Thank you for your patience!
                </p>
                <div style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:16px 20px; margin:20px 0;'>
                    <p style='margin:0; font-size:15px; color:#065f46; font-weight:600;'>✔ Your order is now being processed and will be shipped soon.</p>
                </div>
                <p style='color:#555; line-height:1.8;'>
                    We sincerely apologise for the delay and appreciate your understanding. If you have any further questions, feel free to contact us below.
                </p>
                {$contact_block}
                <div style='text-align:center; margin-top:24px;'>
                    <a href='{$order_link}' style='display:inline-block; padding:12px 28px; border-radius:8px; background:#FF8C00; color:#fff; text-decoration:none; font-weight:700; font-size:14px;'>View My Order</a>
                </div>";
        } else {
            return false;
        }

        $mail->Body = "
            <div style='font-family: \"Segoe UI\", Helvetica, Arial, sans-serif; max-width:700px; margin:auto; border:1px solid #ddd; border-radius:8px; overflow:hidden; color:#444;'>
                {$header}
                <div style='padding:10px 28px 4px; text-align:center; border-bottom:2px solid {$accent};'>
                    <p style='font-size:28px; margin:16px 0 4px;'>{$icon}</p>
                    <h2 style='margin:0 0 14px; color:#111; font-size:20px;'>{$title}</h2>
                </div>
                <div style='padding:24px 28px;'>
                    {$body_content}
                </div>
                {$footer}
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Order status email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

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

// 【修改】让未完成的订单按最新排在前面，把已经 Delivered 的订单沉到底部
$sql .= " ORDER BY CASE WHEN o.Order_Status = 'Delivered' THEN 1 ELSE 0 END ASC, o.Order_Id DESC";

// 🌟【新增修复代码】执行查询，生成 $result 结果集供下方表格循环使用
$result = mysqli_query($conn, $sql);

// --- 处理 AJAX 请求：更新状态、记录各自的时间节点 ---
if (isset($_POST['update_status'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    // 【新增】获取 Reason
    $reason = '';
    $reason_query = '';
    if (isset($_POST['reason']) && !empty($_POST['reason'])) {
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $reason_query = ", Problem_Reason = '$reason'";
    }

    $current_time = date('Y-m-d H:i:s'); 
    $extra_query = ""; 

    // 获取订单基础信息 + Super Admin email + Brand Admin email
    $order_info_res = mysqli_query($conn, "SELECT o.Order_Tracking_Num, u.User_Email, u.User_Name FROM `order` o JOIN `user` u ON o.User_Id = u.User_Id WHERE o.Order_Id = '$order_id'");
    $order_tracking_num = '';
    $customer_email = '';
    $customer_name = '';
    if ($order_info_res && $order_row = mysqli_fetch_assoc($order_info_res)) {
        $order_tracking_num = $order_row['Order_Tracking_Num'];
        $customer_email     = $order_row['User_Email'];
        $customer_name      = $order_row['User_Name'];
    }

    // 获取 Super Admin (Admin_Id = 1) 的 Email
    $superadmin_email = '';
    $sa_res = mysqli_query($conn, "SELECT Admin_Email FROM admin WHERE Admin_Id = 1 LIMIT 1");
    if ($sa_res && $sa_row = mysqli_fetch_assoc($sa_res)) {
        $superadmin_email = $sa_row['Admin_Email'];
    }

    // 获取该订单所属品牌的 Brand Admin Email
    $brand_admin_email = '';
    $ba_res = mysqli_query($conn, "
        SELECT DISTINCT a.Admin_Email
        FROM order_detail od
        JOIN product p  ON od.Pro_Id   = p.Pro_Id
        JOIN brand b    ON p.Brand_Id  = b.Brand_Id
        JOIN admin a    ON b.Admin_Id  = a.Admin_Id
        WHERE od.Order_Id = '$order_id'
        LIMIT 1
    ");
    if ($ba_res && $ba_row = mysqli_fetch_assoc($ba_res)) {
        $brand_admin_email = $ba_row['Admin_Email'];
    }

    // 状态流转处理
    if ($new_status == 'Processing') {
        $extra_query = ", Order_Processing_Date = '$current_time'";
        include 'generate_estimated_arrival_date.php';
        
        $check_shipment = mysqli_query($conn, "SELECT * FROM `shipment` WHERE Order_Id = '$order_id'");
        if (mysqli_num_rows($check_shipment) > 0) {
            $shipment_sql = "UPDATE `shipment` SET Estimated_Arrival_Date = '$estimated_arrival_date' WHERE Order_Id = '$order_id'";
        } else {
            $shipment_sql = "INSERT INTO `shipment` (Order_Id, Estimated_Arrival_Date) VALUES ('$order_id', '$estimated_arrival_date')";
        }
        mysqli_query($conn, $shipment_sql);
    } 
    elseif ($new_status == 'Shipped') {
        $month_day = date('md');
        $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_str = substr(str_shuffle($permitted_chars), 0, 2);
        $tracking_number = "STXMY" . $month_day . $random_str;

        $check_shipment = mysqli_query($conn, "SELECT * FROM `shipment` WHERE Order_Id = '$order_id'");
        if (mysqli_num_rows($check_shipment) > 0) {
            $shipment_sql = "UPDATE `shipment` SET Ship_Tracking_num = '$tracking_number', Shipped_Date = '$current_time' WHERE Order_Id = '$order_id'";
        } else {
            $shipment_sql = "INSERT INTO `shipment` (Order_Id, Ship_Tracking_num, Shipped_Date) VALUES ('$order_id', '$tracking_number', '$current_time')";
        }
        mysqli_query($conn, $shipment_sql);
    } 
    elseif ($new_status == 'Delivered') {
        $check_shipment = mysqli_query($conn, "SELECT * FROM `shipment` WHERE Order_Id = '$order_id'");
        if (mysqli_num_rows($check_shipment) > 0) {
            $shipment_sql = "UPDATE `shipment` SET Delivered_Date = '$current_time' WHERE Order_Id = '$order_id'";
        } else {
            $shipment_sql = "INSERT INTO `shipment` (Order_Id, Delivered_Date) VALUES ('$order_id', '$current_time')";
        }
        mysqli_query($conn, $shipment_sql);
    }

    // 【新增】合并 $reason_query 更新 order 表
    $update_sql = "UPDATE `order` SET Order_Status = '$new_status' $extra_query $reason_query WHERE Order_Id = '$order_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        
        $notif_type = 'status_change';
        $notif_title = "Order Status Updated";

        if ($new_status === 'Shipped' && !empty($customer_email)) {
            $sent_email = sendShipmentNotificationEmail($customer_email, $customer_name ?: 'Customer', $order_id, $tracking_number, $current_time);
        }

        // 发送 Issue / Canceled 通知邮件给客户
        if (in_array($new_status, ['Issue', 'Canceled']) && !empty($customer_email)) {
            sendOrderStatusEmail(
                $new_status,
                $customer_email,
                $customer_name ?: 'Customer',
                $order_tracking_num,
                $reason ?: 'No reason specified.',
                $superadmin_email,
                $brand_admin_email
            );
        }

        // 发送 Issue 已解决、恢复 Processing 的通知邮件
        if ($new_status === 'Processing' && !empty($reason) && strpos($reason, 'Issue Resolved') !== false && !empty($customer_email)) {
            sendOrderStatusEmail(
                'IssueResolved',
                $customer_email,
                $customer_name ?: 'Customer',
                $order_tracking_num,
                $reason,
                $superadmin_email,
                $brand_admin_email
            );
        }
        
        // 【新增】为 Canceled 和 Issue 增加专属通知文字，附带 Reason
        switch ($new_status) {
            case 'Processing': $notif_msg = "Order #ODR{$order_tracking_num} is now being processed."; break;
            case 'Shipped':    $notif_msg = "Order #ODR{$order_tracking_num} has been shipped out."; break;
            case 'Delivered':  $notif_msg = "Order #ODR{$order_tracking_num} has been successfully delivered."; break;
            case 'Canceled':   $notif_msg = "Order #ODR{$order_tracking_num} was CANCELED. Reason: " . ($reason ?: 'None'); break;
            case 'Issue':      $notif_msg = "Order #ODR{$order_tracking_num} flagged with ISSUE. Reason: " . ($reason ?: 'None'); break;
            default:           $notif_msg = "Order #ODR{$order_tracking_num} status changed to {$new_status}."; break;
        }
        // ──────────────────────────────────────────────
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit();
}

// ==========================================
// 顶部状态统计卡片数据获取 (增加异常统计)
// ==========================================
if ($_SESSION['role'] == 3) {
    $current_admin_id = $_SESSION['admin_id'];
    $base_count_sql = "SELECT COUNT(DISTINCT o.Order_Id) as count FROM `order` o
                       JOIN order_detail od ON o.Order_Id = od.Order_Id
                       JOIN product p ON od.Pro_Id = p.Pro_Id
                       JOIN brand b ON p.Brand_Id = b.Brand_Id
                       WHERE b.Admin_Id = '$current_admin_id'";

    $count_pending = mysqli_fetch_assoc(mysqli_query($conn, $base_count_sql . " AND o.Order_Status = 'Pending'"))['count'];
    $count_processing = mysqli_fetch_assoc(mysqli_query($conn, $base_count_sql . " AND o.Order_Status = 'Processing'"))['count'];
    $count_shipped = mysqli_fetch_assoc(mysqli_query($conn, $base_count_sql . " AND o.Order_Status = 'Shipped'"))['count'];
    $count_delivered = mysqli_fetch_assoc(mysqli_query($conn, $base_count_sql . " AND o.Order_Status = 'Delivered'"))['count'];
    
    // 【新增】计算异常和已取消的订单
    $count_issue_canceled = mysqli_fetch_assoc(mysqli_query($conn, $base_count_sql . " AND o.Order_Status IN ('Issue', 'Canceled')"))['count'];
    
    $total_orders = mysqli_fetch_assoc(mysqli_query($conn, $base_count_sql))['count'];
} else {
    $count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM `order` WHERE Order_Status = 'Pending'"))['count'];
    $count_processing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM `order` WHERE Order_Status = 'Processing'"))['count'];
    $count_shipped = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM `order` WHERE Order_Status = 'Shipped'"))['count'];
    $count_delivered = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM `order` WHERE Order_Status = 'Delivered'"))['count'];
    
    // 【新增】计算异常和已取消的订单
    $count_issue_canceled = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM `order` WHERE Order_Status IN ('Issue', 'Canceled')"))['count'];
    
    $total_orders_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM `order`");
    $total_orders = mysqli_fetch_assoc($total_orders_result)['total'];
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
        .bg-processing { background-color: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }  /* 紫色/处理中 */
        .bg-shipped { background-color: #E3F2FD; color: #1976D2; border: 1px solid #BBDEFB; }    /* 蓝色/运输 */
        .bg-delivered { background-color: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }  /* 绿色/完成 */
        .bg-canceled { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }  /* 红色/取消 */
        .bg-issue { background-color: #fff8e1; color: #f57f17; border: 1px solid #ffecb3; }     /* 黄色/问题 */

        /* 移除下拉箭头的默认边距 */
        .dropdown-toggle::after {
            margin-left: 8px;
        }

        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .icon-pending { background: #fff7ed; color: #f59e0b; }
        .icon-processing { background: #f3e8ff; color: #7e22ce; }
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
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            /* 这里加上了灰色的边框，让格子轮廓非常明显 */
            border: 2px solid #e2e8f0; 
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            height: 100%; /* 确保所有格子高度一致 */
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

        /* 超过3天仍是 Pending/Processing 的订单行 — 浅红色提醒 */
        .overdue-row td {
            background-color: #fff5f5 !important;
        }
        .overdue-row:hover td {
            background-color: #ffe9e9 !important;
        }
        /* 左侧加一条红色细线作为视觉强调 */
        .overdue-row td:first-child {
            border-left: 3px solid #f87171 !important;
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
            <!-- 概述卡片 -->
            <div class="row g-3 mb-4">
            
            <div class="col-6 col-md-4 col-lg-2">
                <div class="summary-card p-3" onclick="filterOrders('All', this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold">All Orders</p>
                            <h4 class="mb-0 fw-bold"><?php echo $total_orders; ?></h4>
                        </div>
                        <div class="icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-collection"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-2">
                <div class="summary-card p-3" onclick="filterOrders('Pending', this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold">Pending</p>
                            <h4 class="mb-0 fw-bold"><?php echo $count_pending; ?></h4>
                        </div>
                        <div class="icon-box bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-2">
                <div class="summary-card p-3" onclick="filterOrders('Processing', this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold">Processing</p>
                            <h4 class="mb-0 fw-bold"><?php echo $count_processing; ?></h4>
                        </div>
                        <div class="icon-box bg-info bg-opacity-10 text-info">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-2">
                <div class="summary-card p-3" onclick="filterOrders('Shipped', this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold">Shipped</p>
                            <h4 class="mb-0 fw-bold"><?php echo $count_shipped; ?></h4>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-truck"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-2">
                <div class="summary-card p-3" onclick="filterOrders('Delivered', this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold">Delivered</p>
                            <h4 class="mb-0 fw-bold"><?php echo $count_delivered; ?></h4>
                        </div>
                        <div class="icon-box bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-2">
                <div class="summary-card p-3" onclick="filterOrders('Exception', this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small fw-bold">Issue / Cancel</p>
                            <h4 class="mb-0 fw-bold text-danger"><?php echo $count_issue_canceled; ?></h4>
                        </div>
                        <div class="icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-exclamation-octagon"></i>
                        </div>
                    </div>
                </div>
            </div>

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

                                // 检查是否 Pending/Processing 超过3天（浅红色提醒）
                                $is_overdue = false;
                                if (in_array($current_status, ['Pending', 'Processing'])) {
                                    $order_date = new DateTime(date('Y-m-d', $order_timestamp));
                                    $today      = new DateTime(date('Y-m-d'));
                                    $days_diff  = (int)$today->diff($order_date)->days;
                                    if ($days_diff > 3) {
                                        $is_overdue = true;
                                    }
                                }
                            ?>
                            <tr class="order-row <?php echo $is_overdue ? 'overdue-row' : ''; ?>" data-status="<?php echo ucfirst(strtolower($row['Order_Status'])); ?>"
                                <?php if ($is_overdue): ?>title="⚠️ This order has been <?php echo $current_status; ?> for more than 3 days"<?php endif; ?>>
                                <td class="fw-bold">ODR<?php echo htmlspecialchars($row['Order_Tracking_Num']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['User_Name']); ?></div>
                                    <div class="small text-muted"><?php echo $row['User_Email']; ?></div>
                                </td>
                                <td><?php echo $display_date; ?></td> <td><?php echo $display_time; ?></td> 
                                <td>
                                    <div class="dropdown">
                                        <?php 
                                        // 如果当前状态是 Canceled 或 Issue，它就不在正常流程里，禁止显示下拉菜单
                                        $is_last_status = ($current_index === false || $current_index === count($status_flow) - 1);
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
                                    <div class="d-inline-flex gap-2">
                                        <button onclick="showItemPopup('<?php echo $row['Order_Id']; ?>', '<?php echo $row['Order_Tracking_Num']; ?>')" class="btn btn-sm btn-outline-dark rounded-pill px-3">Details</button>
                                        
                                        <?php if ($row['Order_Status'] === 'Issue'): ?>
                                            <button onclick="resolveIssuePopup('<?php echo $row['Order_Id']; ?>')" class="btn btn-sm btn-outline-warning rounded-circle d-flex align-items-center justify-content-center text-dark" style="width: 32px; height: 32px; border-width: 2px;" title="Resolve Issue">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                            </button>
                                        <?php elseif ($row['Order_Status'] !== 'Canceled' && $row['Order_Status'] !== 'Delivered'): ?>
                                            <button onclick="showIssuePopup('<?php echo $row['Order_Id']; ?>')" class="btn btn-sm btn-outline-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Report Issue / Cancel">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
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

function showItemPopup(orderId, trackingNum) {
    const displayId = trackingNum && trackingNum.trim() !== '' ? trackingNum : ('OD-' + String(orderId).padStart(5, '0'));
    Swal.fire({
        title: 'Order Items (ID: ODR' + displayId + ')',
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

    // 3. 过滤行 (加入对 All 和 Exception 的特殊处理)
    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        
        if (status === 'All') {
            // 选择 All 时，显示所有行
            row.style.display = '';
        } else if (status === 'Exception') {
            // 选择 Exception 时，显示 Issue 和 Canceled 的订单
            if (rowStatus === 'Issue' || rowStatus === 'Canceled') {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        } else {
            // 普通状态 (Pending, Processing, Shipped, Delivered) 的精确匹配
            if (rowStatus === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// ============================================
// 异常与取消订单处理 (Issue & Canceled)
// ============================================

function showIssuePopup(orderId) {
    Swal.fire({
        title: '<i class="bi bi-exclamation-triangle-fill text-danger fs-3 mb-2 d-block"></i> Exception Handling',
        html: `
            <div class="text-start mt-3">
                <label class="form-label fw-bold small text-muted">Select Action Status:</label>
                <select id="issueStatus" class="form-select mb-3 border-danger shadow-sm">
                    <option value="Issue">Flag as Issue</option>
                    <option value="Canceled">Cancel Order</option>
                </select>
                
                <label class="form-label fw-bold small text-muted">Select Reason:</label>
                <select id="issueReason" class="form-select mb-3 shadow-sm" onchange="toggleOtherReason()">
                    <option value="Out of stock">Out of stock</option>
                    <option value="Customer requested cancellation">Customer requested cancellation</option>
                    <option value="Payment verification failed">Payment verification failed</option>
                    <option value="Invalid shipping address">Invalid shipping address</option>
                    <option value="Suspected fraudulent order">Suspected fraudulent order</option>
                    <option value="Logistics partner rejection">Logistics partner rejection</option>
                    <option value="Other">Other (Type your own)</option>
                </select>
                
                <input type="text" id="otherReason" class="form-control shadow-sm border-warning" placeholder="Please specify the reason..." style="display: none;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Confirm Update',
        cancelButtonText: 'Close',
        confirmButtonColor: '#dc3545',
        preConfirm: () => {
            const status = document.getElementById('issueStatus').value;
            const reasonSelect = document.getElementById('issueReason').value;
            const otherReason = document.getElementById('otherReason').value;
            
            let finalReason = reasonSelect;
            if (reasonSelect === 'Other') {
                if (!otherReason.trim()) {
                    Swal.showValidationMessage('Please type the custom reason');
                    return false;
                }
                finalReason = otherReason.trim();
            }
            return { status: status, reason: finalReason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            submitIssueUpdate(orderId, result.value.status, result.value.reason);
        }
    });
}

// 切换显示“其他”输入框
function toggleOtherReason() {
    const select = document.getElementById('issueReason');
    const input = document.getElementById('otherReason');
    if (select.value === 'Other') {
        input.style.display = 'block';
        input.focus();
    } else {
        input.style.display = 'none';
        input.value = '';
    }
}

// 提交异常状态到后台
function submitIssueUpdate(orderId, status, reason) {
    Swal.fire({
        title: 'Updating System...',
        text: 'Recording reason and notifying customer...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    let formData = new FormData();
    formData.append('update_status', true);
    formData.append('order_id', orderId);
    formData.append('new_status', status); // 会是 'Canceled' 或 'Issue'
    formData.append('reason', reason);     // 附带你选择或填写的 Reason

    fetch('admin_manage_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: status + ' Processed',
                text: 'The order status and reason have been updated.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'Failed to update database.', 'error');
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'Server response failed. Please check your PHP logs.', 'error');
    });
}

// ============================================
// 处理已标记为 Issue 的订单 (恢复 Process 或 Cancel)
// ============================================
function resolveIssuePopup(orderId) {
    Swal.fire({
        title: '<i class="bi bi-exclamation-triangle text-warning fs-1 d-block mb-2"></i> Resolve Issue',
        text: "This order is currently on hold due to an Issue. How would you like to proceed?",
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Continue Process',
        denyButtonText: 'Cancel Order',
        cancelButtonText: 'Close',
        confirmButtonColor: '#10b981', // 绿色 (恢复进行)
        denyButtonColor: '#dc3545',    // 红色 (直接取消)
        customClass: {
            actions: 'flex-wrap gap-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // 用户点击了 Continue Process
            // 直接复用之前的提交函数，状态改为 Processing，附带一条自动生成的 Reason 记录
            submitIssueUpdate(orderId, 'Processing', 'Issue Resolved - Resumed Processing');
            
        } else if (result.isDenied) {
            // 用户点击了 Cancel Order
            // 状态改为 Canceled
            submitIssueUpdate(orderId, 'Canceled', 'Issue Unresolved - Order Canceled');
        }
    });
}

</script>
</body>
</html>