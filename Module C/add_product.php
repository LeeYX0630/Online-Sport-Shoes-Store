<?php
session_start();
require_once '../includes/db_connection.php';

// --- 1. 后端处理逻辑 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_product'])) {
    // 获取并转义基本输入
    $pro_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $cat_id   = intval($_POST['category']);
    $brand_id = intval($_POST['brand']);
    $price    = abs(floatval($_POST['selling_price'])); 
    $desc     = mysqli_real_escape_string($conn, $_POST['description']);
    $gender   = $_POST['gender'];
    $status   = ($_POST['status'] == 'Active') ? 'Available' : 'Unavailable';

    // 生成存入数据库的主图片名：移除空格、转小写 (例如 "Air Jordan" -> "airjordan.jpg")
    $pure_name = strtolower(str_replace(' ', '', $_POST['product_name']));
    $db_main_image = $pure_name . ".jpg"; 

    // 处理 Size 和 Colour 数组为逗号分隔的字符串，以便存入 product 表
    $size_string = isset($_POST['variant_sizes']) ? implode(',', $_POST['variant_sizes']) : '';
    $color_string = isset($_POST['selected_colors']) ? implode(',', $_POST['selected_colors']) : '';

    // 插入 product 主表
    $sql_product = "INSERT INTO product (Pro_Name, Cat_Id, Brand_Id, Pro_Price, Pro_Description, Pro_Image, Pro_Size, Pro_Colour, Pro_Gender, Pro_Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql_product);
    $stmt->bind_param("siidssssss", $pro_name, $cat_id, $brand_id, $price, $desc, $db_main_image, $size_string, $color_string, $gender, $status);
    
    if ($stmt->execute()) {
        $new_pro_id = $conn->insert_id;

        // 新的颜色-尺寸-库存 与 颜色图片处理（接收格式：
        // $_POST['selected_colors'] = ['White','Black',...]
        // $_POST['stock'][Color][Size] = qty
        // $_FILES['color_photos'][Color][] = files
        if (!empty($_POST['selected_colors']) && is_array($_POST['selected_colors'])) {
            // 保存库存
            if (!empty($_POST['stock']) && is_array($_POST['stock'])) {
                $stmt_stock = $conn->prepare("INSERT INTO product_stock (Pro_Id, Pro_Size, Pro_Colour, Quantity) VALUES (?, ?, ?, ?)");
                foreach ($_POST['stock'] as $color => $sizesArr) {
                    foreach ($sizesArr as $size => $qty) {
                        $qty_i = intval($qty);
                        $size_s = $conn->real_escape_string($size);
                        $color_s = $conn->real_escape_string($color);
                        if ($stmt_stock) {
                            $stmt_stock->bind_param("issi", $new_pro_id, $size_s, $color_s, $qty_i);
                            $stmt_stock->execute();
                        } else {
                            $conn->query("INSERT INTO product_stock (Pro_Id, Pro_Size, Pro_Colour, Quantity) VALUES ('$new_pro_id', '$size_s', '$color_s', '$qty_i')");
                        }
                    }
                }
                if ($stmt_stock) $stmt_stock->close();
            }

            // 保存按颜色上传的图片（$_FILES 以颜色键组织）
            if (!empty($_FILES['color_photos'])) {
                foreach ($_FILES['color_photos']['name'] as $color => $files) {
                    $safe_color_key = $color; // 原始颜色名称，用于 DB
                    for ($i = 0; $i < count($files); $i++) {
                        if ($_FILES['color_photos']['error'][$color][$i] === 0) {
                            $filename = $files[$i];
                            $ext = pathinfo($filename, PATHINFO_EXTENSION);
                            $safe_color = strtolower(preg_replace('/\s+/', '', $color));
                            $safe_name = strtolower(preg_replace('/\s+/', '', $pure_name));
                            $newfile = $safe_name . '_' . $safe_color . '_' . ($i+1) . '.' . $ext;
                            $target = "../uploads/" . $newfile;
                            if (move_uploaded_file($_FILES['color_photos']['tmp_name'][$color][$i], $target)) {
                                $stmt_img = $conn->prepare("INSERT INTO product_images (Pro_Id, Pro_Colour, Image_Path) VALUES (?, ?, ?)");
                                if ($stmt_img) {
                                    $stmt_img->bind_param('iss', $new_pro_id, $safe_color_key, $newfile);
                                    $stmt_img->execute();
                                    $stmt_img->close();
                                } else {
                                    $conn->query("INSERT INTO product_images (Pro_Id, Pro_Colour, Image_Path) VALUES ('$new_pro_id', '$safe_color_key', '$newfile')");
                                }
                            }
                        }
                    }
                }
            }
        }
        echo "<script>alert('Product Added Successfully!'); window.location.href='admin_manage_products.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}

// 获取品牌和分类用于下拉菜单
$brands = $conn->query("SELECT * FROM brand WHERE Brand_Status = 'Active'");
$categories = $conn->query("SELECT * FROM category");

// 获取当前管理员信息（优先从 DB）
$admin_id = $_SESSION['admin_id'] ?? 0;
$admin_name = $_SESSION['username'] ?? 'Admin';
$admin = null;
if ($admin_id && isset($conn)) {
    $resAdmin = $conn->query("SELECT * FROM admin WHERE Admin_Id = " . (int)$admin_id);
    if ($resAdmin) {
        $admin = $resAdmin->fetch_assoc();
    }
}
$admin_role = $admin['Admin_Level'] ?? $_SESSION['role'] ?? 2;
$admin_name = $admin['Admin_Name'] ?? $admin_name;
$admin_image = !empty($admin['Admin_Image']) ? $admin['Admin_Image'] : ($_SESSION['admin_image'] ?? 'default_admin.png');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - Enhanced UI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --main-orange: #FF8C00; --soft-orange: rgba(255, 140, 0, 0.1); --border-light: #e0e0e0; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar-wrapper { width: 250px; background: white; border-right: 1px solid #eee; }
        .main-content { flex: 1; padding: 35px; }
        
        .top-header { background: white; padding: 25px 35px; border-radius: 16px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--main-orange); object-fit: cover; }
        .back-button-container { display: flex; justify-content: flex-end; margin-bottom: 15px; padding: 0 10px; }
        .btn-back-header { text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; transition: all 0.2s; border: 1px solid #e2e8f0; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .btn-back-header:hover { background-color: #fff; color: var(--main-orange); border-color: var(--main-orange); transform: translateX(-3px); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; transition: 0.3s; }
        .text-orange { color: var(--main-orange); font-weight: 700; }

        .form-control, .form-select {
            border: 1px solid var(--border-light);
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 14px;
            color: #444;
            transition: all 0.3s;
            background-color: #fafafa;
        }
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            border-color: var(--main-orange);
            box-shadow: 0 0 0 4px var(--soft-orange);
            outline: none;
        }

        .btn-outline-orange { 
            border: 1px solid var(--main-orange); 
            color: var(--main-orange); 
            padding: 10px 22px; 
            border-radius: 10px; 
            font-weight: 500;
            margin: 5px;
            transition: all 0.2s;
        }
        .btn-check:checked + .btn-outline-orange { 
            background-color: var(--main-orange); 
            color: white; 
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.3);
            transform: scale(1.05);
        }

        /* Step 3 样式优化 */
        .variant-box { 
            background: #fff; 
            border-left: 6px solid var(--main-orange); 
            border-radius: 12px; 
            padding: 25px; 
            margin-bottom: 20px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.04); 
        }

        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .preview-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .remove-img {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .upload-btn-custom {
            border: 2px dashed var(--main-orange);
            color: var(--main-orange);
            background-color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        /* ----- Lightbox (灯箱) 样式 ----- */
        .lightbox-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(5px);
        }

        .lightbox-content {
            position: relative;
            margin: auto;
            display: block;
            width: auto;
            max-width: 90%;
            max-height: 85vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: zoomIn 0.3s ease;
        }
        @keyframes zoomIn { from {transform:scale(0.8); opacity:0;} to {transform:scale(1); opacity:1;} }

        .lightbox-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 2010;
        }
        .lightbox-close:hover { color: var(--main-orange); transform: rotate(90deg); }

        .lightbox-nav {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            padding: 12px 18px; 
            margin-top: -50px;
            color: rgba(255, 255, 255, 0.8); 
            font-weight: normal; 
            font-size: 24px; 
            -webkit-text-stroke: 0.5px rgba(255, 255, 255, 0.8); 
            transition: 0.3s ease;
            user-select: none;
            background-color: rgba(0, 0, 0, 0.2); 
            border: none;
            z-index: 2010;
            outline: none;
        }
        
        .lightbox-prev { 
            left: 20px; 
            border-radius: 8px; 
        }
        .lightbox-next { 
            right: 20px; 
            border-radius: 8px; 
        }

        .lightbox-nav:hover { 
            background-color: rgba(255, 140, 0, 0.3); 
            color: var(--main-orange); 
            -webkit-text-stroke: 0.5px var(--main-orange);
            transform: scale(1.1); 
        }

        .lightbox-caption-area {
            text-align: center;
            color: #ccc;
            padding: 20px 0;
            width: 90%;
            margin: auto;
        }
        .lightbox-dots-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }
        .lightbox-dot {
            height: 10px;
            width: 10px;
            background-color: #bbb;
            border-radius: 50%;
            display: inline-block;
            transition: background-color 0.6s ease;
            cursor: pointer;
        }
        .lightbox-dot.active { background-color: var(--main-orange); transform: scale(1.2); }

        /* Stock Input Step 4 样式 */
        .stock-item {
            transition: all 0.3s ease;
        }
        .stock-item .p-2 {
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.05) 0%, rgba(255, 140, 0, 0.02) 100%);
            border: 2px solid #FFE4CC;
            transition: all 0.3s ease;
        }
        .stock-item:hover .p-2 {
            border-color: var(--main-orange);
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1) 0%, rgba(255, 140, 0, 0.05) 100%);
            box-shadow: 0 4px 12px rgba(255, 140, 0, 0.15);
        }
        .stock-item label {
            color: #FF8C00;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stock-item input[type="number"] {
            border: 1px solid #FFD699;
            background: white;
            color: var(--main-orange);
            font-weight: 600;
            text-align: center;
        }
        .stock-item input[type="number"]:focus {
            border-color: var(--main-orange);
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.2);
            background: white;
        }
        .stock-item input[type="number"]::placeholder {
            color: #FFAB5A;
        }

        .btn-save { background: var(--main-orange); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 600; font-size: 16px; transition: 0.3s; box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3); }
        .btn-save:hover { background: #e67e00; transform: translateY(-2px); color: white; }
        .back-btn { color: #6c757d; font-weight: 600; text-decoration: none; padding: 10px 20px; border-radius: 12px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s; }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar-wrapper"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-content">
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--main-orange);">Home</a></li>
                        <li class="breadcrumb-item"><a href="admin_manage_products.php" class="text-decoration-none text-muted">Products</a></li>
                        <li class="breadcrumb-item active">Add Products</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Create Product</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3">
                    <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
                    <small class="text-muted"><?php echo ($admin_role == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="back-button-container">
            <a href="admin_manage_products.php" class="btn-back-header"><i class="bi bi-arrow-left"></i> Back to Products</a>
        </div>

            <form action="" method="POST" enctype="multipart/form-data" id="productForm">
                        <!-- Step 1: 保持不变 -->
                        <div class="card p-4">
                            <h5 class="text-orange mb-4"><i class="bi bi-info-circle me-2"></i> Step 1: Basic Information</h5>
                            <div class="row g-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Product Name *</label>
                                    <input type="text" name="product_name" class="form-control" placeholder="Enter shoe name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Brand</label>
                                    <select name="brand" class="form-select"><?php while($b = $brands->fetch_assoc()) echo "<option value='{$b['Brand_Id']}'>{$b['Brand_Name']}</option>"; ?></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Category</label>
                                    <select name="category" class="form-select"><?php while($c = $categories->fetch_assoc()) echo "<option value='{$c['Cat_Id']}'>{$c['Cat_Name']}</option>"; ?></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Gender Group</label>
                                    <select name="gender" class="form-select">
                                        <option value="Unisex">Unisex</option><option value="Men">Men</option><option value="Women">Women</option><option value="Kids">Kids</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="4" placeholder="Describe features..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: 改成填写 Price 和 颜色 -->
                        <div class="card p-4">
                            <h5 class="text-orange mb-4"><i class="bi bi-tag me-2"></i> Step 2: Price & Colors</h5>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Base Price (RM) *</label>
                                    <input type="number" step="0.01" min="0" name="selling_price" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold mb-3">Colors <span class="text-muted fw-normal small">(Select to manage photos and sizes)</span></label>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach(['Black','White','Red','Navy','Grey'] as $col): ?>
                                            <input type="checkbox" name="selected_colors[]" class="btn-check color-selector" id="c_<?= $col ?>" value="<?= $col ?>">
                                            <label class="btn btn-outline-orange" for="c_<?= $col ?>"><?= $col ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3 input-group" style="width: 250px;">
                                        <input type="text" id="custom-color-input" class="form-control" placeholder="Add custom color">
                                        <button type="button" class="btn px-3" style="background:var(--main-orange); color:white;" onclick="addManualColor()"><i class="bi bi-plus-lg"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 & 4 动态容器 -->
                        <div id="variants-master-section" style="display: none;">
                            <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-layers me-2 text-orange"></i> Step 3 & 4: Media, Sizes & Stock</h5>
                            <div id="variant-upload-container">
                                <!-- 动态生成内容 -->
                            </div>
                        </div>

                  

            <div class="mt-5 mb-5 d-flex align-items-center gap-3">
                <button type="submit" name="save_product" class="btn btn-save">Publish Product</button>
                <button type="reset" class="btn btn-light px-4 py-3 border rounded-3 fw-semibold" onclick="location.reload()">Discard Changes</button>
                <div class="ms-auto">
                    <select name="status" class="form-select border-0 shadow-sm" style="width: 150px;">
                        <option value="Active">Live</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="lightboxModal" class="lightbox-modal">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <div style="position:relative; display:flex; align-items:center; justify-content:center; min-height:85vh;">
        <button type="button" class="lightbox-nav lightbox-prev" onclick="changeLightboxSlide(-1)">&#10094;</button>
        <img class="lightbox-content" id="lightboxImage">
        <button type="button" class="lightbox-nav lightbox-next" onclick="changeLightboxSlide(1)">&#10095;</button>
    </div>
    <div class="lightbox-caption-area">
        <div class="fw-bold fs-5 text-white" id="lightboxCaption">Color Name</div>
        <div id="lightboxDots" class="lightbox-dots-container"></div>
    </div>
</div>

<script>
const colorFilesManager = {};

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('color-selector')) {
        const color = e.target.value;
        const section = document.getElementById('variants-master-section');
        if (e.target.checked) {
            section.style.display = 'block';
            addVariantBox(color);
        } else {
            const box = document.getElementById('box_' + color);
            if (box) box.remove();
            delete colorFilesManager[color];
            if (document.querySelectorAll('.color-selector:checked').length === 0) section.style.display = 'none';
        }
    }
});

function addVariantBox(color) {
    const container = document.getElementById('variant-upload-container');
    const index = document.querySelectorAll('.variant-box').length;
    colorFilesManager[color] = new DataTransfer();

    // 生成 Step 4 的库存行 (根据 size 生成)
    const sizes = [5.0, 5.5, 6.0, 6.5, 7.0, 7.5, 8.0, 8.5, 9.0, 9.5, 10.0];
    const safeColorId = color.replace(/\s+/g, '_');
    const sizeCheckboxes = sizes.map(s => {
        const sLabel = s.toFixed(1);
        return `
            <input type="checkbox" class="btn-check size-checkbox" name="variant_sizes[]" id="s_${safeColorId}_${sLabel}" data-color="${color}" value="${sLabel}">
            <label class="btn btn-sm btn-outline-orange" for="s_${safeColorId}_${sLabel}">${sLabel}</label>
        `;
    }).join('');

    const html = `
        <div class="variant-box mb-5" id="box_${color}">
            <input type="hidden" name="selected_colors[${index}]" value="${color}">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-orange">${color} Configuration</h5>
                </div>
                <div class="card-body">
                    <!-- Step 3 上部分：放照片 -->
                    <div class="mb-4">
                        <label class="fw-bold mb-2"><i class="bi bi-camera me-1"></i> Step 3 (Top): Photos for ${color}</label>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="upload-btn-wrapper">
                                <button type="button" class="upload-btn-custom"><i class="bi bi-plus-circle"></i> Upload</button>
                                <input type="file" id="input_${color}" class="real-file-input" style="position:absolute; font-size:100px; opacity:0; right:0; top:0; cursor:pointer;" multiple onchange="handleFileSelect(this, '${color}', ${index})">
                            </div>
                            <input type="file" name="color_photos[${index}][]" id="final_input_${color}" multiple style="display:none">
                            <div id="preview_${color}" class="preview-container mt-0">
                                <span class="text-muted small">No photos.</span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Step 3 下部分：选择 Size (保持不变的展示方式) -->
                    <div class="mb-4">
                        <label class="fw-bold mb-2"><i class="bi bi-rulers me-1"></i> Step 3 (Bottom): Available Sizes for ${color}</label>
                        <div class="d-flex flex-wrap gap-2">
                            ${sizeCheckboxes}
                        </div>
                    </div>

                    <hr>

                    <!-- Step 4: 根据不同的 size 填写不同的 stock (仅在对应尺寸被选中时生成) -->
                    <div>
                        <label class="fw-bold mb-3"><i class="bi bi-box-seize me-1"></i> Step 4: Stock for each Size</label>
                        <div class="row stock-container" id="stock_${safeColorId}">
                            <!-- stock inputs will be inserted here when a size is checked -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

// ... [handleFileSelect, removeFile, lightbox 等 JS 函数保持不变] ...
function handleFileSelect(input, color, index) {
    const files = input.files;
    const previewContainer = document.getElementById(`preview_${color}`);
    const finalInput = document.getElementById(`final_input_${color}`);
    if (colorFilesManager[color].items.length === 0) previewContainer.innerHTML = '';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        colorFilesManager[color].items.add(file);
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.setAttribute('onclick', `openLightbox('${color}', this)`);
            div.innerHTML = `
                <img src="${e.target.result}">
                <button type="button" class="remove-img" onclick="event.stopPropagation(); removeFile('${color}', this, ${index})">×</button>
            `;
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    }
    finalInput.files = colorFilesManager[color].files;
    input.value = '';
}

function removeFile(color, btn, groupIndex) {
    const previewContainer = document.getElementById(`preview_${color}`);
    const finalInput = document.getElementById(`final_input_${color}`);
    const items = Array.from(previewContainer.querySelectorAll('.preview-item'));
    const currentIndex = items.indexOf(btn.parentNode);
    const dt = new DataTransfer();
    const { files } = colorFilesManager[color];
    for (let i = 0; i < files.length; i++) {
        if (i !== currentIndex) dt.items.add(files[i]);
    }
    colorFilesManager[color] = dt;
    finalInput.files = dt.files;
    btn.parentNode.remove();
    if (colorFilesManager[color].items.length === 0) {
        previewContainer.innerHTML = '<div class="text-muted small mt-2">No photos uploaded yet.</div>';
    }
}

function openLightbox(color, clickedDomElement) {
    const previewContainer = document.getElementById(`preview_${color}`);
    const allPreviewItems = Array.from(previewContainer.querySelectorAll('.preview-item'));
    currentLightboxIndex = allPreviewItems.indexOf(clickedDomElement);
    currentLightboxColor = color;
    currentLightboxImages = allPreviewItems.map(item => item.querySelector('img').src);
    if (currentLightboxImages.length === 0) return;
    document.getElementById('lightboxModal').style.display = "block";
    document.body.style.overflow = 'hidden'; 
    updateLightboxDOM();
}

function closeLightbox() {
    document.getElementById('lightboxModal').style.display = "none";
    document.body.style.overflow = 'auto'; 
}

function updateLightboxDOM() {
    const imgElement = document.getElementById('lightboxImage');
    const captionElement = document.getElementById('lightboxCaption');
    const dotsContainer = document.getElementById('lightboxDots');
    imgElement.src = currentLightboxImages[currentLightboxIndex];
    captionElement.innerText = `${currentLightboxColor} (Photo ${currentLightboxIndex + 1} / ${currentLightboxImages.length})`;
    const prevBtn = document.querySelector('.lightbox-prev');
    const nextBtn = document.querySelector('.lightbox-next');
    prevBtn.style.display = nextBtn.style.display = (currentLightboxImages.length <= 1) ? 'none' : 'block';
    dotsContainer.innerHTML = '';
    currentLightboxImages.forEach((_, i) => {
        const dot = document.createElement('span');
        dot.className = `lightbox-dot ${i === currentLightboxIndex ? 'active' : ''}`;
        dot.setAttribute('onclick', `setLightboxSlide(${i})`);
        dotsContainer.appendChild(dot);
    });
}

function changeLightboxSlide(n) {
    currentLightboxIndex += n;
    if (currentLightboxIndex >= currentLightboxImages.length) currentLightboxIndex = 0;
    if (currentLightboxIndex < 0) currentLightboxIndex = currentLightboxImages.length - 1;
    updateLightboxDOM();
}

function setLightboxSlide(n) {
    currentLightboxIndex = n;
    updateLightboxDOM();
}

document.addEventListener('keydown', function(e) {
    if (document.getElementById('lightboxModal').style.display === 'block') {
        if (e.key === 'ArrowLeft') changeLightboxSlide(-1);
        if (e.key === 'ArrowRight') changeLightboxSlide(1);
        if (e.key === 'Escape') closeLightbox();
    }
});

function addManualColor() {
    const input = document.getElementById('custom-color-input');
    const val = input.value.trim();
    if (val) {
        const safeId = 'c_' + val.replace(/\s+/g, '_');
        if (document.getElementById(safeId)) { alert('Color already exists!'); return; }
        const colorsContainer = document.querySelector('.d-flex.flex-wrap');
        if (colorsContainer) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'selected_colors[]';
            checkbox.className = 'btn-check color-selector';
            checkbox.id = safeId;
            checkbox.value = val;

            const label = document.createElement('label');
            label.className = 'btn btn-outline-orange';
            label.htmlFor = safeId;
            label.textContent = val;

            colorsContainer.appendChild(checkbox);
            colorsContainer.appendChild(label);
            checkbox.click();
        } else {
            alert('Unable to add color - container not found.');
        }
        input.value = '';
    }
}

// Delegated handler: 当某个颜色的尺寸被勾选/取消时，动态添加或移除对应的 stock 输入（按尺寸从小到大排序）
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('size-checkbox')) return;
    const checkbox = e.target;
    const color = checkbox.dataset.color;
    const safeColorId = color.replace(/\s+/g, '_');
    const stockContainer = document.getElementById('stock_' + safeColorId);
    if (!stockContainer) return;
    
    // 获取该颜色下所有已勾选的尺寸复选框，按大小排序
    const variantBox = document.getElementById('box_' + color);
    if (!variantBox) return;
    const checkedSizes = Array.from(variantBox.querySelectorAll('.size-checkbox:checked'))
        .map(cb => parseFloat(cb.value))
        .sort((a, b) => a - b);
    
    // 清空容器并按排序顺序重新生成
    stockContainer.innerHTML = '';
    checkedSizes.forEach(size => {
        const sizeLabel = size.toFixed(1);
        const colDiv = document.createElement('div');
        colDiv.className = 'col-md-3 mb-3 stock-item';
        colDiv.dataset.size = sizeLabel;
        colDiv.innerHTML = `<div class="p-2 border rounded" style="background: linear-gradient(135deg, rgba(255, 140, 0, 0.05) 0%, rgba(255, 140, 0, 0.02) 100%); border: 2px solid #FFE4CC; border-radius: 10px;"><label class="small fw-bold d-block mb-2" style="color: #FF8C00; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">UK ${sizeLabel}</label><input type="number" name="stock[${color}][${sizeLabel}]" class="form-control form-control-sm" min="0" value="0" placeholder="Qty" style="border: 1px solid #FFD699; color: var(--main-orange); font-weight: 600; text-align: center; background: white;"></div>`;
        stockContainer.appendChild(colDiv);
    });
});
</script>

</body>
</html>