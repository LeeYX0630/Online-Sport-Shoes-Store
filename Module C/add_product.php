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

        if (isset($_POST['selected_colors'])) {
            foreach ($_POST['selected_colors'] as $index => $color) {
                // 物理文件名的颜色部分 (移除空格)
                $folder_color_name = strtolower(str_replace(' ', '', $color));

                // A. 处理物理图片上传与重命名
                if (isset($_FILES['color_photos']['name'][$index])) {
                    $img_counter = 1;
                    foreach ($_FILES['color_photos']['name'][$index] as $key => $val) {
                        if ($_FILES['color_photos']['error'][$index][$key] == 0) {
                            $ext = pathinfo($_FILES['color_photos']['name'][$index][$key], PATHINFO_EXTENSION);
                            
                            // 生成物理文件名: airjordan_white_1.jpg
                            $physical_file_name = $pure_name . "_" . $folder_color_name . "_" . $img_counter . "." . $ext;
                            $target_path = "../uploads/" . $physical_file_name;
                            
                            if (move_uploaded_file($_FILES['color_photos']['tmp_name'][$index][$key], $target_path)) {
                                // 存入 product_images 表 (关联每张图的物理路径)
                                $conn->query("INSERT INTO product_images (Pro_Id, Pro_Colour, Image_Path) VALUES ('$new_pro_id', '$color', '$physical_file_name')");
                                $img_counter++;
                            }
                        }
                    }
                }

                // B. 处理库存 (写入 product_stock 表)
                if (isset($_POST['stock'][$color])) {
                    foreach ($_POST['stock'][$color] as $size => $quantity) {
                        $qty = intval($quantity); // 确保是整数
                        // 使用 prepared statement 更安全
                        $stmt_stock = $conn->prepare("INSERT INTO product_stock (Pro_Id, Pro_Size, Pro_Colour, Quantity) VALUES (?, ?, ?, ?)");
                        $stmt_stock->bind_param("isss", $new_pro_id, $size, $color, $qty);
                        $stmt_stock->execute();
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

        .btn-save { background: var(--main-orange); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 600; font-size: 16px; transition: 0.3s; box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3); }
        .btn-save:hover { background: #e67e00; transform: translateY(-2px); color: white; }
        .back-btn { color: #6c757d; font-weight: 600; text-decoration: none; padding: 10px 20px; border-radius: 12px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s; }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar-wrapper"><?php include '../includes/admin_sidebar.php'; ?></div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h3 class="mb-1 fw-bold">Create Product</h3>
                <p class="text-muted small mb-0">Fill in the details to list a new item in your store</p>
            </div>
            <a href="admin_manage_products.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back to Products</a>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" id="productForm">
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
                        <textarea name="description" class="form-control" rows="4" placeholder="Describe the product features, materials, and unique selling points..."></textarea>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <h5 class="text-orange mb-4"><i class="bi bi-sliders me-2"></i> Step 2: Price & General Variants</h5>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Base Price (RM) *</label>
                        <input type="number" step="0.01" min="0" name="selling_price" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div class="row g-5">
                    <div class="col-md-6 border-end">
                        <label class="form-label fw-bold mb-3">Size (UK) <span class="text-muted fw-normal small">(Select all that apply)</span></label>
                        <div class="d-flex flex-wrap">
                            <?php for($s=5.0; $s<=10.0; $s+=0.5): ?>
                                <input type="checkbox" class="btn-check" name="variant_sizes[]" id="s<?= $s ?>" value="<?= $s ?>">
                                <label class="btn btn-outline-orange" for="s<?= $s ?>"><?= number_format($s, 1) ?></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-3">Colors <span class="text-muted fw-normal small">(Each color gets its own photos)</span></label>
                        <div class="d-flex flex-wrap">
                            <?php foreach(['Black','White','Red','Navy','Grey'] as $col): ?>
                                <input type="checkbox" class="btn-check color-selector" id="c_<?= $col ?>" value="<?= $col ?>">
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

            <div id="step3-section" style="display: none;">
                <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-camera me-2 text-orange"></i> Step 3: Variant Media</h5>
                <div id="variant-upload-container"></div>
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
// JS 逻辑保持不变 (colorFilesManager, handleFileSelect, openLightbox 等)
const colorFilesManager = {};
let currentLightboxColor = null;
let currentLightboxIndex = 0;
let currentLightboxImages = []; 

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('color-selector')) {
        const color = e.target.value;
        const section = document.getElementById('step3-section');
        if (e.target.checked) {
            section.style.display = 'block';
            addUploadBox(color);
        } else {
            const box = document.getElementById('box_' + color);
            if (box) box.remove();
            delete colorFilesManager[color];
            if (document.querySelectorAll('.color-selector:checked').length === 0) section.style.display = 'none';
        }
    }
});

function addUploadBox(color) {
    const container = document.getElementById('variant-upload-container');
    const index = document.querySelectorAll('.variant-box').length;
    
    colorFilesManager[color] = new DataTransfer();

    // 获取当前选中的所有尺寸
    const selectedSizes = Array.from(document.querySelectorAll('input[name="variant_sizes[]"]:checked')).map(cb => cb.value);

    // 生成库存输入行的 HTML
    let stockHtml = `
        <div class="mt-4">
            <h6 class="fw-bold"><i class="bi bi-box-seize me-2"></i>Stock Management for ${color}</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mt-2 text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Size (UK)</th>
                            <th style="width: 150px;">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>`;
    
    selectedSizes.forEach(size => {
        stockHtml += `
            <tr>
                <td class="align-middle">UK ${size}</td>
                <td>
                    <input type="number" name="stock[${color}][${size}]" 
                           class="form-control form-control-sm" 
                           placeholder="Qty" min="0" value="0" required>
                </td>
            </tr>`;
    });

    stockHtml += `</tbody></table></div></div>`;

    const html = `
        <div class="variant-box" id="box_${color}">
            <input type="hidden" name="selected_colors[${index}]" value="${color}">
            <div class="row">
                <div class="col-md-3 border-end">
                    <h5 class="mb-0 fw-bold">${color}</h5>
                    <p class="text-muted small">Manage photos & stock</p>
                    <div class="upload-btn-wrapper mt-2">
                        <button type="button" class="upload-btn-custom"><i class="bi bi-plus-circle"></i> Add Photos</button>
                        <input type="file" id="input_${color}" class="real-file-input" style="position:absolute; font-size:100px; opacity:0; right:0; top:0; cursor:pointer;" multiple onchange="handleFileSelect(this, '${color}', ${index})">
                    </div>
                    <input type="file" name="color_photos[${index}][]" id="final_input_${color}" multiple style="display:none">
                </div>
                <div class="col-md-9 ps-4">
                    <div class="row">
                        <div class="col-md-6 border-end">
                             <div class="preview-container" id="preview_${color}">
                                <div class="text-muted small mt-2">No photos uploaded yet.</div>
                             </div>
                        </div>
                        <div class="col-md-6">
                            ${stockHtml} </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

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
        if(document.getElementById('c_' + val)) { alert('Color already exists!'); return; }
        document.getElementById('step3-section').style.display = 'block';
        addUploadBox(val);
        input.value = '';
    }
}

// 监听尺寸勾选框的变化
document.querySelectorAll('input[name="variant_sizes[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        // 当尺寸变化时，最简单的办法是清空 Step 3 让用户重新选颜色，
        // 或者动态在已有的 color box 里增加/删除行 (稍微复杂点)。
        // 建议提示用户：
        const checkedColors = document.querySelectorAll('.color-selector:checked');
        if (checkedColors.length > 0) {
            alert('Sizes changed. Please re-toggle your selected colors to update the stock input fields.');
            // 自动取消颜色勾选以便刷新
            checkedColors.forEach(c => c.click());
        }
    });
});
</script>

</body>
</html>