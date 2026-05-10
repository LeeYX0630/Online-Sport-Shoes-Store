<?php
// admin_manage_products.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id']; // 从 Session 获取当前管理员 ID
$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';

// --- 新增逻辑：如果不是超级管理员，则根据 admin_id 去查询他负责的 Brand_Id ---
$admin_brand_id = 0;
if ($admin_role == 3) {
    $brand_check_sql = "SELECT Brand_Id FROM brand WHERE Admin_Id = '$admin_id' LIMIT 1";
    $brand_check_res = $conn->query($brand_check_sql);
    if ($brand_check_res && $brand_check_res->num_rows > 0) {
        $brand_row = $brand_check_res->fetch_assoc();
        $admin_brand_id = $brand_row['Brand_Id'];
    }
}

$swalCode = ""; 

// 1. 删除逻辑
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // 权限检查：Level 1 和 2 可以删除任何产品，Level 3 只能删除属于自己品牌的产品
    $checkSql = ($admin_role == 1 || $admin_role == 2) ? "" : " AND Brand_Id = '$admin_brand_id'";
    $delSql = "DELETE FROM product WHERE Pro_Id = '$id' $checkSql";
    if ($conn->query($delSql) === TRUE) {
        $swalCode = "Swal.fire({ title: 'Deleted!', text: 'Product removed successfully.', icon: 'success', confirmButtonColor: '#FF6B00' }).then(() => { window.location.href = 'admin_manage_products.php'; });";
    }
}

// --- 动态获取数据库中产品的最低价和最高价 ---
$priceBoundsSql = "SELECT MIN(Pro_Price) as minP, MAX(Pro_Price) as maxP FROM product";
$boundsRes = $conn->query($priceBoundsSql)->fetch_assoc();
$list_min = floor($boundsRes['minP'] ?? 0);
$list_max = ceil($boundsRes['maxP'] ?? 1000);

// 2. 获取筛选参数
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['cat'] ?? '';
$brand_filter = $_GET['brand'] ?? '';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : $list_min;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : $list_max;

$gender_filter = $_GET['gender'] ?? '';
$age_filter = $_GET['age'] ?? '';

// --- 修改权限逻辑核心 ---
// 如果是 Level 1 或 Level 2，默认看到全部 (1=1)
// 否则（Level 3），必须限制在刚才查到的 $admin_brand_id 下
if ($admin_role == 1 || $admin_role == 2) {
    $queryCondition = "1=1";
} else {
    $queryCondition = "p.Brand_Id = '$admin_brand_id'";
}

if (!empty($search)) $queryCondition .= " AND (p.Pro_Name LIKE '%$search%' OR p.Pro_Id LIKE '%$search%')";
if (!empty($cat_filter)) $queryCondition .= " AND p.Cat_Id = '$cat_filter'";

// 只有超级管理员和高级管理员可以使用品牌筛选切换
if (($admin_role == 1 || $admin_role == 2) && !empty($brand_filter)) {
    $queryCondition .= " AND p.Brand_Id = '$brand_filter'";
}

if (!empty($gender_filter)) $queryCondition .= " AND p.Pro_Gender = '$gender_filter'";
if (!empty($age_filter)) $queryCondition .= " AND p.Pro_Age_Group = '$age_filter'";
$queryCondition .= " AND p.Pro_Price BETWEEN $min_price AND $max_price";

$sql = "SELECT p.*, b.Brand_Name, c.Cat_Name 
        FROM product p
        LEFT JOIN brand b ON p.Brand_Id = b.Brand_Id 
        LEFT JOIN category c ON p.Cat_Id = c.Cat_Id 
        WHERE $queryCondition 
        ORDER BY p.Pro_Id DESC";
$result = $conn->query($sql);

$categories = $conn->query("SELECT * FROM category");
$brands = $conn->query("SELECT * FROM brand");

// 智能匹配图片函数
function getSmartProductImage($filename, $colors = []) {
    if (empty($filename)) return '../assets/no-image.png';
    $info = pathinfo($filename);
    $baseName = $info['filename'];
    $extension = isset($info['extension']) ? "." . $info['extension'] : ".jpg";
    if (!empty($colors)) {
        foreach ($colors as $color) {
            $color_slug = strtolower(str_replace(' ', '_', trim($color)));
            $variant_path = "../uploads/" . $baseName . "_" . $color_slug . "_1" . $extension;
            if (file_exists($variant_path)) return $variant_path;
        }
    }
    $default_img = "../uploads/" . $baseName . "_1" . $extension;
    return file_exists($default_img) ? $default_img : '../assets/no-image.png';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Online Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --sidebar-width: 260px; --primary-orange: #FF6B00; --hover-orange: #e66000; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; margin: 0; }
        .main-wrapper { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; width: calc(100% - var(--sidebar-width)); }
        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--primary-orange); object-fit: cover; }
        .filter-card, .table-container { background: white; border-radius: 15px; padding: 24px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .filter-row { display: flex; align-items: center; gap: 12px; flex-wrap: nowrap; }
        .search-wrapper { flex: 1.5; position: relative; display: flex; align-items: center; }
        .search-wrapper i.bi-search { position: absolute; left: 15px; color: #94a3b8; }
        .search-wrapper input { width: 100%; padding: 10px 35px 10px 40px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; transition: 0.3s; }
        .clear-search { position: absolute; right: 12px; cursor: pointer; color: #94a3b8; display: none; }
        .select-custom { flex: 0.8; position: relative; display: flex; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 10px; }
        .select-custom select { border: none; background: transparent; padding: 10px 5px; width: 100%; font-size: 14px; outline: none; cursor: pointer; }
        .price-range-wrapper { flex: 1.2; padding: 0 10px; }
        .price-label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .price-label span { color: var(--primary-orange); }
        .range-slider { position: relative; height: 5px; background: #e2e8f0; border-radius: 5px; margin-top: 15px; }
        .slider-track { position: absolute; height: 100%; background: var(--primary-orange); border-radius: 5px; z-index: 1; }
        .range-slider input { position: absolute; width: 100%; height: 5px; top: 0; background: none; pointer-events: none; -webkit-appearance: none; appearance: none; z-index: 2; margin: 0; }
        input[type="range"]::-webkit-slider-thumb { height: 16px; width: 16px; border-radius: 50%; background: #fff; border: 2px solid var(--primary-orange); pointer-events: auto; -webkit-appearance: none; cursor: pointer; }
        input[type="range"]::-moz-range-thumb { height: 16px; width: 16px; border-radius: 50%; background: #fff; border: 2px solid var(--primary-orange); pointer-events: auto; cursor: pointer; }
        .btn-add { background: var(--primary-orange); color: white; padding: 11px 18px; border-radius: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.3s; white-space: nowrap; }
        .btn-add:hover { background: var(--hover-orange); color: white; }
        .btn-filter-submit { background: #f1f5f9; border: none; padding: 10px 15px; border-radius: 12px; color: #475569; font-weight: 600; transition: 0.3s; }
        .btn-filter-submit:hover { background: var(--primary-orange); color: white; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; }
        .brand-badge { background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .action-link { transition: 0.2s; padding: 5px; border-radius: 8px; }
        .view-eye { color: #6366f1; }
        .edit-pen { color: #FF6B00; }
        .delete-trash { color: #ef4444; }
        .cursor-pointer { cursor: pointer; }
        .selectable-box { border: 1px solid #dee2e6; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; color: #555; background-color: white; transition: all 0.2s ease-in-out; min-width: 65px; height: 38px; display: inline-flex; align-items: center; justify-content: center; text-align: center; }
        .selectable-box:hover { border-color: #FF6B00; color: #FF6B00; }
        .selectable-box.active { border: 2px solid #FF6B00; background-color: #FFF8F3; color: #FF6B00; font-weight: bold; }
        .selectable-box.disabled { background-color: #f2f2f2; color: #ccc; border-color: #ddd; cursor: not-allowed; pointer-events: none; text-decoration: line-through; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        .bg-orange { background-color: var(--primary-orange) !important; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; padding: 15px; width: 100%; } }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--primary-orange);">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #6c757d;">Products</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Manage Products</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                    <small class="text-muted"><?php 
                        if($admin_role == 1) echo 'Super Admin';
                        elseif($admin_role == 2) echo 'Senior Manager';
                        else echo 'Manager'; 
                    ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $_SESSION['admin_image'] ?? 'default_admin.png'; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark m-0">Product Inventory</h5>
            <a href="add_product.php" class="btn-add"><i class="bi bi-plus-lg"></i> Add Product</a>
        </div>

        <div class="filter-card">
            <form method="GET" id="filterForm" class="filter-row">
                <div class="search-wrapper">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search name/ID" value="<?php echo htmlspecialchars($search); ?>">
                    <i class="bi bi-x-circle-fill clear-search" id="clearSearch"></i>
                </div>

                <div class="select-custom">
                    <i class="bi bi-tag"></i>
                    <select name="cat" onchange="this.form.submit()">
                        <option value="">Categories</option>
                        <?php while($c = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $c['Cat_Id']; ?>" <?php if($cat_filter == $c['Cat_Id']) echo 'selected'; ?>><?php echo $c['Cat_Name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <?php if($admin_role == 1 || $admin_role == 2): ?>
                <div class="select-custom">
                    <i class="bi bi-award"></i>
                    <select name="brand" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        <?php while($b = $brands->fetch_assoc()): ?>
                            <option value="<?php echo $b['Brand_Id']; ?>" <?php if($brand_filter == $b['Brand_Id']) echo 'selected'; ?>><?php echo $b['Brand_Name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="select-custom" style="flex: 0.6;">
                    <select name="gender" onchange="this.form.submit()">
                        <option value="">Gender</option>
                        <option value="Men" <?php if($gender_filter == 'Men') echo 'selected'; ?>>Men</option>
                        <option value="Women" <?php if($gender_filter == 'Women') echo 'selected'; ?>>Women</option>
                        <option value="Unisex" <?php if($gender_filter == 'Unisex') echo 'selected'; ?>>Unisex</option>
                    </select>
                </div>

                <div class="select-custom" style="flex: 0.6;">
                    <select name="age" onchange="this.form.submit()">
                        <option value="">Age Group</option>
                        <option value="Adult" <?php if($age_filter == 'Adult') echo 'selected'; ?>>Adult</option>
                        <option value="Kids" <?php if($age_filter == 'Kids') echo 'selected'; ?>>Kids</option>
                    </select>
                </div>

                <div class="price-range-wrapper">
                    <div class="price-label">Price: <span>RM <span id="minDisp"><?php echo $min_price; ?></span> - RM <span id="maxDisp"><?php echo $max_price; ?></span></span></div>
                    <div class="range-slider">
                        <div class="slider-track" id="sliderTrack"></div>
                        <input type="range" name="min_price" id="minRange" min="<?php echo $list_min; ?>" max="<?php echo $list_max; ?>" value="<?php echo $min_price; ?>" step="1">
                        <input type="range" name="max_price" id="maxRange" min="<?php echo $list_min; ?>" max="<?php echo $list_max; ?>" value="<?php echo $max_price; ?>" step="1">
                    </div>
                </div>

                <button type="submit" class="btn-filter-submit">Apply</button>
            </form>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>IMAGE</th>
                            <th>PRODUCT INFO</th>
                            <?php if($admin_role == 1 || $admin_role == 2): ?> <th>BRAND</th> <?php endif; ?>
                            <th>CATEGORY</th>
                            <th>PRICE</th>
                            <th class="text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): 
                            $pro_id = $row['Pro_Id'];
                            $variant_map = [];
                            $stock_sql = "SELECT Pro_Size, Pro_Colour, Quantity FROM PRODUCT_STOCK WHERE Pro_Id = '$pro_id'";
                            $stock_res = $conn->query($stock_sql);
                            if($stock_res) {
                                while($s_row = $stock_res->fetch_assoc()) {
                                    $variant_map[trim($s_row['Pro_Colour'])][trim($s_row['Pro_Size'])] = intval($s_row['Quantity']);
                                }
                            }
                            $row['variantMap'] = $variant_map;

                            $raw_colors = preg_split('/[,\/]/', $row['Pro_Colour'] ?? '');
                            $colors = [];
                            foreach($raw_colors as $rc) {
                                $c = trim($rc);
                                if (!empty($c) && !in_array($c, $colors)) { $colors[] = $c; }
                            }
                            if (empty($colors)) $colors[] = "Default";

                            $base_img = $row['Pro_Image'];
                            $path_parts = pathinfo($base_img);
                            $base_name = preg_replace('/_\d+$/', '', $path_parts['filename'] ?? '');

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
                                        $slug = strtolower(str_replace(' ', '_', $c));
                                        if (strpos(strtolower($filename_only), "_" . $slug) !== false) {
                                            $matched_color = $c;
                                            break;
                                        }
                                    }
                                    $color_galleries[$matched_color][] = "../uploads/" . $file_name;
                                }
                            }
                            
                            foreach ($color_galleries as $c => &$images) {
                                if (empty($images)) $images[] = empty($base_img) ? '../assets/no-image.png' : "../uploads/" . $base_img;
                                while (count($images) < 4) $images[] = "../assets/no-image.png";
                            }
                            unset($images);
                            $row['colorGalleries'] = $color_galleries;
                            $img_path = $color_galleries[$colors[0]][0];
                        ?>
                        <tr>
                            <td><img src="<?php echo $img_path; ?>" class="product-img" onerror="this.src='../assets/no-image.png'"></td>
                            <td>
                                <div class="fw-bold"><?php echo $row['Pro_Name']; ?></div>
                                <small class="text-muted">#<?php echo $row['Pro_Id']; ?> <?php if(!empty($colors)) echo "| Colors: ".implode(', ', $colors); ?></small>
                            </td>
                            <?php if($admin_role == 1 || $admin_role == 2): ?><td><span class="brand-badge"><?php echo $row['Brand_Name']; ?></span></td><?php endif; ?>
                            <td><?php echo $row['Cat_Name']; ?></td>
                            <td class="fw-bold">RM <?php echo number_format($row['Pro_Price'], 2); ?></td>
                            <td class="text-end">
                                <a href="javascript:void(0);" class="action-link view-eye me-2 fs-5" onclick='openProductModal(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="bi bi-eye"></i></a>
                                <a href="edit_product.php?id=<?php echo $row['Pro_Id']; ?>" class="action-link edit-pen me-2 fs-5"><i class="bi bi-pencil-square"></i></a>
                                <a href="javascript:void(0);" class="action-link delete-trash fs-5" onclick="confirmDelete(<?php echo $row['Pro_Id']; ?>)"><i class="bi bi-trash3"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-orange text-white">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Product Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="main-img-box mb-3 border rounded d-flex align-items-center justify-content-center" style="height: 420px; background: #fff; overflow: hidden;">
                                <img id="main_view_img" src="" class="img-fluid" style="max-height: 100%; object-fit: contain;" onerror="this.src='../assets/no-image.png'">
                            </div>
                            <div class="row g-2" id="thumbnail_container"></div>
                        </div>
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary px-3 py-2" id="view_brand">BRAND</span>
                                <span class="text-muted small fw-bold">SKU: #<span id="view_pro_id"></span></span>
                            </div>
                            <h2 class="fw-bold mb-1" id="view_pro_title">Product Name</h2>
                            <p id="view_category_name" class="text-secondary small text-uppercase mb-2"></p>
                            <div class="mb-4">
                                <h3 class="text-danger fw-bold mb-0">RM <span id="view_price">0.00</span></h3>
                                <div class="mt-1">
                                    <span class="text-muted small">Availability Stock: </span>
                                    <span id="view_stock_status" class="fw-bold text-dark fs-5"> <span id="dynamic_stock_qty">--</span></span>
                                </div>
                            </div>
                            <div class="p-3 rounded mb-4" style="background-color: #f9f9f9; border-left: 4px solid #212529;">
                                <h6 class="fw-bold small text-muted text-uppercase mb-1">Description</h6>
                                <p id="view_desc" class="mb-0 text-dark small" style="line-height: 1.6;"></p>
                            </div>
                            <div class="mb-4">
                                <h6 class="fw-bold small text-muted text-uppercase mb-2">Color Variation</h6>
                                <div id="view_color_options" class="d-flex flex-wrap gap-2"></div>
                            </div>
                            <div class="mb-4">
                                <h6 class="fw-bold small text-muted text-uppercase mb-2">Select Size (UK)</h6>
                                <div id="view_size_options" class="d-flex flex-wrap gap-2"></div>
                            </div>
                            <div class="row g-0 border rounded text-center bg-white mt-auto">
                                <div class="col-6 border-end p-2"><div class="small text-muted italic">Gender</div><div class="fw-bold" id="view_gender">-</div></div>
                                <div class="col-6 p-2"><div class="small text-muted italic">Age Group</div><div class="fw-bold" id="view_age">-</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const minRange = document.getElementById('minRange');
        const maxRange = document.getElementById('maxRange');
        const minDisp = document.getElementById('minDisp');
        const maxDisp = document.getElementById('maxDisp');
        const track = document.getElementById('sliderTrack');

        function updateSlider() {
            let minVal = parseInt(minRange.value);
            let maxVal = parseInt(maxRange.value);
            let rangeMin = parseInt(minRange.min);
            let rangeMax = parseInt(minRange.max);
            if (minVal > maxVal) { [minVal, maxVal] = [maxVal, minVal]; }
            minDisp.innerText = minVal;
            maxDisp.innerText = maxVal;
            const totalRange = rangeMax - rangeMin;
            const minPercent = ((minVal - rangeMin) / totalRange) * 100;
            const maxPercent = ((maxVal - rangeMin) / totalRange) * 100;
            track.style.left = minPercent + "%";
            track.style.width = (maxPercent - minPercent) + "%";
        }

        minRange.addEventListener('input', updateSlider);
        maxRange.addEventListener('input', updateSlider);
        updateSlider();
    });

    let currentVariantMap = {};
    let currentColorGalleries = {};
    let selectedModalColor = "";
    let selectedModalSize = "";

    function openProductModal(product) {
        currentVariantMap = product.variantMap || {};
        currentColorGalleries = product.colorGalleries || {};
        selectedModalColor = "";
        selectedModalSize = "";

        document.getElementById('view_pro_id').innerText = product.Pro_Id;
        document.getElementById('view_pro_title').innerText = product.Pro_Name;
        document.getElementById('view_brand').innerText = product.Brand_Name;
        document.getElementById('view_category_name').innerText = product.Cat_Name;
        document.getElementById('view_price').innerText = parseFloat(product.Pro_Price).toFixed(2);
        document.getElementById('view_desc').innerText = product.Pro_Description || 'No description.';
        document.getElementById('view_gender').innerText = product.Pro_Gender || '-';
        document.getElementById('view_age').innerText = product.Pro_Age_Group || '-';

        window.renderSizes = function(colorName) {
            const sBox = document.getElementById('view_size_options');
            sBox.innerHTML = '';
            const sizes = product.Pro_Size ? product.Pro_Size.split(/[,\/]/) : [];
            const colorStock = currentVariantMap[colorName] || {};

            sizes.forEach(s => {
                let sizeName = s.trim();
                if(!sizeName) return;
                let qty = colorStock[sizeName] || 0;
                let div = document.createElement('div');
                div.className = 'selectable-box';
                div.innerText = sizeName;

                if (qty <= 0) { 
                    div.classList.add('disabled'); 
                } else {
                    div.onclick = function() {
                        sBox.querySelectorAll('.selectable-box').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        selectedModalSize = sizeName;
                        updateStockDisplay();
                    };
                }
                sBox.appendChild(div);
            });
        };

        const cBox = document.getElementById('view_color_options');
        cBox.innerHTML = '';
        const colors = product.Pro_Colour ? product.Pro_Colour.split(/[,\/]/) : ['Default'];
        colors.forEach((c, idx) => {
            let colorName = c.trim();
            if(!colorName) return;
            let btn = document.createElement('div');
            btn.className = 'selectable-box';
            btn.innerText = colorName;
            btn.onclick = function() {
                cBox.querySelectorAll('.selectable-box').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedModalColor = colorName;
                selectedModalSize = ""; 
                renderSizes(colorName);
                updateStockDisplay();
                renderModalGallery(colorName);
            };
            cBox.appendChild(btn);
            if (idx === 0) btn.click();
        });

        new bootstrap.Modal(document.getElementById('viewProductModal')).show();
    }

    function renderModalGallery(colorName) {
        const mainImg = document.getElementById('main_view_img');
        const thumbContainer = document.getElementById('thumbnail_container');
        thumbContainer.innerHTML = '';
        const images = currentColorGalleries[colorName] || [];
        images.forEach((imgSrc, idx) => {
            let col = document.createElement('div'); col.className = 'col-3';
            let img = document.createElement('img'); img.className = 'img-fluid rounded border cursor-pointer';
            img.style.height = '80px'; img.style.width = '100%'; img.style.objectFit = 'cover';
            img.src = imgSrc;
            if (idx === 0) mainImg.src = imgSrc;
            img.onclick = function() { if (!this.src.includes('no-image.png')) mainImg.src = this.src; };
            col.appendChild(img); thumbContainer.appendChild(col);
        });
    }

    function updateStockDisplay() {
        const stockQtySpan = document.getElementById('dynamic_stock_qty');
        const stockStatusWrapper = document.getElementById('view_stock_status');
        if (!selectedModalColor || !selectedModalSize) {
            stockQtySpan.innerText = "-- (Select Size)";
            stockStatusWrapper.className = "fw-bold text-muted fs-6";
            return;
        }
        const colorStock = currentVariantMap[selectedModalColor] || {};
        const qty = colorStock[selectedModalSize] || 0;
        stockQtySpan.innerText = qty;
        stockStatusWrapper.className = qty > 0 ? "fw-bold text-success fs-5" : "fw-bold text-danger fs-5";
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?', text: "This product will be deleted forever!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#FF6B00', cancelButtonColor: '#d33', confirmButtonText: 'Yes, delete it!'
        }).then((result) => { if (result.isConfirmed) window.location.href = 'admin_manage_products.php?delete=' + id; });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const clearSearch = document.getElementById('clearSearch');
        const filterForm = document.getElementById('filterForm');
        function toggleClearIcon() { clearSearch.style.display = searchInput.value.length > 0 ? 'block' : 'none'; }
        toggleClearIcon();
        searchInput.addEventListener('input', toggleClearIcon);
        clearSearch.addEventListener('click', function() {
            searchInput.value = ''; toggleClearIcon(); filterForm.submit();
        });
    });

    <?php if($swalCode) echo $swalCode; ?>
    </script>
</body>
</html>