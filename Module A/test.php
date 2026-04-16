<?php
// 强制开启 Session 以确保浏览记录和购物车生效
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查是否已登录
$is_logged_in = isset($_SESSION['user_id']);

// ==========================================
// 核心逻辑：处理加入购物车 (Add to Cart / Checkout)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_to_cart']) || isset($_POST['checkout_now']))) {
    if (!$is_logged_in) {
        header("Location: ../Module A/login.php");
        exit;
    }

    $add_pro_id = intval($_POST['pro_id']);
    $add_size = $_POST['selected_size'];
    $add_qty = intval($_POST['quantity']);

    if (!empty($add_pro_id) && !empty($add_size) && $add_qty > 0) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $cart_key = $add_pro_id . '_' . $add_size;
        
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['qty'] += $add_qty; 
        } else {
            $_SESSION['cart'][$cart_key] = [
                'pro_id' => $add_pro_id,
                'size' => $add_size,
                'qty' => $add_qty
            ];
        }

        if (isset($_POST['checkout_now'])) {
            header("Location: cart.php"); 
        } else {
            header("Location: product_details.php?pro_id=$add_pro_id&status=added"); 
        }
        exit;
    }
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
if (($key = array_search($pro_id, $_SESSION['recently_viewed'])) !== false) {
    unset($_SESSION['recently_viewed'][$key]);
}
array_unshift($_SESSION['recently_viewed'], $pro_id);
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
// 新增：同款商品颜色筛选联动 (Color Variants)
// 通过检测相同的 Pro_Name 来抓取同款不同色的商品
// ==========================================
$product_name_safe = $conn->real_escape_string($product['Pro_Name']);
$variant_sql = "SELECT Pro_Id, Pro_Image, Pro_Colour FROM product 
                WHERE Pro_Name = '$product_name_safe' AND Pro_Status = 'Available'";
$variant_res = $conn->query($variant_sql);
$color_variants = [];
if ($variant_res && $variant_res->num_rows > 0) {
    while($v = $variant_res->fetch_assoc()) {
        $color_variants[] = $v;
    }
}

// ==========================================
// 升级：更智能的多角度图片画廊逻辑 (Smart File Detection)
// ==========================================
$base_img = $product['Pro_Image'];
$gallery_images = [];

if (!empty($base_img)) {
    $path_parts = pathinfo($base_img);
    $filename = $path_parts['filename'];
    $ext = isset($path_parts['extension']) ? "." . $path_parts['extension'] : "";

    // 去除文件名末尾可能带有的 _1, _2 等编号，找到真实的 base_name
    // 例如：pegasus42_white_1 -> pegasus42_white
    $base_name = preg_replace('/_\d+$/', '', $filename);

    // 1. 尝试不带编号的原名文件 (如 pegasus42_white.jpg)
    $main_path = "../uploads/" . $base_name . $ext;
    if (file_exists($main_path)) {
        $gallery_images[] = $main_path;
    }

    // 2. 尝试寻找 _1 到 _6 的图片 (如 pegasus42_white_1.jpg, _2.jpg...)
    for ($i = 1; $i <= 6; $i++) {
        $test_path = "../uploads/" . $base_name . "_" . $i . $ext;
        if (file_exists($test_path) && !in_array($test_path, $gallery_images)) {
            $gallery_images[] = $test_path;
        }
    }
    
    // 3. 保底：如果上述逻辑没抓到当前数据库存的主图，强制塞进去
    $original_path = "../uploads/" . $base_img;
    if (file_exists($original_path) && !in_array($original_path, $gallery_images)) {
        array_unshift($gallery_images, $original_path); 
    }
}

// 4. UI 凑数：如果图片不够 4 张，用 placeholder 补齐以保持排版整齐
while (count($gallery_images) < 4) {
    $gallery_images[] = "../assets/images/placeholder.jpg";
}

// ==========================================
// 3. 获取 "You may also like" 推荐商品 
// ==========================================
$cat_id = $product['Cat_Id'];
$rec_sql = "SELECT product.*, brand.Brand_Name FROM product 
            JOIN brand ON product.Brand_Id = brand.Brand_Id
            WHERE Pro_Id != '$pro_id' AND Cat_Id = '$cat_id' AND Pro_Status = 'Available' 
            ORDER BY RAND()";
$rec_res = $conn->query($rec_sql);
$recommended_products = [];
$rec_seen_names = [$product['Pro_Name']]; 

if ($rec_res && $rec_res->num_rows > 0) {
    while($r = $rec_res->fetch_assoc()) { 
        if (!in_array($r['Pro_Name'], $rec_seen_names)) {
            $recommended_products[] = $r; 
            $rec_seen_names[] = $r['Pro_Name'];
        }
        if (count($recommended_products) >= 6) break; 
    }
}

// ==========================================
// 4. 获取 "Recently Viewed" 及 智能填补
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
        $recent_section_title = "Recently Viewed & New Arrivals"; 
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
            if (count($recently_viewed_products) >= 8) break; 
        }
    }
}

// ==========================================
// 5. 获取 "Best Sellers"
// ==========================================
$bs_sql = "SELECT product.*, brand.Brand_Name FROM product 
           JOIN brand ON product.Brand_Id = brand.Brand_Id
           WHERE Pro_Id != '$pro_id' AND Pro_Status = 'Available' 
           ORDER BY Pro_Stock_Quantity ASC LIMIT 8";
$bs_res = $conn->query($bs_sql);
$best_sellers = [];
if ($bs_res && $bs_res->num_rows > 0) {
    while($r = $bs_res->fetch_assoc()) { $best_sellers[] = $r; }
}

// 6. 获取 当前购物车数据 (用于底部悬浮迷你购物车)
$mini_cart_total = 0;
$mini_cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_key => $c_item) {
        $c_pro_id = intval($c_item['pro_id']);
        $c_sql = "SELECT Pro_Price FROM product WHERE Pro_Id = '$c_pro_id'";
        $c_res = $conn->query($c_sql);
        if ($c_res && $c_res->num_rows > 0) {
            $c_row = $c_res->fetch_assoc();
            $mini_cart_total += ($c_item['qty'] * $c_row['Pro_Price']);
            $mini_cart_count += $c_item['qty'];
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
        .product-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 50px; }
        @media (max-width: 992px) { .product-layout { grid-template-columns: 1fr; } }

        /* 画廊 (Product Image Gallery) */
        .product-gallery-container { display: flex; gap: 20px; align-items: flex-start; }
        .thumbnail-list { 
            display: flex; flex-direction: column; gap: 12px; width: 80px; 
            max-height: 500px; overflow-y: auto; scrollbar-width: none; 
        }
        .thumbnail-list::-webkit-scrollbar { display: none; }
        .thumb-box { 
            width: 80px; height: 80px; background: #f9f9f9; border-radius: 8px; 
            border: 2px solid transparent; cursor: pointer; overflow: hidden; 
            display: flex; align-items: center; justify-content: center; 
            transition: 0.3s; flex-shrink: 0;
        }
        .thumb-box:hover { border-color: #ccc; }
        .thumb-box.active { border-color: #333; }
        .thumb-box img { width: 90%; height: 90%; object-fit: contain; mix-blend-mode: multiply; }
        
        .main-image-wrapper { 
            flex: 1; background: #f9f9f9; border-radius: 12px; padding: 20px; 
            text-align: center; position: relative; height: 500px; 
            display: flex; align-items: center; justify-content: center; 
        }
        #mainProductImage { max-width: 100%; max-height: 100%; object-fit: contain; mix-blend-mode: multiply; transition: opacity 0.2s ease-in-out; }
        
        @media (max-width: 768px) {
            .product-gallery-container { flex-direction: column-reverse; }
            .thumbnail-list { flex-direction: row; width: 100%; max-height: auto; overflow-x: auto; overflow-y: hidden; }
            .thumb-box { width: 70px; height: 70px; }
            .main-image-wrapper { width: 100%; height: 350px; }
        }

        .badge-sale { position: absolute; top: 20px; left: 20px; background: #ffeb3b; color: #000; font-size: 13px; font-weight: bold; padding: 6px 12px; border-radius: 4px; z-index: 5;}
        
        .brand-name { font-size: 14px; font-weight: bold; color: #FF6B00; text-transform: uppercase; margin-bottom: 5px; }
        .product-title { font-size: 32px; font-weight: 800; margin: 0 0 15px 0; line-height: 1.2; }
        .current-price { font-size: 28px; font-weight: bold; margin-bottom: 30px; display: block;}
        .info-label { font-weight: bold; display: block; margin-bottom: 10px; font-size: 15px; }
        
        /* ================= 新增：JD风格的颜色选择器 ================= */
        .color-variants-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; }
        .color-variant-box {
            width: 60px; height: 60px; background: #f4f4f4; border: 1px solid #ccc;
            border-radius: 4px; display: flex; align-items: center; justify-content: center;
            overflow: hidden; cursor: pointer; transition: 0.3s; padding: 2px;
        }
        .color-variant-box:hover { border-color: #333; }
        .color-variant-box.active { border-color: #FF6B00; border-width: 2px; box-shadow: 0 2px 8px rgba(255,107,0,0.2); }
        .color-variant-box img { width: 100%; height: 100%; object-fit: contain; mix-blend-mode: multiply; }
        
        .size-selector { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .size-box { width: 50px; height: 50px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; border-radius: 4px; }
        .size-box.selected { background: #333; color: white; border-color: #333; }
        
        .quantity-selector { display: flex; border: 1px solid #ccc; border-radius: 4px; width: 120px; overflow: hidden; }
        .quantity-selector button { background: #f4f4f4; color: #333; font-weight: bold; width: 40px; font-size: 20px; transition: 0.2s; border: none; cursor: pointer; }
        .quantity-selector button:hover { background: #e0e0e0; color: #000; }
        .quantity-selector input { flex: 1; width: 40px; border: none; text-align: center; font-weight: bold; outline: none; background: #fff; color: #333; margin: 0; padding: 0; }
        
        .btn-add-cart {  flex: 1; background-color: #008060; color: white; padding: 15px 20px; font-weight: bold; text-transform: uppercase; border: none; border-radius: 4px; cursor: pointer; transition: 0.3s;}
        .btn-add-cart:hover { background-color: #00664c; }

        .btn-wishlist-main {
            position: absolute; top: 20px; right: 20px;
            background: #fff; width: 44px; height: 44px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); font-size: 20px; color: #666;
            cursor: pointer; border: none; transition: 0.3s; z-index: 2;
        }
        .btn-wishlist-main:hover { color: #E7352B; transform: scale(1.1); }
        
        .btn-wishlist-card {
            position: absolute; top: 10px; right: 10px;
            background: #fff; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); font-size: 15px; color: #666;
            cursor: pointer; border: none; transition: 0.3s; z-index: 2;
        }
        .btn-wishlist-card:hover { color: #E7352B; transform: scale(1.1); }

        .sliders-wrapper { max-width: 1200px; margin: 0 auto 60px auto; padding: 0 20px; }
        .slider-section { margin-top: 50px; }
        .slider-header { display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 25px; font-size: 18px; font-weight: bold; color: #333; }
        .slider-header i { cursor: pointer; color: #999; font-size: 20px; transition: 0.2s; }
        .slider-header i:hover { color: #333; }
        
        .slider-container { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 15px; scrollbar-width: none; scroll-behavior: smooth; }
        .slider-container::-webkit-scrollbar { display: none; }
        
        .slider-card { min-width: 240px; max-width: 240px; text-decoration: none; color: inherit; flex-shrink: 0; display: block; transition: transform 0.3s; background: #fff; padding: 15px; border-radius: 8px; }
        .slider-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .slider-img { width: 100%; aspect-ratio: 1/1; background: #f4f4f4; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; position: relative; }
        .slider-img img { width: 85%; height: 85%; object-fit: contain; mix-blend-mode: multiply; }
        .slider-price { font-weight: bold; font-size: 16px; margin-bottom: 5px; color: #333; }
        .slider-brand { font-size: 13px; font-weight: bold; color: #666; margin-bottom: 3px; }
        .slider-name { font-size: 14px; color: #333; line-height: 1.3; }

        .cart-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 99998; opacity: 0; visibility: hidden; transition: 0.3s ease; }
        .cart-overlay.active { opacity: 1; visibility: visible; }
        .cart-drawer { position: fixed; top: 0; right: -500px; width: 100%; max-width: 480px; height: 100vh; background: #fff; z-index: 99999; box-shadow: -5px 0 20px rgba(0,0,0,0.1); display: flex; flex-direction: column; transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .cart-drawer.active { right: 0; }
        .cart-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .cart-close { font-size: 24px; cursor: pointer; color: #333; position: relative; z-index: 100000; }
        
        .shipping-bar { padding: 15px 20px; border-bottom: 1px solid #eee; }
        .shipping-fill { height: 6px; background: #008060; transition: width 0.5s; border-radius: 3px; }
        
        .drawer-body { flex: 1; overflow-y: auto; }
        .cart-items { padding: 20px; }
        .cart-item { display: grid; grid-template-columns: 100px 1fr auto; gap: 15px; margin-bottom: 25px; position: relative; }
        .cart-item-img { width: 100px; height: 100px; background: #f9f9f9; border-radius: 4px; display: flex; align-items: center; justify-content: center;}
        .cart-item-img img { width: 90%; mix-blend-mode: multiply; }
        .cart-qty-controls { display: flex; border: 1px solid #ddd; border-radius: 4px; width: 100px; height: 35px; margin-top: 10px; overflow: hidden; align-items: center; }
        .cart-qty-controls button { background: #f4f4f4; border: none; width: 30px; font-size: 18px; cursor: pointer; color: #333; font-weight: bold; transition: 0.2s; }
        .cart-qty-controls button:hover { background: #e0e0e0; }
        .cart-qty-controls input { flex: 1; width: 40px; border: none; text-align: center; font-weight: bold; font-size: 14px; outline: none; background: #fff; color: #333; margin: 0; padding: 0; }
        .cart-delete { color: #999; cursor: pointer; font-size: 20px; background: none; border: none; position: absolute; right: 0; bottom: 0; }

        .recommended-section { padding: 20px; border-top: 10px solid #f4f6f9; }
        .recommended-header { font-weight: bold; font-size: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .recommended-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .rec-card { text-decoration: none; color: inherit; display: block; background: #fff; }
        .rec-img-box { width: 100%; aspect-ratio: 1/1.2; background: #f9f9f9; display: flex; align-items: center; justify-content: center; border-radius: 4px; margin-bottom: 10px; position: relative; }
        .rec-img-box img { width: 85%; mix-blend-mode: multiply; }
        .rec-price { font-weight: bold; font-size: 15px; margin-bottom: 2px; }
        .rec-brand { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .rec-name { font-size: 13px; color: #666; line-height: 1.2; }

        .cart-footer { padding: 20px; border-top: 1px solid #eee; background: #fff; }
        .promo-accordion { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }
        .promo-summary { padding: 15px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .promo-content { padding: 0 15px 15px; display: none; }
        .promo-input-group { display: flex; gap: 10px; }
        .promo-input-group input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-promo { background: #333; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; }
        
        .drawer-action-row { display: flex; gap: 10px; margin-bottom: 15px; }
        .btn-drawer-outline {
            flex: 1; background: #fff; color: #333; border: 1px solid #ccc;
            padding: 14px; font-size: 14px; font-weight: bold; border-radius: 4px;
            cursor: pointer; transition: 0.3s; text-align: center; text-transform: uppercase;
        }
        .btn-drawer-outline:hover { border-color: #333; background: #f4f4f4; }
        
        .subtotal-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; margin-bottom: 20px; }
        .btn-checkout { width: 100%; background: #008060; color: white; border: none; padding: 16px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; transition: 0.3s;}
        .btn-checkout:hover { background: #00664c; }

        .floating-mini-cart {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: #fff; padding: 12px 24px; border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex;
            align-items: center; justify-content: space-between; gap: 30px;
            z-index: 9990; border: 2px solid #008060; width: max-content;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp { from { bottom: -100px; opacity: 0; } to { bottom: 30px; opacity: 1; } }
        .fmc-info { display: flex; align-items: center; gap: 15px; }
        .fmc-icon { background: #e6f2ef; color: #008060; width: 40px; height: 40px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; }
        .fmc-text { display: flex; flex-direction: column; }
        .fmc-count { font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase; }
        .fmc-total { font-size: 18px; font-weight: 900; color: #333; }
        .fmc-actions { display: flex; gap: 10px; }
        .fmc-btn-view { padding: 10px 20px; border-radius: 25px; border: 1px solid #ccc; color: #333; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; cursor: pointer; }
        .fmc-btn-view:hover { background: #f4f4f4; }
        .fmc-btn-checkout { padding: 10px 24px; border-radius: 25px; background: #008060; color: #fff; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; border: 1px solid #008060; cursor: pointer; }
        .fmc-btn-checkout:hover { background: #00664c; }
        @media (max-width: 576px) {
            .floating-mini-cart { width: 90%; flex-direction: column; border-radius: 12px; gap: 15px; padding: 15px; bottom: 20px; }
            .fmc-actions { width: 100%; }
            .fmc-btn-view, .fmc-btn-checkout { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<div class="flex-wrapper">
    <div class="detail-container">
        <div class="breadcrumb">
            <a href="catalogue.php">Catalogue</a> / <a href="catalogue.php?brand_id=<?php echo $product['Brand_Id']; ?>"><?php echo $product['Brand_Name']; ?></a> / <?php echo $product['Pro_Name']; ?>
        </div>

        <div class="product-layout">
            
            <div class="product-gallery-container">
                <div class="thumbnail-list">
                    <?php foreach($gallery_images as $idx => $g_img): ?>
                        <div class="thumb-box <?php echo $idx==0 ? 'active' : ''; ?>" onclick="changeMainImage('<?php echo $g_img; ?>', this)">
                            <img src="<?php echo $g_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="main-image-wrapper">
                    <?php if($product['Pro_Sale'] == 1): ?><div class="badge-sale">↗ TRENDING / SALE</div><?php endif; ?>
                    <button class="btn-wishlist-main" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>
                    <img src="<?php echo $gallery_images[0]; ?>" id="mainProductImage" onerror="this.src='../assets/images/placeholder.jpg'">
                </div>
            </div>
            
            <div class="product-info">
                <div class="brand-name"><?php echo $product['Brand_Name']; ?></div>
                <h1 class="product-title"><?php echo $product['Pro_Name']; ?></h1>
                <span class="current-price">RM <?php echo number_format($product['Pro_Price'], 2); ?></span>

                <?php if (count($color_variants) > 1): ?>
                    <div class="info-label">Colour: <span style="font-weight:normal; color:#666;"><?php echo $product['Pro_Colour']; ?></span></div>
                    <div class="color-variants-container">
                        <?php foreach($color_variants as $cv): 
                            $cv_img = !empty($cv['Pro_Image']) ? "../uploads/" . $cv['Pro_Image'] : "../assets/images/placeholder.jpg";
                            $isActive = ($cv['Pro_Id'] == $pro_id) ? 'active' : '';
                        ?>
                            <a href="product_details.php?pro_id=<?php echo $cv['Pro_Id']; ?>" class="color-variant-box <?php echo $isActive; ?>" title="<?php echo $cv['Pro_Colour']; ?>">
                                <img src="<?php echo $cv_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom:25px; color:#666; font-size:14px;">
                        <strong>Colour:</strong> <?php echo $product['Pro_Colour']; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom:25px; color:#666; font-size:14px;">
                    <strong>Gender:</strong> <?php echo $product['Pro_Gender']; ?>
                </div>

                <div class="info-label">Description</div>
                <p style="color:#666; line-height:1.6; margin-bottom:30px;"><?php echo nl2br($product['Pro_Description']); ?></p>

                <form action="product_details.php?pro_id=<?php echo $product['Pro_Id']; ?>" method="POST" id="addToCartForm">
                    <input type="hidden" name="pro_id" value="<?php echo $product['Pro_Id']; ?>">
                    <input type="hidden" name="selected_size" id="selectedSizeInput" value="">

                    <div class="info-label">Select Size (UK) <span id="sizeError" style="color:red; font-size:12px; display:none; margin-left:10px;">*Required</span></div>
                    <div class="size-selector">
                        <?php foreach($sizes as $sz) { $sz = trim($sz); if(!empty($sz)) echo "<div class='size-box' onclick='selectSize(this, \"$sz\")'>$sz</div>"; } ?>
                    </div>

                    <div class="info-label">Quantity</div>
                    <div style="display:flex; gap:15px; align-items:center;">
                        <div class="quantity-selector">
                            <button type="button" onclick="changeQty(-1)">−</button>
                            <input type="number" name="quantity" id="qtyInput" value="1" readonly>
                            <button type="button" onclick="changeQty(1)">+</button>
                        </div>
                        <?php if($isAdmin): ?>
                            <button type="button" class="btn-add-cart" onclick="showAdminWarning()">ADD TO BASKET</button>
                        <?php else: ?>
                            <button type="button" class="btn-add-cart" onclick="openCartDrawer()">ADD TO BASKET</button>
                        <?php endif; ?>
                    </div>
                    <small style="display:block; margin-top:10px; color:#666;">Only <span id="stockLimit"><?php echo $product['Pro_Stock_Quantity']; ?></span> left in stock.</small>
                </form>

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
                        <div class="slider-img">
                            <button class="btn-wishlist-card" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>
                            <img src="<?php echo $bs_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                        </div>
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
                        <div class="slider-img">
                            <button class="btn-wishlist-card" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>
                            <img src="<?php echo $rv_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                        </div>
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

<?php if ($mini_cart_count > 0): ?>
<div class="floating-mini-cart">
    <div class="fmc-info">
        <div class="fmc-icon"><i class="bi bi-cart-check-fill"></i></div>
        <div class="fmc-text">
            <span class="fmc-count"><?php echo $mini_cart_count; ?> Items in Cart</span>
            <span class="fmc-total">RM <?php echo number_format($mini_cart_total, 2); ?></span>
        </div>
    </div>
    <div class="fmc-actions">
        <a href="cart.php" class="fmc-btn-view" onclick="guardLink(event, 'view your cart')">View Cart</a>
        <a href="checkout.php" class="fmc-btn-checkout" onclick="guardLink(event, 'proceed to checkout')">Checkout</a>
    </div>
</div>
<?php endif; ?>

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
                        <div class="rec-img-box">
                            <button class="btn-wishlist-card" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>
                            <img src="../uploads/<?php echo $rp['Pro_Image']; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                        </div>
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
        
        <div class="drawer-action-row">
            <button type="button" class="btn-drawer-outline" onclick="submitCartForm('add')">ADD TO CART</button>
            <button type="button" class="btn-drawer-outline" onclick="if(!isLoggedIn){promptLogin('view your wishlist');}else{window.location.href='wishlist.php';}">WISHLIST</button>
        </div>

        <button type="button" class="btn-checkout" onclick="submitCartForm('checkout')">Checkout securely</button>
    </div>
</div>

<script>
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    let selectedSize = "";
    const price = <?php echo $product['Pro_Price']; ?>;
    const freeShippingLimit = 250;

    <?php if(isset($_GET['status']) && $_GET['status'] == 'added'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: "Added to Cart!",
            text: "Your item is waiting in the shopping cart.",
            icon: "success",
            confirmButtonColor: "#008060",
            confirmButtonText: "Continue Shopping"
        });
    });
    <?php endif; ?>

    function changeMainImage(src, element) {
        const mainImg = document.getElementById('mainProductImage');
        mainImg.style.opacity = 0.5; 
        
        setTimeout(() => {
            mainImg.src = src;
            mainImg.style.opacity = 1;
        }, 150);
        
        document.querySelectorAll('.thumb-box').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
    }

    function promptLogin(actionText) {
        Swal.fire({
            title: "Login Required",
            text: "Please login to " + actionText + ".",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#008060",
            cancelButtonColor: "#d33",
            confirmButtonText: "Login Now"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "../Module A/login.php"; 
            }
        });
    }

    function guardLink(event, actionText) {
        if (!isLoggedIn) {
            event.preventDefault(); 
            promptLogin(actionText);
        }
    }

    function toggleWishlist(event, element) {
        event.preventDefault(); 
        
        if (!isLoggedIn) {
            promptLogin('add items to your wishlist');
            return;
        }

        let icon = element.querySelector('i');
        if (icon.classList.contains('bi-heart')) {
            icon.classList.remove('bi-heart');
            icon.classList.add('bi-heart-fill');
            icon.style.color = '#E7352B';
            
            const Toast = Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 1500,
                timerProgressBar: true,
            });
            Toast.fire({ icon: 'success', title: 'Added to Wishlist!' });
        } else {
            icon.classList.remove('bi-heart-fill');
            icon.classList.add('bi-heart');
            icon.style.color = '#666';
        }
    }

    function selectSize(el, sz) {
        document.querySelectorAll('.size-box').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        selectedSize = sz;
        document.getElementById('selectedSizeInput').value = sz;
        document.getElementById('sizeError').style.display = 'none';
    }

    function changeQty(amt) {
        let input = document.getElementById('qtyInput');
        let val = parseInt(input.value) + amt;
        if(val >= 1 && val <= <?php echo $product['Pro_Stock_Quantity']; ?>) input.value = val;
    }
    
    function updateCartQty(amt, maxStock) {
        let drawerInput = document.getElementById('drawerQty');
        if(!drawerInput) return;
        
        let val = parseInt(drawerInput.value) + amt;
        if(val >= 1 && val <= maxStock) {
            drawerInput.value = val;
            document.getElementById('qtyInput').value = val; 
            updateCartTotals(val);
        }
    }

    function updateCartTotals(qty) {
        let subtotal = (qty * price).toFixed(2);
        
        document.getElementById('cartCount').innerText = qty;
        document.getElementById('cartSubtotalText').innerText = "RM " + subtotal;
        
        let shippingFill = document.getElementById('shippingFill');
        let shippingText = document.getElementById('shippingText');
        
        if(qty === 0) {
            shippingFill.style.width = "0%";
            shippingText.innerText = "Add items to unlock free shipping!";
        } else if(subtotal >= freeShippingLimit) {
            shippingFill.style.width = "100%";
            shippingText.innerHTML = "<span style='color:#008060;'>You qualify for Free Standard Delivery!</span>";
        } else {
            let remain = (freeShippingLimit - subtotal).toFixed(2);
            let percent = (subtotal / freeShippingLimit * 100);
            shippingFill.style.width = percent + "%";
            shippingText.innerText = `You're RM ${remain} away from free shipping.`;
        }
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

    function openCartDrawer() {
        if (!isLoggedIn) {
            promptLogin('add items to your cart');
            return;
        }

        if(!selectedSize) { document.getElementById('sizeError').style.display = 'inline'; return; }
        
        let qty = parseInt(document.getElementById('qtyInput').value);
        let stock = <?php echo $product['Pro_Stock_Quantity']; ?>;
        
        updateCartTotals(qty);

        document.getElementById('cartItemsContainer').innerHTML = `
            <div class="cart-item">
                <div class="cart-item-img"><img src="<?php echo $pro_img; ?>" onerror="this.src='../assets/images/placeholder.jpg'"></div>
                <div class="cart-item-details">
                    <div style="font-size:12px; font-weight:bold; text-transform:uppercase; color:#666;"><?php echo $product['Brand_Name']; ?></div>
                    <div style="font-weight:bold; font-size:14px; margin:4px 0;"><?php echo addslashes($product['Pro_Name']); ?></div>
                    <div style="font-size:13px; color:#666;">Size: ${selectedSize}</div>
                    <div class="cart-qty-controls">
                        <button onclick="updateCartQty(-1, ${stock})">−</button>
                        <input type="text" id="drawerQty" value="${qty}" readonly>
                        <button onclick="updateCartQty(1, ${stock})">+</button>
                    </div>
                </div>
                <div style="font-weight:bold; font-size:14px;">RM ${price.toFixed(2)}</div>
                <button class="cart-delete" onclick="this.parentElement.remove(); updateCartTotals(0); document.getElementById('qtyInput').value=1;"><i class=\"bi bi-trash3\"></i></button>
            </div>
        `;
        
        toggleCart();
    }

    function submitCartForm(actionType) {
        let form = document.getElementById('addToCartForm');
        let hiddenField = document.createElement("input");
        hiddenField.setAttribute("type", "hidden");
        
        if (actionType === 'add') {
            hiddenField.setAttribute("name", "add_to_cart");
        } else {
            hiddenField.setAttribute("name", "checkout_now");
        }
        
        hiddenField.setAttribute("value", "1");
        form.appendChild(hiddenField);
        form.submit();
    }

    function showAdminWarning() { Swal.fire({ title: "Action Denied", text: "Admins cannot purchase products.", icon: "error", confirmButtonColor: "#333" }); }
</script>

</body>
</html>