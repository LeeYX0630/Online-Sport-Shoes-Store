<?php
// admin_manage_products.php
session_start();
require_once '../includes/db_connection.php';

// 1. 安全检查[cite: 2]
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$admin_brand_id = $_SESSION['admin_brand'] ?? 0;
$username = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png'; // 提取自 source 1[cite: 1]

$swalCode = ""; 

// 2. 删除逻辑
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $checkSql = ($admin_role == 1) ? "" : " AND Brand_Id = '$admin_brand_id'";
    $delSql = "DELETE FROM product WHERE Pro_Id = '$id' $checkSql";
    if ($conn->query($delSql) === TRUE) {
        $swalCode = "Swal.fire({ title: 'Deleted!', text: 'Product removed successfully.', icon: 'success', confirmButtonColor: '#FF8C00' }).then(() => { window.location.href = 'admin_manage_products.php'; });";
    }
}

// 3. 获取筛选参数
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['cat'] ?? '';
$brand_filter = $_GET['brand'] ?? '';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 1000;

// 4. 构建 SQL 查询
$queryCondition = ($admin_role == 1) ? "1=1" : "p.Brand_Id = '$admin_brand_id'";
if (!empty($search)) $queryCondition .= " AND (p.Pro_Name LIKE '%$search%' OR p.Pro_Id LIKE '%$search%')";
if (!empty($cat_filter)) $queryCondition .= " AND p.Cat_Id = '$cat_filter'";
if (!empty($brand_filter)) $queryCondition .= " AND p.Brand_Id = '$brand_filter'";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* 统一全局 CSS 变量 (同步自 source 1 & 2)[cite: 1, 2] */
        :root { 
            --orange-primary: #FF8C00; 
            --sidebar-width: 260px; 
        }
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', 'Inter', sans-serif; 
            margin: 0;
        }
        .wrapper { display: flex; }

        /* 统一内容区域布局[cite: 1] */
        .main-content { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
        }

        /* 统一 Header 样式[cite: 1] */
        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }

        /* 统一头像边框[cite: 1] */
        .admin-profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

        /* 统一卡片样式[cite: 1] */
        .filter-card, .table-container { 
            background: white; 
            border-radius: 15px; 
            padding: 24px; 
            border: none; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            margin-bottom: 25px;
        }

        /* 筛选器行布局 */
        .filter-row { display: flex; align-items: center; gap: 15px; flex-wrap: nowrap; }
        .search-wrapper { flex: 1.2; position: relative; display: flex; align-items: center; }
        .search-wrapper i.bi-search { position: absolute; left: 15px; color: #94a3b8; }
        .search-wrapper input { width: 100%; padding: 10px 35px 10px 40px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; }
        
        .select-custom { flex: 0.7; display: flex; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 10px; }
        .select-custom select { border: none; background: transparent; padding: 10px 5px; width: 100%; font-size: 14px; outline: none; }

        /* 价格滑块样式 */
        .price-range-wrapper { flex: 1; padding: 0 10px; }
        .price-label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .price-label span { color: var(--orange-primary); }
        .range-slider { position: relative; height: 5px; background: #e2e8f0; border-radius: 5px; margin-top: 15px; }
        .slider-track { position: absolute; height: 100%; background: var(--orange-primary); border-radius: 5px; z-index: 1; }
        .range-slider input { position: absolute; width: 100%; height: 5px; top: 0; background: none; pointer-events: none; -webkit-appearance: none; appearance: none; z-index: 2; margin: 0; }
        input[type="range"]::-webkit-slider-thumb { height: 16px; width: 16px; border-radius: 50%; background: #fff; border: 2px solid var(--orange-primary); pointer-events: auto; -webkit-appearance: none; cursor: pointer; }

        /* 按钮样式 */
        .btn-orange { 
            background-color: var(--orange-primary); color: white; border: none; 
            padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; 
            display: inline-flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-orange:hover { background-color: #e67e00; color: white; }

        /* 表格样式 */
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; }
        .brand-badge { background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .action-link { transition: 0.2s; padding: 5px; border-radius: 8px; font-size: 1.1rem; }
        .view-eye { color: #6366f1; }
        .edit-pen { color: var(--orange-primary); }
        .delete-trash { color: #ef4444; }

        /* 模态框样式 */
        .bg-orange { background-color: var(--orange-primary) !important; }
        .selectable-box { border: 1px solid #dee2e6; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; background-color: white; transition: 0.2s; }
        .selectable-box.active { border: 2px solid var(--orange-primary); background-color: #FFF8F3; color: var(--orange-primary); font-weight: bold; }
        
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <!-- 同步自 source 1 的 Header 布局[cite: 1] -->
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                        <li class="breadcrumb-item active">Products</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Manage Products</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                    <small class="text-muted"><?php echo ($admin_role == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="container-fluid p-0">
            <!-- 标题栏 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark mb-0">Product Inventory</h5>
                <a href="add_product.php" class="btn-orange shadow-sm"><i class="bi bi-plus-lg"></i> Add Product</a>
            </div>

            <!-- 筛选区域 -->
            <div class="filter-card">
                <form method="GET" id="filterForm" class="filter-row">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" id="searchInput" placeholder="Search product name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="select-custom">
                        <i class="bi bi-tag me-2"></i>
                        <select name="cat" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php while($c = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $c['Cat_Id']; ?>" <?php if($cat_filter == $c['Cat_Id']) echo 'selected'; ?>><?php echo $c['Cat_Name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if($admin_role == 1): ?>
                    <div class="select-custom">
                        <i class="bi bi-award me-2"></i>
                        <select name="brand" onchange="this.form.submit()">
                            <option value="">All Brands</option>
                            <?php while($b = $brands->fetch_assoc()): ?>
                                <option value="<?php echo $b['Brand_Id']; ?>" <?php if($brand_filter == $b['Brand_Id']) echo 'selected'; ?>><?php echo $b['Brand_Name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="price-range-wrapper">
                        <div class="price-label">Price: <span>RM <span id="minDisp"><?php echo $min_price; ?></span> - RM <span id="maxDisp"><?php echo $max_price; ?></span></span></div>
                        <div class="range-slider">
                            <div class="slider-track" id="sliderTrack"></div>
                            <input type="range" name="min_price" id="minRange" min="0" max="1000" value="<?php echo $min_price; ?>" step="10">
                            <input type="range" name="max_price" id="maxRange" min="0" max="1000" value="<?php echo $max_price; ?>" step="10">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-light border fw-bold px-3 py-2 rounded-3">Apply</button>
                </form>
            </div>

            <!-- 数据表格 -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>IMAGE</th>
                                <th>PRODUCT INFO</th>
                                <?php if($admin_role == 1): ?> <th>BRAND</th> <?php endif; ?>
                                <th>CATEGORY</th>
                                <th>PRICE</th>
                                <th class="text-end">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): 
                                // 处理变体与图片画廊逻辑 (保留您原本的功能)
                                $pro_id = $row['Pro_Id'];
                                $variant_map = [];
                                $stock_res = $conn->query("SELECT Pro_Size, Pro_Colour, Quantity FROM PRODUCT_STOCK WHERE Pro_Id = '$pro_id'");
                                while($s_row = $stock_res->fetch_assoc()) {
                                    $variant_map[trim($s_row['Pro_Colour'])][trim($s_row['Pro_Size'])] = intval($s_row['Quantity']);
                                }
                                $row['variantMap'] = $variant_map;

                                $colors = preg_split('/[,\/]/', $row['Pro_Colour'] ?? '');
                                $colors = array_filter(array_unique(array_map('trim', $colors)));
                                if (empty($colors)) $colors[] = "Default";

                                $base_img = $row['Pro_Image'];
                                $base_name = preg_replace('/_\d+$/', '', pathinfo($base_img, PATHINFO_FILENAME));
                                $color_galleries = [];
                                foreach ($colors as $c) {
                                    $slug = strtolower(str_replace(' ', '_', $c));
                                    $files = glob("../uploads/{$base_name}*{$slug}*.*") ?: glob("../uploads/{$base_name}*.*");
                                    $color_galleries[$c] = !empty($files) ? array_map(fn($f) => "../uploads/".basename($f), $files) : ["../uploads/".$base_img];
                                    while (count($color_galleries[$c]) < 4) $color_galleries[$c][] = '../assets/no-image.png';
                                }
                                $row['colorGalleries'] = $color_galleries;
                            ?>
                            <tr>
                                <td><img src="<?php echo $color_galleries[$colors[0]][0]; ?>" class="product-img" onerror="this.src='../assets/no-image.png'"></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo $row['Pro_Name']; ?></div>
                                    <small class="text-muted">ID: #<?php echo $row['Pro_Id']; ?> | <?php echo implode(', ', $colors); ?></small>
                                </td>
                                <?php if($admin_role == 1): ?><td><span class="brand-badge"><?php echo $row['Brand_Name']; ?></span></td><?php endif; ?>
                                <td><?php echo $row['Cat_Name']; ?></td>
                                <td class="fw-bold text-dark">RM <?php echo number_format($row['Pro_Price'], 2); ?></td>
                                <td class="text-end">
                                    <a href="javascript:void(0);" class="action-link view-eye me-2" onclick='viewProduct(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="bi bi-eye"></i></a>
                                    <a href="edit_product.php?id=<?php echo $row['Pro_Id']; ?>" class="action-link edit-pen me-2"><i class="bi bi-pencil-square"></i></a>
                                    <a href="javascript:void(0);" class="action-link delete-trash" onclick="confirmDelete(<?php echo $row['Pro_Id']; ?>)"><i class="bi bi-trash3"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 详情预览 Modal (同步橙色主题) -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header bg-orange text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye-fill me-2"></i>Product Quick View</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="border rounded-4 d-flex align-items-center justify-content-center bg-white mb-3" style="height: 400px;">
                            <img id="main_view_img" src="" class="img-fluid" style="max-height: 90%; object-fit: contain;">
                        </div>
                        <div class="row g-2" id="thumbnail_container"></div>
                    </div>
                    <div class="col-lg-6">
                        <span class="badge bg-primary mb-2 px-3 py-2" id="view_brand">BRAND</span>
                        <h2 class="fw-bold text-dark mb-1" id="view_pro_title">Product Name</h2>
                        <p id="view_category_name" class="text-muted small text-uppercase mb-3"></p>
                        
                        <div class="mb-4">
                            <h3 class="text-danger fw-bold mb-0">RM <span id="view_price">0.00</span></h3>
                            <div class="text-muted small mt-1">Stock: <span id="view_stock_status" class="fw-bold"><span id="dynamic_stock_qty">--</span></span></div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4">
                            <h6 class="fw-bold small text-muted text-uppercase mb-2">Description</h6>
                            <p id="view_desc" class="small mb-0" style="line-height: 1.6;"></p>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-bold small mb-2">COLOR VARIATION</h6>
                            <div id="view_color_options" class="d-flex flex-wrap gap-2"></div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold small mb-2">SELECT SIZE</h6>
                            <div id="view_size_options" class="d-flex flex-wrap gap-2"></div>
                        </div>

                        <div class="row g-2 text-center">
                            <div class="col-6"><div class="border rounded p-2 small">Gender: <b id="view_gender"></b></div></div>
                            <div class="col-6"><div class="border rounded p-2 small">Age: <b id="view_age"></b></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 价格滑块逻辑
const minRange = document.getElementById('minRange'), maxRange = document.getElementById('maxRange');
const minDisp = document.getElementById('minDisp'), maxDisp = document.getElementById('maxDisp');
const track = document.getElementById('sliderTrack');

function updateSlider() {
    let minVal = parseInt(minRange.value), maxVal = parseInt(maxRange.value);
    if (minVal > maxVal) [minVal, maxVal] = [maxVal, minVal];
    minDisp.innerText = minVal; maxDisp.innerText = maxVal;
    track.style.left = (minVal / 1000 * 100) + "%";
    track.style.width = ((maxVal - minVal) / 1000 * 100) + "%";
}
minRange.oninput = updateSlider; maxRange.oninput = updateSlider;
updateSlider();

// 预览逻辑 (保留原本功能)
let currentVariantMap = {}, currentColorGalleries = {}, selectedColor = "", selectedSize = "";

function viewProduct(product) {
    currentVariantMap = product.variantMap;
    currentColorGalleries = product.colorGalleries;
    selectedColor = ""; selectedSize = "";

    document.getElementById('view_pro_title').innerText = product.Pro_Name;
    document.getElementById('view_brand').innerText = product.Brand_Name;
    document.getElementById('view_category_name').innerText = product.Cat_Name;
    document.getElementById('view_price').innerText = parseFloat(product.Pro_Price).toFixed(2);
    document.getElementById('view_desc').innerText = product.Pro_Description || 'No description.';
    document.getElementById('view_gender').innerText = product.Pro_Gender || '-';
    document.getElementById('view_age').innerText = product.Pro_Age_Group || '-';

    // 颜色渲染
    const cBox = document.getElementById('view_color_options');
    cBox.innerHTML = '';
    Object.keys(currentColorGalleries).forEach((c, idx) => {
        let div = document.createElement('div');
        div.className = 'selectable-box'; div.innerText = c;
        div.onclick = function() {
            cBox.querySelectorAll('.selectable-box').forEach(b => b.classList.remove('active'));
            this.classList.add('active'); selectedColor = c;
            renderGallery(c); updateStock();
        };
        cBox.appendChild(div);
        if(idx === 0) div.click();
    });

    // 尺寸渲染
    const sBox = document.getElementById('view_size_options');
    sBox.innerHTML = '';
    (product.Pro_Size || "").split(/[,\/]/).forEach(s => {
        if(!s.trim()) return;
        let div = document.createElement('div');
        div.className = 'selectable-box'; div.innerText = s.trim();
        div.onclick = function() {
            sBox.querySelectorAll('.selectable-box').forEach(b => b.classList.remove('active'));
            this.classList.add('active'); selectedSize = s.trim(); updateStock();
        };
        sBox.appendChild(div);
    });

    new bootstrap.Modal(document.getElementById('viewProductModal')).show();
}

function renderGallery(color) {
    const main = document.getElementById('main_view_img'), thumb = document.getElementById('thumbnail_container');
    thumb.innerHTML = '';
    (currentColorGalleries[color] || []).forEach((img, i) => {
        if(i === 0) main.src = img;
        let col = document.createElement('div'); col.className = 'col-3';
        col.innerHTML = `<img src="${img}" class="img-fluid rounded border p-1" style="height:70px; width:100%; object-fit:cover; cursor:pointer;" onclick="document.getElementById('main_view_img').src='${img}'">`;
        thumb.appendChild(col);
    });
}

function updateStock() {
    const qty = (currentVariantMap[selectedColor] || {})[selectedSize] || 0;
    const disp = document.getElementById('dynamic_stock_qty');
    disp.innerText = (selectedColor && selectedSize) ? qty : "--";
    document.getElementById('view_stock_status').className = qty > 0 ? "text-success fw-bold" : "text-danger fw-bold";
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Delete Product?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF8C00',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => { if (result.isConfirmed) window.location.href = '?delete=' + id; });
}

<?php if($swalCode) echo $swalCode; ?>
</script>

</body>
</html>