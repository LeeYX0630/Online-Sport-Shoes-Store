<?php
// Module B: 核心交易组 - 品牌分类与产品目录页面 (Brands & Catalogue)
include '../includes/db_connection.php'; 
include '../includes/header.php';

$search_query = "";
$where_clauses = [];
$show_brands_mode = true; // 默认显示品牌墙
$page_title = "Shop By Brand";

// 获取数据库中产品最高价格，用于设定价格进度条的上限
$max_price_sql = "SELECT MAX(Pro_Price) AS max_p FROM product";
$max_price_res = $conn->query($max_price_sql);
$db_max_price = 2000; // 默认上限
if ($max_price_res && $max_price_res->num_rows > 0) {
    $mp_row = $max_price_res->fetch_assoc();
    if ($mp_row['max_p']) $db_max_price = ceil($mp_row['max_p']);
}

// 1. 处理顶部搜索栏
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $show_brands_mode = false;
    $search_query = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(product.Pro_Name LIKE '%$search_query%' 
                         OR product.Pro_Description LIKE '%$search_query%' 
                         OR brand.Brand_Name LIKE '%$search_query%')";
    $page_title = "Search Results: " . htmlspecialchars($_GET['search']);
}

// 2. 处理首页/品牌墙传过来的单品牌点击
if (isset($_GET['brand_id']) && !empty($_GET['brand_id'])) {
    $show_brands_mode = false;
    $filter_brand_id = intval($_GET['brand_id']);
    $where_clauses[] = "product.Brand_Id = '$filter_brand_id'";
    
    $b_sql = "SELECT Brand_Name FROM brand WHERE Brand_Id = '$filter_brand_id'";
    $b_res = $conn->query($b_sql);
    if($b_res && $b_res->num_rows > 0) {
        $page_title = $b_res->fetch_assoc()['Brand_Name'] . " Collection";
    }
}

// 3. 处理左侧 Sidebar Filter 多选过滤
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['filter_applied'])) {
        $show_brands_mode = false;
        $page_title = "Filtered Results";
    }

    // Brand
    if (isset($_GET['brands']) && is_array($_GET['brands'])) {
        $brand_ids = array_map('intval', $_GET['brands']);
        $where_clauses[] = "product.Brand_Id IN (" . implode(',', $brand_ids) . ")";
    }
    
    // Category
    if (isset($_GET['categories']) && is_array($_GET['categories'])) {
        $cat_ids = array_map('intval', $_GET['categories']);
        $where_clauses[] = "product.Cat_Id IN (" . implode(',', $cat_ids) . ")";
    }

    // Gender: 选中 Men 或 Women 时，自动包含 Unisex
    if (isset($_GET['gender']) && is_array($_GET['gender'])) {
        $genders = [];
        foreach ($_GET['gender'] as $g) {
            $safe_g = $conn->real_escape_string($g);
            $genders[] = "'$safe_g'";
            if (($safe_g == 'Men' || $safe_g == 'Women') && !in_array('Unisex', $_GET['gender'])) {
                if(!in_array("'Unisex'", $genders)) {
                    $genders[] = "'Unisex'";
                }
            }
        }
        $where_clauses[] = "product.Pro_Gender IN (" . implode(',', $genders) . ")";
    }

    // Age Group
    if (isset($_GET['age_group']) && is_array($_GET['age_group'])) {
        $ages = array_map(function($a) use ($conn) { return "'" . $conn->real_escape_string($a) . "'"; }, $_GET['age_group']);
        $where_clauses[] = "product.Pro_Age_Group IN (" . implode(',', $ages) . ")";
    }

    // Colour
    if (isset($_GET['colours']) && is_array($_GET['colours'])) {
        $colour_conditions = [];
        foreach ($_GET['colours'] as $col) {
            $colour_conditions[] = "product.Pro_Colour LIKE '%" . $conn->real_escape_string($col) . "%'";
        }
        $where_clauses[] = "(" . implode(' OR ', $colour_conditions) . ")";
    }

    // Size
    if (isset($_GET['sizes']) && is_array($_GET['sizes'])) {
        $size_conditions = [];
        foreach ($_GET['sizes'] as $sz) {
            $size_conditions[] = "product.Pro_Size LIKE '%" . $conn->real_escape_string($sz) . "%'";
        }
        $where_clauses[] = "(" . implode(' OR ', $size_conditions) . ")";
    }

    // Price Range (双滑块)
    if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
        $min = floatval($_GET['min_price']);
        $where_clauses[] = "product.Pro_Price >= $min";
    }
    if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
        $max = floatval($_GET['max_price']);
        $where_clauses[] = "product.Pro_Price <= $max";
    }

    // Sale
    if (isset($_GET['on_sale']) && $_GET['on_sale'] == '1') {
        $where_clauses[] = "product.Pro_Sale = 1";
    }
}

$where_clause = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue | Online Sport Shoes Store</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; color: #212529; display: flex; flex-direction: column; min-height: 100vh; }
        .flex-wrapper { flex: 1 0 auto; width: 100%; padding-bottom: 60px; }
        .catalogue-container { max-width: 1300px; margin: 40px auto; padding: 30px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        
        .page-header { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 15px;}
        .page-header h2 { font-size: 26px; color: #333; margin: 0; font-weight: bold; }
        
        /* 布局：左右分栏 */
        .shop-layout { display: grid; grid-template-columns: 260px 1fr; gap: 40px; align-items: start; }
        
        /* --- 左侧 Sidebar Filter --- */
        .filter-sidebar { 
            background: #fff; 
            position: sticky; 
            top: 20px; 
            max-height: calc(100vh - 40px);
            overflow-y: auto; 
            padding-right: 10px; 
        }
        .filter-sidebar::-webkit-scrollbar { width: 4px; }
        .filter-sidebar::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
        
        .filter-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; position: sticky; top: 0; background: #fff; z-index: 10; }
        .filter-header h3 { margin: 0; font-size: 18px; }
        .clear-all { color: #666; text-decoration: underline; font-size: 13px; }
        .clear-all:hover { color: #FF6B00; }
        
        /* 折叠面板 */
        details.filter-group { border-bottom: 1px solid #eee; padding: 15px 0; }
        details.filter-group summary { font-weight: bold; font-size: 15px; cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; outline: none; }
        details.filter-group summary::-webkit-details-marker { display: none; }
        details.filter-group summary::after { content: "⌄"; font-size: 20px; color: #333; transition: transform 0.3s; }
        details.filter-group[open] summary::after { transform: rotate(180deg); }
        
        .filter-options { margin-top: 15px; display: flex; flex-direction: column; gap: 12px; padding-right: 5px;}
        .filter-options label { font-size: 14px; color: #444; display: flex; align-items: center; cursor: pointer; }
        .filter-options input[type="checkbox"] { margin-right: 10px; accent-color: #333; width: 16px; height: 16px; cursor: pointer;}
        
        /* 视觉化颜色筛选器 (Visual Color Grid) */
        .color-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px 5px; margin-top: 15px; }
        .color-swatch-wrapper { display: flex; flex-direction: column; align-items: center; cursor: pointer; text-align: center; }
        .color-swatch-wrapper input { display: none; } 
        .color-swatch-circle { width: 36px; height: 36px; border-radius: 50%; border: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; transition: 0.2s; position: relative; }
        .color-swatch-wrapper:hover .color-swatch-circle { transform: scale(1.1); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        
        .color-swatch-wrapper input:checked + .color-swatch-circle::after {
            content: ''; width: 6px; height: 12px; border: solid #fff; border-width: 0 2px 2px 0; 
            transform: rotate(45deg); margin-bottom: 2px;
        }
        .color-swatch-wrapper input[value="White"]:checked + .color-swatch-circle::after { border-color: #333; }
        .color-name { font-size: 12px; color: #333; }
        
        /* 进度条滑块 (Price Range Slider) */
        .range-slider { position: relative; width: 100%; height: 40px; margin-top: 20px; }
        .slider-track { width: 100%; height: 5px; background: #ddd; position: absolute; top: 50%; transform: translateY(-50%); border-radius: 5px; }
        .slider-range { height: 5px; background: #333; position: absolute; top: 50%; transform: translateY(-50%); border-radius: 5px; }
        .range-slider input[type="range"] { position: absolute; width: 100%; top: 50%; transform: translateY(-50%); background: transparent; pointer-events: none; -webkit-appearance: none; outline: none; }
        .range-slider input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; height: 20px; width: 20px; background: #333; border-radius: 50%; cursor: pointer; pointer-events: auto; position: relative; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .range-slider input[type="range"]::-moz-range-thumb { height: 20px; width: 20px; background: #333; border-radius: 50%; cursor: pointer; pointer-events: auto; border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .price-display { display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-top: 5px; font-weight: bold; }
        
        /* 开关 (Toggle Switch) */
        .toggle-container { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider-round { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider-round:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2);}
        input:checked + .slider-round { background-color: #333; }
        input:checked + .slider-round:before { transform: translateX(20px); }
        
        /* --- 模式 A: 品牌墙 --- */
        .brand-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 50px; }
        .brand-card-img { width: 100%; height: 250px; background: #333; border-radius: 8px; overflow: hidden; position: relative; display: block; transition: transform 0.3s; }
        .brand-card-img:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .brand-card-img img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; transition: 0.3s; }
        .brand-card-img:hover img { opacity: 1; }
        .brand-card-title { margin-top: 15px; font-weight: bold; font-size: 16px; color: #333; display: block; text-decoration: none; text-align: center; }
        .brand-card-title:hover { color: #FF6B00; }
        
        /* A-Z 分类格子 */
        .az-filter { display: flex; flex-wrap: wrap; gap: 12px; margin: 40px 0 40px; justify-content: center; padding-bottom: 40px; border-bottom: 1px solid #eee; }
        .az-btn { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border: 1px solid #e0e0e0; background: #fff; color: #222; text-decoration: none; font-size: 20px; font-weight: 700; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); border-radius: 10px; }
        .az-btn:hover { background: #222; color: #fff; border-color: #222; transform: translateY(-4px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
        .az-btn.disabled { color: #d1d1d1; border-color: #f0f0f0; background: #fafafa; pointer-events: none; }
        
        .brand-list-group { margin-bottom: 30px; display: flex; border-bottom: 1px solid #f4f4f4; padding-bottom: 20px; scroll-margin-top: 100px; align-items: flex-start; }
        .brand-letter { width: 100px; font-size: 32px; font-weight: 900; color: #111; }
        .brand-items { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .brand-link { color: #444; text-decoration: none; font-size: 16px; font-weight: 500; transition: color 0.2s;}
        .brand-link:hover { color: #FF6B00; text-decoration: underline; }
        
        /* --- 模式 B: 产品卡片 --- */
        .room-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .room-card { background: #fff; border-radius: 8px; overflow: hidden; transition: transform 0.3s ease; display: flex; flex-direction: column; border: 1px solid #eee; text-decoration: none; color: inherit; cursor: pointer; position: relative;}
        .room-card:hover { transform: translateY(-5px); box-shadow: 0px 8px 25px rgba(0,0,0,0.1); border-color: #FF6B00; }
        .card-image { width: 100%; height: 220px; background: #f9f9f9; overflow: hidden; }
        .card-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; mix-blend-mode: multiply; }
        .room-card:hover .card-image img { transform: scale(1.05); }
        .card-content { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .category-badge { background: #f8f9fa; color: #FF6B00; font-size: 11px; font-weight: bold; padding: 3px 6px; border-radius: 4px; border: 1px solid #FF6B00; width: fit-content; margin-bottom: 8px;}
        .room-title { font-size: 16px; font-weight: bold; color: #333; margin: 0 0 10px 0; transition: color 0.3s; line-height: 1.3;}
        .room-card:hover .room-title { color: #FF6B00; }
        .room-price { font-size: 18px; color: #333; font-weight: bold; margin-top: auto; }
        .badge-sale { position: absolute; top: 10px; left: 10px; background: #ffeb3b; color: #000; font-size: 11px; font-weight: bold; padding: 4px 8px; border-radius: 4px; display: flex; align-items: center; gap: 4px;}
        .no-results { grid-column: 1/-1; text-align: center; padding: 50px; color: #999; border: 1px dashed #ddd; border-radius: 8px;}
        
        /* 响应式 */
        @media (max-width: 992px) { .shop-layout { grid-template-columns: 1fr; } .room-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .brand-grid, .room-grid { grid-template-columns: repeat(2, 1fr); } .brand-items { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .brand-grid, .room-grid, .brand-items { grid-template-columns: 1fr; } .brand-list-group { flex-direction: column; } .brand-letter { margin-bottom: 15px; } .catalogue-container{ padding: 20px; } .az-btn { width: 40px; height: 40px; font-size: 16px; } }
    </style>
</head>
<body>

<div class="flex-wrapper">
    <div class="catalogue-container">

        <?php if ($show_brands_mode): ?>

            <div class="page-header" style="border: none;">
                <h2 style="text-align: center;">Brands</h2>
            </div>

            <div class="brand-grid">
                <?php
                $top_brands = $conn->query("SELECT * FROM brand ORDER BY Brand_Name ASC LIMIT 8");
                if ($top_brands && $top_brands->num_rows > 0) {
                    while($tb = $top_brands->fetch_assoc()) {
                        $logo = !empty($tb['Brand_Logo']) ? "../images/brands/" . $tb['Brand_Logo'] : "../images/brands/placeholder.png";
                        echo '<div>
                                <a href="catalogue.php?brand_id='.$tb['Brand_Id'].'" class="brand-card-img">
                                    <img src="'.$logo.'" alt="'.$tb['Brand_Name'].'" onerror="this.onerror=null; this.src=\'../images/brands/placeholder.png\'">
                                </a>
                                <a href="catalogue.php?brand_id='.$tb['Brand_Id'].'" class="brand-card-title">'.$tb['Brand_Name'].'</a>
                              </div>';
                    }
                }
                ?>
            </div>

            <?php
            $brands_by_letter = [];
            $brand_list_res = $conn->query("SELECT Brand_Id, Brand_Name FROM brand ORDER BY Brand_Name ASC");
            if ($brand_list_res && $brand_list_res->num_rows > 0) {
                while($b = $brand_list_res->fetch_assoc()) {
                    $first_letter = strtoupper(substr($b['Brand_Name'], 0, 1));
                    $brands_by_letter[$first_letter][] = $b;
                }
            }
            $active_letters = array_keys($brands_by_letter);
            ?>

            <div class="az-filter">
                <?php
                foreach (range('A', 'Z') as $char) {
                    $disabled = in_array($char, $active_letters) ? "" : "disabled";
                    echo '<a href="#letter_'.$char.'" class="az-btn '.$disabled.'">'.$char.'</a>';
                }
                ?>
            </div>

            <div class="brands-directory">
                <?php
                foreach ($active_letters as $letter) {
                    echo '<div class="brand-list-group" id="letter_'.$letter.'"><div class="brand-letter">'.$letter.'</div><div class="brand-items">';
                    foreach($brands_by_letter[$letter] as $br) {
                        echo '<a href="catalogue.php?brand_id='.$br['Brand_Id'].'" class="brand-link">'.$br['Brand_Name'].'</a>';
                    }
                    echo '</div></div>';
                }
                ?>
            </div>

        <?php else: ?>

            <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end;">
                <h2><?php echo $page_title; ?></h2>
                <span style="color: #666; font-size: 14px;">Shop / Catalogue</span>
            </div>

            <div class="shop-layout">
                
                <aside class="filter-sidebar">
                    <form method="GET" action="catalogue.php" id="filterForm">
                        <input type="hidden" name="filter_applied" value="1">
                        <?php if(!empty($_GET['search'])): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                        <?php endif; ?>
                        
                        <div class="filter-header">
                            <h3>Filters</h3>
                            <a href="catalogue.php" class="clear-all">Clear All</a>
                        </div>

                        <details class="filter-group" open>
                            <summary>Category</summary>
                            <div class="filter-options">
                                <?php
                                $cat_res = $conn->query("SELECT * FROM category ORDER BY Cat_Name ASC");
                                while($c = $cat_res->fetch_assoc()) {
                                    $checked = (isset($_GET['categories']) && in_array($c['Cat_Id'], $_GET['categories'])) ? "checked" : "";
                                    echo '<label><input type="checkbox" name="categories[]" value="'.$c['Cat_Id'].'" '.$checked.'> '.$c['Cat_Name'].'</label>';
                                }
                                ?>
                            </div>
                        </details>

                        <details class="filter-group" open>
                            <summary>Gender</summary>
                            <div class="filter-options">
                                <?php
                                $genders = ['Men', 'Women', 'Unisex'];
                                foreach($genders as $g) {
                                    $checked = (isset($_GET['gender']) && in_array($g, $_GET['gender'])) ? "checked" : "";
                                    echo '<label><input type="checkbox" name="gender[]" value="'.$g.'" '.$checked.'> '.$g.'</label>';
                                }
                                ?>
                            </div>
                        </details>

                        <details class="filter-group">
                            <summary>Age Group</summary>
                            <div class="filter-options">
                                <?php
                                $ages = ['Adult', 'Kids'];
                                foreach($ages as $a) {
                                    $checked = (isset($_GET['age_group']) && in_array($a, $_GET['age_group'])) ? "checked" : "";
                                    echo '<label><input type="checkbox" name="age_group[]" value="'.$a.'" '.$checked.'> '.$a.'</label>';
                                }
                                ?>
                            </div>
                        </details>

                        <details class="filter-group" open>
                            <summary>Brand</summary>
                            <div class="filter-options">
                                <?php
                                $br_res = $conn->query("SELECT * FROM brand ORDER BY Brand_Name ASC");
                                while($br = $br_res->fetch_assoc()) {
                                    $checked = "";
                                    if (isset($_GET['brand_id']) && $_GET['brand_id'] == $br['Brand_Id']) $checked = "checked";
                                    if (isset($_GET['brands']) && in_array($br['Brand_Id'], $_GET['brands'])) $checked = "checked";
                                    echo '<label><input type="checkbox" name="brands[]" value="'.$br['Brand_Id'].'" '.$checked.'> '.$br['Brand_Name'].'</label>';
                                }
                                ?>
                            </div>
                        </details>

                        <details class="filter-group">
                            <summary>Size (UK)</summary>
                            <div class="filter-options">
                                <?php
                                $common_sizes = ['6', '7', '8', '9', '10', '11', '12'];
                                foreach($common_sizes as $sz) {
                                    $checked = (isset($_GET['sizes']) && in_array($sz, $_GET['sizes'])) ? "checked" : "";
                                    echo '<label><input type="checkbox" name="sizes[]" value="'.$sz.'" '.$checked.'> UK '.$sz.'</label>';
                                }
                                ?>
                            </div>
                        </details>

                        <details class="filter-group" open>
                            <summary>Colour</summary>
                            <div class="color-grid">
                                <?php
                                $color_map = [
                                    'Purple' => '#7B3B9C', 'Black' => '#000000', 'Red' => '#E7352B',
                                    'Orange' => '#F36B26', 'Blue' => '#1790C8', 'White' => '#FFFFFF',
                                    'Brown' => '#835C3E', 'Green' => '#7BB342', 'Yellow' => '#FCD53F',
                                    'Grey' => '#828282', 'Pink' => '#F0728F'
                                ];
                                foreach($color_map as $col_name => $hex) {
                                    $checked = (isset($_GET['colours']) && in_array($col_name, $_GET['colours'])) ? "checked" : "";
                                    echo '<label class="color-swatch-wrapper">
                                            <input type="checkbox" name="colours[]" value="'.$col_name.'" '.$checked.'>
                                            <div class="color-swatch-circle" style="background-color: '.$hex.';"></div>
                                            <span class="color-name">'.$col_name.'</span>
                                          </label>';
                                }
                                ?>
                            </div>
                        </details>

                        <?php
                            $current_min = isset($_GET['min_price']) ? $_GET['min_price'] : 0;
                            $current_max = isset($_GET['max_price']) ? $_GET['max_price'] : $db_max_price;
                        ?>
                        <details class="filter-group" open>
                            <summary>Price</summary>
                            <div class="range-slider">
                                <div class="slider-track" id="slider-track"></div>
                                <input type="range" name="min_price" id="slider-min" min="0" max="<?php echo $db_max_price; ?>" value="<?php echo $current_min; ?>" oninput="slideMin()">
                                <input type="range" name="max_price" id="slider-max" min="0" max="<?php echo $db_max_price; ?>" value="<?php echo $current_max; ?>" oninput="slideMax()">
                            </div>
                            <div class="price-display">
                                <span>RM <span id="display-min"><?php echo $current_min; ?></span></span>
                                <span>RM <span id="display-max"><?php echo $current_max; ?></span></span>
                            </div>
                        </details>
                        
                        <details class="filter-group" open>
                            <summary>Sale</summary>
                            <div class="toggle-container">
                                <span style="font-size: 14px; font-weight: bold; color: #555;">On Sale</span>
                                <?php $sale_checked = (isset($_GET['on_sale']) && $_GET['on_sale'] == '1') ? 'checked' : ''; ?>
                                <label class="switch">
                                    <input type="checkbox" name="on_sale" value="1" <?php echo $sale_checked; ?>>
                                    <span class="slider-round"></span>
                                </label>
                            </div>
                        </details>
                        
                        </form>
                </aside>

                <main class="product-listing">
                    <div class="room-grid">
                        <?php
                        $sql = "SELECT product.*, brand.Brand_Name 
                                FROM product 
                                JOIN brand ON product.Brand_Id = brand.Brand_Id
                                $where_clause 
                                ORDER BY product.Pro_Id DESC";
                        
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                // 获取该产品的所有颜色
                                $color_sql = "SELECT DISTINCT Pro_Colour FROM PRODUCT_STOCK WHERE Pro_Id = '{$row['Pro_Id']}' ORDER BY Pro_Colour ASC";
                                $color_result = $conn->query($color_sql);
                                
                                $colors = [];
                                if ($color_result && $color_result->num_rows > 0) {
                                    while ($color_row = $color_result->fetch_assoc()) {
                                        $colors[] = $color_row['Pro_Colour'];
                                    }
                                } else {
                                    // 如果没有库存记录，使用产品表中的颜色
                                    // 支持 / 和 , 两种分隔符
                                    if (!empty($row['Pro_Colour'])) {
                                        // 先用 / 分割，然后再用 , 分割
                                        $raw_colors = preg_split('/[,\/]/', $row['Pro_Colour']);
                                        // 清理和去重
                                        $seen_colors = [];
                                        foreach ($raw_colors as $c) {
                                            $c = trim($c);
                                            if (!empty($c) && !in_array(strtolower($c), array_map('strtolower', $seen_colors))) {
                                                $seen_colors[] = $c;
                                            }
                                        }
                                        $colors = $seen_colors;
                                    }
                                }
                                
                                // 如果没有颜色信息，使用默认值
                                if (empty($colors)) {
                                    $colors = ['Default'];
                                }
                                
                                // 为每个颜色生成一张卡片
                                foreach ($colors as $current_variant_color) {
                                    // --- 智能抓取颜色对应的封面图 ---
                                    $base_img = $row['Pro_Image'];
                                    $img_src = "../images/brands/placeholder.png"; 
                                    
                                    if (!empty($base_img)) {
                                        $path_parts = pathinfo($base_img);
                                        $filename = $path_parts['filename'];
                                        $extension = isset($path_parts['extension']) ? "." . $path_parts['extension'] : "";
                                        
                                        // 将颜色转换为小写并处理空格
                                        $color_slug = strtolower(str_replace(' ', '_', $current_variant_color));
                                        
                                        // 拼接配色图片路径，例如 ../uploads/nb530_white_1.jpg
                                        $color_variant_img = "../uploads/" . $filename . "_" . $color_slug . "_1" . $extension;

                                        if (file_exists($color_variant_img)) {
                                            $img_src = $color_variant_img;
                                        } else {
                                            // 备用：查找任何以这个颜色开头的图片
                                            $found_images = glob("../uploads/{$filename}_{$color_slug}*.*");
                                            if (!empty($found_images)) {
                                                $img_src = $found_images[0];
                                            } else {
                                                // 再备用：查找任何相关图片
                                                $found_images = glob("../uploads/{$filename}*.*");
                                                if (!empty($found_images)) {
                                                    $img_src = $found_images[0];
                                                }
                                            }
                                        }
                                    }
                                    
                                    $desc = !empty($row['Pro_Description']) ? substr($row['Pro_Description'], 0, 60) . '...' : 'Premium quality sports shoes.';
                                    ?>
                                    
                                    <a href="product_details.php?pro_id=<?php echo $row['Pro_Id']; ?>&color=<?php echo urlencode($current_variant_color); ?>" class="room-card">
                                        <?php if(isset($row['Pro_Sale']) && $row['Pro_Sale'] == 1): ?>
                                            <div class="badge-sale">↗ TRENDING / SALE</div>
                                        <?php endif; ?>
                                        
                                        <div class="card-image">
                                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($row['Pro_Name']); ?>" onerror="this.onerror=null; this.src='../images/brands/placeholder.png'">
                                        </div>
                                        <div class="card-content">
                                            <div class="category-badge"><?php echo $row['Brand_Name']; ?></div>
                                            <h3 class="room-title"><?php echo $row['Pro_Name']; ?> - <?php echo htmlspecialchars($current_variant_color); ?></h3>
                                            <div class="room-price">RM <?php echo number_format($row['Pro_Price'], 2); ?></div>
                                        </div>
                                    </a>

                                    <?php
                                }
                            }
                        } else {
                            echo "<div class='no-results'>No products match your filters.<br><a href='catalogue.php' style='color:#FF6B00; text-decoration:underline; margin-top:10px; display:inline-block;'>Clear Filters</a></div>";
                        }
                        ?>
                    </div>
                </main>

            </div> <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // ==========================================
    // 新增：保持页面滚动位置不变 (Scroll Restoration)
    // ==========================================
    // 在页面即将刷新/离开前，保存当前的滚动坐标
    window.addEventListener("beforeunload", function() {
        sessionStorage.setItem('scrollpos', window.scrollY);
    });

    // 页面加载完成后，瞬间跳回之前保存的坐标
    document.addEventListener("DOMContentLoaded", function() {
        let scrollpos = sessionStorage.getItem('scrollpos');
        if (scrollpos) {
            window.scrollTo({ top: parseInt(scrollpos), behavior: 'instant' });
            sessionStorage.removeItem('scrollpos'); // 用完即删
        }
    });

    // ==========================================
    // 自动提交表单逻辑 (Auto-Submit)
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('filterForm');
        
        if (filterForm) {
            const checkboxes = filterForm.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(chk => {
                chk.addEventListener('change', function() {
                    filterForm.submit();
                });
            });

            const minPriceSlider = document.getElementById('slider-min');
            const maxPriceSlider = document.getElementById('slider-max');
            
            if (minPriceSlider && maxPriceSlider) {
                minPriceSlider.addEventListener('change', function() {
                    filterForm.submit();
                });
                maxPriceSlider.addEventListener('change', function() {
                    filterForm.submit();
                });
            }
        }
    });

    // 原有的滑块 UI 视觉更新逻辑 (拖动中即时更新数字和颜色)
    let sliderMin = document.getElementById("slider-min");
    let sliderMax = document.getElementById("slider-max");
    let displayMin = document.getElementById("display-min");
    let displayMax = document.getElementById("display-max");
    let sliderTrack = document.getElementById("slider-track");
    
    if(sliderMin && sliderMax) {
        let maxGap = 50; 
        let sliderMaxValue = document.getElementById("slider-max").max;

        function slideMin(){
            if(parseInt(sliderMax.value) - parseInt(sliderMin.value) <= maxGap){
                sliderMin.value = parseInt(sliderMax.value) - maxGap;
            }
            displayMin.textContent = sliderMin.value;
            fillColor();
        }

        function slideMax(){
            if(parseInt(sliderMax.value) - parseInt(sliderMin.value) <= maxGap){
                sliderMax.value = parseInt(sliderMin.value) + maxGap;
            }
            displayMax.textContent = sliderMax.value;
            fillColor();
        }

        function fillColor(){
            let percent1 = (sliderMin.value / sliderMaxValue) * 100;
            let percent2 = (sliderMax.value / sliderMaxValue) * 100;
            sliderTrack.style.background = `linear-gradient(to right, #ddd ${percent1}%, #333 ${percent1}%, #333 ${percent2}%, #ddd ${percent2}%)`;
        }
        
        window.addEventListener('DOMContentLoaded', fillColor);
    }
</script>

</body>
</html>