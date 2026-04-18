

<?php
// 强制开启 Session 以确保浏览记录和购物车生效
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    $add_color = isset($_POST['selected_color']) ? $_POST['selected_color'] : 'Default';
    $add_qty = intval($_POST['quantity']);

    if (!empty($add_pro_id) && !empty($add_size) && $add_qty > 0) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // 购物车唯一键值：产品ID + 尺码 + 颜色
        $cart_key = $add_pro_id . '_' . $add_size . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $add_color);
        
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['qty'] += $add_qty; 
        } else {
            $_SESSION['cart'][$cart_key] = [
                'pro_id' => $add_pro_id,
                'size' => $add_size,
                'color' => $add_color,
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

// ==========================================
// 处理删除购物车商品
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_from_cart'])) {
    if (!$is_logged_in) {
        header("Location: ../Module A/login.php");
        exit;
    }

    $cart_key_to_remove = $_POST['remove_from_cart'];
    if (isset($_SESSION['cart'][$cart_key_to_remove])) {
        unset($_SESSION['cart'][$cart_key_to_remove]);
    }
    
    $current_pro_id = intval($_POST['current_pro_id']);
    header("Location: product_details.php?pro_id=$current_pro_id");
    exit;
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

// 获取当前产品所有颜色和尺码的交叉库存图谱
$sql_stock = "SELECT Pro_Size, Pro_Colour, Quantity FROM PRODUCT_STOCK WHERE Pro_Id = '$pro_id'";
$res_stock = $conn->query($sql_stock);

$variant_map = [];
$all_unique_sizes = []; // 用于渲染尺码按钮
while($row = $res_stock->fetch_assoc()) {
    $variant_map[$row['Pro_Colour']][$row['Pro_Size']] = intval($row['Quantity']);
    if (!in_array($row['Pro_Size'], $all_unique_sizes)) {
        $all_unique_sizes[] = $row['Pro_Size'];
    }
}
sort($all_unique_sizes); // 尺码排序

$sizes = explode(',', $product['Pro_Size']);

// 如果PRODUCT_STOCK表为空，使用Pro_Size字段作为备用，默认库存为10
if (empty($size_stock_map)) {
    foreach($sizes as $sz) {
        $sz = trim($sz);
        if (!empty($sz)) {
            $size_stock_map[$sz] = 10; // 默认库存
        }
    }
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');

// 处理 Recently Viewed 逻辑
if (!isset($_SESSION['recently_viewed'])) $_SESSION['recently_viewed'] = [];
if (($key = array_search($pro_id, $_SESSION['recently_viewed'])) !== false) unset($_SESSION['recently_viewed'][$key]);
array_unshift($_SESSION['recently_viewed'], $pro_id);
if (count($_SESSION['recently_viewed']) > 8) array_pop($_SESSION['recently_viewed']);


// ==========================================
// 升级：智能同款多色画廊逻辑 (Smart Glob & Group)
// ==========================================
// 1. 分割数据库里填写的颜色 (例如: "Black, White" 或 "Black/White")
$raw_colors_str = $product['Pro_Colour'];
// 用逗号或"/"分隔，然后过滤和整理
$raw_colors = preg_split('/[,\/]/', $raw_colors_str);
$colors = [];
foreach($raw_colors as $rc) {
    $c = trim($rc);
    if (!empty($c)) {
        // 避免重复
        if (!in_array(strtolower($c), array_map('strtolower', $colors))) {
            $colors[] = $c;
        }
    }
}
if (empty($colors)) $colors[] = "Default";

$base_img = $product['Pro_Image'];
$path_parts = pathinfo($base_img);
$base_name = preg_replace('/_\d+$/', '', $path_parts['filename']);

$color_galleries = [];
foreach ($colors as $c) {
    $color_galleries[$c] = [];
}

// 3. 扫描 uploads 文件夹里所有相关图片
$all_files = glob("../uploads/{$base_name}*.*");

if ($all_files) {
    foreach ($all_files as $file_path) {
        $file_name = basename($file_path);
        $filename_only = pathinfo($file_name, PATHINFO_FILENAME);
        
        $matched_color = $colors[0]; // 默认分给第一个颜色
        
        // 智能匹配文件名里是否包含颜色的单词
        foreach ($colors as $index => $c) {
            if ($index === 0) continue; // 跳过默认色，先找特殊色
            
            $slugs = [
                strtolower(str_replace(' ', '', $c)),
                strtolower(str_replace(' ', '_', $c)),
                strtolower(explode('/', $c)[0]),
                strtolower(explode(' ', $c)[0])
            ];
            
            foreach ($slugs as $slug) {
                if (!empty($slug) && strpos(strtolower($filename_only), "_" . $slug) !== false) {
                    $matched_color = $c;
                    break 2;
                }
            }
        }
        $color_galleries[$matched_color][] = "../uploads/" . $file_name;
    }
} else {
    $color_galleries[$colors[0]][] = "../uploads/" . $base_img;
}

// 4. 补齐 4 张占位图，确保 JD Sports 风格的 2x2 网格完美呈现
foreach ($color_galleries as $c => &$images) {
    if (empty($images)) $images[] = "../images/placeholder.png";
    while (count($images) < 4) {
        $images[] = "../images/placeholder.png";
    }
}
unset($images);

$cat_id = $product['Cat_Id'];
$rec_sql = "SELECT product.*, brand.Brand_Name FROM product JOIN brand ON product.Brand_Id = brand.Brand_Id WHERE Pro_Id != '$pro_id' AND Cat_Id = '$cat_id' AND Pro_Status = 'Available' ORDER BY RAND() LIMIT 6";
$recommended_products = [];
$rec_res = $conn->query($rec_sql);
if ($rec_res && $rec_res->num_rows > 0) { while($r = $rec_res->fetch_assoc()) { $recommended_products[] = $r; } }

$bs_sql = "SELECT product.*, brand.Brand_Name 
           FROM product 
           JOIN brand ON product.Brand_Id = brand.Brand_Id 
           WHERE Pro_Id != '$pro_id' AND Pro_Status = 'Available' 
           ORDER BY Pro_Id DESC 
           LIMIT 8";
$best_sellers = [];
$bs_res = $conn->query($bs_sql);
if ($bs_res && $bs_res->num_rows > 0) { 
    while($r = $bs_res->fetch_assoc()) { 
        $best_sellers[] = $r; 
    } 
}

// 获取最近浏览过的产品
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

// 计算最大库存用于JavaScript
$max_stock_qty = 10; // 默认值
if (!empty($size_stock_map)) {
    $max_stock_qty = max($size_stock_map);
}

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
            $c_row['pro_id'] = $c_pro_id;
            $c_row['size'] = $c_item['size'];
            $c_row['color'] = isset($c_item['color']) ? $c_item['color'] : '';
            $c_row['qty'] = $c_item['qty'];
            $c_row['subtotal'] = $c_item['qty'] * $c_row['Pro_Price'];
            
            $mini_cart_total += $c_row['subtotal'];
            $mini_cart_count += $c_item['qty'];
            $mini_cart_items[] = $c_row;
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
        .detail-container { max-width: 1300px; margin: 40px auto; padding: 30px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .breadcrumb { margin-bottom: 30px; font-size: 14px; color: #666; }
        .breadcrumb a { color: #333; text-decoration: none; font-weight: bold; }
        
        .product-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 50px; }
        @media (max-width: 992px) { .product-layout { grid-template-columns: 1fr; } }

        /* ================= JD Sports 风格：2x2 图像网格排版 ================= */
        .product-gallery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .gallery-img-box { background: #f4f6f9; border-radius: 8px; overflow: hidden; aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; position: relative; }
        .gallery-img-box img { width: 90%; height: 90%; object-fit: contain; mix-blend-mode: multiply; transition: transform 0.3s; }
        .gallery-img-box:hover img { transform: scale(1.05); }
        
        .badge-sale { position: absolute; top: 15px; left: 15px; background: #ffeb3b; color: #000; font-size: 13px; font-weight: bold; padding: 6px 12px; border-radius: 4px; z-index: 5;}
        
        .brand-name { font-size: 14px; font-weight: bold; color: #FF6B00; text-transform: uppercase; margin-bottom: 5px; }
        .product-title { font-size: 32px; font-weight: 800; margin: 0 0 15px 0; line-height: 1.2; }
        .current-price { font-size: 28px; font-weight: bold; margin-bottom: 25px; display: block;}
        .info-label { font-weight: bold; display: block; margin-bottom: 10px; font-size: 15px; }
        
        /* JD Sports 风格颜色选择器 */
        .color-variants-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; }
        .color-variant-box {
            width: 70px; height: 70px; background: #f4f4f4; border: 1px solid #ddd;
            border-radius: 4px; display: flex; align-items: center; justify-content: center;
            overflow: hidden; cursor: pointer; transition: 0.2s; padding: 2px;
        }
        .color-variant-box:hover { border-color: #999; }
        .color-variant-box.active { border-color: #333; border-width: 2px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .color-variant-box img { width: 100%; height: 100%; object-fit: contain; mix-blend-mode: multiply; }
        
        .size-selector { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .size-box { width: 50px; height: 50px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; border-radius: 4px; transition: 0.2s;}
        .size-box:hover { border-color: #333; }
        .size-box.selected { background: #333; color: white; border-color: #333; }
        
        .quantity-selector { display: flex; border: 1px solid #ccc; border-radius: 4px; width: 120px; overflow: hidden; }
        .quantity-selector button { background: #f4f4f4; color: #333; font-weight: bold; width: 40px; font-size: 20px; transition: 0.2s; border: none; cursor: pointer; }
        .quantity-selector button:hover { background: #e0e0e0; color: #000; }
        .quantity-selector input { flex: 1; width: 40px; border: none; text-align: center; font-weight: bold; outline: none; background: #fff; color: #333; margin: 0; padding: 0; }
        
        .btn-add-cart {  flex: 1; background-color: #008060; color: white; padding: 15px 20px; font-weight: bold; text-transform: uppercase; border: none; border-radius: 4px; cursor: pointer; transition: 0.3s;}
        .btn-add-cart:hover { background-color: #00664c; }

        .btn-wishlist-main {
            position: absolute; top: 15px; right: 15px; background: #fff; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); font-size: 18px; color: #666;
            cursor: pointer; border: none; transition: 0.3s; z-index: 2;
        }
        .btn-wishlist-main:hover { color: #E7352B; transform: scale(1.1); }
        
        .btn-wishlist-card {
            position: absolute; top: 10px; right: 10px; background: #fff; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); font-size: 15px; color: #666;
            cursor: pointer; border: none; transition: 0.3s; z-index: 2;
        }
        .btn-wishlist-card:hover { color: #E7352B; transform: scale(1.1); }

        /* 滑块 */
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

        /* 侧边栏与悬浮车 */
        .cart-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 99998; opacity: 0; visibility: hidden; transition: 0.3s ease; pointer-events: none; }
        .cart-overlay.active { opacity: 1; visibility: visible; pointer-events: auto; }
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

        /* ================= AI 助理对话框 ================= */
        .ai-assistant-container { max-width: 1300px; margin: 60px auto 60px; padding: 0 20px; }
        .ai-chat-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-direction: column; height: 500px; }
        .ai-chat-header { background: linear-gradient(135deg, #008060 0%, #00664c 100%); color: white; padding: 15px 20px; display: flex; align-items: center; gap: 12px; font-weight: bold; }
        .ai-chat-header i { font-size: 20px; }
        .ai-minimize-btn { background: rgba(255,255,255,0.2); border: none; color: white; cursor: pointer; margin-left: auto; font-size: 18px; padding: 5px 10px; border-radius: 4px; transition: 0.2s; }
        .ai-minimize-btn:hover { background: rgba(255,255,255,0.3); }
        .ai-chat-body { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; }
        .ai-message { display: flex; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .ai-message p { margin: 0; line-height: 1.5; }
        .ai-welcome { background: #f0f7f4; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #008060; }
        .ai-welcome p { color: #333; font-size: 14px; }
        .ai-user-msg { margin-left: auto; background: #008060; color: white; padding: 12px 16px; border-radius: 8px; max-width: 75%; }
        .ai-user-msg p { color: white; font-size: 14px; }
        .ai-bot-msg { background: #f0f7f4; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #008060; max-width: 75%; }
        .ai-bot-msg p { color: #333; font-size: 14px; }
        .ai-size-suggestion { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 8px; }
        .ai-size-suggestion p { color: #856404; font-size: 14px; margin: 5px 0; }
        .ai-size-suggestion strong { color: #ff6b00; }
        .ai-chat-footer { padding: 15px 20px; background: #f9f9f9; border-top: 1px solid #eee; display: flex; gap: 10px; }
        .ai-input { flex: 1; padding: 12px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; transition: 0.2s; }
        .ai-input:focus { border-color: #008060; box-shadow: 0 0 8px rgba(0,128,96,0.1); }
        .ai-send-btn { background: #008060; color: white; border: none; width: 44px; height: 44px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .ai-send-btn:hover { background: #00664c; }
        .ai-send-btn i { font-size: 18px; }
        .ai-chat-body::-webkit-scrollbar { width: 6px; }
        .ai-chat-body::-webkit-scrollbar-track { background: #f1f1f1; }
        .ai-chat-body::-webkit-scrollbar-thumb { background: #008060; border-radius: 3px; }
        .ai-chat-body::-webkit-scrollbar-thumb:hover { background: #00664c; }
    </style>
</head>
<body>

<div class="flex-wrapper">
    <div class="detail-container">
        <div class="breadcrumb">
            <a href="catalogue.php">Catalogue</a> / <a href="catalogue.php?brand_id=<?php echo $product['Brand_Id']; ?>"><?php echo $product['Brand_Name']; ?></a> / <?php echo $product['Pro_Name']; ?>
        </div>

        <div class="product-layout">
            
            <div class="product-gallery-grid" id="mainGalleryGrid">
                <div style="grid-column: 1/-1; text-align: center; color: #999; padding: 100px;">Loading images...</div>
            </div>
            
            <div class="product-info">
                <div class="brand-name"><?php echo $product['Brand_Name']; ?></div>
                <h1 class="product-title"><?php echo $product['Pro_Name']; ?></h1>
                <span class="current-price">RM <?php echo number_format($product['Pro_Price'], 2); ?></span>

                <div style="margin-bottom:10px;">
                    <strong>Colour:</strong> <span id="selectedColorText" style="font-weight:normal; color:#666;"><?php echo htmlspecialchars($colors[0]); ?></span>
                </div>
                <div class="color-variants-container">
                    <?php foreach($colors as $idx => $c): ?>
                        <div class="color-variant-box <?php echo $idx==0 ? 'active' : ''; ?>" onclick="selectColor(this, '<?php echo htmlspecialchars($c); ?>')" title="<?php echo htmlspecialchars($c); ?>">
                            <img src="<?php echo $color_galleries[$c][0]; ?>" onerror="this.src='../images/placeholder.png'">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-bottom:25px; color:#666; font-size:14px;">
                    <strong>Gender:</strong> <?php echo $product['Pro_Gender']; ?>
                </div>

                <div class="info-label">Description</div>
                <p style="color:#666; line-height:1.6; margin-bottom:30px;"><?php echo nl2br($product['Pro_Description']); ?></p>

                <form action="product_details.php?pro_id=<?php echo $product['Pro_Id']; ?>" method="POST" id="addToCartForm">
                    <input type="hidden" name="pro_id" value="<?php echo $product['Pro_Id']; ?>">
                    <input type="hidden" name="selected_size" id="selectedSizeInput" value="">
                    
                    <input type="hidden" name="selected_color" id="selectedColorInput" value="<?php echo htmlspecialchars($colors[0]); ?>">

                <div class="info-label">Select Size (UK) <span id="sizeError" style="color:red; font-size:12px; display:none; margin-left:10px;">*Required</span></div>
                <div class="size-selector" id="sizeSelectorContainer">
                    <?php foreach($all_unique_sizes as $sz): ?>
                        <div class='size-box' data-size='<?php echo $sz; ?>' onclick='handleSizeClick(this, "<?php echo $sz; ?>")'>
                            <?php echo $sz; ?>
                        </div>
                    <?php endforeach; ?>
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
                            <button type="button" class="btn-add-cart" id="addToBasketBtn" onclick="addToCartAndOpen.call(this)">ADD TO BASKET</button>
                        <?php endif; ?>
                    </div>
                    <small style="display:block; margin-top:10px; color:#666;">
                    <i class="bi bi-info-circle me-1"></i>
                    <span id="stockDisplay">Please select a size to check availability.</span>
                    </small>
                </form>

            </div>
        </div>
    </div>

    <!-- AI助理对话框 -->
    <div class="ai-assistant-container">
        <div class="ai-chat-box">
            <div class="ai-chat-header">
                <i class="bi bi-robot"></i>
                <span>Size Assistant</span>
                <button class="ai-minimize-btn" onclick="toggleAIChat()"><i class="bi bi-dash-lg"></i></button>
            </div>
            <div class="ai-chat-body" id="aiChatBody">
                <div class="ai-message ai-welcome">
                    <p><strong>Hi! 👟</strong></p>
                    <p>Not sure about your size? I can help! Tell me your height or ask any questions.</p>
                </div>
            </div>
            <div class="ai-chat-footer">
                <input type="text" id="aiChatInput" class="ai-input" placeholder="not sure your size? Type your height (cm) or ask..." onkeypress="if(event.key==='Enter') sendAIMessage()">
                <button class="ai-send-btn" onclick="sendAIMessage()"><i class="bi bi-send-fill"></i></button>
            </div>
        </div>
    </div>
    
    <div class="sliders-wrapper">
        
        <?php if(!empty($recently_viewed_products)): ?>
        <div class="slider-section">
            <div class="slider-header">
                <i class="bi bi-chevron-left" onclick="slideCarousel('recentSlider', -1)"></i>
                <span><?php echo $recent_section_title; ?></span>
                <i class="bi bi-chevron-right" onclick="slideCarousel('recentSlider', 1)"></i>
            </div>
            <div class="slider-container" id="recentSlider">
                <?php foreach($recently_viewed_products as $rv): 
                    $rv_img = !empty($rv['Pro_Image']) ? "../uploads/" . $rv['Pro_Image'] : "../images/placeholder.png";
                ?>
                    <a href="product_details.php?pro_id=<?php echo $rv['Pro_Id']; ?>" class="slider-card">
                        <div class="slider-img">
                            <button class="btn-wishlist-card" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>
                            <img src="<?php echo $rv_img; ?>" onerror="this.src='../images/placeholder.png'">
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
        <a href="cart.php" class="fmc-btn-checkout" onclick="guardLink(event, 'proceed to checkout')">Checkout</a>
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
                            <img src="../uploads/<?php echo $rp['Pro_Image']; ?>" onerror="this.src='../images/placeholder.png'">
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
        <div class="subtotal-row mt-3">
            <span>Subtotal:</span>
            <span id="cartSubtotalText">RM 0.00</span>
        </div>
        
        <div class="drawer-action-row mt-3">
            <button type="button" class="btn-drawer-outline" onclick="toggleCart()">CLOSE</button>
            <button type="button" class="btn-drawer-outline" onclick="if(!isLoggedIn){promptLogin('view your wishlist');}else{window.location.href='wishlist.php';}">WISHLIST</button>
        </div>

        <button type="button" class="btn-checkout" onclick="submitCartForm('checkout')">Checkout securely</button>
    </div>
</div>

<script>
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const price = <?php echo $product['Pro_Price']; ?>;
    const freeShippingLimit = 250;
    const isSale = <?php echo $product['Pro_Sale']; ?>;

    // 获取 PHP 生成的 JSON 画廊数据
    const colorGalleries = <?php echo json_encode($color_galleries); ?>;
    const sizeStockMap = <?php echo json_encode($size_stock_map); ?>;
    const maxStockQty = <?php echo $max_stock_qty; ?>;
    const miniCartItems = <?php echo json_encode($mini_cart_items); ?>;
    const currentProId = <?php echo $pro_id; ?>;
    // 将 PHP 的数组传给 JavaScript
    const variantMap = <?php echo json_encode($variant_map); ?>;
    let selectedColor = "<?php echo htmlspecialchars($colors[0]); ?>";
    let selectedSize = "";

    // ==========================================
    // 动态渲染 2x2 JD Sports 风格网格
    // ==========================================
    function renderGallery(color) {
        const grid = document.getElementById('mainGalleryGrid');
        const images = colorGalleries[color] || [];
        let html = '';
        
        images.forEach((imgSrc, idx) => {
            let badges = '';
            if (idx === 0) {
                if (isSale === 1) badges += '<div class="badge-sale">↗ TRENDING / SALE</div>';
                badges += `<button class="btn-wishlist-main" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>`;
            }
            html += `
                <div class="gallery-img-box">
                    ${badges}
                    <img src="${imgSrc}" onerror="this.src='../images/placeholder.png'">
                </div>
            `;
        });
        grid.innerHTML = html;
    }

    // 切换颜色触发器
    function selectColor(el, color) {
        document.querySelectorAll('.color-variant-box').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        selectedColor = color;
        document.getElementById('selectedColorText').innerText = color;
        document.getElementById('selectedColorInput').value = color;

        refreshSizeButtons(); // 切换颜色后刷新尺码
        renderGallery(color);
    }

    // 页面加载完成后立刻渲染第一种颜色
    document.addEventListener('DOMContentLoaded', () => {
        renderGallery(selectedColor);
        refreshSizeButtons(); // 初始化尺码按钮状态
        
        // 检查是否需要自动打开购物车
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status') && urlParams.get('status') === 'added') {
            // 延迟打开购物车，让页面先完全加载
            setTimeout(() => {
                openCartDrawer();
            }, 500);
        }
    });


    function promptLogin(actionText) {
        Swal.fire({
            title: "Login Required", text: "Please login to " + actionText + ".", icon: "warning",
            showCancelButton: true, confirmButtonColor: "#008060", cancelButtonColor: "#d33", confirmButtonText: "Login Now"
        }).then((result) => { if (result.isConfirmed) { window.location.href = "../Module A/login.php"; } });
    }

    function guardLink(event, actionText) {
        if (!isLoggedIn) { event.preventDefault(); promptLogin(actionText); }
    }

    function toggleWishlist(event, element) {
        event.preventDefault(); 
        if (!isLoggedIn) { promptLogin('add items to your wishlist'); return; }
        let icon = element.querySelector('i');
        if (icon.classList.contains('bi-heart')) {
            icon.classList.remove('bi-heart'); icon.classList.add('bi-heart-fill'); icon.style.color = '#E7352B';
            Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 }).fire({ icon: 'success', title: 'Added to Wishlist!' });
        } else {
            icon.classList.remove('bi-heart-fill'); icon.classList.add('bi-heart'); icon.style.color = '#666';
        }
    }

    // 核心函数：根据当前选中的颜色，更新尺码按钮的状态
    function refreshSizeButtons() {
        const currentColorStock = variantMap[selectedColor] || {};
        const sizeBoxes = document.querySelectorAll('.size-box');

        sizeBoxes.forEach(box => {
            const size = box.getAttribute('data-size');
            const stock = currentColorStock[size] || 0;

            if (stock > 0) {
                box.classList.remove('out-of-stock');
                box.style.opacity = "1";
                box.style.cursor = "pointer";
                box.style.pointerEvents = "auto";
            } else {
                box.classList.add('out-of-stock');
                box.style.opacity = "0.3";
                box.style.cursor = "not-allowed";
                box.style.pointerEvents = "none";
                // 如果当前选中的尺码在该颜色下没货了，取消选中
                if (selectedSize === size) {
                    box.classList.remove('selected');
                    selectedSize = "";
                    document.getElementById('selectedSizeInput').value = "";
                    document.getElementById('stockDisplay').innerHTML = `<span style="color:#DC3545;">Sorry, size ${size} is out of stock in ${selectedColor}.</span>`;
                }
            }
        });
    }

    function handleSizeClick(el, sz) {
        const stock = (variantMap[selectedColor] || {})[sz] || 0;
        if (stock <= 0) return;

        document.querySelectorAll('.size-box').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        selectedSize = sz;
        document.getElementById('selectedSizeInput').value = sz;
        
        const stockDisplay = document.getElementById('stockDisplay');
        stockDisplay.innerHTML = `Only <strong>${stock}</strong> pairs left in stock for ${selectedColor} (Size ${sz}).`;
        document.getElementById('qtyInput').max = stock;
        document.getElementById('sizeError').style.display = 'none';
    }

    function changeQty(amt) {
        let input = document.getElementById('qtyInput');
        let val = parseInt(input.value) + amt;
        let maxQty = maxStockQty;
        if (selectedSize && sizeStockMap[selectedSize]) {
            maxQty = sizeStockMap[selectedSize];
        }
        if(val >= 1 && val <= maxQty) input.value = val;
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
        let existingSubtotal = 0;
        let existingQty = 0;
        if (miniCartItems && miniCartItems.length > 0) {
            miniCartItems.forEach(item => {
                existingSubtotal += item.subtotal;
                existingQty += item.qty;
            });
        }
        
        let currentSubtotal = qty * price;
        
        let totalQty = existingQty + qty;
        let totalSubtotal = (existingSubtotal + currentSubtotal).toFixed(2);
        
        document.getElementById('cartCount').innerText = totalQty;
        document.getElementById('cartSubtotalText').innerText = "RM " + totalSubtotal;
        
        let shippingFill = document.getElementById('shippingFill');
        let shippingText = document.getElementById('shippingText');
        
        if(totalSubtotal === 0 || totalSubtotal == 0) {
            shippingFill.style.width = "0%"; shippingText.innerText = "Add items to unlock free shipping!";
        } else if(totalSubtotal >= freeShippingLimit) {
            shippingFill.style.width = "100%"; shippingText.innerHTML = "<span style='color:#008060;'>You qualify for Free Standard Delivery!</span>";
        } else {
            let remain = (freeShippingLimit - totalSubtotal).toFixed(2);
            shippingFill.style.width = (totalSubtotal / freeShippingLimit * 100) + "%";
            shippingText.innerText = `You're RM ${remain} away from free shipping.`;
        }
    }

    function toggleCart() {
        document.getElementById('cartOverlay').classList.toggle('active');
        document.getElementById('cartDrawer').classList.toggle('active');
    }

    function viewShoppingCart() {
        // 用于从header点击购物车icon时打开抽屉（只显示已有商品，不显示当前产品）
        if (!isLoggedIn) { promptLogin('view your cart'); return; }
        
        let existingItemsHtml = '';
        if (miniCartItems && miniCartItems.length > 0) {
            miniCartItems.forEach((item, index) => {
                let itemImg = item.Pro_Image ? '../uploads/' + item.Pro_Image : '../images/placeholder.png';
                let itemPrice = parseFloat(item.Pro_Price) || 0;
                let cartKey = item.pro_id + '_' + item.size + '_' + item.color.replace(/[^a-zA-Z0-9]/g, '');
                
                existingItemsHtml += `
                    <div class="cart-item" id="cart-item-${index}">
                        <div class="cart-item-img"><img src="${itemImg}" onerror="this.src='../images/placeholder.png'"></div>
                        <div class="cart-item-details">
                            <div style="font-weight:bold; font-size:14px; margin:4px 0; line-height:1.3;">${item.Pro_Name}</div>
                            <div style="font-size:13px; color:#666; margin-top:5px;">
                                Size: <strong>${item.size}</strong> | Col: <strong>${item.color}</strong>
                            </div>
                            <div style="font-size:13px; color:#666; margin-top:2px;">Qty: <strong>${item.qty}</strong></div>
                            <div class="cart-qty-controls">
                                <button type="button" onclick="updateExistingItemQty('${cartKey}', ${index}, -1, 100)">−</button>
                                <input type="text" value="${item.qty}" readonly>
                                <button type="button" onclick="updateExistingItemQty('${cartKey}', ${index}, 1, 100)">+</button>
                            </div>
                        </div>
                        <div style="font-weight:bold; font-size:14px;">RM ${(item.subtotal).toFixed(2)}</div>
                        <button class="cart-delete" onclick="removeFromCart('${cartKey}', ${currentProId})"><i class="bi bi-trash3"></i></button>
                    </div>
                `;
            });
        }

        let totalExistingQty = 0;
        let totalExistingSubtotal = 0;
        if (miniCartItems && miniCartItems.length > 0) {
            miniCartItems.forEach(item => {
                totalExistingQty += item.qty;
                totalExistingSubtotal += item.subtotal;
            });
        }

        document.getElementById('cartCount').innerText = totalExistingQty;
        document.getElementById('cartSubtotalText').innerText = 'RM ' + totalExistingSubtotal.toFixed(2);
        
        let shippingFill = document.getElementById('shippingFill');
        let shippingText = document.getElementById('shippingText');
        
        if(totalExistingSubtotal === 0 || totalExistingSubtotal == 0) {
            shippingFill.style.width = "0%"; 
            shippingText.innerText = "Add items to unlock free shipping!";
        } else if(totalExistingSubtotal >= freeShippingLimit) {
            shippingFill.style.width = "100%"; 
            shippingText.innerHTML = "<span style='color:#008060;'>You qualify for Free Standard Delivery!</span>";
        } else {
            let remain = (freeShippingLimit - totalExistingSubtotal).toFixed(2);
            shippingFill.style.width = (totalExistingSubtotal / freeShippingLimit * 100) + "%";
            shippingText.innerText = `You're RM ${remain} away from free shipping.`;
        }

        document.getElementById('cartItemsContainer').innerHTML = existingItemsHtml;
        
        toggleCart();
    }

    function slideCarousel(id, direction) {
        const container = document.getElementById(id);
        container.scrollBy({ left: direction * 260, behavior: 'smooth' });
    }

    function openCartDrawer() {
        if (!isLoggedIn) { promptLogin('add items to your cart'); return; }
        if(!selectedSize) { document.getElementById('sizeError').style.display = 'inline'; return; }
        
        let qty = parseInt(document.getElementById('qtyInput').value);
        
        let stock = parseInt(document.getElementById('qtyInput').max); 
        
        updateCartTotals(qty);

        let currentThumb = colorGalleries[selectedColor] ? colorGalleries[selectedColor][0] : '../images/placeholder.png';

        let existingItemsHtml = '';
        if (miniCartItems && miniCartItems.length > 0) {
            miniCartItems.forEach((item, index) => {
                let itemImg = item.Pro_Image ? '../uploads/' + item.Pro_Image : '../images/placeholder.png';
                let itemPrice = parseFloat(item.Pro_Price) || 0;
                // 生成购物车键值：用于识别要删除的商品（与后端同样的逻辑）
                let cartKey = item.pro_id + '_' + item.size + '_' + item.color.replace(/[^a-zA-Z0-9]/g, '');
                
                existingItemsHtml += `
                    <div class="cart-item" id="cart-item-${index}">
                        <div class="cart-item-img"><img src="${itemImg}" onerror="this.src='../images/placeholder.png'"></div>
                        <div class="cart-item-details">
                            <div style="font-weight:bold; font-size:14px; margin:4px 0; line-height:1.3;">${item.Pro_Name}</div>
                            <div style="font-size:13px; color:#666; margin-top:5px;">
                                Size: <strong>${item.size}</strong> | Col: <strong>${item.color}</strong>
                            </div>
                            <div style="font-size:13px; color:#666; margin-top:2px;">Qty: <strong>${item.qty}</strong></div>
                            <div class="cart-qty-controls">
                                <button type="button" onclick="updateExistingItemQty('${cartKey}', ${index}, -1, 100)">−</button>
                                <input type="text" value="${item.qty}" readonly>
                                <button type="button" onclick="updateExistingItemQty('${cartKey}', ${index}, 1, 100)">+</button>
                            </div>
                        </div>
                        <div style="font-weight:bold; font-size:14px;">RM ${(item.subtotal).toFixed(2)}</div>
                        <button class="cart-delete" onclick="removeFromCart('${cartKey}', ${currentProId})"><i class="bi bi-trash3"></i></button>
                    </div>
                `;
            });
        }

        let currentItemHtml = `
            <div class="cart-item">
                <div class="cart-item-img"><img src="${currentThumb}" onerror="this.src='../images/placeholder.png'"></div>
                <div class="cart-item-details">
                    <div style="font-weight:bold; font-size:14px; margin:4px 0; line-height:1.3;"><?php echo addslashes($product['Pro_Name']); ?></div>
                    
                    <div style="font-size:13px; color:#666; margin-top:5px;">
                        Size: <strong>${selectedSize}</strong> | Col: <strong>${selectedColor}</strong>
                    </div>
                    
                    <div class="cart-qty-controls">
                        <button onclick="updateCartQty(-1, ${stock})">−</button>
                        <input type="text" id="drawerQty" value="${qty}" readonly>
                        <button onclick="updateCartQty(1, ${stock})">+</button>
                    </div>
                </div>
                <div style="font-weight:bold; font-size:14px;">RM ${price.toFixed(2)}</div>
                <button class="cart-delete" onclick="this.parentElement.remove(); updateCartTotals(0); document.getElementById('qtyInput').value=1;"><i class="bi bi-trash3"></i></button>
            </div>
        `;

        document.getElementById('cartItemsContainer').innerHTML = existingItemsHtml + currentItemHtml;
        
        toggleCart();
    }

    function removeFromCart(cartKey, proId) {
        // 使用 AJAX 而不是刷新页面，保持 drawer 打开
        fetch('product_details.php?pro_id=' + proId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'remove_from_cart=' + encodeURIComponent(cartKey) + '&current_pro_id=' + proId
        })
        .then(response => response.text())
        .then(data => {
            // 刷新页面来更新购物车显示
            location.reload();
        })
        .catch(error => console.error('Error:', error));
    }

    function updateExistingItemQty(cartKey, itemIndex, change, maxStock) {
        // 获取该商品的输入框
        let itemDiv = document.getElementById('cart-item-' + itemIndex);
        if (!itemDiv) return;
        
        let qtyInput = itemDiv.querySelector('.cart-qty-controls input');
        let currentQty = parseInt(qtyInput.value);
        let newQty = currentQty + change;
        
        if (newQty >= 1 && newQty <= maxStock) {
            qtyInput.value = newQty;
            
            // 从 miniCartItems 中获取该商品的信息
            let item = miniCartItems[itemIndex];
            if (!item) return;
            
            let itemPrice = parseFloat(item.Pro_Price) || 0;
            let newSubtotal = newQty * itemPrice;
            
            // 更新该商品显示的小计价格（找到最后一个bold div，即价格显示）
            let allDivs = itemDiv.querySelectorAll('div');
            let priceDiv = null;
            allDivs.forEach(div => {
                if (div.style.fontWeight === 'bold' && div.style.fontSize === '14px' && 
                    !div.classList.contains('cart-item-details') && 
                    !div.querySelector('.cart-qty-controls')) {
                    priceDiv = div;
                }
            });
            if (priceDiv) {
                priceDiv.innerText = 'RM ' + newSubtotal.toFixed(2);
            }
            
            // 重新计算购物车所有商品的总数量和总价
            let totalExistingQty = 0;
            let totalExistingSubtotal = 0;
            
            // 遍历所有已有购物车项，计算总数和总价
            miniCartItems.forEach((cartItem, idx) => {
                let cartItemDiv = document.getElementById('cart-item-' + idx);
                if (cartItemDiv) {
                    let itemQtyInput = cartItemDiv.querySelector('.cart-qty-controls input');
                    if (itemQtyInput) {
                        let itemQty = parseInt(itemQtyInput.value);
                        let itemPr = parseFloat(cartItem.Pro_Price) || 0;
                        totalExistingQty += itemQty;
                        totalExistingSubtotal += itemQty * itemPr;
                    }
                }
            });
            
            // 获取正在添加商品的数量
            let mainFormQty = parseInt(document.getElementById('qtyInput').value);
            let mainFormPrice = price; // 全局变量
            
            // 总计
            let totalQty = totalExistingQty + mainFormQty;
            let totalSubtotal = (totalExistingSubtotal + mainFormQty * mainFormPrice).toFixed(2);
            
            // 更新UI显示
            document.getElementById('cartCount').innerText = totalQty;
            document.getElementById('cartSubtotalText').innerText = 'RM ' + totalSubtotal;
            
            // 更新运费状态
            let shippingFill = document.getElementById('shippingFill');
            let shippingText = document.getElementById('shippingText');
            
            let numSubtotal = parseFloat(totalSubtotal);
            if(numSubtotal == 0 || totalSubtotal === '0.00') {
                shippingFill.style.width = "0%"; 
                shippingText.innerText = "Add items to unlock free shipping!";
            } else if(numSubtotal >= freeShippingLimit) {
                shippingFill.style.width = "100%"; 
                shippingText.innerHTML = "<span style='color:#008060;'>You qualify for Free Standard Delivery!</span>";
            } else {
                let remain = (freeShippingLimit - numSubtotal).toFixed(2);
                shippingFill.style.width = (numSubtotal / freeShippingLimit * 100) + "%";
                shippingText.innerText = `You're RM ${remain} away from free shipping.`;
            }
        }
    }

    function addToCartAndOpen() {
        // 验证是否选择了size
        if (!selectedSize) {
            document.getElementById('sizeError').style.display = 'inline';
            Swal.fire({ icon: 'warning', title: 'Select Size', text: 'Please select a size before adding to basket' });
            return;
        }

        // 隐藏错误提示
        document.getElementById('sizeError').style.display = 'none';

        // 禁用按钮防止重复提交
        this.disabled = true;
        this.style.opacity = '0.6';
        let originalText = this.textContent;
        this.textContent = 'Adding...';
        
        // 使用AJAX提交表单
        let formData = new FormData(document.getElementById('addToCartForm'));
        formData.append('add_to_cart', '1');
        let btn = this; // 保存按钮引用

        fetch('product_details.php?pro_id=<?php echo $product['Pro_Id']; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // 显示成功提示后刷新页面
            Swal.fire({
                title: 'Added to Cart!',
                text: 'Your item is waiting in the shopping cart.',
                icon: 'success',
                confirmButtonColor: '#008060',
                confirmButtonText: 'Continue Shopping',
                timer: 1500,
                didClose: () => {
                    // 刷新页面让PHP重新生成miniCartItems，然后自动打开购物车
                    window.location.href = 'product_details.php?pro_id=<?php echo $product["Pro_Id"]; ?>&status=added';
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.textContent = originalText;
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to add to cart' });
        });
    }

    function submitCartForm(actionType) {
        let form = document.getElementById('addToCartForm');
        let hiddenField = document.createElement("input");
        hiddenField.setAttribute("type", "hidden");
        hiddenField.setAttribute("name", actionType === 'add' ? "add_to_cart" : "checkout_now");
        hiddenField.setAttribute("value", "1");
        form.appendChild(hiddenField);
        form.submit();
    }

    function showAdminWarning() { Swal.fire({ title: "Action Denied", text: "Admins cannot purchase products.", icon: "error" }); }

    // ==========================================
    // AI 助理对话功能
    // ==========================================
    const sizeConversionMap = {
        // 女性：身高 (cm) -> 鞋码对应范围
        female: [
            { minHeight: 150, maxHeight: 155, size: 4, label: "XS (Size 4)" },
            { minHeight: 155, maxHeight: 160, size: 5, label: "XS (Size 5)" },
            { minHeight: 160, maxHeight: 165, size: 6, label: "S (Size 6)" },
            { minHeight: 165, maxHeight: 170, size: 7, label: "M (Size 7)" },
            { minHeight: 170, maxHeight: 175, size: 8, label: "L (Size 8)" },
            { minHeight: 175, maxHeight: 180, size: 9, label: "XL (Size 9)" },
            { minHeight: 180, maxHeight: 190, size: 10, label: "XXL (Size 10)" }
        ],
        // 男性：身高 (cm) -> 鞋码对应范围
        male: [
            { minHeight: 160, maxHeight: 165, size: 6, label: "S (Size 6)" },
            { minHeight: 165, maxHeight: 170, size: 7, label: "M (Size 7)" },
            { minHeight: 170, maxHeight: 175, size: 8, label: "M (Size 8)" },
            { minHeight: 175, maxHeight: 180, size: 9, label: "L (Size 9)" },
            { minHeight: 180, maxHeight: 185, size: 10, label: "L (Size 10)" },
            { minHeight: 185, maxHeight: 190, size: 11, label: "XL (Size 11)" },
            { minHeight: 190, maxHeight: 200, size: 12, label: "XXL (Size 12)" }
        ]
    };

    // ==========================================
    // 智能 AI 助理对话功能 (已连通 Gemini 后端)
    // ==========================================
    async function sendAIMessage() {
        const input = document.getElementById('aiChatInput');
        const userMessage = input.value.trim();
        
        if (!userMessage) return;
        
        // 1. 显示用户消息
        appendUserMessage(userMessage);
        input.value = '';
        
        const chatBody = document.getElementById('aiChatBody');

        // 2. 显示"正在思考"状态
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'ai-message ai-bot-msg';
        loadingDiv.innerHTML = `<p><i class="bi bi-three-dots"></i> Assistant is thinking...</p>`;
        chatBody.appendChild(loadingDiv);
        chatBody.scrollTop = chatBody.scrollHeight;

        try {
            const response = await fetch('../Module B/gemini_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: userMessage })
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            // 4. 更新 AI 的回复
            loadingDiv.innerHTML = `<p>${formatBotResponse(data.reply)}</p>`;
        } catch (error) {
            loadingDiv.innerHTML = `<p style="color: #DC3545;">Sorry, I'm having trouble connecting to the brain. Please try again later.</p>`;
            console.error('Chatbot Error:', error);
        }
        
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function appendUserMessage(msg) {
        const chatBody = document.getElementById('aiChatBody');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'ai-message ai-user-msg';
        msgDiv.innerHTML = `<p>${escapeHtml(msg)}</p>`;
        chatBody.appendChild(msgDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // 处理 AI 返回的 Markdown 或换行符，使其在 HTML 中显示美观
    function formatBotResponse(text) {
        if (!text) return "No response received.";
        // 简单的 Markdown 处理：将 **text** 转换为 <strong>text</strong>
        let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // 将换行符转换为 <br>
        formatted = formatted.replace(/\n/g, '<br>');
        return formatted;
    }

    function toggleAIChat() {
        const chatBox = document.querySelector('.ai-chat-box');
        chatBox.style.height = chatBox.style.height === '50px' ? '500px' : '50px';
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
</script>

</body>
</html>