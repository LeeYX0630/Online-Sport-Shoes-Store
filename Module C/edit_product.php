<?php
session_start();
require_once '../includes/db_connection.php';


// ── 读取 .env ──────────────────────────────────────────────────
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (!empty($key)) { putenv("$key=$value"); $_ENV[$key] = $value; }
    }
}
loadEnv(__DIR__ . '/../includes/Tung_Gemini_API.env');

// ── AI 生成描述 AJAX ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'generate_desc') {
    header('Content-Type: application/json');
    $pro_name  = trim($_POST['pro_name']  ?? '');
    $brand     = trim($_POST['brand']     ?? '');
    $category  = trim($_POST['category']  ?? '');
    $gender    = trim($_POST['gender']    ?? '');
    $age_group = trim($_POST['age_group'] ?? '');

    if (empty($pro_name)) { echo json_encode(['error' => 'Product name is required.']); exit(); }

    $prompt = "Write a compelling product description for an online sports shoe store.\n"
            . "Product: {$pro_name}\nBrand: {$brand}\nCategory: {$category}\nGender: {$gender}\nAge Group: {$age_group}\n\n"
            . "Requirements:\n- 2-3 sentences only\n- Professional and persuasive tone\n"
            . "- Highlight performance, comfort, and style\n- Do NOT use bullet points\n"
            . "- Do NOT start with 'Introducing'\n- Output the description text only, no extra commentary";

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 200]
    ]);

    $apiKey = getenv('GEMINI_API_KEY');
    if (empty($apiKey)) { echo json_encode(['error' => 'API key not found.']); exit(); }

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey;
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) { echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]); curl_close($ch); exit(); }
    curl_close($ch);

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text) {
        echo json_encode(['description' => trim($text)]);
    } else {
        echo json_encode(['error' => $data['error']['message'] ?? 'Failed to generate description.']);
    }
    exit();
}


// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

// --- 变量初始化 ---
$is_edit = false;
$edit_pro_id = 0;
$product_data = [];
$existing_stock = [];
$existing_colors = [];
$existing_sizes = [];

// --- 获取原有产品资料 (用于编辑模式) ---
if (isset($_GET['id'])) {
    $is_edit = true;
    $edit_pro_id = intval($_GET['id']);
    
    $stmt_get = $conn->prepare("SELECT * FROM product WHERE Pro_Id = ?");
    $stmt_get->bind_param("i", $edit_pro_id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result();

    if ($res_get->num_rows > 0) {
        $product_data = $res_get->fetch_assoc();
        
        // 拆分颜色和尺寸用于 UI 显示
        $raw_colors = preg_split('/[\/,]/', $product_data['Pro_Colour']);
        $existing_colors = array_map('trim', $raw_colors);
        $existing_colors = array_filter($existing_colors); 

        $existing_sizes = explode(',', $product_data['Pro_Size']);
    }
    $stmt_get->close();

    // 获取库存数据
    $stmt_stock_get = $conn->prepare("SELECT * FROM product_stock WHERE Pro_Id = ?");
    $stmt_stock_get->bind_param("i", $edit_pro_id);
    $stmt_stock_get->execute();
    $res_stock = $stmt_stock_get->get_result();
    while ($row = $res_stock->fetch_assoc()) {
        $existing_stock[$row['Pro_Colour']][$row['Pro_Size']] = $row['Quantity'];
    }
    $stmt_stock_get->close();
}

// --- 后端保存处理逻辑 (Add & Edit 通用) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_product'])) {
    $pro_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    
    // 修复：如果 brand 被禁用，则从 hidden 字段或旧数据中获取，防止外键报错
    $brand_id = isset($_POST['brand']) ? intval($_POST['brand']) : ($is_edit ? $product_data['Brand_Id'] : 0); 
    
    $price    = abs(floatval($_POST['selling_price'])); 
    // 从请求中获取 description 并清理（支持来自表单或其他来源）
    $desc     = mysqli_real_escape_string($conn, $_REQUEST['description'] ?? '');
    // 强制服务器端验证：Description 不能为空
    if (trim($desc) === '') {
        $_SESSION['swal_status'] = 'desc_required';
        $redirect = 'edit_product.php' . ($is_edit ? '?id=' . intval($edit_pro_id) : '');
        header('Location: ' . $redirect);
        exit();
    }
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

    // 图片命名处理
    $pure_name = strtolower(str_replace(' ', '', $_POST['product_name']));
    // 如果是编辑且没传新图，先默认使用数据库旧图名
    $db_main_image = $is_edit ? $product_data['Pro_Image'] : ($pure_name . ".jpg");

    // 上传照片处理 (增强：支持保留旧图并按需删除/重命名)
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $first_image_uploaded = false;

    if (isset($_FILES['color_photos'])) {
        foreach ($_FILES['color_photos']['tmp_name'] as $color => $tmp_names) {
            $color_clean = strtolower(str_replace(' ', '_', $color));

            // ── 获取前端传来的要保留的旧图列表（basename）
            $keep_files = $_POST['keep_photos'][$color] ?? [];

            // ── 检查这个颜色是否有新图上传
            $has_new_for_this_color = false;
            foreach ($tmp_names as $tmp_name) {
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $has_new_for_this_color = true;
                    break;
                }
            }

if ($is_edit) {
                $path_info     = pathinfo($product_data['Pro_Image']);
                $img_base_name = $path_info['filename'];

                // 找出这个颜色所有旧图
                $all_old = glob($upload_dir . $img_base_name . '_' . $color_clean . '_*.*') ?: [];

                // 1. 只删除用户在前端移除了的旧图（不在 keep_photos 里的）
                foreach ($all_old as $f) {
                    $basename = basename($f);
                    if (!in_array($basename, $keep_files) && is_file($f)) {
                        unlink($f);
                    }
                }
                
                // 2. 重新按序号命名留下来（没有被删）的旧文件，保持连续性
                $remaining = glob($upload_dir . $img_base_name . '_' . $color_clean . '_*.*') ?: [];
                sort($remaining);
                foreach ($remaining as $idx => $f) {
                    $ext      = pathinfo($f, PATHINFO_EXTENSION);
                    $new_name = $upload_dir . $img_base_name . '_' . $color_clean . '_' . ($idx + 1) . '.' . $ext;
                    if ($f !== $new_name) {
                        rename($f, $new_name);
                    }
                }
            }

            // ── 3. 重要修复：确定统一的文件名前缀（编辑模式用旧前缀，新增模式用新商品名） ──
            $final_base_name = $is_edit ? pathinfo($product_data['Pro_Image'], PATHINFO_FILENAME) : $pure_name;

            // ── 4. 重新计算当前文件夹中该颜色实际留下的有效旧图数量 ──
            $existing_count = count(glob($upload_dir . $final_base_name . '_' . $color_clean . '_*.*') ?: []);
            $count = $existing_count;

            // ── 5. 追加新图 ──
            foreach ($tmp_names as $index => $tmp_name) {
                if ($count >= 4) break; // 确保总数（旧图+新图）不超过4张
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $original_name = $_FILES['color_photos']['name'][$color][$index];
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (empty($ext)) $ext = 'jpg';

                    if (!$first_image_uploaded) {
                        // 统一保持主图扩展名一致
                        $db_main_image        = $final_base_name . '.' . $ext;
                        $first_image_uploaded = true;
                    }

                    // 使用统一确定的 $final_base_name 追加编号，绝对不会覆盖旧图
                    $new_filename = $final_base_name . '_' . $color_clean . '_' . ($count + 1) . '.' . $ext;
                    move_uploaded_file($tmp_name, $upload_dir . $new_filename);
                    $count++;
                }
            }
            // ── 上传新图：从现有文件数开始计数，避免覆盖留下的旧图
            $base_name_for_count = $is_edit ? pathinfo($product_data['Pro_Image'], PATHINFO_FILENAME) : $pure_name;
            $existing_count = count(glob($upload_dir . $base_name_for_count . '_' . $color_clean . '_*.*') ?: []);
            $count = $existing_count;

            foreach ($tmp_names as $index => $tmp_name) {
                if ($count >= 4) break;
                if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                    $original_name = $_FILES['color_photos']['name'][$color][$index];
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (empty($ext)) $ext = 'jpg';

                    if (!$first_image_uploaded) {
                        $db_main_image        = $pure_name . '.' . $ext;
                        $first_image_uploaded = true;
                    }

                    $new_filename = $pure_name . '_' . $color_clean . '_' . ($count + 1) . '.' . $ext;
                    move_uploaded_file($tmp_name, $upload_dir . $new_filename);
                    $count++;
                }
            }
                }

        // ── 【添加此段逻辑】检查数据库执行状态并设置提示变量 ──
        // 注意：请确保你的主要 SQL 执行（如 $stmt->execute()）在这里已经完成。
        if (!isset($conn->error) || empty($conn->error)) {
            $_SESSION['swal_status'] = 'success';
        } else {
            $_SESSION['swal_status'] = 'failed';
        }
    }
}


// 管理员信息获取 (保持不变)
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

// 品牌与分类获取 (保持不变)
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

// 图片预览路径逻辑 (保持不变)
$existing_images = []; 
if ($is_edit && !empty($product_data['Pro_Image'])) {
    $path_info = pathinfo($product_data['Pro_Image']);
    $pure_name_img = $path_info['filename']; 
    foreach ($existing_colors as $color) {
        $color_slug = strtolower(str_replace(' ', '_', trim($color)));
        $search_pattern = "../uploads/" . $pure_name_img . "_" . $color_slug . "_*.*";
        $files = glob($search_pattern);
        if ($files) {
            sort($files);
            $existing_images[$color] = $files;
        }
    }
}
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

        .typing-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #FF8C00;
            animation: typingBounce 0.6s infinite alternate;
        }
        @keyframes typingBounce {
            from { transform: translateY(0); opacity: 0.5; }
            to   { transform: translateY(-6px); opacity: 1; }
        }
        #generateDescBtn:hover { background: linear-gradient(135deg, #e67e00, #c94d00) !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(255,140,0,0.35); }
        #generateDescBtn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        
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
            <div id="keepPhotosContainer"></div>
            <div class="back-button-container">
                <a href="admin_manage_products.php" class="btn-back-header"><i class="bi bi-arrow-left"></i> Back to Products</a>
            </div>

            <div class="card p-4">
                <h5 class="text-orange mb-4"><i class="bi bi-1-circle-fill me-2"></i> Step 1: Basic Information</h5>
                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" class="form-control" placeholder="e.g. Nike Air Max 270" value="<?php echo $is_edit ? htmlspecialchars($product_data['Pro_Name']) : ''; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand</label>
                        <?php $selected_brand_id = ""; ?>
                        <select name="brand" class="form-select" <?= $brand_locked ? 'disabled' : '' ?>>
                            <?php 
                            $current_selected_brand = 0;
                            while($b = $brands->fetch_assoc()) {
                                $selected = ($is_edit && $product_data['Brand_Id'] == $b['Brand_Id']) ? 'selected' : ($brand_locked && !$is_edit ? 'selected' : '');
                                if($selected) $current_selected_brand = $b['Brand_Id'];
                                echo "<option value='{$b['Brand_Id']}' $selected>{$b['Brand_Name']}</option>";
                            } 
                            ?>
                        </select>
                        <?php if($brand_locked || $is_edit): ?>
                            <input type="hidden" name="brand" value="<?= $current_selected_brand ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="category" id="category_select" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php 
                            $categories->data_seek(0); // 重置指针
                            while($c = $categories->fetch_assoc()) {
                                $selected = ($is_edit && $product_data['Cat_Id'] == $c['Cat_Id']) ? 'selected' : '';
                                echo "<option value='{$c['Cat_Id']}' $selected>{$c['Cat_Name']}</option>";
                            } 
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">New Category (Optional)</label>
                        <input type="text" name="new_category" id="new_category_input" class="form-control" placeholder="Add custom category">
                    </div>

                    <div class="col-md-12">
                        <div class="form-label d-flex justify-content-between align-items-center">
                            <label for="descriptionTextarea" style="margin:0; font-weight:600; font-size:0.9rem; color:#475569;">Description</label>
                            <button type="button" class="btn btn-sm d-flex align-items-center gap-2" id="generateDescBtn"
                                style="background: linear-gradient(135deg, #FF8C00, #e05a00); color: white; border: none; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 600; transition: all 0.2s;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                </svg>
                                <span id="generateBtnText">Generate with AI</span>
                            </button>
                        </div>
                        <div style="position: relative;">
                            <textarea name="description" id="descriptionTextarea" class="form-control" rows="3"
                                placeholder="Enter product details, or click 'Generate with AI' above..."><?php echo $is_edit ? htmlspecialchars($product_data['Pro_Description']) : ''; ?></textarea>
                            <div id="typingOverlay" style="display:none; position:absolute; inset:0; background: rgba(255,255,255,0.85); border-radius: 12px; align-items:center; justify-content:center; gap: 8px; flex-direction: column;">
                                <div style="display:flex; gap:5px; align-items:center;">
                                    <div class="typing-dot"></div>
                                    <div class="typing-dot" style="animation-delay: 0.15s;"></div>
                                    <div class="typing-dot" style="animation-delay: 0.3s;"></div>
                                </div>
                                <span style="font-size: 12px; color: #FF8C00; font-weight: 600;">AI is writing...</span>
                            </div>
                        </div>
                        <div id="aiDescError" class="text-danger small mt-1" style="display:none;"></div>
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
                            <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="0.00" value="<?php echo $is_edit ? $product_data['Pro_Price'] : ''; ?>" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Unisex" <?= ($is_edit && $product_data['Pro_Gender'] == 'Unisex') ? 'selected' : '' ?>>Unisex</option>
                            <option value="Men" <?= ($is_edit && $product_data['Pro_Gender'] == 'Men') ? 'selected' : '' ?>>Men</option>
                            <option value="Women" <?= ($is_edit && $product_data['Pro_Gender'] == 'Women') ? 'selected' : '' ?>>Women</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Age Group</label>
                        <select name="age_group" class="form-select">
                            <option value="Adult" <?= ($is_edit && $product_data['Pro_Age_Group'] == 'Adult') ? 'selected' : '' ?>>Adult</option>
                            <option value="Kids" <?= ($is_edit && $product_data['Pro_Age_Group'] == 'Kids') ? 'selected' : '' ?>>Kids</option>
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
    <?php $checked = ($is_edit && in_array($col, $existing_colors)) ? 'checked' : ''; ?>
    <input type="checkbox" name="selected_colors[]" class="btn-check color-selector" 
           id="c_<?= $col ?>" value="<?= $col ?>" <?= $checked ?>>
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
                    <button type="submit" name="save_product" class="btn btn-save">
                        <?= $is_edit ? 'Update Product' : 'Create Product' ?>
                    </button>
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
    // 生成带下划线的 ID
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
                <button type="button" class="btn-close btn-sm" onclick="document.getElementById('c_${safeId}').click()"></button>
            </div>
            <div class="row g-4">
                <div class="col-md-6 border-end">
                    <label class="form-label d-block"><i class="bi bi-images me-2"></i>Gallery for ${color}</label>
                    <div class="d-flex flex-wrap gap-3 mb-3" id="preview_${safeId}"><div class="text-muted small py-4 text-center w-100 border rounded-4 bg-light">No photos yet</div></div>
                    <button type="button" class="btn btn-sm btn-dark rounded-3 px-3" onclick="document.getElementById('input_${safeId}').click()"><i class="bi bi-cloud-upload me-2"></i>Upload Photos</button>
                    <input type="file" id="input_${safeId}" class="d-none" multiple onchange="handleFileSelect(this, '${color.replace(/'/g, "\\'")}')">
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


// ── AI Generate Description ────────────────────────────────────
document.getElementById('generateDescBtn').addEventListener('click', async function () {
    const btn      = this;
    const btnText  = document.getElementById('generateBtnText');
    const textarea = document.getElementById('descriptionTextarea');
    const overlay  = document.getElementById('typingOverlay');
    const errBox   = document.getElementById('aiDescError');

    const proName  = document.querySelector('input[name="product_name"]').value.trim();
    const gender   = document.querySelector('select[name="gender"]').value;
    const ageGroup = document.querySelector('select[name="age_group"]').value;
    const brandSel = document.querySelector('select[name="brand"]');
    const brandName = brandSel ? (brandSel.options[brandSel.selectedIndex]?.text ?? '') : '';
    const catSel   = document.getElementById('category_select');
    const newCat   = document.getElementById('new_category_input').value.trim();
    const catName  = newCat || (catSel ? (catSel.options[catSel.selectedIndex]?.text ?? '') : '');

    if (!proName) {
        errBox.textContent = 'Please enter a product name first.';
        errBox.style.display = 'block';
        return;
    }

    const confirm = await Swal.fire({
        title: 'Confirm Details',
        html: `
            <p style="color:#64748b; font-size:14px; margin-bottom:16px;">
                Please confirm these details are correct before generating the description.
            </p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <div style="background:#fff4e6; border:1px solid #ffe0b2; border-radius:12px; padding:14px 24px; text-align:center; min-width:110px;">
                    <div style="font-size:11px; color:#aaa; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Gender</div>
                    <div style="font-size:18px; font-weight:700; color:#FF8C00;">${gender}</div>
                </div>
                <div style="background:#fff4e6; border:1px solid #ffe0b2; border-radius:12px; padding:14px 24px; text-align:center; min-width:110px;">
                    <div style="font-size:11px; color:#aaa; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Age Group</div>
                    <div style="font-size:18px; font-weight:700; color:#FF8C00;">${ageGroup}</div>
                </div>
            </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#FF8C00',
        cancelButtonColor: '#e2e8f0',
        confirmButtonText: '✓ Yes, Generate',
        cancelButtonText: 'Go back & edit',
        customClass: { cancelButton: 'text-dark' }
    });

    if (!confirm.isConfirmed) return;

    errBox.style.display = 'none';
    btn.disabled = true;
    btnText.textContent = 'Generating...';
    overlay.style.display = 'flex';
    textarea.value = '';

    try {
        const fd = new FormData();
        fd.append('action',    'generate_desc');
        fd.append('pro_name',  proName);
        fd.append('brand',     brandName);
        fd.append('category',  catName);
        fd.append('gender',    gender);
        fd.append('age_group', ageGroup);

        const res  = await fetch('edit_product.php', { method: 'POST', body: fd });
        const data = await res.json();
        overlay.style.display = 'none';

        if (data.error) {
            errBox.textContent = data.error;
            errBox.style.display = 'block';
        } else {
            const text = data.description;
            textarea.value = '';
            let i = 0;
            const timer = setInterval(() => {
                if (i < text.length) { textarea.value += text[i++]; }
                else { clearInterval(timer); }
            }, 18);
        }
    } catch (err) {
        overlay.style.display = 'none';
        errBox.textContent = 'Network error. Please try again.';
        errBox.style.display = 'block';
    } finally {
        btn.disabled = false;
        btnText.textContent = 'Generate with AI';
    }
});

// ── 照片限制 4 张（替换原有的 handleFileSelect）────────────────
function handleFileSelect(input, color) {
    const safeId     = color.replace(/\s+/g, '_');
    const preview    = document.getElementById(`preview_${safeId}`);
    const finalInput = document.getElementById(`final_input_${safeId}`);
    const MAX_PHOTOS = 4;

    // 已有的预览图数量（包含旧图 + 新上传）
    const existing = preview.querySelectorAll('.preview-item').length;
    const slots    = MAX_PHOTOS - existing;

    if (slots <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Photo Limit Reached',
            text: `Each color can only have a maximum of ${MAX_PHOTOS} photos.`,
            confirmButtonColor: '#FF8C00'
        });
        input.value = '';
        return;
    }

    const files    = Array.from(input.files);
    const allowed  = files.slice(0, slots);
    const rejected = files.length - allowed.length;

    if (rejected > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Too Many Photos',
            text: `Only ${slots} more photo(s) allowed for "${color}". ${rejected} file(s) were ignored.`,
            confirmButtonColor: '#FF8C00'
        });
    }

    if (colorFilesManager[color].items.length === 0 && existing === 0) preview.innerHTML = '';

    allowed.forEach(file => {
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
    input.value = '';
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
    // 注意这里把 const 改成了 let，因为我们要重新赋值
    let val = input.value.trim(); 

    if (val) {
        // --- 新增功能：自动将每个单词首字母大写，其余小写 ---
        // 过程：先全部转小写 (virus green / VIRUS GREEN -> virus green)
        // 然后利用正则 \b\w 找到每个单词的首字母并转为大写 -> Virus Green
        val = val.toLowerCase().replace(/\b\w/g, function(char) {
            return char.toUpperCase();
        });
        // ----------------------------------------------------

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

    // 2. Description 验证（必须填写）
    if (description === "") {
        e.preventDefault();
        Swal.fire({
            title: 'Description Required',
            text: 'Please fill in the product description, or use the AI generator to create one.',
            icon: 'warning',
            confirmButtonColor: '#FF8C00',
            confirmButtonText: 'OK'
        }).then(() => {
            const ta = form.querySelector('textarea[name="description"]');
            if (ta) ta.focus();
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

// --- 修正版：编辑模式下的自动初始化 ---
window.addEventListener('load', function() {
    <?php if ($is_edit): ?>
        // 1. 获取从 PHP 正则拆分后的颜色数组
        const existingColors = <?php echo json_encode($existing_colors); ?>;
        const stockData = <?php echo json_encode($existing_stock); ?>;
        
        existingColors.forEach(color => {
            if (!color) return;

            // 检查是否是预设颜色
            const checkbox = document.getElementById('c_' + color);
            
            if (checkbox) {
                // 勾选 UI 上的 Checkbox
                checkbox.checked = true;
            } else {
                // 如果是自定义颜色，先手动添加到 UI 列表
                const id = 'c_' + color.replace(/\s+/g, '_');
                const wrapper = document.querySelector('.d-flex.flex-wrap.gap-2');
                wrapper.insertAdjacentHTML('beforeend', 
                    `<input type="checkbox" name="selected_colors[]" class="btn-check color-selector" id="${id}" value="${color}" checked>` +
                    `<label class="btn btn-outline-orange" for="${id}">${color}</label>`
                );
            }

            // 【核心修正】：直接调用你原有的函数生成变体框，不依赖模拟点击
            // 移除可能存在的 empty-hint
            const hint = document.getElementById('empty-hint');
            if(hint) hint.remove();
            
            // 执行你代码中定义的函数
            addVariantBox(color);
        });

        // --- 最终修正：确保 Stock Quantity 正确填充 ---
        setTimeout(() => {
            const stockData = <?php echo json_encode($existing_stock); ?>;
            
            for (const color in stockData) {
                const safeId = color.replace(/\s+/g, '_');
                
                for (const size in stockData[color]) {
                    // 1. 统一格式，例如 "4.0"
                    const formattedSize = parseFloat(size).toFixed(1);
                    const sizeId = `s_${safeId}_${formattedSize}`;
                    const sizeCb = document.getElementById(sizeId);
                    
                    if (sizeCb) {
                        // 2. 勾选尺寸
                        sizeCb.checked = true;
                        
                        // 3. 强制触发 change 事件来执行你原有的库存输入框生成逻辑
                        const event = new Event('change', { bubbles: true });
                        sizeCb.dispatchEvent(event);
                        
                        // 4. 【关键】：在当前事件循环之后立即填值
                        // 使用 requestAnimationFrame 或 setTimeout(..., 0) 确保输入框已渲染
                        (function(c, s, val) {
                            setTimeout(() => {
                                // 这里的选择器必须匹配你 HTML 逻辑生成的 name 格式
                                const stockInput = document.querySelector(`input[name="stock[${c}][${s}]"]`);
                                if (stockInput) {
                                    stockInput.value = val;
                                    // 打印调试（可选）：console.log(`Filled ${c} size ${s} with ${val}`);
                                }
                            }, 50); // 给浏览器 50ms 时间渲染 HTML 结构
                        })(color, formattedSize, stockData[color][size]);
                    }
                }
            }
        }, 500);

        
setTimeout(() => {
    const imageData = <?php echo json_encode($existing_images); ?>;

    for (const color in imageData) {
        const safeId = color.replace(/\s+/g, '_');
        const previewContainer = document.getElementById(`preview_${safeId}`);
        if (!previewContainer) continue;

        const emptyHint = previewContainer.querySelector('.text-muted');
        if (emptyHint) emptyHint.remove();

        imageData[color].forEach((imgPath) => {
            const basename = imgPath.split('/').pop(); // e.g. "nikeDunk_black_1.jpg"

            // 默认加一个 hidden input 代表"保留这张"
            const keepInput = document.createElement('input');
            keepInput.type  = 'hidden';
            keepInput.name  = `keep_photos[${color}][]`;
            keepInput.value = basename;
            keepInput.id    = `keep_${safeId}_${basename}`;
            document.getElementById('keepPhotosContainer').appendChild(keepInput);

            const div = document.createElement('div');
            div.className = 'preview-item';
            div.onclick = function() { openLightbox(color, div); };

            const imgEl = document.createElement('img');
            imgEl.src = imgPath;
            const btnEl = document.createElement('button');
            btnEl.type = 'button';
            btnEl.className = 'remove-img';
            btnEl.textContent = '×';
            btnEl.addEventListener('click', function(e) {
                e.stopPropagation();
                removeOldPhoto(color, basename, this);
            });

            div.appendChild(imgEl);
            div.appendChild(btnEl);
            previewContainer.appendChild(div);
        });
    }
}, 800);

// ── 删除旧图：移除 DOM + 移除 hidden input（让后端知道不保留这张）──
function removeOldPhoto(color, basename, btn) {
    // 1. 移除预览 DOM
    btn.parentNode.remove();

    // 2. 移除对应的 hidden keep input
    const safeId    = color.replace(/\s+/g, '_');
    const keepInput = document.getElementById(`keep_${safeId}_${basename}`);
    if (keepInput) keepInput.remove();
}




    <?php endif; ?>
});

<?php if (isset($_SESSION['swal_status'])): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const status = "<?php echo $_SESSION['swal_status']; ?>";
        
        if (status === 'success') {
            Swal.fire({
                title: 'Successful',
                text: 'Product updated successfully!',
                icon: 'success',
                confirmButtonColor: '#FF8C00',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../Module C/admin_manage_products.php';
            });
        } else if (status === 'failed') {
            Swal.fire({
                title: 'Failed',
                text: 'Something went wrong. Please try again.',
                icon: 'error',
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'OK'
            }).then((result) => {
                window.location.href = '../Module C/Product.php';
            });
        } else if (status === 'desc_required') {
            Swal.fire({
                title: 'Description Required',
                text: 'Product description is required. Please fill it before saving.',
                icon: 'warning',
                confirmButtonColor: '#FF8C00',
                confirmButtonText: 'OK'
            }).then(() => {
                const ta = document.querySelector('textarea[name="description"]');
                if (ta) { ta.focus(); window.scrollTo({ top: ta.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' }); }
            });
        }
    });
<?php 
    // 触发完后清除 Session 防止刷新时重复弹窗
    unset($_SESSION['swal_status']); 
endif; 
?>
</script>
</body>
</html>