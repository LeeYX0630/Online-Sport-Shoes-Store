<?php
// includes/mini_cart.php
// 注意：此文件假设已经在调用它的主文件中开启了 session 并连接了 $conn

$mini_cart_total = 0;
$mini_cart_count = 0;
$mini_cart_items = [];

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_key => $c_item) {
        $c_pro_id = intval($c_item['pro_id']);
        $c_sql = "SELECT Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id = '$c_pro_id'";
        $c_res = $conn->query($c_sql);
        if ($c_res && $c_res->num_rows > 0) {
            $c_row = $c_res->fetch_assoc();
            // 处理路径：由于此文件被 Module B 引用，路径需指向 ../uploads/
            $base_img = $c_row['Pro_Image'];
            $path_parts = pathinfo($base_img);
            $base_name = preg_replace('/_\d+$/', '', $path_parts['filename']);
            $all_files = glob("../uploads/{$base_name}*.*");
            $c_row['img_src'] = (!empty($all_files)) ? $all_files[0] : "../images/placeholder.png";
            
            $c_row['pro_id'] = $c_pro_id;
            $c_row['size'] = $c_item['size'];
            $c_row['color'] = $c_item['color'] ?? '';
            $c_row['qty'] = $c_item['qty'];
            $c_row['subtotal'] = $c_item['qty'] * $c_row['Pro_Price'];
            
            $mini_cart_total += $c_row['subtotal'];
            $mini_cart_count += $c_item['qty'];
            $mini_cart_items[] = $c_row;
        }
    }
}
?>

<style>
    .cart-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 99998; opacity: 0; visibility: hidden; transition: 0.3s ease; }
    .cart-overlay.active { opacity: 1; visibility: visible; }
    .cart-drawer { position: fixed; top: 0; right: -500px; width: 100%; max-width: 480px; height: 100vh; background: #fff; z-index: 99999; box-shadow: -5px 0 20px rgba(0,0,0,0.1); display: flex; flex-direction: column; transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
    .cart-drawer.active { right: 0; }
    .cart-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .shipping-bar { padding: 15px 20px; border-bottom: 1px solid #eee; }
    .shipping-fill { height: 6px; background: #008060; transition: width 0.5s; border-radius: 3px; }
    .drawer-body { flex: 1; overflow-y: auto; }
    .cart-items { padding: 20px; }
    .cart-item { display: grid; grid-template-columns: 100px 1fr auto; gap: 15px; margin-bottom: 25px; position: relative; border-bottom: 1px solid #f9f9f9; padding-bottom: 15px; }
    .cart-item-img img { width: 100px; height: 100px; object-fit: contain; background: #f9f9f9; mix-blend-mode: multiply; }
    .cart-qty-controls { display: flex; border: 1px solid #ddd; border-radius: 4px; width: 90px; height: 30px; margin-top: 8px; overflow: hidden; }
    .cart-qty-controls button { flex: 1; border: none; background: #f4f4f4; cursor: pointer; }
    .cart-qty-controls input { width: 30px; border: none; text-align: center; font-size: 13px; }
    .floating-mini-cart { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #fff; padding: 12px 24px; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 30px; z-index: 9990; border: 2px solid #008060; }
</style>

<?php if ($mini_cart_count > 0): ?>
<div class="floating-mini-cart">
    <div class="fmc-info" style="display: flex; align-items: center; gap: 15px;">
        <div style="color: #008060; font-size: 20px;"><i class="bi bi-cart-check-fill"></i></div>
        <div>
            <div style="font-size: 12px; color: #666; font-weight: bold;"><?php echo $mini_cart_count; ?> ITEMS</div>
            <div style="font-size: 18px; font-weight: 900;">RM <?php echo number_format($mini_cart_total, 2); ?></div>
        </div>
    </div>
    <div class="fmc-actions" style="display: flex; gap: 10px;">
        <button onclick="toggleCart()" style="padding: 10px 20px; border-radius: 25px; border: 1px solid #ccc; background: #fff; font-weight: bold; cursor: pointer;">VIEW CART</button>
        <a href="cart.php" style="padding: 10px 24px; border-radius: 25px; background: #008060; color: #fff; text-decoration: none; font-weight: bold;">CHECKOUT</a>
    </div>
</div>
<?php endif; ?>

<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">
        <h3 style="margin:0; font-size:18px;">Basket items (<span id="cartCount"><?php echo $mini_cart_count; ?></span>)</h3>
        <i class="bi bi-x-lg" style="cursor:pointer;" onclick="toggleCart()"></i>
    </div>

    <div class="shipping-bar">
        <div id="shippingText" style="font-size:13px; font-weight:bold; margin-bottom:8px;"></div>
        <div style="width:100%; height:6px; background:#eee; border-radius:3px;"><div class="shipping-fill" id="shippingFill"></div></div>
    </div>

    <div class="drawer-body">
        <div class="cart-items" id="cartItemsContainer">
            <?php foreach($mini_cart_items as $index => $item): 
                $cartKey = $item['pro_id'] . '_' . $item['size'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $item['color']);
            ?>
            <div class="cart-item" id="cart-item-<?php echo $index; ?>">
                <div class="cart-item-img"><img src="<?php echo $item['img_src']; ?>" onerror="this.src='../images/placeholder.png'"></div>
                <div>
                    <div style="font-weight:bold; font-size:14px;"><?php echo $item['Pro_Name']; ?></div>
                    <div style="font-size:12px; color:#666;">Size: <?php echo $item['size']; ?> | Col: <?php echo $item['color']; ?></div>
                    <div class="cart-qty-controls">
                        <button onclick="updateExistingItemQty('<?php echo $cartKey; ?>', <?php echo $index; ?>, -1, 100)">−</button>
                        <input type="text" value="<?php echo $item['qty']; ?>" readonly>
                        <button onclick="updateExistingItemQty('<?php echo $cartKey; ?>', <?php echo $index; ?>, 1, 100)">+</button>
                    </div>
                </div>
                <div style="font-weight:bold; text-align:right;">
                    RM <?php echo number_format($item['subtotal'], 2); ?>
                    <div style="margin-top:20px; color:#999; cursor:pointer;" onclick="removeFromCart('<?php echo $cartKey; ?>', <?php echo $item['pro_id']; ?>)"><i class="bi bi-trash3"></i></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cart-footer" style="padding: 20px; border-top: 1px solid #eee;">
        <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; margin-bottom: 20px;">
            <span>Subtotal:</span>
            <span id="cartSubtotalText">RM <?php echo number_format($mini_cart_total, 2); ?></span>
        </div>
        <button class="btn-checkout" style="width: 100%; background: #008060; color: white; border: none; padding: 16px; font-weight: bold; border-radius: 4px; cursor: pointer;" onclick="window.location.href='cart.php'">Checkout securely</button>
    </div>
</div>

<script>
    const miniCartData = <?php echo json_encode($mini_cart_items); ?>;
    const freeShippingLimit = 250;

    function toggleCart() {
        document.getElementById('cartOverlay').classList.toggle('active');
        document.getElementById('cartDrawer').classList.toggle('active');
        updateShippingBar(parseFloat("<?php echo $mini_cart_total; ?>"));
    }

    function updateShippingBar(total) {
        const fill = document.getElementById('shippingFill');
        const text = document.getElementById('shippingText');
        if(total >= freeShippingLimit) {
            fill.style.width = "100%";
            text.innerHTML = "<span style='color:#008060;'>You qualify for Free Standard Delivery!</span>";
        } else {
            fill.style.width = (total / freeShippingLimit * 100) + "%";
            text.innerText = `You're RM ${(freeShippingLimit - total).toFixed(2)} away from free shipping.`;
        }
    }

    function removeFromCart(cartKey, proId) {
        fetch('product_details.php?pro_id=' + proId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'remove_from_cart=' + encodeURIComponent(cartKey) + '&current_pro_id=' + proId
        }).then(() => location.reload());
    }

    // 初始化进度条
    updateShippingBar(parseFloat("<?php echo $mini_cart_total; ?>"));
</script>