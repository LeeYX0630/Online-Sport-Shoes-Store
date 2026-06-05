<?php
// 强制开启 Session 以确保浏览记录和购物车生效
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$uid = $is_logged_in ? $_SESSION['user_id'] : null;

include '../includes/db_connection.php';
require_once '../includes/material_configs.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    // Validate CSRF token
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== ($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }

    // Validate and sanitize input
    $add_pro_id = isset($_POST['pro_id']) ? intval($_POST['pro_id']) : 0;
    $add_size = isset($_POST['selected_size']) ? trim($_POST['selected_size']) : '';
    $add_qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $add_color = isset($_POST['selected_color']) ? trim($_POST['selected_color']) : '';
    $design_id = isset($_POST['custom_design_id']) ? trim($_POST['custom_design_id']) : '';

    // Validate required fields
    if ($add_pro_id <= 0 || empty($add_size) || $add_qty <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input parameters']);
        exit;
    }

    if (!empty($add_pro_id) && !empty($add_size)) {
        if ($add_pro_id == 16 || $add_pro_id == 17) {
            $db_color_key = 'Default';
        } else {
            $db_color_key = $add_color;
        }
        $stmt_check_stock = $conn->prepare("SELECT Quantity FROM product_stock WHERE Pro_Id = ? AND Pro_Size = ? AND Pro_Colour = ?");
        $stmt_check_stock->bind_param("iss", $add_pro_id, $add_size, $db_color_key);
        $stmt_check_stock->execute();
        $res_stock = $stmt_check_stock->get_result();
        $stock_row = $res_stock->fetch_assoc();
        $real_stock = ($stock_row) ? intval($stock_row['Quantity']) : 0;

        if ($real_stock <= 0) {
            echo "<script>alert('Error: This size is currently out of stock for the selected colour.'); window.location.href='product_details.php?pro_id=$add_pro_id';</script>";
            exit;
        }
        
        if ($add_qty > $real_stock) {
            $add_qty = $real_stock; // 自动修正为最大库存
        }
        $design_data = null;

        // 1. 尝试从 Session 找设计
        if (!empty($design_id) && isset($_SESSION['saved_designs'][$add_pro_id][$design_id])) {
            $design_data = $_SESSION['saved_designs'][$add_pro_id][$design_id];
        } 
        // 2. 如果已登录且 Session 没找到，尝试从数据库找 (使用预处理语句)
        elseif (!empty($design_id) && $is_logged_in) {
            $stmt_check = $conn->prepare("SELECT Design_JSON, Preview_Image FROM user_saved_designs WHERE Design_Id = ? AND User_Id = ?");
            $stmt_check->bind_param("si", $design_id, $uid);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            if ($res_check && $res_check->num_rows > 0) {
                $row = $res_check->fetch_assoc();
                $design_data = [
                    'design_details' => $row['Design_JSON'],
                    'custom_preview' => $row['Preview_Image']
                ];
            }
            $stmt_check->close();
        }

        if ($design_data) {
            $final_price = calculateCustomDesignPrice($design_data['design_details']);
            
            $cart_key = 'custom_' . $design_id . '_' . $add_size;
            $_SESSION['cart'][$cart_key] = [
                'pro_id' => $add_pro_id,
                'size' => $add_size,
                'qty' => $add_qty,
                'color' => 'Custom Design',
                'price' => $final_price, // 【关键】：存入计算后的价格
                'design_details' => $design_data['design_details'],
                'custom_preview' => $design_data['custom_preview']
            ];
        } else {
            // 情况 B: 添加普通配色
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
        }

        if (isset($_POST['checkout_now'])) {
            header("Location: cart.php"); 
        } else {
            header("Location: product_details.php?pro_id=$add_pro_id&status=added"); 
        }
        exit;
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../includes/header.php';

// 1. 检查 URL 是否带了产品 ID
if (!isset($_GET['pro_id']) || empty($_GET['pro_id'])) {
    echo "<script>alert('No Product Selected'); window.location.href='catalogue.php';</script>";
    exit;
}

$pro_id = intval($_GET['pro_id']); 

// 2. 获取当前产品数据 (使用预处理语句)
$stmt_pro = $conn->prepare("SELECT product.*, brand.Brand_Name 
                            FROM product 
                            JOIN brand ON product.Brand_Id = brand.Brand_Id 
                            WHERE Pro_Id = ? AND Pro_Status = 'Available'");
$stmt_pro->bind_param("i", $pro_id);
$stmt_pro->execute();
$res_pro = $stmt_pro->get_result();

if ($res_pro->num_rows == 0) {
    echo "<div style='padding:50px; text-align:center;'>Product not found or unavailable.</div>";
    include '../includes/footer.php';
    exit;
}
$product = $res_pro->fetch_assoc();
$stmt_pro->close();

$is_in_wishlist = false;
if ($is_logged_in) {
    $stmt_wish_check = $conn->prepare("SELECT Wishlist_Id FROM wishlist WHERE User_Id = ? AND Pro_Id = ?");
    $stmt_wish_check->bind_param("ii", $uid, $pro_id);
    $stmt_wish_check->execute();
    $res_wish_check = $stmt_wish_check->get_result();
    if ($res_wish_check->num_rows > 0) {
        $is_in_wishlist = true;
    }
    $stmt_wish_check->close();
}

$raw_colors_str = $product['Pro_Colour'];
$raw_colors = preg_split('/[,\/]/', $raw_colors_str);
$colors = [];
foreach($raw_colors as $rc) {
    $c = trim($rc);
    if (!empty($c)) {
        if (!in_array(strtolower($c), array_map('strtolower', $colors))) {
            $colors[] = $c;
        }
    }
}
if (empty($colors)) $colors[] = "Default";

$stmt_stock = $conn->prepare("SELECT Pro_Size, Pro_Colour, Quantity FROM product_stock WHERE Pro_Id = ?");
$stmt_stock->bind_param("i", $pro_id);
$stmt_stock->execute();
$res_stock = $stmt_stock->get_result();

$variant_map = [];
$all_unique_sizes = []; 
while($row = $res_stock->fetch_assoc()) {
    $db_colour = $row['Pro_Colour'];
    $db_size = $row['Pro_Size'];
    $db_qty = intval($row['Quantity']);

    // 【核心逻辑修改】：定制款 Pro_Id 16/17 统一共享 'Default' 库存
    if ($pro_id == 16 || $pro_id == 17) {
        // 无论数据库存的是 'Default' 还是 'Custom'，都映射给所有可能的选择键
        foreach($colors as $c) {
            $variant_map[$c][$db_size] = $db_qty;
        }
        $variant_map['Custom Design'][$db_size] = $db_qty;
        $variant_map['Default'][$db_size] = $db_qty; 
    } else {
        // 普通商品按颜色存储
        $variant_map[$db_colour][$db_size] = $db_qty;
    }
    
    if (!in_array($db_size, $all_unique_sizes)) {
        $all_unique_sizes[] = $db_size;
    }
}
sort($all_unique_sizes); 
$stmt_stock->close();

if (!isset($_SESSION['recently_viewed'])) $_SESSION['recently_viewed'] = [];
if (($key = array_search($pro_id, $_SESSION['recently_viewed'])) !== false) unset($_SESSION['recently_viewed'][$key]);
array_unshift($_SESSION['recently_viewed'], $pro_id);
if (count($_SESSION['recently_viewed']) > 8) array_pop($_SESSION['recently_viewed']);

$rv_products = [];
// 1. 获取过滤掉当前页面的历史 ID
$history_ids = array_filter($_SESSION['recently_viewed'], function($id) use ($pro_id) {
    return $id != $pro_id;
});

// 2. 首先加载浏览历史 (最多 8 个)
if (!empty($history_ids)) {
    $ids_limit = array_slice($history_ids, 0, 8);
    $ids_str = implode(',', $ids_limit);
    $rv_sql = "SELECT Pro_Id, Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id IN ($ids_str) ORDER BY FIELD(Pro_Id, $ids_str)";
    $rv_res = $conn->query($rv_sql);
    if ($rv_res) {
        while($r = $rv_res->fetch_assoc()) { $rv_products[] = $r; }
    }
}

// 3. 差额补全逻辑：如果历史不足 8 个，用最新上架的商品填满
$count_needed = 8 - count($rv_products);
if ($count_needed > 0) {
    // 排除已经在历史里的 ID 和当前 ID
    $exclude_ids = $_SESSION['recently_viewed'];
    $exclude_str = !empty($exclude_ids) ? implode(',', $exclude_ids) : '0';
    
    $fill_sql = "SELECT Pro_Id, Pro_Name, Pro_Price, Pro_Image 
                 FROM product 
                 WHERE Pro_Id NOT IN ($exclude_str) 
                 AND Pro_Status = 'Available' 
                 ORDER BY Pro_Added_Date DESC 
                 LIMIT $count_needed";
    $fill_res = $conn->query($fill_sql);
    if ($fill_res) {
        while($r = $fill_res->fetch_assoc()) { $rv_products[] = $r; }
    }
}


$all_designs = [];
if (isset($_SESSION['saved_designs'][$pro_id])) {
    $all_designs = $_SESSION['saved_designs'][$pro_id];
}
if ($is_logged_in) {
    $stmt_designs = $conn->prepare("SELECT * FROM user_saved_designs WHERE User_Id = ? AND Pro_Id = ? ORDER BY Created_At DESC");
    $stmt_designs->bind_param("ii", $uid, $pro_id);
    $stmt_designs->execute();
    $db_designs_res = $stmt_designs->get_result();
    if ($db_designs_res) {
        while ($row = $db_designs_res->fetch_assoc()) {
            $all_designs[$row['Design_Id']] = [
                'design_id' => $row['Design_Id'],
                'design_details' => $row['Design_JSON'],
                'custom_preview' => $row['Preview_Image']
            ];
        }
    }
    $stmt_designs->close();
}

$base_img = $product['Pro_Image'];
$path_parts = pathinfo($base_img);
$base_name = preg_replace('/_\d+$/', '', $path_parts['filename']);

$color_galleries = [];
foreach ($colors as $c) { $color_galleries[$c] = []; }

$all_files = glob("../uploads/{$base_name}*.*");
if ($all_files) {
    foreach ($all_files as $file_path) {
        $file_name = basename($file_path);
        $filename_only = pathinfo($file_name, PATHINFO_FILENAME);
        $matched_color = $colors[0]; 
        foreach ($colors as $index => $c) {
            if ($index === 0) continue;
            $slugs = [strtolower(str_replace(' ', '', $c)), strtolower(str_replace(' ', '_', $c)), strtolower(explode('/', $c)[0])];
            foreach ($slugs as $slug) {
                if (!empty($slug) && strpos(strtolower($filename_only), "_" . $slug) !== false) {
                    $matched_color = $c; break 2;
                }
            }
        }
        $color_galleries[$matched_color][] = "../uploads/" . $file_name;
    }
} else {
    $color_galleries[$colors[0]][] = "../uploads/" . $base_img;
}
foreach ($color_galleries as $c => &$images) {
    if (empty($images)) $images[] = "../images/placeholder.png";
    while (count($images) < 4) { $images[] = "../images/placeholder.png"; }
}

$mini_cart_total = 0;
$mini_cart_count = 0;
$mini_cart_items = [];
if (!empty($_SESSION['cart'])) {
    // Batch query instead of N+1 queries for better performance
    $pro_ids = array_unique(array_map(function($item) { return intval($item['pro_id']); }, $_SESSION['cart']));
    
    if (!empty($pro_ids)) {
        $placeholders = implode(',', array_fill(0, count($pro_ids), '?'));
        $stmt_mini = $conn->prepare("SELECT Pro_Id, Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id IN ($placeholders)");
        
        // Bind parameters dynamically
        $types = str_repeat('i', count($pro_ids));
        $stmt_mini->bind_param($types, ...$pro_ids);
        $stmt_mini->execute();
        $c_res = $stmt_mini->get_result();
        
        $products_map = [];
        while ($row = $c_res->fetch_assoc()) {
            $products_map[$row['Pro_Id']] = $row;
        }
        
        // Now build mini cart from session
        foreach ($_SESSION['cart'] as $cart_key => $c_item) {
            $c_pro_id = intval($c_item['pro_id']);
            
            if (isset($products_map[$c_pro_id])) {
                $c_row = $products_map[$c_pro_id];
                if (isset($c_item['price'])) {
                    $c_row['Pro_Price'] = $c_item['price'];
                }
                $c_row['qty'] = (int)$c_item['qty'];
                $c_row['subtotal'] = $c_row['qty'] * (float)$c_row['Pro_Price'];
                $c_row['custom_preview'] = $c_item['custom_preview'] ?? '';
                
                $mini_cart_total += $c_row['subtotal'];
                $mini_cart_count += $c_row['qty'];
                $mini_cart_items[] = $c_row;
            }
        }
        $stmt_mini->close();
    }
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['Pro_Name']; ?> | Sport Shoes Store</title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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

        .product-gallery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .gallery-img-box { background: #f4f6f9; border-radius: 8px; overflow: hidden; aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; position: relative; }
        .gallery-img-box img { width: 90%; height: 90%; object-fit: contain; mix-blend-mode: multiply; transition: transform 0.3s; }
        
        .badge-sale { position: absolute; top: 15px; left: 15px; background: #ffeb3b; color: #000; font-size: 13px; font-weight: bold; padding: 6px 12px; border-radius: 4px; z-index: 5;}
        
        .brand-name { font-size: 14px; font-weight: bold; color: #FF6B00; text-transform: uppercase; margin-bottom: 5px; }
        .product-title { font-size: 32px; font-weight: 800; margin: 0 0 15px 0; line-height: 1.2; }
        .current-price { font-size: 28px; font-weight: bold; margin-bottom: 25px; display: block;}
        .info-label { font-weight: bold; display: block; margin-bottom: 10px; font-size: 15px; }
        
        .color-variants-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; }
        .color-variant-box {
            width: 80px; height: 80px; 
            background: #ffffff; border: 1px solid #ddd; border-radius: 6px; 
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; cursor: pointer; transition: 0.2s; padding: 5px;
        }
        .color-variant-box img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.3s; }
        .color-variant-box.active { border-color: #008060; border-width: 2px; }
        .color-variant-box:hover img { transform: scale(1.1);}
        .color-variant-box.custom-plus-box { border: 2px dashed #bbb; background: #ffffff; text-decoration: none; }
        .color-variant-box.custom-plus-box:hover { border: 2px solid #008060; background: #f0f7f4; }

        .size-selector { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .size-box { width: 50px; height: 50px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; border-radius: 4px; transition: 0.2s;}
        .size-box.selected { background: #333; color: white; border-color: #333; }
        
        .quantity-selector { display: flex; border: 1px solid #ccc; border-radius: 4px; width: 120px; overflow: hidden; }
        .quantity-selector button { background: #f4f4f4; width: 40px; font-size: 20px; border: none; cursor: pointer; }
        .quantity-selector input { flex: 1; width: 40px; border: none; text-align: center; font-weight: bold; }
        
        .btn-add-cart {  flex: 1; background-color: #008060; color: white; padding: 15px 20px; font-weight: bold; text-transform: uppercase; border: none; border-radius: 4px; cursor: pointer; transition: 0.3s;}
        .btn-add-cart:hover { background-color: #00664c; }

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
        .fmc-total { font-size: 18px; font-weight: 900; color: #333; }
        .fmc-actions { display: flex; gap: 10px; }
        .fmc-btn-view { padding: 10px 20px; border-radius: 25px; border: 1px solid #ccc; color: #333; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; cursor: pointer; }
        .fmc-btn-checkout { padding: 10px 24px; border-radius: 25px; background: #008060; color: #fff; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; border: 1px solid #008060; cursor: pointer; }

        .design-tabs { display: flex; gap: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
        .design-tab { padding-bottom: 10px; cursor: pointer; font-weight: bold; color: #999; border-bottom: 2px solid transparent; }
        .design-tab.active { color: #000; border-bottom-color: #000; }
        .design-content { display: none; }
        .design-content.active { display: block; }
        .custom-design-thumb { border: 2px solid #eee; border-radius: 4px; padding: 2px; cursor: pointer; width: 60px; position:relative; overflow:visible; }
        .custom-design-thumb.active { border-color: #008060; }
        .btn-wishlist-main { background: none; border: none; font-size: 24px; cursor: pointer; float: right; color: #999; }
    
        /* AI Chatbot Styles */
.apply-size-btn {
    background: #ffeb3b;
    border: 1px solid #000;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 10px;
    display: block;
}

/* Recently Viewed Styles */
.rv-card {
    text-decoration: none;
    color: inherit;
    display: block;
    transition: 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.rv-card:hover {
    transform: translateY(-8px);
}
.rv-img-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.rv-img-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    mix-blend-mode: multiply;
}
.rv-info {
    margin-top: 12px;
    padding: 0 5px;
}
.rv-name {
    font-weight: bold;
    font-size: 14px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rv-price {
    color: #008060;
    font-weight: 800;
    margin-top: 4px;
}
/* --- 新的横向滑动器样式 --- */
.rv-slider-track {
    display: flex;
    gap: 20px;
    overflow-x: auto; /* 允许横向滚动 */
    scroll-behavior: smooth; /* 平滑滚动 */
    padding: 10px 5px 30px;
    scrollbar-width: none; /* Firefox 隐藏滚动条 */
    -ms-overflow-style: none; /* IE 隐藏滚动条 */
    scroll-snap-type: x mandatory; /* 自动对齐 */
}

.rv-slider-track::-webkit-scrollbar {
    display: none; /* Chrome/Safari 隐藏滚动条 */
}

.rv-slide-item {
    flex: 0 0 calc(25% - 15px); /* 默认一排显示4个 */
    min-width: 250px;
    scroll-snap-align: start; /* 对齐到起始位置 */
}

.rv-controls {
    display: flex;
    gap: 10px;
}

.rv-arrow-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
    font-size: 18px;
}

.rv-arrow-btn:hover {
    background: #000;
    color: #fff;
    border-color: #000;
}

/* 响应式调整 */
@media (max-width: 992px) {
    .rv-slide-item { flex: 0 0 calc(50% - 10px); } /* 平板一排2个 */
}
@media (max-width: 600px) {
    .rv-slide-item { flex: 0 0 85%; } /* 手机一排1.15个，露出下一个提示滑动 */
}

/* 实时扫描线动画 */
@keyframes scanMove {
    0% { top: 0%; }
    100% { top: 100%; }
}

.scanner-line {
    position: absolute;
    width: 100%;
    height: 4px;
    background: rgba(0, 128, 96, 0.8);
    box-shadow: 0 0 15px 5px rgba(0, 128, 96, 0.5);
    z-index: 10;
    animation: scanMove 2s linear infinite;
    pointer-events: none;
}

.camera-flash {
    position: absolute;
    inset: 0;
    background: white;
    opacity: 0;
    z-index: 20;
    pointer-events: none;
}

.flash-active {
    animation: flashAnim 0.3s ease-out;
}

@keyframes flashAnim {
    0% { opacity: 1; }
    100% { opacity: 0; }
}

/* --- Mobile Responsive Optimizations --- */
@media (max-width: 768px) {
    /* 1. 缩小整体容器的内外边距，节省屏幕空间 */
    .detail-container { 
        margin: 15px auto; 
        padding: 15px; 
    }
    
    /* 2. 缩小标题和价格字体 */
    .product-title { font-size: 24px; margin-bottom: 10px; }
    .current-price { font-size: 22px; margin-bottom: 15px; }
    
    /* 3. 颜色选择框稍微缩小，确保一行能排下更多 */
    .color-variant-box { width: 60px; height: 60px; }
    
    /* 4. 优化底部悬浮购物车，使其适配屏幕宽度并分行显示 */
    .floating-mini-cart { 
        width: 90%; 
        bottom: 15px; 
        flex-direction: column; 
        gap: 12px; 
        padding: 15px;
    }
    .fmc-info { width: 100%; justify-content: space-between; }
    .fmc-actions { width: 100%; justify-content: space-between; gap: 8px; }
    .fmc-btn-view, .fmc-btn-checkout { flex: 1; text-align: center; padding: 10px 0; }
    
    /* 5. 调整数量选择器和添加购物车按钮为垂直排列 (可选，防止拥挤) */
    .product-info > form > div:nth-of-type(4) {
        flex-direction: column;
        align-items: stretch !important;
    }
    .quantity-selector { width: 100%; justify-content: center; margin-bottom: 10px; }
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
            <div class="product-gallery-grid" id="mainGalleryGrid"></div>
            
            <div class="product-info">
                <button class="btn-wishlist-main" onclick="toggleWishlist(event, this)">
                    <i class="bi <?php echo $is_in_wishlist ? 'bi-heart-fill' : 'bi-heart'; ?>" 
                    style="<?php echo $is_in_wishlist ? 'color: #e74c3c;' : ''; ?>"></i>
                </button>
                <div class="brand-name"><?php echo $product['Brand_Name']; ?></div>
                <h1 class="product-title"><?php echo $product['Pro_Name']; ?></h1>
                <span class="current-price">RM <?php echo number_format($product['Pro_Price'], 2); ?></span>

                <div class="design-tabs">
                    <?php if ($pro_id == 16 || $pro_id == 17): ?>
                        <div class="design-tab active" onclick="switchDesignTab(event, 'inspiration')">Inspiration</div>
                        <div class="design-tab" onclick="switchDesignTab(event, 'your-designs')">
                            Your Designs (<?php echo count($all_designs); ?>)
                        </div>
                    <?php else: ?>
                        <div class="design-tab active">Colour: <span id="currentColorText" style="color:#333; margin-left:5px;"><?php echo htmlspecialchars($colors[0]); ?></span></div>
                    <?php endif; ?>
                </div>

                <div id="inspiration-content" class="design-content active">
                    <div class="color-variants-container">
                        <?php foreach($colors as $idx => $c): ?>
                            <div class="color-variant-box <?php echo $idx==0 ? 'active' : ''; ?>" 
                                onclick="selectColor(this, '<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>')" 
                                title="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo $color_galleries[$c][0]; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($pro_id == 16 || $pro_id == 17): ?>
                <div id="your-designs-content" class="design-content">
                    <div class="color-variants-container">
                        <?php foreach($all_designs as $d_id => $design): ?>
                            <div class="color-variant-box custom-design-thumb">
                                <img src="<?php echo htmlspecialchars($design['custom_preview'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     onclick="selectCustomDesign('<?php echo htmlspecialchars($d_id, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($design['custom_preview'], ENT_QUOTES, 'UTF-8'); ?>', this.parentElement)">
                                <a href="custom_builder.php?pro_id=<?php echo $pro_id; ?>&edit_design=<?php echo htmlspecialchars($d_id, ENT_QUOTES, 'UTF-8'); ?>" 
                                   style="position:absolute; top:-10px; right:-10px; background:#000; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:12px; border:2px solid #fff; z-index:10;">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        <a href="javascript:void(0)" onclick="handleCustomBuilderLink(<?php echo $pro_id; ?>)" class="color-variant-box custom-plus-box">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <form action="product_details.php?pro_id=<?php echo $product['Pro_Id']; ?>" method="POST" id="addToCartForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="custom_design_id" id="customDesignIdInput" value="">
                    <input type="hidden" name="pro_id" value="<?php echo $product['Pro_Id']; ?>">
                    <input type="hidden" name="selected_size" id="selectedSizeInput" value="">
                    <input type="hidden" name="selected_color" id="selectedColorInput" value="<?php echo htmlspecialchars($colors[0], ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <!-- AR 尺码扫描入口 -->
                    <div style="background: #f0f7f4; border: 1px dashed #008060; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="font-size: 24px;">📏</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 800; font-size: 14px; color: #008060;">Not sure about your size?</div>
                                <div style="font-size: 12px; color: #666;">Use our AI Vision Sizer for 99% accuracy.</div>
                            </div>
                            <button type="button" onclick="openARScanner()" 
                                    style="background: #008060; color: #fff; border: none; padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 12px; cursor: pointer;">
                                START SCAN
                            </button>
                        </div>
                    </div>
                    <div style="background: #fff9f0; border: 1px dashed #e67e22; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="font-size: 24px;">👟</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 800; font-size: 14px; color: #e67e22;">Not sure about your shoe health?</div>
                                <div style="font-size: 12px; color: #666;">Upload a photo of your worn sole for AI-powered wear assessment and safety insights.</div>
                            </div>
                            <button type="button" onclick="openWearScanner()" 
                                    style="background: #e67e22; color: #fff; border: none; padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 12px; cursor: pointer;">
                                Start Assessment
                            </button>
                        </div>
                    </div>

                    <div class="info-label">Select Size (UK) <span id="sizeError" style="color:red; font-size:12px; display:none; margin-left:10px;">*Required</span></div>
                    <div class="size-selector">
                        <?php foreach($all_unique_sizes as $sz): ?>
                            <div class='size-box' data-size='<?php echo $sz; ?>' onclick='handleSizeClick(this, "<?php echo $sz; ?>")'><?php echo $sz; ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="info-label">Quantity</div>
                    <div style="display:flex; gap:15px; align-items:center;">
                        <div class="quantity-selector">
                            <button type="button" onclick="changeQty(-1)">−</button>
                            <input type="number" name="quantity" id="qtyInput" value="1" readonly>
                            <button type="button" onclick="changeQty(1)">+</button>
                        </div>
                        <button type="button" class="btn-add-cart" id="addToBasketBtn" onclick="addToCartAndOpen.call(this)">ADD TO BASKET</button>
                    </div>
                    <small id="stockDisplay" style="display:block; margin-top:10px; color:#666;">Please select a size to check availability.</small>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="detail-container" style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 40px; position: relative;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h3 style="font-weight: 800; margin: 0; letter-spacing: -0.5px;">Inspired by your browsing</h3>
        <div class="rv-controls">
            <button onclick="scrollRV(-1)" class="rv-arrow-btn"><i class="bi bi-chevron-left"></i></button>
            <button onclick="scrollRV(1)" class="rv-arrow-btn"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
    
    <div class="rv-slider-track" id="rvSlider">
        <?php foreach($rv_products as $rv_p): ?>
            <?php 
                $base_img = $rv_p['Pro_Image'];
                $path_info = pathinfo($base_img);
                $clean_name = preg_replace('/_\d+$/', '', $path_info['filename']);
                $files = glob("../uploads/{$clean_name}*.*");
                $final_src = (!empty($files)) ? $files[0] : "../images/placeholder.png";
            ?>
            <div class="rv-slide-item">
                <a href="product_details.php?pro_id=<?php echo $rv_p['Pro_Id']; ?>" class="rv-card">
                    <div class="rv-img-box">
                        <img src="<?php echo $final_src; ?>" onerror="this.src='../images/placeholder.png'">
                    </div>
                    <div class="rv-info">
                        <div class="rv-name"><?php echo htmlspecialchars($rv_p['Pro_Name']); ?></div>
                        <div class="rv-price">RM <?php echo number_format($rv_p['Pro_Price'], 2); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($mini_cart_count > 0): ?>
<div class="floating-mini-cart">
    <div class="fmc-info">
        <div class="fmc-icon"><i class="bi bi-cart-check-fill"></i></div>
        <div class="fmc-text">
            <div style="font-size: 12px; color: #666; font-weight: bold;"><?php echo $mini_cart_count; ?> ITEMS</div>
            <div class="fmc-total">RM <?php echo number_format($mini_cart_total, 2); ?></div>
        </div>
    </div>
    <div class="fmc-actions">
        <a href="cart.php" class="fmc-btn-view">VIEW CART</a>
        <a href="cart.php" class="fmc-btn-checkout">CHECKOUT</a>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

<script>
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const colorGalleries = <?php echo json_encode($color_galleries); ?>;
    const variantMap = <?php echo json_encode($variant_map); ?>;
    const currentProId = <?php echo $pro_id; ?>;
    const isSale = <?php echo $product['Pro_Sale']; ?>;
    let selectedColor = "<?php echo htmlspecialchars($colors[0]); ?>";
    let selectedSize = "";

    function renderGallery(color) {
        const grid = document.getElementById('mainGalleryGrid');
        const images = colorGalleries[color] || [];
        let html = '';
        images.forEach((imgSrc, idx) => {
            let badges = (idx === 0 && isSale === 1) ? '<div class="badge-sale">↗ TRENDING / SALE</div>' : '';
            html += `<div class="gallery-img-box">${badges}<img src="${imgSrc}"></div>`;
        });
        grid.innerHTML = html;
    }

    function selectColor(el, color) {
        document.querySelectorAll('.color-variant-box').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        selectedColor = color;
        document.getElementById('selectedColorInput').value = color;
        
        const colorLabel = document.getElementById('currentColorText');
        if (colorLabel) colorLabel.innerText = color;

        document.getElementById('customDesignIdInput').value = ""; 
        refreshSizeButtons();
        renderGallery(color);
    }

    function selectCustomDesign(designId, previewImg, el) {
        document.querySelectorAll('.color-variant-box, .custom-design-thumb').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        
        document.getElementById('customDesignIdInput').value = designId;
        document.getElementById('selectedColorInput').value = "Custom Design";
        selectedColor = "Custom Design";

        const grid = document.getElementById('mainGalleryGrid');
        grid.innerHTML = `
            <div class="gallery-img-box" style="grid-column: 1/-1; aspect-ratio: auto;">
                <div class="badge-sale" style="background:#ffeb3b; display:flex; align-items:center; gap:10px; padding: 6px 15px;">
                    <span style="font-weight:800;">↗ YOUR CUSTOM DESIGN</span>
                    <a href="custom_builder.php?pro_id=${currentProId}&edit_design=${designId}" style="background:#fff; color:#000; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:bold; border:1px solid #000; display:flex; align-items:center; gap:5px; text-decoration:none;">
                       <i class="bi bi-pencil-fill"></i> EDIT
                    </a>
                </div>
                <img src="${previewImg}" style="width:100%; height:auto;">
            </div>
        `;
        refreshSizeButtons();
    }

function refreshSizeButtons() {
    const currentColorStock = variantMap[selectedColor] || {};
    let isCurrentlySelectedSizeValid = false;

    document.querySelectorAll('.size-box').forEach(box => {
        const size = box.getAttribute('data-size');
        const stock = parseInt(currentColorStock[size]) || 0;
        
        if (stock > 0) {
            box.style.opacity = "1";
            box.style.pointerEvents = "auto";
            if (selectedSize === size) isCurrentlySelectedSizeValid = true;
        } else {
            box.style.opacity = "0.3";
            box.style.pointerEvents = "none";
            if (selectedSize === size) box.classList.remove('selected');
        }
    });

    // --- 【核心修复】：切换颜色时同步更新显示文本 ---
    const stockDisplay = document.getElementById('stockDisplay');
    if (isCurrentlySelectedSizeValid && selectedSize !== "") {
        const stock = currentColorStock[selectedSize] || 0;
        if (selectedColor === "Custom Design") {
            stockDisplay.innerHTML = `<span style="color:#008060; font-weight:bold;"><i class="bi bi-hammer"></i> Custom Built to Order</span>`;
        } else {
            stockDisplay.innerHTML = `Only <strong>${stock}</strong> left in stock.`;
        }
    } else if (selectedSize !== "") {
        selectedSize = "";
        document.getElementById('selectedSizeInput').value = "";
        stockDisplay.innerHTML = `<span style="color: #dc3545; font-weight: bold;">Size not available for this choice.</span>`;
    }
}

function handleSizeClick(el, sz) {
    document.querySelectorAll('.size-box').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    selectedSize = sz;
    document.getElementById('selectedSizeInput').value = sz;
    document.getElementById('sizeError').style.display = 'none';

    const stock = (variantMap[selectedColor] || {})[sz] || 0;
    const qtyInput = document.getElementById('qtyInput');
    qtyInput.max = stock; 

    // --- 【核心修复】：定制款不显示具体数字，仅显示状态 ---
    const stockDisplay = document.getElementById('stockDisplay');
    if (selectedColor === "Custom Design") {
        if (stock > 0) {
            stockDisplay.innerHTML = `<span style="color:#008060; font-weight:bold;"><i class="bi bi-hammer"></i> Custom Built to Order (Available)</span>`;
        } else {
            stockDisplay.innerHTML = `<span style="color:#dc3545; font-weight:bold;">Out of materials for this size.</span>`;
        }
    } else {
        stockDisplay.innerHTML = `Only <strong>${stock}</strong> left in stock.`;
    }

    if (parseInt(qtyInput.value) > stock) {
        qtyInput.value = stock > 0 ? stock : 1;
    }
}

    function changeQty(amt) {
    let input = document.getElementById('qtyInput');
    let val = parseInt(input.value) + amt;
    
    // 逻辑校验
    if (val < 1) {
        Swal.fire({ icon: 'info', title: 'Minimum Reach', text: 'You must select at least 1 pair.' });
        return;
    }
    if (val > 99) {
        Swal.fire({ icon: 'warning', title: 'Limit Exceeded', text: 'Maximum limit is 99 pairs.' });
        return;
    }
    
    // 检查是否超过库存 (你原有的逻辑)
    let maxStock = parseInt(input.max) || 99;
    if (val > maxStock) {
        Swal.fire({ icon: 'error', title: 'Out of Stock', text: 'Only ' + maxStock + ' units available.' });
        return;
    }
    
    input.value = val;
}

// 增加：直接输入时的实时拦截
document.getElementById('qtyInput').addEventListener('input', function() {
    if (this.value > 99) {
        Swal.fire({ icon: 'warning', title: 'Limit Exceeded', text: 'Maximum limit is 99 pairs.' });
        this.value = 99;
    }
    if (this.value < 0) {
        Swal.fire({ icon: 'error', title: 'Invalid Entry', text: 'Negative numbers are not allowed.' });
        this.value = 1;
    }
});

function addToCartAndOpen() {
    // 1. 登录检查
    if (!isLoggedIn) { promptLogin('add items to your cart'); return; }

    // 2. 尺码选中检查
    if (!selectedSize) { 
        document.getElementById('sizeError').style.display = 'inline'; 
        // 增加红色震动提示效果
        const selector = document.querySelector('.size-selector');
        selector.style.border = "1px solid red";
        selector.style.padding = "5px";
        selector.style.borderRadius = "8px";
        setTimeout(() => selector.style.border = "none", 2000);
        return; 
    }

    // 3. 库存实时校验（前端第一道防线）
    const stock = (variantMap[selectedColor] || {})[selectedSize] || 0;
    if (stock <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Out of Stock',
            text: 'Sorry, this size is no longer available in the selected colour.',
            confirmButtonColor: '#008060'
        });
        return;
    }

    // --- 【核心修复点】：定义并配置 FormData ---
    const formEl = document.getElementById('addToCartForm');
    let formData = new FormData(formEl);
    
    // 确保发送了触发 PHP 处理逻辑的标识符
    formData.append('add_to_cart', '1');

    // 4. 发送异步请求
    fetch(window.location.href, { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        if (response.ok) {
            Swal.fire({
                title: 'Added to Cart!',
                text: 'Your item is waiting in the shopping cart.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#008060',
                confirmButtonText: 'View Cart',
                cancelButtonText: 'Continue Shopping'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'cart.php';
                } else {
                    // 刷新页面以更新下方悬浮窗的金额和数量
                    location.reload(); 
                }
            });
        } else {
            throw new Error('Network response was not ok.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Something went wrong while adding to cart.', 'error');
    });
}

    function switchDesignTab(event, tabName) {
        document.querySelectorAll('.design-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.design-content').forEach(c => c.classList.remove('active'));
        event.currentTarget.classList.add('active');
        document.getElementById(tabName + '-content').classList.add('active');
    }

    function promptLogin(actionText) {
        Swal.fire({
            title: "Login Required", text: "Please login to " + actionText + ".", icon: "warning",
            showCancelButton: true, confirmButtonColor: "#008060", confirmButtonText: "Login Now"
        }).then((result) => { if (result.isConfirmed) window.location.href = "../Module A/login.php"; });
    }

    function handleCustomBuilderLink(proId) {
        if (!isLoggedIn) promptLogin('customize 3D design');
        else window.location.href = `custom_builder.php?pro_id=${proId}`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderGallery(selectedColor);
        refreshSizeButtons();
    });

/*    async function askAI() {
    const input = document.getElementById('aiInput');
    const btn = document.getElementById('aiSendBtn');
    const container = document.getElementById('chatMessages');
    const message = input.value.trim();

    if (!message) return;

    // 1. 显示用户消息
    appendMessage('user', message);
    input.value = '';
    input.disabled = true;
    btn.disabled = true;

    // 2. 显示加载状态
    const loadingId = 'loading-' + Date.now();
    appendMessage('ai', '<span id="' + loadingId + '">AI is calculating...</span>');

    try {
        const response = await fetch('gemini_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                message: message,
                current_product: "<?php echo $product['Pro_Name']; ?>" 
            })
        });
        
        const data = await response.json();
        let reply = data.reply;

        // 3. 安全解析：将 [RECOMMENDED_SIZE:X] 转换为可点击按钮
        const sizeTagRegex = /\[RECOMMENDED_SIZE:(\d+)\]/g;
        reply = reply.replace(sizeTagRegex, (match, size) => {
            return `<br><button class="apply-size-btn" onclick="autoSelectSize('${size}')">Apply UK ${size} to my order</button>`;
        });

        // 移除加载文字并显示正式回复
        document.getElementById(loadingId).parentElement.innerHTML = reply;

    } catch (error) {
        document.getElementById(loadingId).innerText = "System error. Please try again.";
    } finally {
        input.disabled = false;
        btn.disabled = false;
        input.focus();
    }
}*/

function appendMessage(type, text) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `msg ${type}`;
    div.innerHTML = text;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// 尺码计算算法（基于标准运动鞋对照表）
function calculateUKSize(cm) {
    if (cm <= 24) return "5";
    if (cm <= 25) return "6";
    if (cm <= 26) return "7";
    if (cm <= 27) return "8";
    if (cm <= 28) return "9";
    if (cm <= 29) return "10";
    return "11";
}

// 联动功能：AI 推荐后点击按钮，自动选中上方的尺码
function autoSelectSize(size) {
    const sizeBox = document.querySelector(`.size-box[data-size="${size}"]`);
    if (sizeBox) {
        if (sizeBox.style.opacity === "0.3") {
            Swal.fire('Out of Stock', `Sorry, UK ${size} is currently unavailable.`, 'warning');
        } else {
            sizeBox.click(); // 模拟点击尺码格子
            Swal.fire({
                icon: 'success',
                title: `UK ${size} Selected`,
                showConfirmButton: false,
                timer: 1000
            });
        }
    } else {
        Swal.fire('Notice', `Size UK ${size} is not available for this model.`, 'info');
    }
}
// 1. 左右按钮点击滚动
function scrollRV(direction) {
    const slider = document.getElementById('rvSlider');
    const scrollAmount = slider.clientWidth * 0.8; // 每次滚动容器宽度的 80%
    slider.scrollBy({
        left: direction * scrollAmount,
        behavior: 'smooth'
    });
}

// 2. 鼠标滚轮横向移动 (垂直滚轮转横向滚动)
document.getElementById('rvSlider').addEventListener('wheel', (evt) => {
    // 只有当鼠标在 Recently Viewed 区域内时生效
    evt.preventDefault(); // 阻止默认的上下滚动
    const slider = document.getElementById('rvSlider');
    slider.scrollLeft += evt.deltaY; // 将垂直滚动的偏移量应用到横向滚动上
});

async function openARScanner() {
    // Generate cryptographically secure token using crypto API
    const tokenArray = new Uint8Array(16);
    crypto.getRandomValues(tokenArray);
    const sessionToken = 'SS-' + Array.from(tokenArray, byte => byte.toString(16).padStart(2, '0')).join('');
    await fetch(`init_bridge.php?token=${sessionToken}`);

    const computerIP = "<?php echo htmlspecialchars(MOBILE_DEVICE_IP, ENT_QUOTES, 'UTF-8'); ?>";
    const folderPath = "Module%20B";
    const mobileURL = `http://${computerIP}/${folderPath}/mobile_capture.php?token=${sessionToken}`;

    Swal.fire({
        title: 'AI Foot Sizer Scanner',
        width: window.innerWidth < 768 ? '95%' : '650px',
        html: `
            <div style="padding: 10px; font-family: 'Segoe UI', sans-serif;">
                <div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; align-items: center; text-align: center;">
                    <div style="flex: 1; min-width: 250px; border-bottom: ${window.innerWidth < 768 ? '1px solid #eee' : 'none'}; padding-bottom: ${window.innerWidth < 768 ? '15px' : '0'}; border-right: ${window.innerWidth >= 768 ? '1px solid #eee' : 'none'};">
                    
                    <div style="flex: 1; border-right: 1px solid #eee; padding-right: 20px;">
                        <h6 style="font-weight: 800; color: #333; margin: 0 0 15px 0; font-size: 14px;">Option 1: Scan via Mobile</h6>
                        <div id="qrcode" style="display:flex; justify-content:center; margin-bottom: 15px;"></div>
                        <div id="status-container">
                            <div class="spinner-border text-success" role="status" style="width:1rem; height:1rem;"></div>
                            <span id="sync-status" style="font-size: 11px; color: #666; margin-left:5px;">Waiting for phone...</span>
                        </div>
                    </div>
                    
                    <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding-left: 10px;">
                        <h6 style="font-weight: 800; color: #333; margin: 0 0 20px 0; font-size: 14px;">Option 2: Upload from Current Device</h6>
                        
                        <button type="button" onclick="document.getElementById('select_image_input').click()" 
                                style="background: #008060; color: #fff; border: none; padding: 12px 25px; border-radius: 30px; font-weight: bold; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,128,96,0.2); display: flex; align-items: center; gap: 8px;">
                            <i class="bi bi-upload"></i> SELECT IMAGE
                        </button>
                        
                        <input type="file" id="select_image_input" accept="image/*" style="display: none;" onchange="handleLocalUpload(this, '${sessionToken}')">
                        
                        <p style="font-size: 11px; color: #999; margin-top: 15px; line-height: 1.4; max-width: 180px;">
                            Ensure both your <strong>foot</strong> and <strong>A4 paper</strong> reference are fully visible.
                        </p>
                    </div>
                    
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        didOpen: () => {
            new QRCode(document.getElementById("qrcode"), {
                text: mobileURL,
                width: 150,
                height: 150
            });
            startPolling(sessionToken);
        }
    });
}

async function handleLocalUpload(input, token) {
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    const syncStatus = document.getElementById('sync-status');
    if (syncStatus) syncStatus.innerText = "Optimizing image for AI Sizer...";

    // 创建图像预处理容器
    const img = new Image();
    img.src = URL.createObjectURL(file);
    
    img.onload = async function() {
        // Create canvas with standard resolution using config
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Smart portrait/landscape detection
        let targetWidth = <?php echo IMAGE_RESOLUTION_WIDTH; ?>;
        let targetHeight = <?php echo IMAGE_RESOLUTION_HEIGHT; ?>;
        
        // Swap axes for portrait-oriented uploads
        if (img.height > img.width) {
            targetWidth = <?php echo IMAGE_RESOLUTION_HEIGHT; ?>;
            targetHeight = <?php echo IMAGE_RESOLUTION_WIDTH; ?>;
        }
        
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        
        // Draw image with high quality
        ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
        
        // Convert to binary blob stream
        canvas.toBlob(async (blob) => {
            const formData = new FormData();
            formData.append('image', blob, 'capture.jpg'); 
            formData.append('token', token);

            if (syncStatus) syncStatus.innerText = "Analyzing scaled contours...";

            try {
                const response = await fetch('upload_bridge.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (!result.success) throw new Error(result.error);
                
            } catch (error) {
                console.error('Processing error:', error);
                Swal.fire({ icon: 'error', title: 'Analysis Failed', text: 'Image resolution too high or layout clear cutoff.' });
            }
        }, 'image/jpeg', <?php echo IMAGE_COMPRESSION_QUALITY; ?>);
    };
}

// 轮询检查手机是否拍好了
function startPolling(token) {
    const checkTimer = setInterval(async () => {
        const response = await fetch(`check_bridge.php?token=${token}`);
        const data = await response.json();
        
        if (data.status === 'captured') {
            clearInterval(checkTimer);
            Swal.fire({
                icon: 'success',
                title: 'Image Received!',
                text: 'AI is now calculating your size from your phone data...'
            });
            processMeasurement(data.image_url); // 传入从手机传来的照片进行分析
        }
    }, 2000); // 每2秒问一次服务器：“手机拍好了吗？”
}

// 模拟真实相机的快门闪光
function triggerFlashEffect() {
    const flash = document.getElementById('flash-layer');
    if (flash) {
        flash.classList.add('flash-active');
        setTimeout(() => flash.classList.remove('flash-active'), 300);
    }
}

// 升级版：直接调用 Module B 保存的物理照片，附加缓存击穿机制
async function processMeasurement(imagePath) {
    // 1. 弹出等待动画
    Swal.fire({
        title: 'AI Neural Analysis...',
        html: `
            <div style="padding: 20px;">
                <div class="spinner-border text-success" role="status"></div>
                <p style="margin-top: 15px; font-weight: bold;">正在提取脚部拓扑骨架与解剖轴线...</p>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        // 【核心修复】：构建绝对正确的图片显示路径
        let displaySrc = imagePath;
        // 如果是数据库传来的路径 (uploads/xxx.jpg)，则补全相对路径并加上时间戳防止黑屏缓存
        if (!imagePath.startsWith('data:') && !imagePath.startsWith('http')) {
            displaySrc = '../Module B/' + imagePath + '?t=' + new Date().getTime(); 
        }

        // 2. 将原始图片路径发给后端 Gemini 进行分析
        const response = await fetch('gemini_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                mode: 'sizer',
                image_path: imagePath 
            })
        });

        const data = await response.json();
        
        if (!data.measured_length_cm || isNaN(parseFloat(data.measured_length_cm))) {
            throw new Error("Invalid measurement data received from AI.");
        }

        const realFootLength = parseFloat(data.measured_length_cm);
        const recommendedUK = calculateUKSize(realFootLength); 
        
        const footShape = data.foot_shape_type || "unknown";
        const shapeDesc = data.description || "No detailed description available.";
        const landmarks = data.landmarks || null;

        // 3. 渲染最终报告（直接调用 displaySrc 物理路径显示图片）
        Swal.fire({
            icon: 'success',
            title: 'AI Analysis Complete!',
            width: '550px',
            html: `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: left; font-family: sans-serif;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #008060; color:#fff; padding:12px; border-radius:8px; margin-bottom:15px;">
                        <div>
                            <span style="font-size:12px; opacity:0.9;">Measured Foot Length</span>
                            <h2 style="margin:0; font-weight:800;">${realFootLength} cm</h2>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size:12px; opacity:0.9;">Recommended Size</span>
                            <h2 style="margin:0; font-weight:800; color:#ffeb3b;">UK ${recommendedUK}</h2>
                        </div>
                    </div>

                    <div style="position: relative; width: 100%; min-height: 250px; background: #111; border-radius: 8px; overflow: hidden; margin-bottom: 15px; display: flex; align-items: center; justify-content: center;">
                        <img id="analyzedFootImg" src="${displaySrc}" style="width:100%; max-height: 450px; object-fit: contain; display: block;" onerror="this.src='../images/placeholder.png'; console.error('Image load failed:', this.src);">
                        <canvas id="analysisOverlayCanvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></canvas>
                    </div>

                    <div style="border-left: 4px solid #008060; padding-left: 12px; margin-top: 15px;">
                        <h4 style="margin: 0 0 5px 0; color: #333; font-weight: bold;">Your Foot Shape: <span style="color:#008060;">${footShape}</span></h4>
                        <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.4;">${shapeDesc}</p>
                    </div>

                    <div style="font-size: 11px; color: #999; line-height: 1.5; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ddd;">
                        <i class="bi bi-exclamation-triangle-fill" style="color: #e67e22;"></i> 
                        <strong>Technical Tip:</strong> The green circles are AI-detected key points, and the red line represents the measured anatomical axis.
                    </div>
                </div>
            `,
            confirmButtonText: 'Select This Size and Lock Order',
            confirmButtonColor: '#008060'
        }).then((result) => {
            if (result.isConfirmed) {
                autoSelectSize(recommendedUK);
            }
        });

        // 4. 精确监听图片加载状态，加载完成后再绘制 AI 坐标线
        const footImg = document.getElementById('analyzedFootImg');
        if (footImg) {
            footImg.onload = function() {
                drawAnalysisLines(landmarks);
            };
            // 应对图片已被浏览器秒级缓存的情况
            if (footImg.complete && footImg.naturalHeight !== 0) {
                drawAnalysisLines(landmarks);
            }
        }

    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Analysis Interrupted',
            text: error.message || 'System error, unable to fetch image. Please try again.'
        });
    }
}

function drawAnalysisLines(landmarks) {
    const canvas = document.getElementById('analysisOverlayCanvas');
    const img = document.getElementById('analyzedFootImg');
    if (!canvas || !img || !landmarks) return;

    // 1. 让 Canvas 的画布分辨率与它的显示区域大小完美一致
    canvas.width = canvas.clientWidth;
    canvas.height = canvas.clientHeight;
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height); // 清空旧画布

    // 2. 获取图片在 object-fit: contain 规则下在屏幕上的绝对绘制边界
    const imgW = img.naturalWidth;
    const imgH = img.naturalHeight;
    const canvasW = canvas.width;
    const canvasH = canvas.height;

    const imgScale = Math.min(canvasW / imgW, canvasH / imgH);
    
    // 计算出图片在容器内居中显示后的实际左上角起点 (x, y) 以及实际宽高
    const realWidth = imgW * imgScale;
    const realHeight = imgH * imgScale;
    const startX = (canvasW - realWidth) / 2;
    const startY = (canvasH - realHeight) / 2;

    // 3. 将 AI 返回的 0~1 相对坐标映射到图片实际渲染出的像素坐标上
    const pHeel = { 
        x: startX + (landmarks.heel_center.x * realWidth), 
        y: startY + (landmarks.heel_center.y * realHeight) 
    };
    const pToe = { 
        x: startX + (landmarks.longest_toe_tip.x * realWidth), 
        y: startY + (landmarks.longest_toe_tip.y * realHeight) 
    };
    const pWidth = landmarks.forefoot_width_outer ? { 
        x: startX + (landmarks.forefoot_width_outer.x * realWidth), 
        y: startY + (landmarks.forefoot_width_outer.y * realHeight) 
    } : null;

    // 4. 绘制高能科技感红线（纵向解剖轴线）
    ctx.strokeStyle = '#ff3b30'; // 苹果高能红
    ctx.lineWidth = 3;
    ctx.shadowBlur = 8;
    ctx.shadowColor = 'rgba(255, 59, 48, 0.8)';
    
    ctx.beginPath();
    ctx.moveTo(pHeel.x, pHeel.y);
    ctx.lineTo(pToe.x, pToe.y);
    ctx.stroke();

    // 5. 绘制横向测量范围虚线（前掌脚宽）
    if (pWidth) {
        ctx.strokeStyle = '#ffcc00'; // 警告黄
        ctx.lineWidth = 2;
        ctx.setLineDash([6, 4]);
        ctx.beginPath();
        ctx.moveTo(pToe.x, pToe.y);
        ctx.lineTo(pWidth.x, pWidth.y);
        ctx.stroke();
        ctx.setLineDash([]); // 恢复实线
    }

    // 6. 绘制激光雷达定位点（绿色双环靶心）
    ctx.shadowBlur = 0; // 关闭阴影避免模糊
    [pHeel, pToe].forEach(p => {
        // 内实心圆
        ctx.fillStyle = '#00ff9d'; 
        ctx.beginPath();
        ctx.arc(p.x, p.y, 5, 0, 2 * Math.PI);
        ctx.fill();
        
        // 外准星圈
        ctx.strokeStyle = '#00ff9d';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.arc(p.x, p.y, 12, 0, 2 * Math.PI);
        ctx.stroke();
    });
}

function toggleWishlist(event, btn) {
    event.preventDefault();
    // 1. 登录检查
    if (!isLoggedIn) {
        promptLogin('add items to your wishlist');
        return;
    }

    // 2. 发送异步请求给后端处理程序
    fetch('wishlist_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pro_id: currentProId })
    })
    .then(res => res.json())
    .then(data => {
        const icon = btn.querySelector('i');
        if (data.status === 'added') {
            // 切换为实心爱心
            icon.classList.replace('bi-heart', 'bi-heart-fill');
            icon.style.color = '#e74c3c';
            Swal.fire({ icon: 'success', title: 'Added to Wishlist', showConfirmButton: false, timer: 1000 });
        } else if (data.status === 'removed') {
            // 切换为空心爱心
            icon.classList.replace('bi-heart-fill', 'bi-heart');
            icon.style.color = '';
            Swal.fire({ icon: 'info', title: 'Removed from Wishlist', showConfirmButton: false, timer: 1000 });
        }
    })
    .catch(err => console.error('Wishlist Error:', err));
}

// 存储 3 张图片的路径
let wearImages = { front: null, left: null, right: null };
let wearPollingTimer = null;
let sessionToken = null;

async function openWearScanner() {
    // Every time open, reset data
    wearImages = { front: null, left: null, right: null };
    
    // Generate cryptographically secure token
    const tokenArray = new Uint8Array(16);
    crypto.getRandomValues(tokenArray);
    sessionToken = 'WEAR-' + Array.from(tokenArray, byte => byte.toString(16).padStart(2, '0')).join('');
    await fetch(`init_bridge.php?token=${sessionToken}`);

    // Assemble mobile URL (use config for IP address)
    const computerIP = "<?php echo htmlspecialchars(MOBILE_DEVICE_IP, ENT_QUOTES, 'UTF-8'); ?>";
    const folderPath = "Module%20B";
    const mobileURL = `http://${computerIP}/${folderPath}/mobile_capture.php?token=${sessionToken}&mode=wear`;

    Swal.fire({
        title: '上传三视角进行深度检测',
        width: window.innerWidth < 768 ? '95%' : '780px',
        html: `
            <div style="display: flex; flex-wrap: wrap; gap: 25px; align-items: center; text-align: left;">
                    <div style="flex: 1; min-width: 250px; border-bottom: ${window.innerWidth < 768 ? '1px solid #eee' : 'none'}; padding-bottom: ${window.innerWidth < 768 ? '15px' : '0'}; border-right: ${window.innerWidth >= 768 ? '1px solid #eee' : 'none'}; display: flex; flex-direction: column; align-items: center;">
                    <h6 style="font-weight: 800; color: #333; margin: 0 0 15px 0;">Option A: Scan QR Code with Phone, Take Three Photos</h6>
                    <div id="wear_qrcode" style="display:flex; justify-content:center; margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 8px;"></div>
                    <div id="wear_status" style="display: flex; align-items: center; gap: 8px; background: #fff9f0; padding: 5px 15px; border-radius: 20px; border: 1px solid #e67e22;">
                        <div class="spinner-border text-warning" role="status" style="width:1rem; height:1rem;"></div>
                        <span style="font-size: 12px; color: #e67e22; font-weight: bold;">Waiting for phone synchronization...</span>
                    </div>
                </div>
                
                <div style="flex: 2; display: flex; flex-direction: column; justify-content: center;">
                    <h6 style="font-weight: 800; color: #333; margin: 0 0 15px 0; text-align: center;">Option B: Manually Select Local Images</h6>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        ${['front', 'left', 'right'].map(view => `
                            <div style="flex:1; border:2px dashed #ccc; padding:10px; border-radius:8px; cursor:pointer; text-align:center; background: #f9f9f9; transition: 0.3s;" 
                                 onclick="document.getElementById('upload_${view}').click()" onmouseover="this.style.borderColor='#e67e22'" onmouseout="this.style.borderColor='#ccc'">
                                <div id="preview_${view}" style="font-size:24px; color:#999; margin-bottom:5px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-camera"></i>
                                </div>
                                <div style="font-size:11px; font-weight:bold; color: #555;">${view.toUpperCase()} VIEW</div>
                                <input type="file" id="upload_${view}" accept="image/*" style="display:none;" onchange="handleViewUpload(this, '${view}')">
                            </div>
                        `).join('')}
                    </div>
                    <button id="startAiBtn" disabled onclick="submitMultiViewAnalysis()" 
                            style="margin-top:20px; width:100%; padding:14px; background:#e67e22; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:not-allowed; opacity:0.5; transition: 0.3s;">
                        Waiting for images to be ready...
                    </button>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        didOpen: () => {
            // 渲染二维码
            new QRCode(document.getElementById("wear_qrcode"), { text: mobileURL, width: 130, height: 130 });
            // 开启轮询，等待手机端传回数据
            startWearPolling(sessionToken);
        },
        willClose: () => {
            // 【核心修复 2】：生命周期管理。弹窗关闭时，必须强制销毁轮询！
            if (wearPollingTimer) {
                clearInterval(wearPollingTimer);
                wearPollingTimer = null;
            }
        }
    });
}

// 轮询监听器（手机端 3 张图片全部就位后触发）
function startWearPolling(token) {
    // 确保如果多次调用，先清除旧的定时器
    if (wearPollingTimer) clearInterval(wearPollingTimer);

    wearPollingTimer = setInterval(async () => {
        // 【核心修复 3】：如果在执行过程中弹窗被意外销毁，立即中断并停止轮询
        if (!document.getElementById('wear_status')) {
            clearInterval(wearPollingTimer);
            return;
        }

        try {
            const response = await fetch(`check_bridge.php?token=${token}`);
            const data = await response.json();
            
            // 当手机端主令牌激活时，说明 3 张附图已经全部传好了
            if (data.status === 'captured') {
                clearInterval(wearPollingTimer);
                
                // 1. 自动拼接 3 张图片的物理路径并装载到对象中
                const views = ['front', 'left', 'right'];
                views.forEach(view => {
                    const dbPath = `uploads/${token}_${view}_capture.jpg`; 
                    wearImages[view] = dbPath;
                    
                    // 渲染到电脑端方格中 (加入时间戳防止缓存)
                    const displayPath = `../Module B/${dbPath}?t=${Date.now()}`;
                    const previewEl = document.getElementById(`preview_${view}`);
                    if (previewEl) {
                        previewEl.innerHTML = `<img src="${displayPath}" style="width:100%; height:80px; object-fit:contain; border-radius:4px;">`;
                    }
                });

                // 2. 更新状态 UI 并解锁按钮
                const statusEl = document.getElementById('wear_status');
                if (statusEl) {
                    statusEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> <span style="font-weight:bold;">同步完成！</span>';
                    statusEl.style.background = '#e8f8f5';
                    statusEl.style.borderColor = '#008060';
                    statusEl.style.color = '#008060';
                }
                
                const btn = document.getElementById('startAiBtn');
                if (btn) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                    btn.style.background = '#008060';
                    btn.innerText = 'Start AI Deep Analysis';
                }
            }
        } catch (e) { 
            console.warn("Polling wait...", e); 
        }
    }, 2000);
}

async function handleViewUpload(input, view) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    
    // 1. 预览图片
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById(`preview_${view}`).innerHTML = `<img src="${e.target.result}" style="width:100%; height:80px; object-fit:cover; border-radius:4px;">`;
    }
    reader.readAsDataURL(file);

    // 2. 状态提示
    const btn = document.getElementById('startAiBtn');
    btn.innerText = `Processing ${view.toUpperCase()} View...`;

    // 3. 生成临时前缀
    const token = 'WEAR_' + view + '_' + Date.now();

    try {
        // 【核心修复 1】：先呼叫 init_bridge 写入数据库，防止 upload_bridge 更新失败
        await fetch(`init_bridge.php?token=${token}`);

        // 自动上传到临时目录
        const formData = new FormData();
        // 【核心修复 2】：强制指定文件名为 'capture.jpg'，确保后端生成的文件名完全可控
        formData.append('image', file, 'capture.jpg');
        formData.append('token', token);
        
        const res = await fetch('upload_bridge.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            wearImages[view] = `uploads/${token}_capture.jpg`;
        }

        // 恢复按钮文字
        btn.innerText = 'Upload Complete, Start AI Deep Analysis';

        // 检查是否三张都传完了 (确保对象里不为空且不是 undefined)
        if (wearImages.front && wearImages.left && wearImages.right) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btn.style.background = '#008060'; // 亮起绿色表示可以点击
        }
    } catch (err) {
        console.error("Upload error: ", err);
        Swal.fire('Upload Error', 'An network error occurred while processing the image. Please try again.', 'error');
    }
}

async function submitMultiViewAnalysis() {
    Swal.fire({ title: 'AI Triple View Analysis...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('gemini_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'wear_detector', images: wearImages })
        });

        const rawText = await res.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (parseError) {
            console.error("【Format Error】:", rawText);
            throw new Error("AI returned an unexpected format. Please try again.");
        }

        if (wearPollingTimer) { clearInterval(wearPollingTimer); wearPollingTimer = null; }

        if (data.error) {
            throw new Error(data.message || 'AI analysis returned an error.');
        }

        // ✅ Bug 1 Fix: 字段名从 overall_level_zh 改为 overall_level
        if (!data.overall_level || !data.front) {
            throw new Error('AI analysis failed to return expected results. Please ensure the images are clear and try again.');
        }

        Swal.fire({
            title: '👟 Deep Wear Report',
            width: '780px',
            html: generateWearReportHTML(data)
        });

    } catch (err) {
        console.error("Analysis error:", err);
        Swal.fire('Analysis Failed', err.message || 'An error occurred. Please try again.', 'error');
    }
}

function generateWearReportHTML(data) {
    return `
        <div style="text-align: left; font-size: 13px;">
            <div style="background:#333; color:#fff; padding:12px; border-radius:6px; margin-bottom:15px; text-align:center;">
                <!-- ✅ Bug 2 Fix: overall_level_zh → overall_level -->
                <strong style="font-size: 15px;">Overall Rating: ${data.overall_level}</strong>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:12px;">
                ${['front', 'left', 'right'].map(v => {
                    const percent = data[v].wear_percent;
                    const hue = Math.max(0, (100 - percent) * 1.2);
                    const overlayColor = `hsla(${hue}, 85%, 50%, 0.55)`;
                    const borderColor = `hsl(${hue}, 80%, 45%)`;
                    const textColor = percent > 70 ? '#e74c3c' : (percent > 40 ? '#f39c12' : '#27ae60');
                    const imgPath = `../Module B/${wearImages[v]}?t=${Date.now()}`;

                    return `
                    <div style="display:flex; gap:18px; border-left: 5px solid ${borderColor}; background:#f9f9f9; padding:12px; border-radius:0 8px 8px 0; align-items:stretch;">
                        <div style="position:relative; width:140px; height:110px; border-radius:6px; overflow:hidden; flex-shrink:0; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                            <img src="${imgPath}" style="width:100%; height:100%; object-fit:cover; display:block; filter:grayscale(20%);">
                            <div style="position:absolute; inset:0; background-color:${overlayColor}; mix-blend-mode:multiply;"></div>
                            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:900; color:#fff; text-shadow:0px 2px 8px rgba(0,0,0,0.9);">
                                ${percent}%
                            </div>
                        </div>
                        <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                            <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:8px; font-size:14px;">
                                <span style="color:#333;">${v.toUpperCase()} VIEW</span>
                                <span style="color:${textColor}; background:#fff; padding:2px 8px; border-radius:4px; border:1px solid #ddd;">Wear ${percent}%</span>
                            </div>
                            <p style="margin:0; color:#555; line-height:1.5; text-align:justify;">${data[v].detail}</p>
                        </div>
                    </div>`;
                }).join('')}
            </div>

            <!-- ✅ Bug 2 Fix: 恢复 final_advice 建议显示 -->
            <div style="margin-top:15px; padding:12px; background:#e8f8f5; color:#008060; border-radius:6px; line-height:1.6; border:1px solid #c3e6cb;">
                <strong>💡 AI Final Recommendation:</strong><br>
                ${data.final_advice}
            </div>
        </div>
    `;
}

/*async function submitMultiViewAnalysis() {
    Swal.fire({ title: 'AI Triple View Analysis...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('gemini_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'wear_detector', images: wearImages })
        });
        
        // 先作为普通文本读取，防止非 JSON 格式直接引发代码崩溃
        const rawText = await res.text();
        let data;
        
        try {
            data = JSON.parse(rawText);
        } catch (parseError) {
            console.error("【Format Error】:", rawText);
            throw new Error("AI analysis returned an unexpected format. Please ensure the images are clear and try again.");
        }

        // 拦截 PHP 层面拦截到的图片丢失等错误
        if (data.error) {
            throw new Error(data.message || data.error);
        }

        // 拦截 AI 未按要求返回 JSON 字段的情况
        if (!data.overall_level_zh || !data.front) {
            console.error("【AI Error】:", data);
            throw new Error("AI analysis failed to return expected results. Please ensure the images are clear and try again.");
        }

        Swal.fire({
            title: '👟 Deep Wear Report',
            width: '780px',
            html: `
                <div style="text-align: left; font-size: 13px;">
                    <div style="background:#333; color:#fff; padding:12px; border-radius:6px; margin-bottom:15px; text-align:center;">
                        <strong style="font-size: 15px;">Overall Rating: ${data.overall_level_zh}</strong>
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        ${['front', 'left', 'right'].map(v => {
                            const percent = data[v].wear_percent;
                            
                            // 💡 核心算法：利用 HSL 色彩空间动态生成青(绿)到红的颜色
                            const hue = Math.max(0, (100 - percent) * 1.2); 
                            const overlayColor = \`hsla(${hue}, 85%, 50%, 0.55)\`; // 半透明彩色遮罩
                            const borderColor = \`hsl(${hue}, 80%, 45%)\`; // 边框颜色同步
                            const textColor = percent > 70 ? '#e74c3c' : (percent > 40 ? '#f39c12' : '#27ae60');
                            
                            // 彻底去除了反斜杠，变量现在可以完美加载真实的图片路径！
                            const imgPath = \`../Module B/${wearImages[v]}?t=${Date.now()}\`;

                            return \`
                            <div style="display:flex; gap:18px; border-left: 5px solid ${borderColor}; background:#f9f9f9; padding:12px; border-radius:0 8px 8px 0; align-items: stretch;">
                                
                                <div style="position: relative; width: 140px; height: 110px; border-radius: 6px; overflow: hidden; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                    <img src="${imgPath}" style="width: 100%; height: 100%; object-fit: cover; display: block; filter: grayscale(20%);">
                                    
                                    <div style="position: absolute; inset: 0; background-color: ${overlayColor}; mix-blend-mode: multiply;"></div>
                                    
                                    <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 900; color: #fff; text-shadow: 0px 2px 8px rgba(0,0,0,0.9), 0px 0px 3px rgba(0,0,0,0.6);">
                                        ${percent}%
                                    </div>
                                </div>
                                
                                <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                                    <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom: 8px; font-size: 14px;">
                                        <span style="color: #333;">${v.toUpperCase()} VIEW</span>
                                        <span style="color: ${textColor}; background: #fff; padding: 2px 8px; border-radius: 4px; border: 1px solid #ddd;">
                                            Wear ${percent}%
                                        </span>
                                    </div>
                                    <p style="margin:0; color:#555; line-height: 1.5; text-align: justify;">${data[v].detail}</p>
                                </div>

                            </div>
                            \`;
                        }).join('')}
                    </div>

                    <div style="margin-top:15px; padding:12px; background:#e8f8f5; color:#008060; border-radius:6px; line-height: 1.6; border: 1px solid #c3e6cb;">
                        <strong>💡 AI Final Recommendation:</strong><br>
                        ${data.final_advice}
                    </div>
                </div>
            `,
            confirmButtonText: 'Got It',
            confirmButtonColor: '#008060'
        });
        
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Analysis Interrupted',
            text: e.message || 'System busy, please try again later'
        });
    }
}*/
</script>

</body>
</html>