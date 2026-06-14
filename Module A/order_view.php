<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    header('Location: user_dashboard.php');
    exit;
}

// ==========================================================================
// 【核心修改】：完全匹配你的真实表名 review_and_rating 以及真实字段名
// ==========================================================================
$review_success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 5;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    $review_image_path = null;

    // 处理图片上传逻辑
    if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['review_image']['tmp_name'];
        $file_name = $_FILES['review_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            // 创建存储评价图片的文件夹
            $upload_dir = '../uploads/reviews/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // 重新命名图片，防止名字重复
            $new_file_name = 'rev_' . time() . '_' . uniqid() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $review_image_path = 'uploads/reviews/' . $new_file_name; // 数据库里存入相对路径
            }
        } else {
            $error_message = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
        }
    }
    
    if (empty($error_message) && $rating >= 1 && $rating <= 5) {
        // 匹配你的真实字段：RR_Date, Rev_Content, User_Id, Order_Id, Rat_Star, Rev_Image
        $review_stmt = $conn->prepare("INSERT INTO `review_and_rating` (`RR_Date`, `Rev_Content`, `User_Id`, `Order_Id`, `Rat_Star`, `Rev_Image`) VALUES (NOW(), ?, ?, ?, ?, ?)");
        if ($review_stmt) {
            $review_stmt->bind_param('siiis', $comments, $user_id, $order_id, $rating, $review_image_path);
            if ($review_stmt->execute()) {
                $review_success = true;
            } else {
                $error_message = 'Database error: Unable to save review.';
            }
            $review_stmt->close();
        }
    }
}

// 1. 获取订单主表及物流信息
$stmt = $conn->prepare("
    SELECT o.*, u.`User_Name`, u.`User_Email`, u.`User_Phone`,
           s.`Ship_Tracking_num`, s.`Estimated_Arrival_Date`, s.`Shipped_Date`, s.`Delivered_Date`
    FROM `order` o 
    JOIN `user` u ON o.`User_Id` = u.`User_Id`
    LEFT JOIN `shipment` s ON o.`Order_Id` = s.`Order_Id`
    WHERE o.`Order_Id` = ? AND o.`User_Id` = ?
");
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: user_dashboard.php');
    exit;
}

$customer_name = !empty($order['User_Name']) ? trim($order['User_Name']) : 'Customer';

// 2. 获取订单详情（商品列表）
$detail_stmt = $conn->prepare(
    "SELECT od.`Pro_Id`, od.`Order_Qty`, od.`Order_Subtotal`, od.`Pro_Colour`, od.`Custom_Preview`, p.`Pro_Name`, p.`Pro_Image`, p.`Pro_Price`, b.`Brand_Name`
     FROM `order_detail` od
     JOIN `product` p ON od.`Pro_Id` = p.`Pro_Id`
     LEFT JOIN `brand` b ON p.`Brand_Id` = b.`Brand_Id`
     WHERE od.`Order_Id` = ?"
);
$detail_stmt->bind_param('i', $order_id);
$detail_stmt->execute();
$details = $detail_stmt->get_result();

$products_total = 0.0;
$line_items = [];

while ($item = $details->fetch_assoc()) {
    $line_total = floatval($item['Order_Subtotal'] ?? 0);
    $unit_price = floatval($item['Pro_Price'] ?? 0);
    $qty = intval($item['Order_Qty'] ?? 0);

    if ($line_total <= 0 && $qty > 0 && $unit_price > 0) {
        $line_total = $unit_price * $qty;
    }

    $products_total += $line_total;
    $image_path = '../images/brands/placeholder.png';

    $item_color = $item['Pro_Colour'] ?? ($item['color'] ?? '');
    $custom_preview = $item['Custom_Preview'] ?? ($item['custom_preview'] ?? '');
    
    if (strcasecmp(trim($item_color), 'Custom Design') === 0 && !empty($custom_preview)) {
        $image_path = $custom_preview;
    } elseif (!empty($item['Pro_Image'])) {
        $path_parts = pathinfo($item['Pro_Image']);
        $filename = $path_parts['filename'];
        $found_images = glob('../uploads/' . $filename . '*.*');
        if (!empty($found_images)) {
            $image_path = $found_images[0];
        }
    }

    $line_items[] = [
        'pro_id' => $item['Pro_Id'],
        'brand' => $item['Brand_Name'] ?? 'STRYDEX Sport',
        'name' => $item['Pro_Name'] ?? 'Unknown Shoe',
        'image' => $image_path,
        'qty' => $qty,
        'unit_price' => $unit_price,
        'line_total' => $line_total,
    ];
}

$order_total = floatval($order['Order_Amount'] ?? 0);
$payment_method = !empty($order['Payment_Method']) ? $order['Payment_Method'] : 'Online Payment';
$payment_status = !empty($order['Payment_Status']) ? strtoupper($order['Payment_Status']) : 'PENDING';

// 3. Promo Code 逻辑兼容
$promo_amount = 0.00;
$promo_id = intval($order['Promo_Id'] ?? 0);
$is_percentage = false;
$promo_info = null;

if ($promo_id > 0) {
    $promo_row = $conn->query("SELECT Promo_Value, Promo_Type, Promo_Code FROM promo WHERE Promo_Id = $promo_id")->fetch_assoc();
    if ($promo_row) {
        $promo_info = $promo_row;
        if (strcasecmp($promo_row['Promo_Type'], 'Percentage') === 0) {
            $is_percentage = true;
            $promo_pct = floatval($promo_row['Promo_Value']);
            $promo_amount = round($products_total * ($promo_pct / 100), 2);
        } else {
            $promo_amount = floatval($promo_row['Promo_Value']);
        }
    }
}

// 4. 地址过滤处理
$shipping_address = !empty($order['Order_Shipping_Addr']) ? trim($order['Order_Shipping_Addr']) : 'Address not available';
if (!empty($shipping_address)) {
    $shipping_address = preg_replace('/^[^,，:：\-]+[\s,，:：\-]+/u', '', $shipping_address);
    if (!empty($customer_name)) {
        $shipping_address = preg_replace('/^' . preg_quote($customer_name, '/') . '\s*/iu', '', $shipping_address);
    }
    $shipping_address = trim($shipping_address);
}

$order_date = !empty($order['Order_Date']) ? date('d-m-Y', strtotime($order['Order_Date'])) : 'N/A';
$tracking_number = !empty($order['Order_Tracking_Num']) ? $order['Order_Tracking_Num'] : 'Not available';

function normalizeOrderStatus(string $status): string {
    $status = trim($status);
    if (strcasecmp($status, 'Shipping') === 0) { return 'Shipped'; }
    if (strcasecmp($status, 'Complete') === 0) { return 'Delivered'; }
    if (strcasecmp($status, 'Delivered') === 0) { return 'Delivered'; }
    if (strcasecmp($status, 'Shipped') === 0) { return 'Shipped'; }
    if (strcasecmp($status, 'Processing') === 0) { return 'Processing'; }
    return 'Pending';
}

function getOrderStatusStepLevel(string $status): int {
    switch (normalizeOrderStatus($status)) {
        case 'Delivered': return 4;
        case 'Shipped': return 3;
        case 'Processing': return 2;
        case 'Pending': default: return 1;
    }
}

function format_nullable_datetime($dt, $fmt = 'd M Y, h:i A') {
    if (empty($dt) || $dt === '0000-00-00 00:00:00') return '--';
    $ts = strtotime($dt);
    if ($ts === false || $ts <= 0) return '--';
    return date($fmt, $ts);
}

$order_status = normalizeOrderStatus($order['Order_Status'] ?? 'Pending');
$order_status_step = getOrderStatusStepLevel($order_status);
$order_status_progress = ($order_status_step - 1) * 33.3333333;

$estimated_arrival = !empty($order['Estimated_Arrival_Date']) ? date('d M Y', strtotime($order['Estimated_Arrival_Date'])) : 'Pending Date';
$shipped_date_formatted = format_nullable_datetime($order['Shipped_Date'] ?? null, 'd M Y, H:i');
$delivered_date_formatted = format_nullable_datetime($order['Delivered_Date'] ?? null, 'd M Y, H:i');

// ==========================================================================
// 【核心修改】：匹配你的真实表名和真实字段，获取全部人公开的评论列表
// ==========================================================================
$all_reviews_query = "
    SELECT r.*, u.`User_Name` 
    FROM `review_and_rating` r 
    JOIN `user` u ON r.`User_Id` = u.`User_Id` 
    ORDER BY r.`RR_Date` DESC
";
$all_reviews_result = $conn->query($all_reviews_query);

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | STRYDEX STORE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif; background-color: #f1f5f9 !important; color: #1e293b;
            -webkit-font-smoothing: antialiased; position: relative; min-height: 100vh; overflow-x: hidden;
        }
        body::before, body::after {
            content: ""; position: absolute; border-radius: 50%; filter: blur(140px); opacity: 0.35; z-index: 0; pointer-events: none;
            animation: cloudFloat 14s infinite alternate ease-in-out;
        }
        body::before { width: 500px; height: 500px; background: #ff6600; top: -10%; right: -5%; }
        body::after { width: 550px; height: 550px; background: #3b82f6; bottom: 5%; left: -10%; animation-delay: 2.5s; }

        @keyframes cloudFloat {
            0% { transform: translate(0px, 0px) scale(1); }
            100% { transform: translate(50px, -40px) scale(1.1); }
        }

        .order-container { max-width: 850px; margin: 40px auto 80px; padding: 0 20px; position: relative; z-index: 10; }
        .action-bar { margin-bottom: 24px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; }
        .back-btn:hover { color: #ff6600; transform: translateX(-3px); }

        .tracker-card, .all-in-one-receipt, .review-interactive-card, .community-reviews-card {
            background: rgba(255, 255, 255, 0.72) !important;
            backdrop-filter: blur(25px) saturate(160%) !important;
            -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
            border: 1px solid rgba(255, 255, 255, 0.7) !important;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.04) !important;
            border-radius: 20px; padding: 35px; margin-bottom: 28px;
        }

        .tracker-card { padding: 32px; }
        .tracker-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .tracker-status { font-size: 1.3rem; font-weight: 800; color: #0f172a; }
        .tracker-subtext { font-size: 0.85rem; color: #64748b; margin-top: 2px; }

        .status-stepper { position: relative; display: flex; align-items: center; margin-top: 24px; width: 100%; justify-content: space-between; }
        .status-stepper .step { width: 20px; height: 20px; border-radius: 50%; background: rgba(15, 23, 42, 0.08); position: relative; z-index: 1; flex-shrink: 0; }
        .status-stepper .step.active { background: #ff6600; box-shadow: 0 0 0 6px rgba(255, 102, 0, 0.25); }
        .status-stepper::before, .status-progress-fill { content: ''; position: absolute; top: 50%; left: 0; height: 4px; transform: translateY(-50%); border-radius: 2px; }
        .status-stepper::before { right: 0; background: rgba(15, 23, 42, 0.05); z-index: 0; }
        .status-progress-fill { background: #ff6600; z-index: 0; }
        
        .tracker-labels { display: flex; justify-content: space-between; margin-top: 16px; font-size: 0.8rem; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .tracker-labels span { width: 100%; text-align: center; }

        .all-in-one-receipt { padding: 40px; }
        .receipt-top-profile { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .company-address-info { font-size: 0.9rem; color: #334155; line-height: 1.6; }
        .company-address-info strong { font-size: 1.1rem; color: #0f172a; }
        .receipt-main-title { text-align: right; font-size: 1.8rem; font-weight: 800; color: #0f172a; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 30px; border-bottom: 2px solid rgba(15, 23, 42, 0.06); padding-bottom: 15px; }

        .receipt-meta-container { display: flex; justify-content: space-between; align-items: flex-start; gap: 40px; margin-bottom: 35px; }
        .billed-to-box h4 { font-size: 0.85rem; text-transform: uppercase; color: #0f172a; font-weight: 700; margin: 0 0 8px 0; letter-spacing: 0.05em; }
        .billed-to-box p { font-size: 0.95rem; margin: 0; line-height: 1.6; color: #334155; }

        .receipt-numbers-box { display: flex; flex-direction: column; gap: 10px; align-items: flex-end; min-width: 240px; }
        .receipt-num-row { display: flex; justify-content: space-between; width: 100%; font-size: 0.95rem; }
        .receipt-num-row span { font-weight: 700; color: #0f172a; }

        .receipt-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: rgba(255, 255, 255, 0.3); border-radius: 12px; overflow: hidden; }
        .receipt-table th { background-color: #ff6600; color: #ffffff; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; padding: 14px 16px; text-align: left; }
        .receipt-table th.num-col, .receipt-table td.num-col { text-align: right; }
        .receipt-table th.qty-col, .receipt-table td.qty-col { text-align: center; width: 70px; }
        .receipt-table td { padding: 16px; border-bottom: 1px solid rgba(15, 23, 42, 0.05); font-size: 0.95rem; color: #0f172a; vertical-align: middle; }
        
        .item-description-wrapper { display: flex; align-items: center; gap: 14px; }
        .item-thumbnail { width: 55px; height: 55px; object-fit: cover; border-radius: 10px; background: #f8fafc; }
        .item-details-text { display: flex; flex-direction: column; gap: 2px; }
        .item-brand { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #64748b; }
        .item-name { font-weight: 600; color: #0f172a; }

        .receipt-financials-section { display: flex; justify-content: flex-end; margin-bottom: 40px; }
        .financials-box { width: 380px; display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem; background: rgba(255, 255, 255, 0.4); padding: 20px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.5); }
        .financial-row { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        
        .receipt-divider { border-top: 1px dashed rgba(15, 23, 42, 0.1); margin: 4px 0; }
        .grand-total-row { display: flex; justify-content: space-between; border-top: 2px solid #0f172a; padding-top: 14px; font-size: 1.2rem; font-weight: 800; }

        .receipt-footer-notes { font-size: 0.85rem; color: #64748b; border-top: 1px solid rgba(15, 23, 42, 0.06); padding-top: 20px; }

        /* 高质感正序星星选择器 */
        .star-rating-wrapper { display: inline-flex; gap: 8px; margin-bottom: 20px; }
        .star-rating-wrapper i { font-size: 1.8rem; color: #cbd5e1; cursor: pointer; transition: color 0.15s ease, transform 0.1s; }
        .star-rating-wrapper i:hover { transform: scale(1.1); }
        .star-rating-wrapper i.active { color: #ffb700; }

        .comment-box-textarea {
            width: 100%; border: 1px solid rgba(15, 23, 42, 0.12); background: rgba(255, 255, 255, 0.5);
            border-radius: 12px; padding: 14px; font-size: 0.95rem; resize: none; transition: all 0.2s;
        }
        .comment-box-textarea:focus { outline: none; border-color: #ff6600; background: #ffffff; box-shadow: 0 0 0 4px rgba(255, 102, 0, 0.12); }

        .custom-file-upload {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px;
            background: #ffffff; border: 1px dashed rgba(15, 23, 42, 0.2); border-radius: 10px;
            cursor: pointer; font-size: 0.9rem; font-weight: 500; transition: all 0.2s;
        }
        .custom-file-upload:hover { border-color: #ff6600; color: #ff6600; background: rgba(255,102,0,0.02); }

        .submit-review-btn { background-color: #ff6600; color: #ffffff; font-weight: 600; padding: 10px 24px; border: none; border-radius: 10px; cursor: pointer; transition: 0.2s; }
        .submit-review-btn:hover { background-color: #e05500; }

        .success-toast-alert { background: rgba(22, 163, 74, 0.08); border: 1px solid rgba(22, 163, 74, 0.2); color: #16a34a; border-radius: 12px; padding: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        /* 公共评论列表样式 */
        .community-reviews-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 10px; }
        .review-item { padding: 16px 0; border-bottom: 1px solid rgba(15,23,42,0.06); }
        .review-item:last-child { border-bottom: none; }
        .review-user-info { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 6px; }
        .review-user-name { font-weight: 700; color: #0f172a; }
        .review-date { color: #94a3b8; font-size: 0.8rem; }
        .review-stars-static { color: #ffb700; font-size: 0.9rem; margin-bottom: 6px; }
        .review-comment-text { font-size: 0.95rem; color: #334155; line-height: 1.5; }
        .review-uploaded-img { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; margin-top: 10px; border: 1px solid #e2e8f0; cursor: pointer; }

        @media (max-width: 600px) {
            .receipt-meta-container, .receipt-top-profile { flex-direction: column; gap: 20px; }
            .all-in-one-receipt, .tracker-card, .review-interactive-card, .community-reviews-card { padding: 24px; }
            .item-thumbnail { display: none; } .financials-box { width: 100%; }
        }

        #receipt-content {
            background: white !important;
            max-width: 850px;
            margin: 20px auto 40px;
            padding: 50px;
            border: 1px solid #eee;
            border-radius: 8px;
            color: #000;
        }
        .brand-logo { color: #FF6B00; font-weight: 800; font-size: 26px; text-transform: uppercase; }
        .receipt-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .item-img { width: 60px; height: 60px; object-fit: contain; background: #f9f9f9; mix-blend-mode: multiply; border-radius: 6px; border: 1px solid #dee2e6; }
        .badge-paid { background-color: #198754; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; }
        
        @media print { 
            body { background: #fff !important; }
            .no-print, .action-bar, .tracker-card, .review-interactive-card, .community-reviews-card, header, footer { display: none !important; }
            #receipt-content { border: none !important; padding: 10px !important; margin: 0 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

    <div class="order-container">
        
        <div class="action-bar">
            <a href="user_dashboard.php" class="back-btn">
                <i class="bi bi-arrow-left-short fs-4"></i> Back to Dashboard
            </a>
        </div>

        <div class="tracker-card">
            <div class="tracker-header">
                <div>
                    <div class="tracker-status">Fulfillment: <?php echo htmlspecialchars($order_status); ?></div>
                    <div class="tracker-subtext">Order progress tracker</div>
                </div>
                <div style="text-align: right;">
                    <div class="tracker-status" style="font-size: 1rem; opacity: 0.75;">Ref: <?php echo htmlspecialchars($tracking_number); ?></div>
                    <div class="tracker-subtext" style="margin-top: 8px; font-size: 0.9rem;">Est. Arrival: <strong><?php echo htmlspecialchars($estimated_arrival); ?></strong></div>
                </div>
            </div>
            <div class="status-stepper">
                <span class="status-progress-fill" style="width: <?php echo $order_status_progress; ?>%;"></span>
                <div class="step <?php echo $order_status_step >= 1 ? 'active' : ''; ?>"></div>
                <div class="step <?php echo $order_status_step >= 2 ? 'active' : ''; ?>"></div>
                <div class="step <?php echo $order_status_step >= 3 ? 'active' : ''; ?>"></div>
                <div class="step <?php echo $order_status_step >= 4 ? 'active' : ''; ?>"></div>
            </div>
            <div class="tracker-labels">
                <span>Pending<br><small><?php echo htmlspecialchars(date('d M Y', strtotime($order['Order_Date'] ?? 'now'))); ?></small></span>
                <span>Processing<br><small><?php echo $order_status_step >= 2 ? htmlspecialchars(format_nullable_datetime($order['Order_Processing_Date'] ?? null, 'd M Y')) : '--'; ?></small></span>
                <span>Shipped<br><small><?php echo $order_status_step >= 3 ? htmlspecialchars($shipped_date_formatted) : '--'; ?></small></span>
                <span>Delivered<br><small><?php echo $order_status_step >= 4 ? htmlspecialchars($delivered_date_formatted) : '--'; ?></small></span>
            </div>
        </div>

        <div id="receipt-content" class="shadow-sm mb-5">
            <div class="receipt-header d-flex justify-content-between align-items-center">
                <div class="brand">
                    <div class="brand-logo">STRYDEX SPORT SHOES STORE</div>
                    <p class="text-muted mb-0 small">Multimedia University, Melaka, Malaysia</p>
                    <p class="text-muted mb-0 small">Email: sportshoes.system@gmail.com</p>
                </div>
                <div class="text-end">
                    <h2 class="fw-bold mb-0" style="color: #333;">OFFICIAL RECEIPT</h2>
                    <p class="mb-0 text-muted">Order ID: <strong>#<?php echo htmlspecialchars($tracking_number); ?></strong></p>
                    <p class="mb-0 text-muted">Date: <strong><?php echo htmlspecialchars($order_date); ?></strong></p>
                </div>
            </div>

            <div class="row mb-5">
                <div class="col-6">
                    <h6 class="text-uppercase fw-bold text-muted small">Billed To:</h6>
                    <p class="mb-0"><strong><?php echo htmlspecialchars($customer_name); ?></strong></p>
                    <p class="mb-0 small"><?php echo htmlspecialchars($order['User_Email'] ?? ''); ?></p>
                    <p class="mb-0 small"><?php echo htmlspecialchars($order['User_Phone'] ?? ''); ?></p>
                </div>
                <div class="col-6 text-end">
                    <h6 class="text-uppercase fw-bold text-muted small">Shipping Address:</h6>
                    <p class="mb-0 text-break small"><?php echo nl2br(htmlspecialchars($shipping_address)); ?></p>
                </div>
            </div>

            <table class="table table-borderless align-middle mb-5">
                <thead class="border-bottom">
                    <tr>
                        <th style="width: 45%;">Item Description</th>
                        <th class="text-center">Price</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line_items as $item): ?>
                    <tr class="border-bottom">
                        <td>
                            <div class="d-flex align-items-center py-2">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" class="item-img me-3" onerror="this.src='../images/brands/placeholder.png'">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="text-muted small" style="font-size: 0.75rem; text-transform: uppercase;"><?php echo htmlspecialchars($item['brand']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center text-dark">RM <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-center text-dark"><?php echo $item['qty']; ?></td>
                        <td class="text-end fw-bold text-dark">RM <?php echo number_format($item['line_total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="row justify-content-end">
                <div class="col-md-6 col-lg-5">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Payment Method:</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($payment_method); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Payment Status:</span>
                        <span class="badge-paid">PAID (<?php echo htmlspecialchars($payment_status); ?>)</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Order Status:</span>
                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($order_status); ?></span>
                    </div>
                    
                    <?php if (floatval($promo_amount) > 0.0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Promo Used:</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($promo_info['Promo_Code'] ?? 'Applied'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Promo Discount:</span>
                        <span class="fw-bold text-success">- RM <?php echo number_format($promo_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="fw-bold mb-0 text-dark">Total Paid:</h4>
                        <h4 class="fw-bold text-success mb-0">RM <?php echo number_format($order_total, 2); ?></h4>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <p class="text-muted small mb-1">This is a computer-generated receipt. No signature is required.</p>
                <p class="fw-bold text-dark">Thank you for shopping with STRYDEX STORE!</p>
            </div>
            
            <div class="text-end mt-4 no-print" id="actionBtnContainer">
                <button type="button" onclick="downloadPDF()" class="btn btn-dark px-4 py-2"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</button>
            </div>
        </div>

        <div class="review-interactive-card">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mb-3"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($review_success): ?>
                <div class="success-toast-alert">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div>
                        Thank you! Your feedback has been submitted successfully.
                        <span style="font-weight: 400; display: block; font-size: 0.85rem; color: #475569; margin-top: 2px;">Your comment and uploaded picture are now visible in the community feed below.</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="review-title"><i class="bi bi-chat-heart-fill" style="color: #ff6600;"></i> Share Your Feedback</div>
                <div class="review-subtitle">Let us know about your shopping experience or custom design quality!</div>

                <form action="" method="POST" enctype="multipart/form-data">
                    
                    <div style="margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; color: #475569;">Your Rating :</div>
                    <div class="star-rating-wrapper" id="starContainer">
                        <i class="bi bi-star-fill active" data-value="1"></i>
                        <i class="bi bi-star-fill active" data-value="2"></i>
                        <i class="bi bi-star-fill active" data-value="3"></i>
                        <i class="bi bi-star-fill active" data-value="4"></i>
                        <i class="bi bi-star-fill active" data-value="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInputValue" value="5">

                    <div class="mb-3">
                        <div style="margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; color: #475569;">Upload Photo (Optional) :</div>
                        <label for="review_image" class="custom-file-upload">
                            <i class="bi bi-camera-fill" style="color: #ff6600;"></i> <span id="file-chosen-text">Choose Image (JPG, PNG)</span>
                        </label>
                        <input type="file" name="review_image" id="review_image" accept="image/*" style="display: none;">
                    </div>

                    <div style="margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: #475569;">Comments / Suggestions :</div>
                    <textarea class="comment-box-textarea" name="comments" rows="4" placeholder="Write your thoughts here..."></textarea>

                    <div class="text-end mt-3">
                        <button type="submit" name="submit_review" class="submit-review-btn"><i class="bi bi-send-fill me-1"></i> Submit Review</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="community-reviews-card">
            <div class="community-reviews-title"><i class="bi bi-people-fill me-2" style="color: #3b82f6;"></i> Customer Reviews & Testimonials</div>
            
            <?php if ($all_reviews_result && $all_reviews_result->num_rows > 0): ?>
                <?php while ($rev = $all_reviews_result->fetch_assoc()): ?>
                    <div class="review-item">
                        <div class="review-user-info">
                            <span class="review-user-name"><?php echo htmlspecialchars($rev['User_Name']); ?></span>
                            <span class="review-date"><?php echo date('d M Y, h:i A', strtotime($rev['RR_Date'])); ?></span>
                        </div>
                        
                        <div class="review-stars-static">
                            <?php 
                            $stars_count = intval($rev['Rat_Star']);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $stars_count) {
                                    echo '<i class="bi bi-star-fill"></i> ';
                                } else {
                                    echo '<i class="bi bi-star" style="color: #cbd5e1;"></i> ';
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="review-comment-text">
                            <?php echo !empty($rev['Rev_Content']) ? nl2br(htmlspecialchars($rev['Rev_Content'])) : '<em style="color: #94a3b8;">No verbal comment left.</em>'; ?>
                        </div>

                        <?php if (!empty($rev['Rev_Image'])): ?>
                            <div>
                                <img src="../<?php echo htmlspecialchars($rev['Rev_Image']); ?>" class="review-uploaded-img" alt="Review Photo" onclick="window.open(this.src)">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; color: #94a3b8; padding: 20px 0;">
                    <i class="bi bi-chat-left-dots fs-2 d-block mb-2"></i>
                    No reviews published yet. Be the first to leave one!
                </div>
            <?php endif; ?>
        </div>
        
    </div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const stars = document.querySelectorAll("#starContainer i");
        const ratingInput = document.getElementById("ratingInputValue");
        
        stars.forEach(star => {
            star.addEventListener("click", function() {
                const clickValue = parseInt(this.getAttribute("data-value"));
                ratingInput.value = clickValue;
                
                stars.forEach(s => {
                    const starValue = parseInt(s.getAttribute("data-value"));
                    if (starValue <= clickValue) {
                        s.classList.add("active");
                    } else {
                        s.classList.remove("active");
                    }
                });
            });
        });

        const fileInput = document.getElementById("review_image");
        const fileText = document.getElementById("file-chosen-text");
        if (fileInput) {
            fileInput.addEventListener("change", function() {
                if (this.files && this.files.length > 0) {
                    fileText.textContent = "✓ " + this.files[0].name;
                    fileText.style.color = "#16a34a";
                } else {
                    fileText.textContent = "Choose Image (JPG, PNG)";
                    fileText.style.color = "";
                }
            });
        }
    });

    function downloadPDF() {
    const element = document.getElementById('receipt-content');
    
    // 1. 隐藏收据内部的“Download PDF”按钮，防止被印进 PDF
    const actionBtn = document.getElementById('actionBtnContainer');
    if (actionBtn) actionBtn.style.display = 'none';

    // 2. 核心修正：将收据临时移动到 body 根节点，脱离毛玻璃/渐变等复杂父级样式
    const originalParent = element.parentNode;
    const nextSibling = element.nextSibling;
    document.body.appendChild(element);

    const options = {
        margin:       [10, 10, 10, 10],
        filename:     'Receipt_Order_#<?php echo $order_id; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // 3. 执行 html2pdf 转换
    html2pdf().set(options).from(element).save().then(() => {
        // 4. 下载完成后还原：把收据移回原来的 HTML 位置
        if (nextSibling) {
            originalParent.insertBefore(element, nextSibling);
        } else {
            originalParent.appendChild(element);
        }
        // 5. 重新显示下载按钮
        if (actionBtn) actionBtn.style.display = 'block';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>