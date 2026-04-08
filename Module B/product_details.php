<?php
// 强制开启 Session 以确保浏览记录生效
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Module B: 核心交易组 - 产品详情页面 (Product Details)
include '../includes/db_connection.php';
include '../includes/header.php';

// 1. 检查 URL 是否带了产品 ID
if (!isset($_GET['pro_id']) || empty($_GET['pro_id'])) {
    echo "<script>alert('No Product Selected'); window.location.href='catalogue.php';</script>";
    exit;
}

$pro_id = intval($_GET['pro_id']); 

// ==========================================
// 处理 Recently Viewed 逻辑 (Session 记录)
// ==========================================
if (!isset($_SESSION['recently_viewed'])) {
    $_SESSION['recently_viewed'] = [];
}
// 如果已存在该商品，先从记录中移除（为了把它重新推到最前面）
if (($key = array_search($pro_id, $_SESSION['recently_viewed'])) !== false) {
    unset($_SESSION['recently_viewed'][$key]);
}
// 将当前商品插入到数组最前面
array_unshift($_SESSION['recently_viewed'], $pro_id);
// 最多只保留最近浏览的 8 个商品
if (count($_SESSION['recently_viewed']) > 8) {
    array_pop($_SESSION['recently_viewed']);
}

// 2. 获取当前产品数据
$sql_pro = "SELECT product.*, brand.Brand_Name 
            FROM product 
            JOIN brand ON product.Brand_Id = brand.Brand_Id 
            WHERE Pro_Id = '$pro_id' AND Pro_Status = 'Available'";
$res_pro = $conn->query($sql_pro);

if ($res_pro->num_rows == 0) {
    echo "<div style='padding:50px; text-align:center;'>Product not found or unavailable.</div>";
    include '../includes/footer.php';
    exit;
}

$product = $res_pro->fetch_assoc();
$pro_img = !empty($product['Pro_Image']) ? "../uploads/" . $product['Pro_Image'] : "../assets/images/placeholder.jpg";
$sizes = explode(',', $product['Pro_Size']);
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');


// ==========================================
// 3. 获取 "You may also like" 推荐商品 (加入去重逻辑)
// ==========================================
$cat_id = $product['Cat_Id'];
$rec_sql = "SELECT product.*, brand.Brand_Name FROM product 
            JOIN brand ON product.Brand_Id = brand.Brand_Id
            WHERE Pro_Id != '$pro_id' AND Cat_Id = '$cat_id' AND Pro_Status = 'Available' 
            ORDER BY RAND()";
$rec_res = $conn->query($rec_sql);
$recommended_products = [];
$rec_seen_names = [$product['Pro_Name']]; // 记录已经出现过的名字（包含当前鞋子）

if ($rec_res && $rec_res->num_rows > 0) {
    while($r = $rec_res->fetch_assoc()) { 
        // 只有当名字没出现过时才加入数组
        if (!in_array($r['Pro_Name'], $rec_seen_names)) {
            $recommended_products[] = $r; 
            $rec_seen_names[] = $r['Pro_Name'];
        }
        if (count($recommended_products) >= 6) break; // 限制数量
    }
}

// ==========================================
// 4. 获取 "Best Sellers" (加入去重逻辑)
// ==========================================
$bs_sql = "SELECT product.*, brand.Brand_Name FROM product 
           JOIN brand ON product.Brand_Id = brand.Brand_Id
           WHERE Pro_Id != '$pro_id' AND Pro_Status = 'Available' 
           ORDER BY Pro_Stock_Quantity ASC";
$bs_res = $conn->query($bs_sql);
$best_sellers = [];
$bs_seen_names = [$product['Pro_Name']]; 

if ($bs_res && $bs_res->num_rows > 0) {
    while($r = $bs_res->fetch_assoc()) { 
        if (!in_array($r['Pro_Name'], $bs_seen_names)) {
            $best_sellers[] = $r; 
            $bs_seen_names[] = $r['Pro_Name'];
        }
        if (count($best_sellers) >= 8) break;
    }
}

// ==========================================
// 5. 获取 "Recently Viewed" 及 智能填补 (加入去重逻辑)
// ==========================================
$recently_viewed_products = [];
$recent_section_title = "Recently Viewed"; 
$rv_seen_names = [$product['Pro_Name']];

$rv_ids = array_diff($_SESSION['recently_viewed'], [$pro_id]);
$fetched_ids = [$pro_id]; 

if (!empty($rv_ids)) {
    $ids_string = implode(',', $rv_ids);
    $rv_sql = "SELECT product.*, brand.Brand_Name FROM product 
               JOIN brand ON product.Brand_Id = brand.Brand_Id
               WHERE Pro_Id IN ($ids_string) AND Pro_Status = 'Available'
               ORDER BY FIELD(product.Pro_Id, $ids_string)";
    $rv_res = $conn->query($rv_sql);
    if ($rv_res && $rv_res->num_rows > 0) {
        while($r = $rv_res->fetch_assoc()) { 
            if (!in_array($r['Pro_Name'], $rv_seen_names)) {
                $recently_viewed_products[] = $r; 
                $rv_seen_names[] = $r['Pro_Name'];
                $fetched_ids[] = $r['Pro_Id']; 
            }
        }
    }
}

$current_count = count($recently_viewed_products);
if ($current_count < 8) {
    if ($current_count == 0) {
        $recent_section_title = "Trending & New Arrivals"; 
    }
    
    $exclude_string = implode(',', $fetched_ids); 
    $fallback_sql = "SELECT product.*, brand.Brand_Name FROM product 
                     JOIN brand ON product.Brand_Id = brand.Brand_Id
                     WHERE Pro_Id NOT IN ($exclude_string) AND Pro_Status = 'Available'
                     ORDER BY Pro_Sale DESC, Pro_Id DESC";
    $fallback_res = $conn->query($fallback_sql);
    
    if ($fallback_res && $fallback_res->num_rows > 0) {
        while($r = $fallback_res->fetch_assoc()) { 
            if (!in_array($r['Pro_Name'], $rv_seen_names)) {
                $recently_viewed_products[] = $r; 
                $rv_seen_names[] = $r['Pro_Name'];
            }
            if (count($recently_viewed_products) >= 8) break; // 补齐到 8 个停止
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['Pro_Name']; ?> | Sport Shoes Store</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        .flex-wrapper { flex: 1 0 auto; width: 100%; padding-bottom: 60px; }
        .detail-container { max-width: 1200px; margin: 40px auto; padding: 30px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .breadcrumb { margin-bottom: 30px; font-size: 14px; color: #666; }
        .breadcrumb a { color: #333; text-decoration: none; font-weight: bold; }
        .product-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; }
        @media (max-width: 992px) { .product-layout { grid-template-columns: 1fr; } }

        .image-gallery { background: #f9f9f9; border-radius: 8px; padding: 20px; text-align: center; position: relative; }
        .main-image { width: 100%; max-width: 500px; height: auto; mix-blend-mode: multiply; }
        .badge-sale { position: absolute; top: 20px; left: 20px; background: #ffeb3b; color: #000; font-size: 13px; font-weight: bold; padding: 6px 12px; border-radius: 4px; }
        .brand-name { font-size: 14px; font-weight: bold; color: #FF6B00; text-transform: uppercase; margin-bottom: 5px; }
        .product-title { font-size: 32px; font-weight: 800; margin: 0 0 15px 0; line-height: 1.2; }
        .current-price { font-size: 28px; font-weight: bold; margin-bottom: 30px; display: block;}
        .info-label { font-weight: bold; display: block; margin-bottom: 10px; font-size: 15px; }
        .size-selector { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .size-box { width: 50px; height: 50px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; border-radius: 4px; }
        .size-box.selected { background: #333; color: white; border-color: #333; }
        .quantity-selector { display: flex; border: 1px solid #ccc; border-radius: 4px; width: 120px; }
        .quantity-selector button { background: #f4f4f4; color: #333; font-weight: bold; border: none; width: 40px; font-size: 20px; cursor: pointer; transition: 0.2s; }
        .quantity-selector button:hover { background: #e0e0e0; color: #000; }
        .quantity-selector input { width: 40px; border: none; text-align: center; font-weight: bold; outline: none; }
        .btn-add-cart { flex: 1; background-color: #008060; color: white; padding: 15px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-transform: uppercase; }

        /* 滑动展示区 */
        .sliders-wrapper { max-width: 1200px; margin: 0 auto 60px auto; padding: 0 20px; }
        .slider-section { margin-top: 50px; }
        .slider-header { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 25px; font-size: 18px; font-weight: bold; color: #333; }
        .slider-header i { cursor: pointer; color: #999; font-size: 20px; transition: 0.2s; }
        .slider-header i:hover { color: #333; }
        
        .slider-container { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 15px; scrollbar-width: none; scroll-behavior: smooth; }
        .slider-container::-webkit-scrollbar { display: none; }
        
        .slider-card { min-width: 240px; max-width: 240px; text-decoration: none; color: inherit; flex-shrink: 0; display: block; transition: transform 0.3s; background: #fff; padding: 15px; border-radius: 8px; }
        .slider-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .slider-img { width: 100%; aspect-ratio: 1/1; background: #f4f4f4; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
        .slider-img img { width: 85%; height: 85%; object-fit: contain; mix-blend-mode: multiply; }
        .slider-price { font-weight: bold; font-size: 16px; margin-bottom: 5px; color: #333; }
        .slider-brand { font-size: 13px; font-weight: bold; color: #666; margin-bottom: 3px; }
        .slider-name { font-size: 14px; color: #333; line-height: 1.3; }

        /* 侧边栏购物车 */
        .cart-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 9998; opacity: 0; visibility: hidden; transition: 0.3s ease; }
        .cart-overlay.active { opacity: 1; visibility: visible; }
        .cart-drawer { position: fixed; top: 0; right: -500px; width: 100%; max-width: 480px; height: 100vh; background: #fff; z-index: 9999; box-shadow: -5px 0 20px rgba(0,0,0,0.1); display: flex; flex-direction: column; transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .cart-drawer.active { right: 0; }
        .cart-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .cart-close { font-size: 24px; cursor: pointer; color: #333; }
        .shipping-bar { padding: 15px 20px; border-bottom: 1px solid #eee; }
        .shipping-fill { height: 6px; background: #008060; transition: width 0.5s; border-radius: 3px; }
        
        .drawer-body { flex: 1; overflow-y: auto; }
        .cart-items { padding: 20px; }
        .cart-item { display: grid; grid-template-columns: 100px 1fr auto; gap: 15px; margin-bottom: 25px; position: relative; }
        .cart-item-img { width: 100px; height: 100px; background: #f9f9f9; border-radius: 4px; display: flex; align-items: center; justify-content: center;}
        .cart-item-img img { width: 90%; mix-blend-mode: multiply; }
        .cart-qty-controls { display: flex; border: 1px solid #ddd; border-radius: 4px; width: 100px; height: 35px; margin-top: 10px;}
        .cart-qty-controls button { background: #fff; border: none; flex: 1; font-size: 18px; cursor: pointer; color: #333; font-weight: bold;}
        .cart-delete { color: #999; cursor: pointer; font-size: 20px; background: none; border: none; position: absolute; right: 0; bottom: 0; }

        /* 推荐商品区 (You may also like) */
        .recommended-section { padding: 20px; border-top: 10px solid #f4f6f9; }
        .recommended-header { font-weight: bold; font-size: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .recommended-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .rec-card { text-decoration: none; color: inherit; display: block; background: #fff; }
        .rec-img-box { width: 100%; aspect-ratio: 1/1.2; background: #f9f9f9; display: flex; align-items: center; justify-content: center; border-radius: 4px; margin-bottom: 10px;}
        .rec-img-box img { width: 85%; mix-blend-mode: multiply; }
        .rec-price { font-weight: bold; font-size: 15px; margin-bottom: 2px; }
        .rec-brand { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .rec-name { font-size: 13px; color: #666; line-height: 1.2; }

        /* 优惠码与结算 */
        .cart-footer { padding: 20px; border-top: 1px solid #eee; background: #fff; }
        .promo-accordion { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }
        .promo-summary { padding: 15px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .promo-content { padding: 0 15px 15px; display: none; }
        .promo-input-group { display: flex; gap: 10px; }
        .promo-input-group input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-promo { background: #333; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; }
        .subtotal-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; margin-bottom: 20px; }
        .btn-checkout { width: 100%; background: #008060; color: white; border: none; padding: 16px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

<div class="flex-wrapper">
    <div class="detail-container">
        <div class="breadcrumb">
            <a href="catalogue.php">Catalogue</a> / <a href="catalogue.php?brand_id=<?php echo $product['Brand_Id']; ?>"><?php echo $product['Brand_Name']; ?></a> / <?php echo $product['Pro_Name']; ?>
        </div>

        <div class="product-layout">
            <div class="image-gallery">
                <?php if($product['Pro_Sale'] == 1): ?><div class="badge-sale">↗ TRENDING / SALE</div><?php endif; ?>
                <img src="<?php echo $pro_img; ?>" class="main-image" onerror="this.src='../assets/images/placeholder.jpg'">
            </div>
            
            <div class="product-info">
                <div class="brand-name"><?php echo $product['Brand_Name']; ?></div>
                <h1 class="product-title"><?php echo $product['Pro_Name']; ?></h1>
                <span class="current-price">RM <?php echo number_format($product['Pro_Price'], 2); ?></span>

                <div style="margin-bottom:25px; color:#666; font-size:14px;">
                    <strong>Colour:</strong> <?php echo $product['Pro_Colour']; ?> | <strong>Gender:</strong> <?php echo $product['Pro_Gender']; ?>
                </div>

                <div class="info-label">Description</div>
                <p style="color:#666; line-height:1.6; margin-bottom:30px;"><?php echo nl2br($product['Pro_Description']); ?></p>

                <div class="info-label">Select Size (UK) <span id="sizeError" style="color:red; font-size:12px; display:none; margin-left:10px;">*Required</span></div>
                <div class="size-selector">
                    <?php foreach($sizes as $sz) { $sz = trim($sz); if(!empty($sz)) echo "<div class='size-box' onclick='selectSize(this, \"$sz\")'>$sz</div>"; } ?>
                </div>

                <div class="info-label">Quantity</div>
                <div style="display:flex; gap:15px; align-items:center;">
                    <div class="quantity-selector">
                        <button type="button" onclick="changeQty(-1)">−</button>
                        <input type="number" id="qtyInput" value="1" readonly>
                        <button type="button" onclick="changeQty(1)">+</button>
                    </div>
                    <?php if($isAdmin): ?>
                        <button type="button" class="btn-add-cart" onclick="showAdminWarning()">ADD TO BASKET</button>
                    <?php else: ?>
                        <button type="button" class="btn-add-cart" onclick="addToCart()">ADD TO BASKET</button>
                    <?php endif; ?>
                </div>
                <small style="display:block; margin-top:10px; color:#666;">Only <span id="stockLimit"><?php echo $product['Pro_Stock_Quantity']; ?></span> left in stock.</small>
            </div>
        </div>
    </div> 
    
    <div class="sliders-wrapper">
        
        <?php if(!empty($best_sellers)): ?>
        <div class="slider-section">
            <div class="slider-header">
                <i class="bi bi-chevron-left" onclick="slideCarousel('bestSellerSlider', -1)"></i>
                <span>Best Sellers</span>
                <i class="bi bi-chevron-right" onclick="slideCarousel('bestSellerSlider', 1)"></i>
            </div>
            <div class="slider-container" id="bestSellerSlider">
                <?php foreach($best_sellers as $bs): 
                    $bs_img = !empty($bs['Pro_Image']) ? "../uploads/" . $bs['Pro_Image'] : "../assets/images/placeholder.jpg";
                ?>
                    <a href="product_details.php?pro_id=<?php echo $bs['Pro_Id']; ?>" class="slider-card">
                        <div class="slider-img"><img src="<?php echo $bs_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'"></div>
                        <div class="slider-price">RM <?php echo number_format($bs['Pro_Price'], 0); ?></div>
                        <div class="slider-brand"><?php echo $bs['Brand_Name']; ?></div>
                        <div class="slider-name"><?php echo $bs['Pro_Name']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($recently_viewed_products)): ?>
        <div class="slider-section">
            <div class="slider-header">
                <i class="bi bi-chevron-left" onclick="slideCarousel('recentSlider', -1)"></i>
                <span><?php echo $recent_section_title; ?></span>
                <i class="bi bi-chevron-right" onclick="slideCarousel('recentSlider', 1)"></i>
            </div>
            <div class="slider-container" id="recentSlider">
                <?php foreach($recently_viewed_products as $rv): 
                    $rv_img = !empty($rv['Pro_Image']) ? "../uploads/" . $rv['Pro_Image'] : "../assets/images/placeholder.jpg";
                ?>
                    <a href="product_details.php?pro_id=<?php echo $rv['Pro_Id']; ?>" class="slider-card">
                        <div class="slider-img"><img src="<?php echo $rv_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'"></div>
                        <div class="slider-price">RM <?php echo number_format($rv['Pro_Price'], 0); ?></div>
                        <div class="slider-brand"><?php echo $rv['Brand_Name']; ?></div>
                        <div class="slider-name"><?php echo $rv['Pro_Name']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<?php include '../includes/footer.php'; ?>

<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">
        <h3 style="margin:0; font-size:18px;">Basket items (<span id="cartCount">0</span>)</h3>
        <i class="bi bi-x-lg cart-close" onclick="toggleCart()"></i>
    </div>

    <div class="shipping-bar">
        <div id="shippingText" style="font-size:13px; font-weight:bold; margin-bottom:8px;"></div>
        <div style="width:100%; height:6px; background:#eee; border-radius:3px;"><div class="shipping-fill" id="shippingFill"></div></div>
    </div>

    <div class="drawer-body">
        <div class="cart-items" id="cartItemsContainer"></div>

        <div class="recommended-section">
            <div class="recommended-header">
                <span>You may also like</span>
            </div>
            <div class="recommended-list">
                <?php foreach($recommended_products as $rp): ?>
                    <a href="product_details.php?pro_id=<?php echo $rp['Pro_Id']; ?>" class="rec-card">
                        <div class="rec-img-box"><img src="../uploads/<?php echo $rp['Pro_Image']; ?>" onerror="this.src='../assets/images/placeholder.jpg'"></div>
                        <div class="rec-price">RM <?php echo number_format($rp['Pro_Price'], 0); ?></div>
                        <div class="rec-brand"><?php echo $rp['Brand_Name']; ?></div>
                        <div class="rec-name"><?php echo $rp['Pro_Name']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="cart-footer">
        <div class="promo-accordion">
            <div class="promo-summary" onclick="togglePromo()">
                <span><i class="bi bi-percent" style="margin-right:10px;"></i> Add Promotion Code</span>
                <i class="bi bi-chevron-down" id="promoArrow"></i>
            </div>
            <div class="promo-content" id="promoContent">
                <div class="promo-input-group">
                    <input type="text" placeholder="Enter code">
                    <button class="btn-promo">Apply</button>
                </div>
            </div>
        </div>

        <div class="subtotal-row">
            <span>Subtotal:</span>
            <span id="cartSubtotalText">RM 0.00</span>
        </div>
        <button class="btn-checkout" onclick="window.location.href='cart.php'">Checkout securely</button>
    </div>
</div>

<script>
    let selectedSize = "";
    const price = <?php echo $product['Pro_Price']; ?>;
    const freeShippingLimit = 250;

    function selectSize(el, sz) {
        document.querySelectorAll('.size-box').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        selectedSize = sz;
        document.getElementById('sizeError').style.display = 'none';
    }

    function changeQty(amt) {
        let input = document.getElementById('qtyInput');
        let val = parseInt(input.value) + amt;
        if(val >= 1 && val <= <?php echo $product['Pro_Stock_Quantity']; ?>) input.value = val;
    }

    function toggleCart() {
        document.getElementById('cartOverlay').classList.toggle('active');
        document.getElementById('cartDrawer').classList.toggle('active');
    }

    function togglePromo() {
        let content = document.getElementById('promoContent');
        let arrow = document.getElementById('promoArrow');
        if(content.style.display === "block") {
            content.style.display = "none";
            arrow.style.transform = "rotate(0deg)";
        } else {
            content.style.display = "block";
            arrow.style.transform = "rotate(180deg)";
        }
    }

    function slideCarousel(id, direction) {
        const container = document.getElementById(id);
        const scrollAmount = 260; 
        container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
    }

    function addToCart() {
        if(!selectedSize) { document.getElementById('sizeError').style.display = 'inline'; return; }
        
        let qty = parseInt(document.getElementById('qtyInput').value);
        let subtotal = (qty * price).toFixed(2);
        
        document.getElementById('cartCount').innerText = qty;
        document.getElementById('cartSubtotalText').innerText = "RM " + subtotal;
        
        let shippingFill = document.getElementById('shippingFill');
        let shippingText = document.getElementById('shippingText');
        if(subtotal >= freeShippingLimit) {
            shippingFill.style.width = "100%";
            shippingText.innerHTML = "<span style='color:#008060;'>You qualify for Free Standard Delivery!</span>";
        } else {
            let remain = (freeShippingLimit - subtotal).toFixed(2);
            shippingFill.style.width = (subtotal / freeShippingLimit * 100) + "%";
            shippingText.innerText = `You're RM ${remain} away from free shipping.`;
        }

        document.getElementById('cartItemsContainer').innerHTML = `
            <div class="cart-item">
                <div class="cart-item-img"><img src="<?php echo $pro_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'"></div>
                <div class="cart-item-details">
                    <div style="font-size:12px; font-weight:bold; text-transform:uppercase; color:#666;"><?php echo $product['Brand_Name']; ?></div>
                    <div style="font-weight:bold; font-size:14px; margin:4px 0;"><?php echo $product['Pro_Name']; ?></div>
                    <div style="font-size:13px; color:#666;">Size: ${selectedSize}</div>
                    <div class="cart-qty-controls">
                        <button onclick="changeQty(-1)">−</button>
                        <input type="text" value="${qty}" readonly>
                        <button onclick="changeQty(1)">+</button>
                    </div>
                </div>
                <div style="font-weight:bold; font-size:14px;">RM ${price.toFixed(2)}</div>
                <button class="cart-delete" onclick="this.parentElement.remove(); updateCartTotals(0);"><i class="bi bi-trash3"></i></button>
            </div>
        `;
        
        toggleCart();
    }

    function showAdminWarning() { Swal.fire({ title: "Action Denied", text: "Admins cannot purchase products.", icon: "error", confirmButtonColor: "#333" }); }
</script>

</body>
</html>