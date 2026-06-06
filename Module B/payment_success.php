<?php
// module_b/payment_success.php
session_start();
require_once '../includes/db_connection.php';

// 设置时区为马来西亚
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$uid = $_SESSION['user_id'];

// 1. 获取订单总表信息
$sql_order = "SELECT o.*, u.User_Name, u.User_Email, u.User_Phone 
              FROM `ORDER` o 
              JOIN USER u ON o.User_Id = u.User_Id
              WHERE o.Order_Id = '$order_id' AND o.User_Id = '$uid'";
$res_order = $conn->query($sql_order);

if ($res_order->num_rows == 0) {
    die("Order not found.");
}
$order = $res_order->fetch_assoc();

// 2. 获取订单详情
$sql_details = "SELECT od.*, p.Pro_Name, p.Pro_Price, p.Pro_Image, od.Custom_Preview 
                FROM ORDER_DETAIL od 
                JOIN product p ON od.Pro_Id = p.Pro_Id 
                WHERE od.Order_Id = '$order_id'";
$res_details = $conn->query($sql_details);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Order #<?php echo $order_id; ?></title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        #receipt-content {
            background: white;
            max-width: 850px;
            margin: 20px auto;
            padding: 50px;
            border: 1px solid #eee;
            border-radius: 8px;
        }
        .brand-logo { color: #FF6B00; font-weight: 800; font-size: 26px; text-transform: uppercase; }
        .receipt-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .item-img { width: 60px; height: 60px; object-fit: contain; background: #f9f9f9; mix-blend-mode: multiply; }
        .badge-paid { background-color: #198754; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; }
        
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="container py-5">
    
    <div class="text-center mb-5 no-print">
        <div class="display-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
        <h1 class="fw-bold">Payment Successful!</h1>
        <p class="text-muted">Thank you for your purchase. Your order is now being processed.</p>
        
        <div class="mt-4">
            <button onclick="downloadPDF()" class="btn btn-dark px-4 py-2 me-2">
                <i class="bi bi-file-earmark-pdf me-2"></i>Download Receipt (PDF)
            </button>
            <a href="catalogue.php" class="btn btn-outline-secondary px-4 py-2">Back to Shopping</a>
        </div>
    </div>

    <div id="receipt-content" class="shadow-sm">
        
        <div class="receipt-header d-flex justify-content-between align-items-center">
            <div class="brand">
                <div class="brand-logo">STRYDEX SPORT SHOES STORE</div>
                <p class="text-muted mb-0 small">Multimedia University, Melaka, Malaysia</p>
                <p class="text-muted mb-0 small">Email: sportshoes.system@gmail.com</p>
            </div>
            <div class="text-end">
                <h2 class="fw-bold mb-0">OFFICIAL RECEIPT</h2>
                <p class="mb-0 text-muted">Order ID: <strong>#<?php echo $order['Order_Tracking_Num']; ?></strong></p>
                <p class="mb-0 text-muted">Date: <strong><?php echo date('d M Y, h:i A', strtotime($order['Order_Date'])); ?></strong></p>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <h6 class="text-uppercase fw-bold text-muted small">Billed To:</h6>
                <p class="mb-0"><strong><?php echo htmlspecialchars($order['User_Name']); ?></strong></p>
                <p class="mb-0 small"><?php echo htmlspecialchars($order['User_Email']); ?></p>
                <p class="mb-0 small"><?php echo htmlspecialchars($order['User_Phone']); ?></p>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase fw-bold text-muted small">Shipping Address:</h6>
                <?php 
                    $display_addr = $order['Order_Shipping_Addr'];
                    $display_addr = preg_replace('/\. Tel:.*$/i', '', $display_addr);
                ?>
                <p class="mb-0 text-break small"><?php echo nl2br(htmlspecialchars($display_addr)); ?></p>
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
                <?php while($item = $res_details->fetch_assoc()): ?>
                <tr class="border-bottom">
                    <td>
                        <div class="d-flex align-items-center py-2">
                            <?php 
                                if (!empty($item['Custom_Preview'])) {
                                    $display_img = $item['Custom_Preview'];
                                } else {
                                    $base_img = $item['Pro_Image'];
                                    $path_parts = pathinfo($base_img);
                                    $base_name = preg_replace('/_\d+$/', '', $path_parts['filename']);
                                    $found_files = glob("../uploads/{$base_name}*.*");
                                    $display_img = (!empty($found_files)) ? $found_files[0] : "../images/placeholder.png";
                                }
                            ?>
                            <img src="<?php echo $display_img; ?>" class="item-img me-3 rounded border" onerror="this.src='../images/placeholder.png'">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($item['Pro_Name']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">RM <?php echo number_format($item['Order_Subtotal'] / $item['Order_Qty'], 2); ?></td>
                    <td class="text-center"><?php echo $item['Order_Qty']; ?></td>
                    <td class="text-end fw-bold">RM <?php echo number_format($item['Order_Subtotal'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="row justify-content-end">
            <div class="col-md-5">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Payment Status:</span>
                    <span class="badge-paid">PAID (<?php echo strtoupper($order['Payment_Status']); ?>)</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Order Status:</span>
                    <span class="fw-bold text-primary"><?php echo $order['Order_Status']; ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold">Total Paid:</h4>
                    <h4 class="fw-bold text-success">RM <?php echo number_format($order['Order_Amount'], 2); ?></h4>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-muted small">This is a computer-generated receipt. No signature is required.</p>
            <p class="fw-bold">Thank you for shopping with Sport Shoes Store!</p>
        </div>
    </div>
</div>

<script>
    function downloadPDF() {
        const element = document.getElementById('receipt-content');
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