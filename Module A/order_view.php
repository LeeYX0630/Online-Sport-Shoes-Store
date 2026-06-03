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

// Fixed MySQL reserved words conflict by wrapping tables in backticks ``
$stmt = $conn->prepare("
    SELECT o.*, u.`User_Name` 
    FROM `order` o 
    JOIN `user` u ON o.`User_Id` = u.`User_Id` 
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

// Fetching order line details safely with backticks
$detail_stmt = $conn->prepare(
    "SELECT od.`Order_Qty`, od.`Order_Subtotal`, p.`Pro_Name`, p.`Pro_Image`, p.`Pro_Price`, b.`Brand_Name`
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
    if (!empty($item['Pro_Image'])) {
        $path_parts = pathinfo($item['Pro_Image']);
        $filename = $path_parts['filename'];
        $found_images = glob('../uploads/' . $filename . '*.*');
        if (!empty($found_images)) {
            $image_path = $found_images[0];
        }
    }

    $line_items[] = [
        'brand' => $item['Brand_Name'] ?? 'SS Sport',
        'name' => $item['Pro_Name'] ?? 'Unknown Shoe',
        'image' => $image_path,
        'qty' => $qty,
        'unit_price' => $unit_price,
        'line_total' => $line_total,
    ];
}

$order_total = floatval($order['Order_Amount'] ?? 0);
$adjustment = round($order_total - $products_total, 2);
$payment_method = !empty($order['Payment_Method']) ? $order['Payment_Method'] : 'Online Payment';
$payment_status = !empty($order['Payment_Status']) ? strtoupper($order['Payment_Status']) : 'PENDING';

// Smart deduplication: structural string cleaning if the username is appended into the address string
$shipping_address = !empty($order['Order_Shipping_Addr']) ? trim($order['Order_Shipping_Addr']) : 'Address not available';
if (stripos($shipping_address, $customer_name) === 0) {
    $shipping_address = ltrim(substr($shipping_address, strlen($customer_name)), " ,，\t\n\r\0\x0B");
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

$order_status = normalizeOrderStatus($order['Order_Status'] ?? 'Pending');
$order_status_step = getOrderStatusStepLevel($order_status);
$order_status_progress = ($order_status_step - 1) * 33.3333333;

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
        /* ==========================================
           1. 全局流云画布背景与毛玻璃变量
           ========================================== */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9 !important; /* 稍深底色以完美衬托白砂玻璃 */
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* 统一的 STRYDEX 品牌流云微光气泡 */
        body::before, body::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            filter: blur(140px);
            opacity: 0.35;
            z-index: 0;
            pointer-events: none;
            animation: cloudFloat 14s infinite alternate ease-in-out;
        }
        /* 气泡 1：STRYDEX 核心暖橙 */
        body::before {
            width: 500px;
            height: 500px;
            background: #ff6600;
            top: -10%;
            right: -5%;
        }
        /* 气泡 2：赛博深海蓝（提供高端冷暖渐变对比） */
        body::after {
            width: 550px;
            height: 550px;
            background: #3b82f6;
            bottom: 5%;
            left: -10%;
            animation-delay: 2.5s;
        }

        @keyframes cloudFloat {
            0% { transform: translate(0px, 0px) scale(1); }
            100% { transform: translate(50px, -40px) scale(1.1); }
        }

        /* 确保页面核心容器浮在云雾之上 */
        .order-container {
            max-width: 850px;
            margin: 40px auto 80px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .action-bar {
            margin-bottom: 24px;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .back-btn:hover {
            color: #ff6600;
            transform: translateX(-3px);
        }

        /* ==========================================
           2. 核心卡片升级：悬浮半透明磨砂玻璃
           ========================================== */
        .tracker-card, .all-in-one-receipt {
            background: rgba(255, 255, 255, 0.72) !important; /* 半透明白 */
            backdrop-filter: blur(25px) saturate(160%) !important; /* 强效毛玻璃 */
            -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
            border: 1px solid rgba(255, 255, 255, 0.7) !important; /* 晶莹高光边框 */
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.04) !important; /* 柔和空气感阴影 */
        }

        /* 进度条卡片微调 */
        .tracker-card {
            border-radius: 20px; /* 稍微放大圆角提升现代感 */
            padding: 32px;
            margin-bottom: 28px;
        }
        .tracker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .tracker-status {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
        }
        .tracker-subtext {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
        }

        /* 状态步进器微光处理 */
        .status-stepper {
            position: relative;
            display: flex;
            align-items: center;
            margin-top: 24px;
            width: 100%;
            justify-content: space-between;
        }
        .status-stepper .step {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.08);
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        .status-stepper .step.active {
            background: #ff6600; 
            box-shadow: 0 0 0 6px rgba(255, 102, 0, 0.25);
        }
        .status-stepper::before, .status-progress-fill {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            height: 4px;
            transform: translateY(-50%);
            border-radius: 2px;
        }
        .status-stepper::before {
            right: 0;
            background: rgba(15, 23, 42, 0.05);
            z-index: 0;
        }
        .status-progress-fill {
            background: #ff6600; 
            z-index: 0;
        }
        .tracker-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 0.8rem;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        .tracker-labels span {
            width: 100%;
            text-align: center;
        }

        /* 收据主卡片微调 */
        .all-in-one-receipt {
            border-radius: 20px; /* 统一改为大圆角 */
            padding: 40px;
        }

        .receipt-top-profile {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .company-address-info {
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.6;
        }
        .company-address-info strong {
            font-size: 1.1rem;
            color: #0f172a;
        }

        .receipt-main-title {
            text-align: right;
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(15, 23, 42, 0.06);
            padding-bottom: 15px;
        }

        .receipt-meta-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
            margin-bottom: 35px;
        }
        .billed-to-box h4 {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #0f172a;
            font-weight: 700;
            margin: 0 0 8px 0;
            letter-spacing: 0.05em;
        }
        .billed-to-box p {
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.6;
            color: #334155;
        }
        .billed-to-box strong {
            color: #0f172a;
            font-size: 1.05rem;
        }

        .receipt-numbers-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
            min-width: 240px;
        }
        .receipt-num-row {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .receipt-num-row span {
            font-weight: 700;
            color: #0f172a;
            margin-right: 15px;
        }
        .receipt-num-row var {
            font-style: normal;
            color: #334155;
            font-weight: 500;
            text-align: right;
        }

        /* ==========================================
           3. 表格与财务明细毛玻璃适配
           ========================================== */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.3); /* 让表格层略微透光 */
            border-radius: 12px;
            overflow: hidden;
        }
        .receipt-table th {
            background-color: #ff6600; 
            color: #ffffff;            
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 14px 16px;
            text-align: left;
            letter-spacing: 0.05em;
        }
        .receipt-table th.num-col, .receipt-table td.num-col {
            text-align: right;
        }
        .receipt-table th.qty-col, .receipt-table td.qty-col {
            text-align: center;
            width: 70px;
        }
        .receipt-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05); /* 极淡透明边框替代死板灰色 */
            font-size: 0.95rem;
            color: #0f172a;
            vertical-align: middle;
        }
        .item-description-wrapper {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .item-thumbnail {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            background: #f8fafc;
        }
        .item-details-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .item-brand {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .item-name {
            font-weight: 600;
            color: #0f172a;
        }

        .receipt-financials-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }
        .financials-box {
            width: 360px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            font-size: 0.95rem;
            color: #334155;
            background: rgba(255, 255, 255, 0.4); /* 财务模块独立微光分块 */
            padding: 20px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .financial-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .financial-row label {
            font-weight: 500;
            color: #475569;
            text-align: left;
            flex-shrink: 0;
        }
        .financial-row label.strong-label {
            font-weight: 700;
            color: #0f172a;
        }
        .financial-row label.subtotal-label {
            font-weight: 700; 
            color: #0f172a;
        }
        .financial-row span {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
        .financial-row span.subtotal-value {
            font-weight: 800; 
            color: #0f172a;
        }
        
        .receipt-divider {
            border-top: 1px dashed rgba(15, 23, 42, 0.1);
            margin: 4px 0;
            width: 100%;
        }

        .grand-total-row {
            display: flex;
            justify-content: space-between;
            border-top: 2px solid #0f172a; 
            padding-top: 14px;
            margin-top: 6px;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            width: 100%;
        }

        .receipt-footer-notes {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.6;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
            padding-top: 20px;
        }
        .receipt-footer-notes strong {
            color: #0f172a;
            display: block;
            margin-bottom: 4px;
        }

        /* 移动端响应式完美自适应 */
        @media (max-width: 600px) {
            .receipt-meta-container, .receipt-top-profile {
                flex-direction: column;
                gap: 20px;
            }
            .receipt-main-title, .receipt-numbers-box {
                text-align: left;
                align-items: flex-start;
            }
            .all-in-one-receipt {
                padding: 24px;
            }
            .item-thumbnail {
                display: none;
            }
            .financials-box {
                width: 100%;
            }
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
                <div class="tracker-status" style="font-size: 1rem; opacity: 0.75;">Ref: <?php echo htmlspecialchars($tracking_number); ?></div>
            </div>
            <div class="status-stepper">
                <span class="status-progress-fill" style="width: <?php echo $order_status_progress; ?>%;"></span>
                <div class="step <?php echo $order_status_step >= 1 ? 'active' : ''; ?>" title="Pending"></div>
                <div class="step <?php echo $order_status_step >= 2 ? 'active' : ''; ?>" title="Processing"></div>
                <div class="step <?php echo $order_status_step >= 3 ? 'active' : ''; ?>" title="Shipped"></div>
                <div class="step <?php echo $order_status_step >= 4 ? 'active' : ''; ?>" title="Delivered"></div>
            </div>
            <div class="tracker-labels">
                <span>Pending</span>
                <span>Processing</span>
                <span>Shipped</span>
                <span>Delivered</span>
            </div>
        </div>

        <div class="all-in-one-receipt">
            
            <div class="receipt-top-profile">
                <div class="company-address-info">
                    <strong>STRYDEX STORE</strong><br>
                    Multimedia University, Melaka<br>
                    sportshoes.system@gmail.com<br>
                    +60 12-345 6789
                </div>
            </div>

            <div class="receipt-main-title">Order Receipt</div>

            <div class="receipt-meta-container">
                <div class="billed-to-box">
                    <h4>Billed To</h4>
                    <p>
                        <strong><?php echo htmlspecialchars($customer_name); ?></strong><br>
                        <?php echo nl2br(htmlspecialchars($shipping_address)); ?>
                    </p>
                </div>
                
                <div class="receipt-numbers-box">
                    <div class="receipt-num-row">
                        <span>Receipt #</span>
                        <var><?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></var>
                    </div>
                    <div class="receipt-num-row">
                        <span>Receipt Date</span>
                        <var><?php echo htmlspecialchars($order_date); ?></var>
                    </div>
                </div>
            </div>

            <table class="receipt-table">
                <thead>
                    <tr>
                        <th class="qty-col">QTY</th>
                        <th>Description</th>
                        <th class="num-col">Unit Price</th>
                        <th class="num-col">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($line_items)): ?>
                        <?php foreach ($line_items as $item): ?>
                            <tr>
                                <td class="qty-col"><?php echo (int)$item['qty']; ?></td>
                                <td>
                                    <div class="item-description-wrapper">
                                        <img class="item-thumbnail" src="<?php echo htmlspecialchars($item['image']); ?>" alt="" onerror="this.src='../images/brands/placeholder.png'">
                                        <div class="item-details-text">
                                            <span class="item-brand"><?php echo htmlspecialchars($item['brand']); ?></span>
                                            <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="num-col">RM <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="num-col">RM <?php echo number_format($item['line_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">
                                No item records linked to this invoice.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="receipt-financials-section">
                <div class="financials-box">
                    <div class="financial-row">
                        <label class="strong-label">Payment Method :</label>
                        <span><?php echo htmlspecialchars($payment_method); ?></span>
                    </div>

                    <div class="financial-row">
                        <label class="strong-label">Payment Status :</label>
                        <span style="color: <?php echo $payment_status === 'PAID' ? '#16a34a' : '#d97706'; ?>;">
                            <?php echo htmlspecialchars($payment_status); ?>
                        </span>
                    </div>

                    <div class="receipt-divider"></div>

                    <div class="financial-row">
                        <label class="subtotal-label">Subtotal</label>
                        <span class="subtotal-value">RM <?php echo number_format($products_total, 2); ?></span>
                    </div>
                    
                    <?php if (abs($adjustment) >= 0.01): ?>
                        <div class="financial-row">
                            <label>Shipping / Adjustments</label>
                            <span>RM <?php echo number_format($adjustment, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grand-total-row">
                        <span>Total (MYR)</span>
                        <span>RM <?php echo number_format($order_total, 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="receipt-footer-notes">
                <strong>Notes & Information</strong>
                Thank you for shopping with STRYDEX STORE! All sales are processed safely through our secure order system. Please keep this receipt reference <strong><?php echo htmlspecialchars($tracking_number); ?></strong> handy for your warranty or return records.
            </div>

            <div class="receipt-actions text-end mt-4">
                <button type="button" onclick="downloadReceiptPDF()" class="btn btn-dark px-4 py-2">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Print / Download Receipt
                </button>
            </div>

        </div>
        
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function downloadReceiptPDF() {
            const element = document.querySelector('.all-in-one-receipt');
            const options = {
                margin:       [10, 10, 10, 10],
                filename:     'Receipt_Order_#<?php echo $order_id; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(options).from(element).save();
        }
    </script>
</body>
</html>