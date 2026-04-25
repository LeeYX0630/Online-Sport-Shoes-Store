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
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        
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

    if (($pro_id == 16 || $pro_id == 17) && $db_colour == 'Custom') {
        // 如果是定制款，且数据库里写的是 'Custom'，则把这个库存应用到所有颜色和自定义设计上
        foreach($colors as $c) {
            $variant_map[$c][$db_size] = $db_qty;
        }
        $variant_map['Custom Design'][$db_size] = $db_qty;
    } else {
        // 普通款，按数据库实际颜色存储
        $variant_map[$db_colour][$db_size] = $db_qty;
    }
    // --------------------

    if (!in_array($db_size, $all_unique_sizes)) {
        $all_unique_sizes[] = $db_size;
    }
}
sort($all_unique_sizes); 
$stmt_stock->close();

// 处理 Recently Viewed 逻辑
if (!isset($_SESSION['recently_viewed'])) $_SESSION['recently_viewed'] = [];
if (($key = array_search($pro_id, $_SESSION['recently_viewed'])) !== false) unset($_SESSION['recently_viewed'][$key]);
array_unshift($_SESSION['recently_viewed'], $pro_id);
if (count($_SESSION['recently_viewed']) > 8) array_pop($_SESSION['recently_viewed']);

// 获取用户的自定义设计 (使用预处理语句)
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

// 悬浮窗数据准备 (使用预处理语句)
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
                <button class="btn-wishlist-main" onclick="toggleWishlist(event, this)"><i class="bi bi-heart"></i></button>
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
        document.querySelectorAll('.size-box').forEach(box => {
            const size = box.getAttribute('data-size');
            const stock = parseInt(currentColorStock[size]) || 0;
            box.style.opacity = stock > 0 ? "1" : "0.3";
            box.style.pointerEvents = stock > 0 ? "auto" : "none";
        });
    }

    function handleSizeClick(el, sz) {
        document.querySelectorAll('.size-box').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        selectedSize = sz;
        document.getElementById('selectedSizeInput').value = sz;
        document.getElementById('sizeError').style.display = 'none';
        const stock = (variantMap[selectedColor] || {})[sz] || 0;
        document.getElementById('stockDisplay').innerHTML = `Only <strong>${stock}</strong> left in stock.`;
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
        if (!isLoggedIn) { promptLogin('add items to your cart'); return; }
        if (!selectedSize) { document.getElementById('sizeError').style.display = 'inline'; return; }

        let formData = new FormData(document.getElementById('addToCartForm'));
        formData.append('add_to_cart', '1');

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(() => {
            Swal.fire({
                title: 'Added to Cart!',
                text: 'Your item is waiting in the shopping cart.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#008060',
                confirmButtonText: 'View Cart',
                cancelButtonText: 'Continue Shopping'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'cart.php';
                else location.reload();
            });
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
</script>

</body>
</html>