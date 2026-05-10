<?php
session_start();
require_once '../includes/db_connection.php';

// --- 1. 后端处理逻辑 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_product'])) {
    $pro_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $brand_id = intval($_POST['brand']); 
    $price    = abs(floatval($_POST['selling_price'])); 
    $desc     = mysqli_real_escape_string($conn, $_POST['description']);
    $gender   = $_POST['gender'];
    $age_group = $_POST['age_group'];
    $status   = ($_POST['status'] == 'Active') ? 'Available' : 'Unavailable';

    // 处理 Category 逻辑
    $cat_id = 0;
    if (!empty($_POST['new_category'])) {
        $new_cat_name = mysqli_real_escape_string($conn, $_POST['new_category']);
        $check_cat = $conn->prepare("SELECT Cat_Id FROM category WHERE Cat_Name = ?");
        $check_cat->bind_param("s", $new_cat_name);
        $check_cat->execute();
        $res_cat = $check_cat->get_result();
        
        if ($res_cat->num_rows > 0) {
            $existing_cat = $res_cat->fetch_assoc();
            $cat_id = $existing_cat['Cat_Id'];
        } else {
            $insert_cat = $conn->prepare("INSERT INTO category (Cat_Name) VALUES (?)");
            $insert_cat->bind_param("s", $new_cat_name);
            $insert_cat->execute();
            $cat_id = $conn->insert_id;
            $insert_cat->close();
        }
        $check_cat->close();
    } else {
        $cat_id = intval($_POST['category']);
    }

    // 处理名字转为全小写且无空格 (例如 Niki Air -> nikiair)
    $pure_name = strtolower(str_replace(' ', '', $_POST['product_name']));
    $db_main_image = $pure_name . ".jpg"; // 默认图片名

    // ==========================================
    // 上传照片处理逻辑开始
    // ==========================================
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $first_image_uploaded = false;

    if (isset($_FILES['color_photos'])) {
        foreach ($_FILES['color_photos']['tmp_name'] as $color => $tmp_names) {
            // 清理颜色名称（去掉空格并转小写），例如: Black -> black
            $color_clean = strtolower(str_replace(' ', '', $color));
            $count = 0;

            foreach ($tmp_names as $index => $tmp_name) {
                if ($count >= 4) break; // 每个颜色最大只能存4张
                
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $original_name = $_FILES['color_photos']['name'][$color][$index];
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (empty($ext)) $ext = 'jpg'; 
                    
                    // 只在 SQL 存名字，不执行实体 copy，避免文件夹出现重复照片
                    if (!$first_image_uploaded) {
                        $db_main_image = $pure_name . "." . $ext;
                        $first_image_uploaded = true;
                    }
                    
                    // 命名格式: 名字_颜色_1/2/3/4 (例如: nikiair_black_1.jpg)
                    $new_filename = $pure_name . "_" . $color_clean . "_" . ($count + 1) . "." . $ext;
                    $destination = $upload_dir . $new_filename;
                    
                    move_uploaded_file($tmp_name, $destination);
                    $count++;
                }
            }
        }
    }
    // ==========================================
    // 上传照片处理逻辑结束
    // ==========================================

    $size_string = isset($_POST['variant_sizes']) ? implode(',', array_unique($_POST['variant_sizes'])) : '';
    $color_string = isset($_POST['selected_colors']) ? implode(',', $_POST['selected_colors']) : '';

    $sql_product = "INSERT INTO product (Pro_Name, Cat_Id, Brand_Id, Pro_Price, Pro_Description, Pro_Image, Pro_Size, Pro_Colour, Pro_Gender, Pro_Age_Group, Pro_Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql_product);
    $stmt->bind_param("siidsssssss", $pro_name, $cat_id, $brand_id, $price, $desc, $db_main_image, $size_string, $color_string, $gender, $age_group, $status);
    
    if ($stmt->execute()) {
        $new_pro_id = $conn->insert_id;
        if (!empty($_POST['selected_colors']) && is_array($_POST['selected_colors'])) {
            if (!empty($_POST['stock']) && is_array($_POST['stock'])) {
                $stmt_stock = $conn->prepare("INSERT INTO product_stock (Pro_Id, Pro_Size, Pro_Colour, Quantity) VALUES (?, ?, ?, ?)");
                foreach ($_POST['stock'] as $color => $sizesArr) {
                    foreach ($sizesArr as $size => $qty) {
                        $qty_i = intval($qty);
                        $size_s = $conn->real_escape_string($size);
                        $color_s = $conn->real_escape_string($color);
                        $stmt_stock->bind_param("issi", $new_pro_id, $size_s, $color_s, $qty_i);
                        $stmt_stock->execute();
                    }
                }
                $stmt_stock->close();
            }
        }
        echo "<script>alert('Product Added Successfully!'); window.location.href='admin_manage_products.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}

// --- 2. 管理员与品牌获取逻辑 ---
$admin_id = $_SESSION['admin_id'] ?? 0;
$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_image = 'default_admin.png';

if ($admin_id && isset($conn)) {
    $resAdmin = $conn->query("SELECT * FROM admin WHERE Admin_Id = " . (int)$admin_id);
    if ($resAdmin && $admin = $resAdmin->fetch_assoc()) {
        $admin_role = $admin['Admin_Level'] ?? $_SESSION['role'] ?? 2;
        $admin_name = $admin['Admin_Name'] ?? $admin_name;
        $admin_image = !empty($admin['Admin_Image']) ? $admin['Admin_Image'] : ($_SESSION['admin_image'] ?? 'default_admin.png');
    }
}
$admin_role = $admin_role ?? $_SESSION['role'] ?? 2;

// 恢复品牌逻辑
if ($admin_role == 1 || $admin_role == 2) {
    $brands = $conn->query("SELECT * FROM brand WHERE Brand_Status = 'Active'");
    $brand_locked = false;
} else {
    $stmt_b = $conn->prepare("SELECT * FROM brand WHERE Brand_Status = 'Active' AND Admin_Id = ?");
    $stmt_b->bind_param("i", $admin_id);
    $stmt_b->execute();
    $brands = $stmt_b->get_result();
    $brand_locked = true; 
}

$categories = $conn->query("SELECT * FROM category");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --orange-primary: #FF8C00; 
            --soft-orange: rgba(255, 140, 0, 0.1);
            --sidebar-width: 260px; 
        }
        
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', 'Inter', sans-serif; 
            margin: 0;
            color: #1e293b;
        }
        
        .wrapper { display: flex; }

        .main-content { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
        }

        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }

        .admin-profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
            background: white; 
        }
        
        .text-orange { color: var(--orange-primary); font-weight: 700; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #475569; margin-bottom: 8px; }

        .form-control, .form-select {
            border: 1.5px solid #e2e8f0; padding: 12px 16px; border-radius: 12px; transition: all 0.2s; background-color: #fdfdfd;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--orange-primary); box-shadow: 0 0 0 4px var(--soft-orange); background-color: #fff;
        }

        .btn-outline-orange { 
            border: 1.5px solid var(--orange-primary); color: var(--orange-primary); padding: 10px 20px; border-radius: 12px; font-weight: 600; margin: 4px; transition: 0.2s;
        }
        .btn-outline-orange:hover {
            background-color: var(--soft-orange);
            color: var(--orange-primary);
        }
        .btn-check:checked + .btn-outline-orange { 
            background-color: var(--orange-primary); color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 140, 0, 0.2);
        }

        .variant-box { 
            background: #fff; border: 1.5px solid #f1f5f9; border-radius: 20px; padding: 25px; margin-bottom: 25px; transition: 0.3s;
        }
        .variant-box:hover { border-color: var(--soft-orange); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.05); }

        .preview-item { width: 110px; height: 110px; border-radius: 14px; overflow: hidden; border: 2px solid #f1f5f9; position: relative; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-img { position: absolute; top: 5px; right: 5px; background: #ef4444; color: white; border: none; border-radius: 8px; width: 24px; height: 24px; font-size: 14px; cursor: pointer; }

        .btn-save { background: var(--orange-primary); color: white; padding: 16px 45px; border-radius: 14px; font-weight: 700; border: none; transition: 0.3s; box-shadow: 0 10px 15px -3px rgba(255, 140, 0, 0.3); }
        .btn-save:hover { background: #e67e00; transform: translateY(-3px); box-shadow: 0 20px 25px -5px rgba(255, 140, 0, 0.4); color: white; }

        .lightbox-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); backdrop-filter: blur(8px); }
        .lightbox-content { max-width: 85%; max-height: 80vh; border-radius: 15px; margin: auto; display: block; }
        .lightbox-close { position: absolute; top: 30px; right: 40px; color: white; font-size: 40px; cursor: pointer; }
        .lightbox-nav { background: rgba(255,255,255,0.1); border: none; color: white; padding: 15px 20px; border-radius: 50%; position: absolute; top: 50%; transform: translateY(-50%); cursor: pointer; }
        .lightbox-prev { left: 30px; } .lightbox-next { right: 30px; }

        .back-button-container { display: flex; justify-content: flex-end; margin-bottom: 15px; padding: 0 10px; }
        .btn-back-header { text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; transition: all 0.2s; border: 1px solid #e2e8f0; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .btn-back-header:hover { background-color: #fff; color: #FA8A34; border-color: #FA8A34; transform: translateX(-3px); }

        /* 库存输入框样式 */
        .stock-input {
            background-color:  ;
            color: black !important;
            border: 2px solid #FA8A34 ;

        }
        .stock-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<?php include_once '../includes/admin_sidebar.php'; ?>

<div class="wrapper">
    <div class="main-content">
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="admin_manage_products.php" class="text-decoration-none" style="color: var(--orange-primary);">Products</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page" style="color: #6c757d;">Add New</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Create New Product</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
                    <small class="text-muted"><?php echo ($admin_role == 1) ? 'Super Admin' : (($admin_role == 2) ? 'Admin' : 'Vendor'); ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <form action="" method="POST" enctype="multipart/form-data" id="productForm">
            <div class="back-button-container">
                <a href="admin_manage_products.php" class="btn-back-header"><i class="bi bi-arrow-left"></i> Back to Products</a>
            </div>

            <div class="card p-4">
                <h5 class="text-orange mb-4"><i class="bi bi-1-circle-fill me-2"></i> Step 1: Basic Information</h5>
                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" class="form-control" placeholder="e.g. Nike Air Max 270" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand</label>
                        <?php $selected_brand_id = ""; ?>
                        <select name="brand" class="form-select" <?= $brand_locked ? 'disabled' : '' ?>>
                            <?php 
                            while($b = $brands->fetch_assoc()) {
                                $selected = $brand_locked ? 'selected' : '';
                                if ($selected) $selected_brand_id = $b['Brand_Id'];
                                echo "<option value='{$b['Brand_Id']}' $selected>{$b['Brand_Name']}</option>";
                            } 
                            ?>
                        </select>
                        <?php if($brand_locked): ?>
                            <input type="hidden" name="brand" value="<?= $selected_brand_id ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="category" id="category_select" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php while($c = $categories->fetch_assoc()) echo "<option value='{$c['Cat_Id']}'>{$c['Cat_Name']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">New Category (Optional)</label>
                        <input type="text" name="new_category" id="new_category_input" class="form-control" placeholder="Add custom category">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Enter product details..."></textarea>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <h5 class="text-orange mb-4"><i class="bi bi-2-circle-fill me-2"></i> Step 2: Pricing & Audience</h5>
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label">Base Price (RM) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 rounded-start-3">RM</span>
                            <input type="number" step="0.01" name="selling_price" class="form-control  border-start-0" min="1" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Unisex">Unisex</option><option value="Men">Men</option><option value="Women">Women</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Age Group</label>
                        <select name="age_group" class="form-select">
                            <option value="Adult">Adult</option><option value="Kids">Kids</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <h5 class="text-orange mb-4"><i class="bi bi-3-circle-fill me-2"></i> Step 3: Colors & Variations</h5>
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <label class="form-label d-block">Select Available Colors</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach(['Black','White','Red','Blue','Grey','Green'] as $col): ?>
                                <input type="checkbox" name="selected_colors[]" class="btn-check color-selector" id="c_<?= $col ?>" value="<?= $col ?>">
                                <label class="btn btn-outline-orange" for="c_<?= $col ?>"><?= $col ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="input-group" style="max-width: 300px;">
                            <input type="text" id="custom-color-input" class="form-control" placeholder="Other Color Name">
                            <button type="button" class="btn btn-dark px-3 rounded-end-3" onclick="addManualColor()"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>
                </div>
                <div id="variant-upload-container">
                    <div class="text-center py-5 text-muted bg-light rounded-4 border-dashed" id="empty-hint">
                        <i class="bi bi-palette fs-1 d-block mb-2"></i>Select colors above to configure photos and stock.
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-end gap-3 mt-5 mb-5">
                    <select name="status" class="form-select border-0 shadow-sm" style="width: 140px;">
                        <option value="Active">Publish Now</option>
                        <option value="Draft">Save Draft</option>
                    </select>
                    <button type="submit" name="save_product" class="btn btn-save">Create Product</button>
            </div>
        </form>
    </div>
</div>

<div id="lightboxModal" class="lightbox-modal">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <button type="button" class="lightbox-nav lightbox-prev" onclick="changeLightboxSlide(-1)"><i class="bi bi-chevron-left"></i></button>
    <img class="lightbox-content" id="lightboxImage">
    <button type="button" class="lightbox-nav lightbox-next" onclick="changeLightboxSlide(1)"><i class="bi bi-chevron-right"></i></button>
</div>

<script>
const colorFilesManager = {};
let currentLightboxIndex = 0;
let currentLightboxColor = '';
let currentLightboxImages = [];

document.getElementById('new_category_input').addEventListener('input', function() {
    const select = document.getElementById('category_select');
    if (this.value.trim() !== "") { select.value = ""; select.disabled = true; } else { select.disabled = false; }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('color-selector')) {
        const color = e.target.value;
        const safeId = color.replace(/\s+/g, '_');
        const hint = document.getElementById('empty-hint');
        if (e.target.checked) {
            if(hint) hint.remove();
            addVariantBox(color);
        } else {
            const box = document.getElementById('box_' + safeId);
            if (box) box.remove();
            delete colorFilesManager[color];
            if (document.querySelectorAll('.color-selector:checked').length === 0) {
                document.getElementById('variant-upload-container').innerHTML = `<div class="text-center py-5 text-muted bg-light rounded-4 border-dashed" id="empty-hint"><i class="bi bi-palette fs-1 d-block mb-2"></i>Select colors above to configure photos and stock.</div>`;
            }
        }
    }
});

function addVariantBox(color) {
    const container = document.getElementById('variant-upload-container');
    const safeId = color.replace(/\s+/g, '_');
    colorFilesManager[color] = new DataTransfer();
    const sizes = [3.0, 3.5, 4.0, 4.5, 5.0, 5.5, 6.0, 6.5, 7.0, 7.5, 8.0, 8.5, 9.0, 9.5, 10.0, 10.5, 11.0, 11.5, 12.0];
    const sizeCheckboxes = sizes.map(s => {
        const label = s.toFixed(1);
        return `<input type="checkbox" class="btn-check size-checkbox" name="variant_sizes[]" id="s_${safeId}_${label}" data-color="${color}" value="${label}"><label class="btn btn-sm btn-outline-orange" for="s_${safeId}_${label}">${label}</label>`;
    }).join('');

    const html = `
        <div class="variant-box" id="box_${safeId}">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold mb-0 text-dark"><span class="badge bg-warning me-2">${color}</span> Variation Details</h6>
                <button type="button" class="btn-close btn-sm" onclick="document.getElementById('c_${color}').click()"></button>
            </div>
            <div class="row g-4">
                <div class="col-md-6 border-end">
                    <label class="form-label d-block"><i class="bi bi-images me-2"></i>Gallery for ${color}</label>
                    <div class="d-flex flex-wrap gap-3 mb-3" id="preview_${safeId}"><div class="text-muted small py-4 text-center w-100 border rounded-4 bg-light">No photos yet</div></div>
                    <button type="button" class="btn btn-sm btn-dark rounded-3 px-3" onclick="document.getElementById('input_${safeId}').click()"><i class="bi bi-cloud-upload me-2"></i>Upload Photos</button>
                    <input type="file" id="input_${safeId}" class="d-none" multiple onchange="handleFileSelect(this, '${color}')">
                    <input type="file" name="color_photos[${color}][]" id="final_input_${safeId}" multiple class="d-none">
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block"><i class="bi bi-rulers me-2"></i>Available Sizes (UK)</label>
                    <div class="d-flex flex-wrap gap-2 mb-4">${sizeCheckboxes}</div>
                    <label class="form-label d-block"><i class="bi bi-box-seam me-2"></i>Set Stock Quantity</label>
                    <div class="row g-2" id="stock_${safeId}"><div class="col-12 text-muted small">Select sizes above first...</div></div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function handleFileSelect(input, color) {
    const safeId = color.replace(/\s+/g, '_');
    const preview = document.getElementById(`preview_${safeId}`);
    const finalInput = document.getElementById(`final_input_${safeId}`);
    if (colorFilesManager[color].items.length === 0) preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
        colorFilesManager[color].items.add(file);
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.onclick = () => openLightbox(color, div);
            div.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img" onclick="event.stopPropagation(); removeFile('${color}', this)">×</button>`;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
    finalInput.files = colorFilesManager[color].files;
}

function removeFile(color, btn) {
    const safeId = color.replace(/\s+/g, '_');
    const preview = document.getElementById(`preview_${safeId}`);
    const items = Array.from(preview.querySelectorAll('.preview-item'));
    const idx = items.indexOf(btn.parentNode);
    const dt = new DataTransfer();
    Array.from(colorFilesManager[color].files).forEach((f, i) => { if(i !== idx) dt.items.add(f); });
    colorFilesManager[color] = dt;
    document.getElementById(`final_input_${safeId}`).files = dt.files;
    btn.parentNode.remove();
}

document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('size-checkbox')) return;
    const color = e.target.dataset.color;
    const safeId = color.replace(/\s+/g, '_');
    const container = document.getElementById('stock_' + safeId);
    const checked = Array.from(document.getElementById('box_' + safeId).querySelectorAll('.size-checkbox:checked'));
    container.innerHTML = checked.length ? '' : '<div class="col-12 text-muted small">Select sizes above first...</div>';
    checked.forEach(cb => {
        const size = cb.value;
        const div = document.createElement('div');
        div.className = 'col-4';
        div.innerHTML = `<div class="p-2 border rounded-3 bg-light"><label class="d-block small fw-bold mb-1">UK ${size}</label><input type="number" name="stock[${color}][${size}]" class="form-control form-control-sm text-center stock-input" value="0" min="0"></div>`;
        container.appendChild(div);
    });
});

function addManualColor() {
    const input = document.getElementById('custom-color-input');
    const val = input.value.trim();
    if (val) {
        const id = 'c_' + val.replace(/\s+/g, '_');
        if (document.getElementById(id)) return;
        const wrapper = document.querySelector('.d-flex.flex-wrap.gap-2');
        wrapper.insertAdjacentHTML('beforeend', `<input type="checkbox" name="selected_colors[]" class="btn-check color-selector" id="${id}" value="${val}"><label class="btn btn-outline-orange" for="${id}">${val}</label>`);
        document.getElementById(id).click();
        input.value = '';
    }
}

function openLightbox(color, el) {
    const safeId = color.replace(/\s+/g, '_');
    const imgs = Array.from(document.getElementById('preview_'+safeId).querySelectorAll('img'));
    currentLightboxImages = imgs.map(i => i.src);
    currentLightboxIndex = imgs.indexOf(el.querySelector('img'));
    document.getElementById('lightboxImage').src = currentLightboxImages[currentLightboxIndex];
    document.getElementById('lightboxModal').style.display = 'flex';
}
function closeLightbox() { document.getElementById('lightboxModal').style.display = 'none'; }
function changeLightboxSlide(n) {
    currentLightboxIndex = (currentLightboxIndex + n + currentLightboxImages.length) % currentLightboxImages.length;
    document.getElementById('lightboxImage').src = currentLightboxImages[currentLightboxIndex];
}

// 处理表单提交验证
document.getElementById('productForm').addEventListener('submit', function(e) {
    const form = this;
    const catSelect = document.getElementById('category_select');
    const newCatInput = document.getElementById('new_category_input');
    const description = form.querySelector('textarea[name="description"]').value.trim();
    const selectedColors = document.querySelectorAll('.color-selector:checked');
    const selectedSizes = document.querySelectorAll('.size-checkbox:checked');
    const priceInput = form.querySelector('input[name="selling_price"]');

    // 1. Category 验证 (必须选择或填写新的)
    if (catSelect.value === "" && newCatInput.value.trim() === "") {
        e.preventDefault();
        Swal.fire('Error', 'Please select a Category or enter a New Category!', 'error');
        return;
    }

    // 3. Color 验证 (必须选择)
    if (selectedColors.length === 0) {
        e.preventDefault();
        Swal.fire('Error', 'Please select at least one color!', 'error');
        return;
    }

    // 6. Sizes 验证 (必须选择)
    if (selectedSizes.length === 0) {
        e.preventDefault();
        Swal.fire('Error', 'Please select at least one available size!', 'error');
        return;
    }

    // 4. Price 验证 (最低1)
    if (parseFloat(priceInput.value) < 1 || priceInput.value === "") {
        e.preventDefault();
        Swal.fire('Error', 'Price must be at least 1 RM!', 'error');
        return;
    }

    // 2. Description 验证 (警告但允许通过)
    if (description === "") {
        e.preventDefault(); // 先拦截
        Swal.fire({
            title: 'Empty Description',
            text: "You haven't added a description. Do you want to continue?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF8C00',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, submit anyway'
        }).then((result) => {
            if (result.isConfirmed) {
                // 如果用户确认，解除拦截并再次触发提交
                form.submit(); 
            }
        });
        return;
    }
});

// 4 & 5. 实时限制负数输入
document.addEventListener('input', function(e) {
    if (e.target.name === 'selling_price' || e.target.classList.contains('stock-input')) {
        if (e.target.value < 0) {
            e.target.value = 0;
        }
        // 价格特别处理：如果用户尝试删掉数字或输入0，在失去焦点时可以检查，这里实时强制不能小于1（针对价格）
        if (e.target.name === 'selling_price' && e.target.value !== "" && e.target.value < 1) {
            // 这里不强制，留给提交验证，或者在此强制 e.target.value = 1;
        }
    }
});

// 修正价格输入框的最小属性 (在原有HTML中找到该行并确保有 min="1")
document.querySelector('input[name="selling_price"]').setAttribute('min', '1');
</script>
</body>
</html>