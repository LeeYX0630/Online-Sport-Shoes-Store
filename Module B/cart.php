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
// 1. 处理表单提交 (更新数量或删除)
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
            // --- 【核心修复 1】：直接使用 $session_key，不再转换下划线 ---
            // PHP 数组键名中的点和空格不会被自动转换，之前的 str_replace 导致了查找失败
            if (isset($_POST['qty'][$session_key])) {
                $quantity = intval($_POST['qty'][$session_key]);
                
                $pid = $item['pro_id'];
                $sz  = $item['size'];
                $color_in_session = $item['color'] ?? 'Default';

                // 统一 16/17 定制款检查 'Default' 库存池
                if ($pid == 16 || $pid == 17) {
                    $col_for_db = 'Default'; 
                } else {
                    $col_for_db = $color_in_session;
                }

                $st_sql = "SELECT Quantity FROM PRODUCT_STOCK WHERE Pro_Id = '$pid' AND Pro_Size = '$sz' AND Pro_Colour = '$col_for_db'";
                $st_res = $conn->query($st_sql);
                $db_stock = ($st_res && $st_res->num_rows > 0) ? intval($st_res->fetch_assoc()['Quantity']) : 0;

                // 数量校验逻辑
                if ($quantity > $db_stock) $quantity = $db_stock;
                if ($quantity < 1) $quantity = 1;

                // 更新 Session
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
                <form method="POST" action="cart.php" id="cartForm">
                    <input type="hidden" name="update_cart" value="1">
                    <input type="hidden" name="remove_item" id="remove_item_trigger" value="0">
                    <input type="hidden" name="remove_key" id="remove_key" value="">

                    <div class="cart-items">
                        <?php
                        foreach ($_SESSION['cart'] as $cart_key => $item) {
                            $pro_id = $item['pro_id'];
                            $size   = $item['size'];
                            $qty    = $item['qty'];
                            $color_in_session = !empty($item['custom_preview']) ? 'Custom Design' : ($item['color'] ?? 'Default');

                            if ($pro_id == 16 || $pro_id == 17) {
                                $stock_search_color = 'Default';
                                $display_color_name = ($color_in_session === 'Custom Design') ? 'Custom Design' : $color_in_session;
                            } else {
                                $stock_search_color = $color_in_session;
                                $display_color_name = $color_in_session;
                            }

                            $sql = "SELECT p.*, b.Brand_Name, s.Quantity AS DB_Stock 
                                    FROM product p 
                                    JOIN brand b ON p.Brand_Id = b.Brand_Id 
                                    LEFT JOIN PRODUCT_STOCK s ON p.Pro_Id = s.Pro_Id AND s.Pro_Size = '$size' AND s.Pro_Colour = '$stock_search_color'
                                    WHERE p.Pro_Id = '$pro_id'";
                            $res = $conn->query($sql);

                            if ($res && $res->num_rows > 0) {
                                $product = $res->fetch_assoc();
                                $current_stock = intval($product['DB_Stock'] ?? 0);
                                
                                if (isset($item['price'])) {
                                    $product['Pro_Price'] = $item['price'];
                                }
                                if (!empty($item['custom_preview'])) {
                                    $display_img = $item['custom_preview'];
                                } else {
                                    $base_img = $product['Pro_Image'];
                                    $path_parts = pathinfo($base_img);
                                    $base_name = preg_replace('/_\d+$/', '', $path_parts['filename']);
                                    $found_files = glob("../uploads/{$base_name}*.*");
                                    $display_img = (!empty($found_files)) ? $found_files[0] : "../images/placeholder.png";
                                }
                                
                                $item_total = $product['Pro_Price'] * $qty;
                                $subtotal += $item_total;
                                ?>
                                <div class="cart-item-row">
                                    <div class="item-img"><img src="<?php echo $display_img; ?>" onerror="this.src='../images/placeholder.png'"></div>
                                    <div>
                                        <div style="font-size:12px; font-weight:bold; color:#666;"><?php echo $product['Brand_Name']; ?></div>
                                        <strong style="color:#333;"><?php echo $product['Pro_Name']; ?></strong>
                                        <?php
                                        // PHP 静态尺码对照矩阵（完整版本 UK 3-13）
                                        $php_size_matrix = [
                                            "3"   => ["US-M" => "4",   "US-F" => "5",   "EUR" => "36"],
                                            "3.5" => ["US-M" => "4.5", "US-F" => "5.5", "EUR" => "36.5"],
                                            "4"   => ["US-M" => "5",   "US-F" => "6",   "EUR" => "37"],
                                            "4.5" => ["US-M" => "5.5", "US-F" => "6.5", "EUR" => "37.5"],
                                            "5"   => ["US-M" => "6",   "US-F" => "7",   "EUR" => "38"],
                                            "5.5" => ["US-M" => "6.5", "US-F" => "7.5", "EUR" => "38.5"],
                                            "6"   => ["US-M" => "7",   "US-F" => "8",   "EUR" => "39"],
                                            "6.5" => ["US-M" => "7.5", "US-F" => "8.5", "EUR" => "40"],
                                            "7"   => ["US-M" => "8",   "US-F" => "9",   "EUR" => "40.5"],
                                            "7.5" => ["US-M" => "8.5", "US-F" => "9.5", "EUR" => "41"],
                                            "8"   => ["US-M" => "9",   "US-F" => "10",  "EUR" => "42"],
                                            "8.5" => ["US-M" => "9.5", "US-F" => "10.5", "EUR" => "42.5"],
                                            "9"   => ["US-M" => "10",  "US-F" => "11",  "EUR" => "43"],
                                            "9.5" => ["US-M" => "10.5", "US-F" => "11.5", "EUR" => "43.5"],
                                            "10"  => ["US-M" => "11",  "US-F" => "12",  "EUR" => "44"],
                                            "10.5" => ["US-M" => "11.5", "US-F" => "12.5", "EUR" => "44.5"],
                                            "11"  => ["US-M" => "12",  "US-F" => "13",  "EUR" => "45"],
                                            "11.5" => ["US-M" => "12.5", "US-F" => "13.5", "EUR" => "45.5"],
                                            "12"  => ["US-M" => "13",  "US-F" => "14",  "EUR" => "46"],
                                            "12.5" => ["US-M" => "13.5", "US-F" => "14.5", "EUR" => "46.5"],
                                            "13"  => ["US-M" => "14",  "US-F" => "15",  "EUR" => "47"],
                                        ];

                                        $user_sys = $_SESSION['size_system'] ?? 'UK';
                                        $display_size_text = "UK " . htmlspecialchars($size);

                                        if ($user_sys !== 'UK' && isset($php_size_matrix[$size][$user_sys])) {
                                            $display_size_text = $user_sys . " " . htmlspecialchars($php_size_matrix[$size][$user_sys]);
                                        }
                                        ?>
                                        <div style="font-size:13px; color:#666; margin-top:5px;">Size: <strong><?php echo $display_size_text; ?></strong> | Col: <?php echo $display_color_name; ?></div>
                                        
                                        <?php if (($pro_id == 16 || $pro_id == 17) && $display_color_name == 'Custom Design'): ?>
                                            <div style="font-size:11px; color:#008060; font-weight:bold;">
                                                <i class="bi bi-hammer"></i> Custom Built to Order (Available)
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size:11px; color:#dc3545; font-weight:bold;">Stock: <?php echo $current_stock; ?> left</div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-weight:bold;">RM <?php echo number_format($product['Pro_Price'], 2); ?></div>
                                    <div>
                                        <input type="number" name="qty[<?php echo $cart_key; ?>]" 
                                               value="<?php echo $qty; ?>" 
                                               min="1"
                                               class="qty-input" 
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '');"
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
    
    // 处理空值、0 或负数的情况
    if (val < 1 || isNaN(val)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Quantity',
            text: 'Minimum quantity is 1.',
            confirmButtonColor: '#008060'
        }).then(() => {
            input.value = 1;
            document.getElementById('cartForm').submit();
        });
        return;
    } 
    // 处理超出库存的情况
    else if (val > maxStock) {
        Swal.fire({ 
            icon: 'warning', 
            title: 'Stock Limit', 
            text: 'Maximum available: ' + maxStock,
            confirmButtonColor: '#008060'
        }).then(() => {
            input.value = maxStock;
            document.getElementById('cartForm').submit();
        });
        return;
    }
    
    // 正常数值，直接提交更新
    document.getElementById('cartForm').submit();
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