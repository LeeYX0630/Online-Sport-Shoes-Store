<?php
// Module B: 核心交易组 - 购物车页面 (Shopping Cart)
require_once '../includes/db_connection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 确保购物车 Session 已初始化
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ---------------------------------------------------------
// 1. 处理表单提交 (更新数量 / 删除商品)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 更新数量逻辑
    if (isset($_POST['update_cart'])) {
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            foreach ($_POST['qty'] as $cart_key => $quantity) {
                $quantity = intval($quantity);
                if ($quantity > 0 && isset($_SESSION['cart'][$cart_key])) {
                    $_SESSION['cart'][$cart_key]['qty'] = $quantity;
                }
            }
        }
    } 
    // 删除单个商品逻辑
    elseif (isset($_POST['remove_item'])) {
        $key_to_remove = $_POST['remove_key'];
        if (isset($_SESSION['cart'][$key_to_remove])) {
            unset($_SESSION['cart'][$key_to_remove]);
        }
    }
    
    // 刷新页面以防止表单重复提交
    header("Location: cart.php");
    exit;
}

$cart_empty = empty($_SESSION['cart']);
$subtotal = 0;
$shipping_fee = 0;
$free_shipping_threshold = 250.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Online Sport Shoes Store</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; color: #212529; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .flex-wrapper { flex: 1 0 auto; width: 100%; padding-bottom: 60px; }
        .cart-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 28px; font-weight: 800; color: #333333; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; align-items: start; }
        @media (max-width: 992px) { .cart-layout { grid-template-columns: 1fr; } }

        /* 购物车列表区 */
        .cart-items { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 20px; }
        .cart-item-row { display: grid; grid-template-columns: 100px 2fr 1fr 1fr auto; gap: 20px; align-items: center; padding: 20px 0; border-bottom: 1px solid #eee; }
        .cart-item-row:last-child { border-bottom: none; }
        @media (max-width: 768px) { .cart-item-row { grid-template-columns: 80px 1fr; } .desktop-only { display: none; } }

        .item-img { width: 100px; height: 100px; background: #f9f9f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .item-img img { width: 90%; mix-blend-mode: multiply; }
        
        .item-details { display: flex; flex-direction: column; gap: 5px; }
        .item-brand { font-size: 12px; font-weight: bold; color: #666; text-transform: uppercase; }
        .item-name { font-size: 16px; font-weight: bold; color: #333; text-decoration: none; }
        .item-name:hover { color: #FF6B00; }
        .item-size { font-size: 14px; color: #666; }

        .item-price { font-weight: bold; font-size: 16px; color: #333; }
        
        .qty-input { width: 60px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-weight: bold; }
        
        .btn-remove { background: none; border: none; color: #999; cursor: pointer; font-size: 18px; transition: 0.2s; }
        .btn-remove:hover { color: #DC3545; }

        .cart-actions { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-outline { background: #fff; border: 1px solid #333; color: #333; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.3s; text-decoration: none; }
        .btn-outline:hover { background: #f4f4f4; }
        .btn-update { background: #333; border: 1px solid #333; color: #fff; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-update:hover { background: #222; }

        /* 订单摘要区 */
        .order-summary { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 25px; position: sticky; top: 20px; }
        .summary-title { font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #333; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 15px; color: #555; }
        .summary-total { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee; font-size: 20px; font-weight: bold; color: #333; }
        
        .btn-checkout { display: block; width: 100%; background: #FF6B00; color: #fff; text-align: center; padding: 15px; font-size: 16px; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; margin-top: 25px; text-decoration: none; transition: 0.3s; }
        .btn-checkout:hover { background: #E56000; box-shadow: 0 4px 10px rgba(255,107,0,0.2); }

        .empty-cart { text-align: center; padding: 60px 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .empty-cart i { font-size: 60px; color: #ccc; margin-bottom: 20px; }
        .empty-cart h3 { font-size: 24px; color: #333; margin-bottom: 10px; }
        .empty-cart p { color: #666; margin-bottom: 30px; }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="flex-wrapper">
    <div class="cart-container">
        <h1 class="page-title">Your Shopping Cart</h1>

        <?php if ($cart_empty): ?>
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <h3>Your cart is currently empty.</h3>
                <p>Looks like you haven't added anything to your cart yet.</p>
                <a href="catalogue.php" class="btn-checkout" style="display: inline-block; width: auto; padding: 12px 30px;">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                
                <form method="POST" action="cart.php" id="cartForm">
                    <div class="cart-items">
                        <?php
                        // 获取购物车中所有产品的详细信息
                        foreach ($_SESSION['cart'] as $cart_key => $item) {
                            $pro_id = $item['pro_id'];
                            $size = $item['size'];
                            $qty = $item['qty'];

                            // 从数据库拉取最新的产品信息
                            $sql = "SELECT product.*, brand.Brand_Name 
                                    FROM product 
                                    JOIN brand ON product.Brand_Id = brand.Brand_Id 
                                    WHERE Pro_Id = '$pro_id'";
                            $res = $conn->query($sql);

                            if ($res && $res->num_rows > 0) {
                                $product = $res->fetch_assoc();
                                $img_src = !empty($product['Pro_Image']) ? "../uploads/" . $product['Pro_Image'] : "../assets/images/placeholder.jpg";
                                $item_total = $product['Pro_Price'] * $qty;
                                $subtotal += $item_total;
                                ?>
                                <div class="cart-item-row">
                                    <div class="item-img">
                                        <img src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                                    </div>
                                    <div class="item-details">
                                        <span class="item-brand"><?php echo htmlspecialchars($product['Brand_Name']); ?></span>
                                        <a href="product_details.php?pro_id=<?php echo $pro_id; ?>" class="item-name"><?php echo htmlspecialchars($product['Pro_Name']); ?></a>
                                        <span class="item-size">Size (UK): <?php echo htmlspecialchars($size); ?></span>
                                    </div>
                                    <div class="item-price desktop-only">
                                        RM <?php echo number_format($product['Pro_Price'], 2); ?>
                                    </div>
                                    <div>
                                        <input type="number" name="qty[<?php echo $cart_key; ?>]" value="<?php echo $qty; ?>" min="1" max="<?php echo $product['Pro_Stock_Quantity']; ?>" class="qty-input">
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="item-price">RM <?php echo number_format($item_total, 2); ?></div>
                                        <button type="submit" name="remove_item" value="1" onclick="document.getElementById('remove_key').value='<?php echo $cart_key; ?>'" class="btn-remove" title="Remove Item">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        
                        <input type="hidden" name="remove_key" id="remove_key" value="">
                        
                        <div class="cart-actions">
                            <a href="catalogue.php" class="btn-outline">Continue Shopping</a>
                            <button type="submit" name="update_cart" class="btn-update">Update Cart</button>
                        </div>
                    </div>
                </form>

                <div class="order-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span style="font-weight: bold; color: #333;">RM <?php echo number_format($subtotal, 2); ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Estimated Shipping</span>
                        <?php 
                        if ($subtotal >= $free_shipping_threshold) {
                            $shipping_fee = 0;
                            echo "<span style='color: #28A745; font-weight: bold;'>Free</span>";
                        } else {
                            $shipping_fee = 15.00; // 默认运费
                            echo "<span style='font-weight: bold; color: #333;'>RM " . number_format($shipping_fee, 2) . "</span>";
                        }
                        ?>
                    </div>
                    
                    <?php if($shipping_fee > 0): ?>
                        <div style="font-size: 13px; color: #666; margin-top: -10px; margin-bottom: 15px;">
                            Add RM <?php echo number_format($free_shipping_threshold - $subtotal, 2); ?> more for FREE shipping!
                        </div>
                    <?php endif; ?>

                    <div class="summary-total">
                        <span>Grand Total</span>
                        <span>RM <?php echo number_format($subtotal + $shipping_fee, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="btn-checkout">Proceed to Checkout securely</a>
                    
                    <div style="margin-top: 20px; font-size: 13px; color: #999; text-align: center;">
                        <i class="bi bi-shield-lock" style="margin-right: 5px;"></i> Secure Checkout Process
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>