<?php
// 强制开启 Session 以确保浏览记录和购物车生效
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$uid = $is_logged_in ? $_SESSION['user_id'] : null;

include '../includes/db_connection.php';

// ==========================================
// 核心逻辑：处理加入购物车 (Add to Cart / Checkout)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $add_pro_id = intval($_POST['pro_id']);
    $add_size = $_POST['selected_size'];
    $add_qty = intval($_POST['quantity']);
    $add_color = $_POST['selected_color'];
    $design_id = isset($_POST['custom_design_id']) ? $_POST['custom_design_id'] : '';

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
            // 情况 A: 添加自定义设计
            $cart_key = 'custom_' . $design_id . '_' . $add_size;
            $_SESSION['cart'][$cart_key] = [
                'pro_id' => $add_pro_id,
                'size' => $add_size,
                'qty' => $add_qty,
                'color' => 'Custom Design',
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

$stmt_stock = $conn->prepare("SELECT Pro_Size, Pro_Colour, Quantity FROM PRODUCT_STOCK WHERE Pro_Id = ?");
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
    $stmt_mini = $conn->prepare("SELECT Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id = ?");
    foreach ($_SESSION['cart'] as $cart_key => $c_item) {
        $c_pro_id = intval($c_item['pro_id']);
        $stmt_mini->bind_param("i", $c_pro_id);
        $stmt_mini->execute();
        $c_res = $stmt_mini->get_result();
        if ($c_res && $c_res->num_rows > 0) {
            $c_row = $c_res->fetch_assoc();
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
.ai-assistant-container {
    max-width: 1300px;
    margin: 40px auto;
    padding: 0 30px;
}
.assistant-header {
    background: #008060;
    color: white;
    padding: 15px 25px;
    border-radius: 12px 12px 0 0;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ai-chat-box {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow: hidden;
}
.chat-messages {
    height: 250px;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    background: #f9f9f9;
}
.msg {
    max-width: 80%;
    padding: 12px 18px;
    border-radius: 15px;
    font-size: 14px;
    line-height: 1.5;
}
.msg.ai {
    align-self: flex-start;
    background: #fff;
    color: #333;
    border: 1px solid #eee;
    border-bottom-left-radius: 2px;
}
.msg.user {
    align-self: flex-end;
    background: #333;
    color: #fff;
    border-bottom-right-radius: 2px;
}
.chat-input-area {
    padding: 20px;
    display: flex;
    gap: 10px;
    border-top: 1px solid #eee;
}
.chat-input-area input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    outline: none;
}
.chat-input-area button {
    background: #008060;
    color: white;
    border: none;
    padding: 0 25px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}
.chat-input-area button:hover { background: #00664c; }

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
                                onclick="selectColor(this, '<?php echo htmlspecialchars($c); ?>')" 
                                title="<?php echo htmlspecialchars($c); ?>">
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
                                <img src="<?php echo $design['custom_preview']; ?>" 
                                     onclick="selectCustomDesign('<?php echo $d_id; ?>', '<?php echo $design['custom_preview']; ?>', this.parentElement)">
                                <a href="custom_builder.php?pro_id=<?php echo $pro_id; ?>&edit_design=<?php echo $d_id; ?>" 
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
                    <input type="hidden" name="custom_design_id" id="customDesignIdInput" value="">
                    <input type="hidden" name="pro_id" value="<?php echo $product['Pro_Id']; ?>">
                    <input type="hidden" name="selected_size" id="selectedSizeInput" value="">
                    <input type="hidden" name="selected_color" id="selectedColorInput" value="<?php echo htmlspecialchars($colors[0]); ?>">
                    
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

    async function askAI() {
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
}

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
    const sessionToken = 'SS-' + Math.random().toString(36).substr(2, 9);
    await fetch(`init_bridge.php?token=${sessionToken}`);

const computerIP = "10.83.114.155";
const folderPath = "Module%20B";
const mobileURL = `http://${computerIP}/${folderPath}/mobile_capture.php?token=${sessionToken}`;

    Swal.fire({
        title: 'Connect Mobile Camera',
        html: `
            <div style="padding: 10px;">
                <p style="font-size: 14px;">Scan this QR code with your phone.</p>
                <div id="qrcode" style="display:flex; justify-content:center; margin: 20px 0;"></div>
                <div id="status-container">
                    <div class="spinner-border text-primary" role="status" style="width:1rem; height:1rem;"></div>
                    <span id="sync-status" style="font-size: 12px; color: #666; margin-left:10px;">Waiting for phone to connect...</span>
                </div>
            </div>
        `,
        didOpen: () => {
            new QRCode(document.getElementById("qrcode"), {
                text: mobileURL, // 现在的二维码包含的是 IP 地址，手机能识别了
                width: 200,
                height: 200
            });
            startPolling(sessionToken);
        }
    });
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

// 替换第 1177 行附近的 processMeasurement 函数
async function processMeasurement(imagePath) {
    Swal.fire({
        title: 'AI Neural Analysis...',
        html: `
            <div style="padding: 20px;">
                <div class="spinner-border text-success" role="status"></div>
                <p style="margin-top: 15px; font-weight: bold;">Detecting foot contours & A4 reference...</p>
                <div style="font-size: 11px; color: #888;">Gemini Vision is measuring your foot...</div>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('gemini_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                mode: 'sizer',
                image_path: imagePath 
            })
        });

        const data = await response.json();
        const aiReply = data.reply ? data.reply.trim() : "";

        // --- 核心修复：增加准确度校验逻辑 ---
        if (aiReply === "ERROR" || isNaN(parseFloat(aiReply))) {
            throw new Error("No foot or A4 paper detected. Please ensure your foot is centered on the paper.");
        }

        const realFootLength = parseFloat(aiReply);
        const recommendedUK = calculateUKSize(realFootLength); // 调用尺码计算算法
        
        Swal.fire({
            icon: 'success',
            title: 'Scan Successful!',
            html: `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center;">
                    <p style="margin: 0; color: #666; font-size: 14px;">AI Measured Length:</p>
                    <h2 style="color: #008060; margin: 5px 0; font-weight: 800;">${realFootLength} cm</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 10px 0;">
                    <p style="font-weight: bold; margin-bottom: 10px; font-size: 16px;">Perfect Fit: UK ${recommendedUK}</p>
                    
                    <div style="font-size: 11px; color: #999; line-height: 1.5; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ddd; text-align: left;">
                        <i class="bi bi-exclamation-triangle-fill" style="color: #e67e22;"></i> 
                        <strong>Disclaimer:</strong> AI Measured Length may have some inaccuracies and the response may not be completely accurate.
                    </div>
                </div>
            `,
            confirmButtonText: 'Apply to My Order',
            confirmButtonColor: '#008060'
        }).then((result) => {
            if (result.isConfirmed) {
                autoSelectSize(recommendedUK);
            }
        });

    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Measurement Failed',
            text: error.message || 'AI could not analyze the photo. Please try again.'
        });
    }
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
</script>

</body>
</html>