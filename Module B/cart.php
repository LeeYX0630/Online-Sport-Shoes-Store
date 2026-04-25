<?php
// Module B: 核心交易组 - 购物车页面 (Shopping Cart)
require_once '../includes/db_connection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please login to view your shopping cart.'); window.location.href='../Module A/login.php';</script>";
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ---------------------------------------------------------
// 1. 处理表单提交
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['remove_item']) && $_POST['remove_item'] == "1") {
        $key_to_remove = $_POST['remove_key'];
        if (isset($_SESSION['cart'][$key_to_remove])) {
            unset($_SESSION['cart'][$key_to_remove]);
        }
    } 
    elseif (isset($_POST['update_cart']) || isset($_POST['qty'])) {
        foreach ($_SESSION['cart'] as $session_key => $item) {
            $php_converted_key = str_replace([' ', '.'], '_', $session_key);
            
            if (isset($_POST['qty'][$php_converted_key])) {
                $quantity = intval($_POST['qty'][$php_converted_key]);
                
                $pid = $item['pro_id'];
                $sz  = $item['size'];
                $color_in_session = $item['color'] ?? 'Default';

                if ($pid == 16 && ($color_in_session == 'Custom Design' || $color_in_session == 'Default')) {
                    $col_for_db = 'Custom'; 
                } else {
                    $col_for_db = $color_in_session;
                }

                $st_sql = "SELECT Quantity FROM PRODUCT_STOCK WHERE Pro_Id = '$pid' AND Pro_Size = '$sz' AND Pro_Colour = '$col_for_db'";
                $st_res = $conn->query($st_sql);
                $db_stock = ($st_res && $st_res->num_rows > 0) ? intval($st_res->fetch_assoc()['Quantity']) : 0;

                if ($quantity > $db_stock) $quantity = $db_stock;
                if ($quantity < 1) $quantity = 1;

                $_SESSION['cart'][$session_key]['qty'] = $quantity;
            }
        }
    }
    header("Location: cart.php");
    exit;
}

$cart_empty = empty($_SESSION['cart']);
$subtotal = 0;
$free_shipping_threshold = 250.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Stealth Sport Shoes</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .flex-wrapper { min-height: 100vh; padding-bottom: 60px; }
        .cart-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 28px; font-weight: 800; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
        .cart-items { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .cart-item-row { display: grid; grid-template-columns: 100px 2fr 1fr 1fr auto; gap: 20px; align-items: center; padding: 20px 0; border-bottom: 1px solid #eee; }
        .item-img img { width: 100px; height: 100px; object-fit: contain; mix-blend-mode: multiply; border-radius: 4px; background: #f9f9f9; }
        .qty-input { width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-weight: bold; }
        .order-summary { background: #fff; border-radius: 8px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); height: fit-content; position: sticky; top: 20px; }
        .btn-checkout { display: block; width: 100%; background: #008060; color: #fff; text-align: center; padding: 15px; font-weight: bold; border-radius: 4px; text-decoration: none; margin-top: 20px; transition: 0.3s; }
        .btn-checkout:hover { background: #00664c; }
        .btn-continue-shopping { display: block; width: 100%; background: #fff; color: #333; text-align: center; padding: 15px; font-weight: bold; border-radius: 4px; text-decoration: none; margin-top: 12px; border: 1px solid #ccc; transition: 0.3s; }
        .btn-continue-shopping:hover { background: #f8f9fa; border-color: #333; }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="flex-wrapper">
    <div class="cart-container">
        <h1 class="page-title">Your Shopping Cart</h1>

        <?php if ($cart_empty): ?>
            <div style="text-align:center; padding:60px; background:#fff; border-radius:8px;">
                <i class="bi bi-cart-x" style="font-size:3rem; color:#ccc;"></i>
                <h3 class="mt-3">Your cart is empty</h3>
                <a href="catalogue.php" class="btn btn-dark mt-3">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <form method="POST" action="cart.php" id="cartForm" onsubmit="return false;">
                    <input type="hidden" name="update_cart" value="1">
                    <input type="hidden" name="remove_item" id="remove_item_trigger" value="0">
                    <input type="hidden" name="remove_key" id="remove_key" value="">

                    <div class="cart-items">
                        <?php
                        foreach ($_SESSION['cart'] as $cart_key => $item) {
                            $pro_id = $item['pro_id'];
                            $size   = $item['size'];
                            $qty    = $item['qty'];
                            $color_in_session = $item['color'] ?? 'Default';

                            // 确定显示颜色名称
                            if ($pro_id == 16 && ($color_in_session == 'Custom Design' || $color_in_session == 'Default')) {
                                $display_color = 'Custom'; 
                            } else {
                                $display_color = $color_in_session;
                            }

                            $sql = "SELECT p.*, b.Brand_Name, s.Quantity AS DB_Stock 
                                    FROM product p 
                                    JOIN brand b ON p.Brand_Id = b.Brand_Id 
                                    LEFT JOIN PRODUCT_STOCK s ON p.Pro_Id = s.Pro_Id AND s.Pro_Size = '$size' AND s.Pro_Colour = '$display_color'
                                    WHERE p.Pro_Id = '$pro_id'";
                            $res = $conn->query($sql);

                            if ($res && $res->num_rows > 0) {
                                $product = $res->fetch_assoc();
                                $current_stock = intval($product['DB_Stock'] ?? 0);
                                
                                // --- 【核心修复逻辑：优先检查并使用自定义 3D 快照】 ---
                                if (!empty($item['custom_preview'])) {
                                    // 用户自定义设计的图片
                                    $display_img = $item['custom_preview'];
                                } else {
                                    // 普通商品的图片匹配逻辑
                                    $base_img = $product['Pro_Image'];
                                    $path_parts = pathinfo($base_img);
                                    $base_name = preg_replace('/_\d+$/', '', $path_parts['filename']);
                                    $found_files = glob("../uploads/{$base_name}*.*");
                                    
                                    if (!empty($found_files)) {
                                        $display_img = $found_files[0]; // 默认取第一张
                                        
                                        // 如果不是默认颜色，尝试匹配文件名中的颜色词
                                        if ($display_color !== 'Default' && $display_color !== 'Custom') {
                                            $color_slug = strtolower(str_replace([' ', '/'], '_', $display_color));
                                            foreach ($found_files as $file) {
                                                if (strpos(strtolower($file), $color_slug) !== false) {
                                                    $display_img = $file;
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        $display_img = "../images/placeholder.png";
                                    }
                                }
                                // ----------------------------------------------------
                                
                                $item_total = $product['Pro_Price'] * $qty;
                                $subtotal += $item_total;
                                ?>
                                <div class="cart-item-row">
                                    <div class="item-img"><img src="<?php echo $display_img; ?>" onerror="this.src='../images/placeholder.png'"></div>
                                    <div>
                                        <div style="font-size:12px; font-weight:bold; color:#666;"><?php echo $product['Brand_Name']; ?></div>
                                        <strong style="color:#333;"><?php echo $product['Pro_Name']; ?></strong>
                                        <div style="font-size:13px; color:#666; margin-top:5px;">Size: <?php echo $size; ?> | Col: <?php echo $display_color; ?></div>
                                        <div style="font-size:11px; color:#dc3545; font-weight:bold;">Stock: <?php echo $current_stock; ?> left</div>
                                    </div>
                                    <div style="font-weight:bold;">RM <?php echo number_format($product['Pro_Price'], 2); ?></div>
                                    <div>
                                        <input type="number" name="qty[<?php echo $cart_key; ?>]" 
                                               value="<?php echo $qty; ?>" 
                                               class="qty-input" 
                                               onkeydown="if(event.key==='Enter'){ event.preventDefault(); checkStockQty(this, '<?php echo $cart_key; ?>', <?php echo $current_stock; ?>); }"
                                               onchange="checkStockQty(this, '<?php echo $cart_key; ?>', <?php echo $current_stock; ?>)">
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight:bold;">RM <?php echo number_format($item_total, 2); ?></div>
                                        <button type="button" onclick="removeItem('<?php echo $cart_key; ?>')" style="background:none; border:none; color:#999; cursor:pointer;">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </form>

                <div class="order-summary">
                    <h3 style="font-size:20px; margin-bottom:20px;">Order Summary</h3>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;"><span>Subtotal</span><span>RM <?php echo number_format($subtotal, 2); ?></span></div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;"><span>Shipping</span><span><?php echo ($subtotal >= $free_shipping_threshold) ? '<span style="color:#008060; font-weight:bold;">Free</span>' : 'RM 15.00'; ?></span></div>
                    <hr>
                    <div style="display:flex; justify-content:space-between; font-size:22px; font-weight:bold;"><span>Total</span><span>RM <?php echo number_format($subtotal + ($subtotal >= $free_shipping_threshold ? 0 : 15), 2); ?></span></div>
                    
                    <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
                    <a href="catalogue.php" class="btn-continue-shopping">Continue Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
function checkStockQty(input, cartKey, maxStock) {
    let val = parseInt(input.value);
    if (val < 1 || isNaN(val)) {
        input.value = 1;
        document.getElementById('cartForm').submit();
    } else if (val > maxStock) {
        Swal.fire({ icon: 'warning', title: 'Stock Limit', text: 'Resetting to max available: ' + maxStock }).then(() => {
            input.value = maxStock;
            document.getElementById('cartForm').submit();
        });
    } else {
        document.getElementById('cartForm').submit();
    }
}

function removeItem(cartKey) {
    Swal.fire({
        title: 'Remove item?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('remove_key').value = cartKey;
            document.getElementById('remove_item_trigger').value = "1";
            document.getElementById('cartForm').submit();
        }
    });
}
</script>

</body>
</html>