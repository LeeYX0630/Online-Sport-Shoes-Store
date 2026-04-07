<?php
// module_b/select_product_options.php
session_start();
require_once '../includes/db_connection.php';

$pro_id = isset($_GET['pro_id']) ? intval($_GET['pro_id']) : 0;

// 如果是管理员，禁止购买
if (isset($_SESSION['admin_id'])) {
    echo "<script>alert('Administrators cannot place orders.'); window.location.href='../Module C/admin_dashboard.php';</script>";
    exit();
}

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    $return_url = urlencode("../Module B/select_product_options.php?pro_id=" . $pro_id);
    echo "<script>alert('Please login first!'); window.location.href='../Module A/login.php?redirect=$return_url';</script>";
    exit();
}

$product_details = null;
if ($pro_id) {
    // 严格遵循 PRODUCT 表字段 
    $sql = "SELECT * FROM PRODUCT WHERE Pro_Id = $pro_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $product_details = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $qty = intval($_POST['quantity']);
    $size = $_POST['pro_size'];
    $addr = $_POST['shipping_address'];
    
    // 跳转至支付页面
    $next_url = "checkout_payment.php?pro_id=$pro_id&qty=$qty&size=$size&addr=" . urlencode($addr);
    header("Location: $next_url");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Options - Online Sport Shoes Store</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fa; }
        .container-flex { max-width: 900px; margin: 40px auto; display: flex; gap: 20px; padding: 0 20px; }
        .product-section { flex: 1; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-section { flex: 1; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-proceed { width: 100%; padding: 12px; background: #FF6B00; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        /* 进度条保持 */
        .progressbar { counter-reset: step; padding: 0; display: flex; justify-content: space-between; list-style: none; margin-bottom: 30px; }
        .progressbar li { width: 33.33%; position: relative; text-align: center; font-size: 13px; color: #ccc; font-weight: 600; }
        .progressbar li:before { content: counter(step); counter-increment: step; width: 30px; height: 30px; line-height: 28px; border: 2px solid #e0e0e0; background: #fff; display: block; margin: 0 auto 10px auto; border-radius: 50%; }
        .progressbar li.active { color: #333; }
        .progressbar li.active:before { border-color: #FF6B00; color: #FF6B00; }
    </style>
</head>
<body>

<div style="max-width: 600px; margin: 30px auto;">
    <ul class="progressbar">
        <li class="active">Product Options</li>
        <li>Payment</li>
        <li>Confirmation</li>
    </ul>
</div>

<div class="container-flex">
    <?php if ($product_details): ?>
    <div class="product-section text-center">
        <img src="../uploads/<?php echo $product_details['Pro_Image']; ?>" style="width:100%; border-radius:8px;">
        <h3 class="mt-3"><?php echo htmlspecialchars($product_details['Pro_Name']); ?></h3>
        <p class="text-danger fw-bold">RM <?php echo number_format($product_details['Pro_Price'], 2); ?></p>
    </div>

    <div class="form-section">
        <form method="POST">
            <div style="margin-bottom:15px;">
                <label>Select Size</label>
                <select name="pro_size" required style="width:100%; padding:8px;">
                    <?php 
                    $sizes = explode(',', $product_details['Pro_Size']); 
                    foreach($sizes as $s) echo "<option value='$s'>$s</option>";
                    ?>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label>Quantity</label>
                <input type="number" name="quantity" value="1" min="1" max="<?php echo $product_details['Pro_Stock_Quantity']; ?>" style="width:100%; padding:8px;">
                <small>Available: <?php echo $product_details['Pro_Stock_Quantity']; ?></small>
            </div>

            <div style="margin-bottom:15px;">
                <label>Shipping Address</label>
                <textarea name="shipping_address" required style="width:100%; padding:8px;" rows="3"></textarea>
            </div>

            <button type="submit" class="btn-proceed">Proceed to Checkout</button>
        </form>
    </div>
    <?php endif; ?>
</div>

</body>
</html>