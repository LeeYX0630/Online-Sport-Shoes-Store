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

$stmt = $conn->prepare("SELECT * FROM `order` WHERE Order_Id = ? AND User_Id = ?");
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: user_dashboard.php');
    exit;
}

$detail_stmt = $conn->prepare(
    "SELECT od.Order_Qty, od.Order_Subtotal, p.Pro_Name, p.Pro_Image, p.Pro_Price, b.Brand_Name
     FROM order_detail od
     JOIN product p ON od.Pro_Id = p.Pro_Id
     LEFT JOIN brand b ON p.Brand_Id = b.Brand_Id
     WHERE od.Order_Id = ?"
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
$shipping_address = !empty($order['Order_Shipping_Addr']) ? $order['Order_Shipping_Addr'] : 'Address not available';
$order_date = !empty($order['Order_Date']) ? date('d M Y, h:i A', strtotime($order['Order_Date'])) : 'N/A';
$tracking_number = !empty($order['Order_Tracking_Num']) ? $order['Order_Tracking_Num'] : 'Not available';

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | Online Sport Shoes Store</title>
    <!-- Google Fonts & Bootstrap Icons for refined look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        .order-container {
            max-width: 1200px;
            margin: 40px auto 80px;
            padding: 0 24px;
        }

        /* Top Action Bar */
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
            transition: color 0.2s ease;
        }

        .back-btn:hover {
            color: #0f172a;
        }

        /* Header Summary Card */
        .order-master-header {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 32px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 24px;
        }

        .order-title-area h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 8px 0;
            color: #0f172a;
        }

        .order-id-badge {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .order-meta-grid {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: 700;
        }

        .meta-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #334155;
        }

        /* Dynamic Badges styling based on context */
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            background: #f1f5f9;
            color: #475569;
        }

        /* Main Workspace Split Layout */
        .order-split-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .order-split-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Left Column: Products Card */
        .main-content-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-inner-header {
            padding: 24px 28px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-inner-header h2 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }

        .items-list {
            padding: 0 28px;
        }

        .product-item-row {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            padding: 24px 0;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .product-item-row:last-child {
            border-bottom: none;
        }

        .product-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .product-details {
            display: flex;
            flex-direction: column;
        }

        .brand-tag {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #3b82f6;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .product-title {
            font-weight: 600;
            font-size: 1rem;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .product-spec {
            font-size: 0.85rem;
            color: #64748b;
        }

        .product-price-final {
            font-weight: 700;
            font-size: 1.05rem;
            color: #0f172a;
            text-align: right;
        }

        /* Right Column: Invoice Overview & Shipping */
        .sidebar-sticky-panel {
            display: flex;
            flex-direction: column;
            gap: 24px;
            position: sticky;
            top: 40px;
        }

        .summary-bill-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        .summary-bill-card h2 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 20px 0;
            color: #0f172a;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        .invoice-row.adjustment-row {
            color: #059669;
        }

        .invoice-row.grand-total-row {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0;
        }

        .info-block-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        .info-block-card h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: 700;
            margin: 0 0 16px 0;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
        }

        .shipping-address-text {
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.6;
            margin: 0;
            white-space: pre-line;
        }

        .payment-meta-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .payment-meta-item:last-child {
            margin-bottom: 0;
        }
        .payment-meta-item span {
            color: #64748b;
        }
        .payment-meta-item strong {
            color: #334155;
            font-weight: 600;
        }

        @media (max-width: 576px) {
            .product-item-row {
                grid-template-columns: 1fr;
                gap: 12px;
                text-align: center;
            }
            .product-thumbnail {
                margin: 0 auto;
            }
            .product-price-final {
                text-align: center;
            }
            .order-master-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div class="order-container">
        
        <!-- Top Action Bar -->
        <div class="action-bar">
            <a href="user_dashboard.php" class="back-btn">
                <i class="bi bi-arrow-left-short fs-4"></i> Back to Dashboard
            </a>
        </div>

        <!-- Header Summary Card -->
        <div class="order-master-header">
            <div class="order-title-area">
                <h1>Order Overview</h1>
                <div class="order-id-badge">ID: ORD#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></div>
            </div>
            
            <div class="order-meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Date Placed</span>
                    <span class="meta-value"><?php echo htmlspecialchars($order_date); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Fulfillment</span>
                    <div>
                        <span class="status-pill"><?php echo htmlspecialchars($order['Order_Status'] ?? 'Pending'); ?></span>
                    </div>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tracking Ref.</span>
                    <span class="meta-value" style="font-family: monospace; color:#0f172a;"><?php echo htmlspecialchars($tracking_number); ?></span>
                </div>
            </div>
        </div>

        <!-- Split Layout Panel Grid -->
        <div class="order-split-layout">
            
            <!-- Left Workspace Box: Item list -->
            <div class="main-content-card">
                <div class="card-inner-header">
                    <h2>Purchased Items (<?php echo count($line_items); ?>)</h2>
                </div>
                
                <div class="items-list">
                    <?php if (!empty($line_items)): ?>
                        <?php foreach ($line_items as $item): ?>
                            <div class="product-item-row">
                                <img class="product-thumbnail" src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.src='../images/brands/placeholder.png'">
                                <div class="product-details">
                                    <span class="brand-tag"><?php echo htmlspecialchars($item['brand']); ?></span>
                                    <span class="product-title"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="product-spec">Qty: <?php echo (int)$item['qty']; ?> &middot; Unit Price: RM <?php echo number_format($item['unit_price'], 2); ?></span>
                                </div>
                                <div class="product-price-final">
                                    RM <?php echo number_format($item['line_total'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #94a3b8;">
                            <i class="bi bi-bag-x fs-1"></i>
                            <p style="margin-top: 12px; font-weight:500;">No product data linked to this dynamic record.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Workspace Sidebar: Financial Invoice details & Addresses -->
            <div class="sidebar-sticky-panel">
                
                <!-- Financial Breakdown -->
                <div class="summary-bill-card">
                    <h2>Payment Summary</h2>
                    
                    <div class="invoice-row">
                        <span>Items Total</span>
                        <span>RM <?php echo number_format($products_total, 2); ?></span>
                    </div>
                    
                    <?php if (abs($adjustment) >= 0.01): ?>
                        <div class="invoice-row adjustment-row">
                            <span>Adjustments / Shipping</span>
                            <span><?php echo $adjustment >= 0 ? '+' : '-'; ?> RM <?php echo number_format(abs($adjustment), 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="invoice-row grand-total-row">
                        <span>Grand Total</span>
                        <span>RM <?php echo number_format($order_total, 2); ?></span>
                    </div>
                </div>

                <!-- Shipping Destination Block -->
                <div class="info-block-card">
                    <h3>Shipping Address</h3>
                    <p class="shipping-address-text"><?php echo nl2br(htmlspecialchars($shipping_address)); ?></p>
                </div>

                <!-- Transaction Summary Details Box -->
                <div class="info-block-card">
                    <h3>Payment Credentials</h3>
                    <div class="payment-meta-item">
                        <span>Method</span>
                        <strong><?php echo htmlspecialchars($payment_method); ?></strong>
                    </div>
                    <div class="payment-meta-item">
                        <span>Status</span>
                        <strong style="color: <?php echo $payment_status === 'PAID' ? '#059669' : '#d97706'; ?>;">
                            <?php echo htmlspecialchars($payment_status); ?>
                        </strong>
                    </div>
                </div>

            </div>
        </div>
        
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>