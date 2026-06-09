<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

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

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['username'] ?? 'Admin';

$sql    = "SELECT * FROM admin WHERE Admin_Id = $admin_id";
$result = $conn->query($sql);
$admin  = $result->fetch_assoc();

$admin_image = !empty($admin['Admin_Image'])
    ? $admin['Admin_Image']
    : ($_SESSION['admin_image'] ?? 'default_admin.png');

// ── 处理 Logo 上传与 AI 背景生成（使用 gemini-3.1-flash-image generateContent） ──
if (isset($_POST['action']) && $_POST['action'] === 'generate_logo_bg') {
    header('Content-Type: application/json');

    $brand_id   = intval($_POST['brand_id'] ?? 0);
    $brand_name = trim($_POST['brand_name'] ?? '');

    if (!$brand_id || !$brand_name) {
        echo json_encode(['error' => 'Brand info missing.']); exit();
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if (empty($apiKey)) {
        echo json_encode(['error' => 'API key not found.']); exit();
    }

    $brand_logo_path_db = "";

    // Ensure upload dir exists
    $save_dir = __DIR__ . '/../images/brands/';
    if (!is_dir($save_dir) && !mkdir($save_dir, 0777, true) && !is_dir($save_dir)) {
        echo json_encode(['error' => 'Unable to create upload directory.']);
        exit();
    }

    // ── 开始替换的部分 ──
    
    // 1. 接收前端传过来的 Base64 图片数据
    $logo_base64 = $_POST['logo_base64'] ?? '';
    $logo_mime   = $_POST['logo_mime'] ?? 'image/png';

    if (empty($logo_base64)) {
        echo json_encode(['error' => 'No logo file uploaded.']);
        exit();
    }

    $logo_decoded = base64_decode($logo_base64, true);
    if ($logo_decoded === false) {
        echo json_encode(['error' => 'Invalid logo base64 data.']);
        exit();
    }

    $brand_name_sanitized = str_replace(array(' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'), '_', $brand_name);
    
    // 2. 将 Base64 保存为实体的原图文件
    $ext = ($logo_mime === 'image/jpeg') ? 'jpg' : 'png';
    $brand_original_filename = $brand_name_sanitized . "_original." . $ext;
    $brand_original_path = $save_dir . $brand_original_filename;
    
    if (file_put_contents($brand_original_path, $logo_decoded) === false) {
        echo json_encode(['error' => 'Unable to save logo file.']);
        exit();
    }

    // 3. 构建专业的 AI Prompt
$prompt = "A high-end cinematic sports brand product photography banner (16:9). 
CORE ELEMENTS (FIXED): A pair of premium athletic sneakers prominently displayed in the center, occupying at least 60% of the frame. The brand logo must be integrated as a 3D matte-white embossed element on the shoe or on the central display surface, ensuring it is clear, sharp, and perfectly legible.
ENVIRONMENT (RANDOMIZED): Generate a unique and professional commercial background each time. Choose one of the following distinct settings:
- A soft, moody studio setting with dark silk fabric drapes and cinematic spotlighting.
- A clean, minimalist 'infinite' dark void background with sharp, clean rim lighting that traces the sneaker silhouette.
- An abstract product display surface featuring high-end materials like textured leather and carbon fiber, with soft, diffused professional studio light.
- A sleek, modern podium setup in a dark, atmospheric space with subtle glowing edges.
STYLE: Ultra-realistic, 8k resolution, professional advertising quality, deep color grading, dynamic depth of field with the sneakers in sharp focus and background softly blurred.";

    // 4. 构建发给 Gemini 的请求（直接使用前端传来的 Base64 数据）
    $request_data = [
        "contents" => [[
            "parts" => [
                ["text" => $prompt],
                ["inlineData" => [
                    "mimeType" => $logo_mime,
                    "data" => $logo_base64
                ]]
            ]
        ]]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent?key=" . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $err = curl_error($ch); curl_close($ch);
        echo json_encode(['error' => 'Curl error: ' . $err]); exit();
    }
    curl_close($ch);

    $decoded_result = json_decode($result, true);
    if ($decoded_result === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Invalid API response: ' . json_last_error_msg()]);
        exit();
    }

    // 5. 解析 AI 返回的图片
    $generated_image_data = null;
    if (isset($decoded_result['candidates'][0]['content']['parts'])) {
        foreach ($decoded_result['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                $generated_image_data = base64_decode($part['inlineData']['data']);
                break;
            }
        }
    }

    if ($generated_image_data) {
        $brand_generated_filename = $brand_name_sanitized . ".png";
        $brand_generated_path = $save_dir . $brand_generated_filename;
        
        if (file_put_contents($brand_generated_path, $generated_image_data) === false) {
            echo json_encode(['error' => 'Unable to save generated image.']);
            exit();
        }
        
        $web_path = '../images/brands/' . $brand_generated_filename; // used for JSON response
        $brand_logo_db_filename = $brand_generated_filename; // store only filename in DB
    } else {
        // 如果 AI 生成失败，降级使用保存的原图
        $web_path = '../images/brands/' . $brand_original_filename;
        $brand_logo_db_filename = $brand_original_filename; // store original filename in DB
    }

    // ── 替换到此结束 ──

    // Update DB if we have a path
    if (!empty($brand_logo_db_filename)) {
        $esc_filename = $conn->real_escape_string($brand_logo_db_filename);
        $conn->query("UPDATE brand SET Brand_Logo = '$esc_filename' WHERE Brand_Id = $brand_id");
        echo json_encode(['success' => true, 'img_url' => $web_path . '?t=' . time()]);
        exit();
    }
}

// ── 处理 Profile 更新 ──────────────────────────────────────────
$message = "";
if (isset($_POST['update_profile'])) {
    $new_name  = $conn->real_escape_string($_POST['admin_name']);
    $new_email = $conn->real_escape_string($_POST['admin_email']);
    $image_name = $admin['Admin_Image'];

    if (!empty($_FILES['admin_pic']['name'])) {
        $upload_dir = "../uploads/admin/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $image_name = str_replace(' ', '', $new_name) . ".jpg";
        $target = $upload_dir . $image_name;
        if (move_uploaded_file($_FILES['admin_pic']['tmp_name'], $target)) {
            $_SESSION['admin_image'] = $image_name;
        }
    }

    $update_sql = "UPDATE admin SET Admin_Name='$new_name', Admin_Email='$new_email', Admin_Image='$image_name' WHERE Admin_Id=$admin_id";
    if ($conn->query($update_sql)) {
        $_SESSION['username']    = $new_name;
        $_SESSION['admin_image'] = $image_name;
        header("Location: admin_profile.php?updated=1");
        exit();
    }
}

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = "Profile updated successfully!";
}

// ── Stats ──────────────────────────────────────────────────────
$admin_level = $admin['Admin_Level'];
if ($admin_level == 3) {
    $orders_res   = $conn->query("SELECT COUNT(DISTINCT o.Order_Id) as c FROM `order` o WHERE EXISTS (SELECT 1 FROM order_detail od JOIN product p ON od.Pro_Id = p.Pro_Id JOIN brand b ON p.Brand_Id = b.Brand_Id WHERE od.Order_Id = o.Order_Id AND b.Admin_Id = $admin_id)");
    $orders_count = $orders_res ? $orders_res->fetch_assoc()['c'] : 0;
    $products_res = $conn->query("SELECT COUNT(*) as c FROM product p JOIN brand b ON p.Brand_Id = b.Brand_Id WHERE b.Admin_Id = $admin_id");
    $products_count = $products_res ? $products_res->fetch_assoc()['c'] : 0;
} else {
    $orders_count = $products_count = null;
}

$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($admin['Admin_Name'])))));
$initials = substr($initials, 0, 2);

// ── 获取 Brand 信息 (Level 3 才有) ────────────────────────────
$brand_info = null;
if ($admin_level == 3) {
    $b_res = $conn->query("SELECT * FROM brand WHERE Admin_Id = $admin_id LIMIT 1");
    if ($b_res) $brand_info = $b_res->fetch_assoc();
}

// 决定 Banner 显示方式 — 支持数据库存放完整路径或仅存文件名两种情况
$brand_logo_web = '';
$has_brand_logo = false;
if ($brand_info && !empty($brand_info['Brand_Logo'])) {
    $raw = $brand_info['Brand_Logo'];
    // 如果已经存的是带路径的值（包含 / 或 images/ 前缀），优先使用它
    if (strpos($raw, '/') !== false) {
        $candidate = __DIR__ . '/../' . ltrim($raw, './');
        if (file_exists($candidate)) {
            $brand_logo_web = $raw;
            $has_brand_logo = true;
        }
    } else {
        // 仅文件名，尝试 images/brands/ 下的文件
        $candidate = __DIR__ . '/../images/brands/' . $raw;
        if (file_exists($candidate)) {
            $brand_logo_web = '../images/brands/' . $raw;
            $has_brand_logo = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile | Online Sports Shoes Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --orange-primary: #FF8C00; --sidebar-width: 260px; }
        body { background-color: #f4f5f7; margin: 0; font-family: 'Segoe UI', 'Inter', sans-serif; }
        .wrapper { display: flex; }
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; }

        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--orange-primary); object-fit: cover; }

        .profile-card { background: #fff; border-radius: 20px; overflow: hidden; border: 1px solid #ebebeb; }

        /* ── Banner ── */
        .profile-banner {
            height: 140px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center;
        }
        /* 橙色默认 */
        .profile-banner.banner-orange {
            background: linear-gradient(135deg, #FF8C00 0%, #FF5E00 60%, #e04800 100%);
            padding: 32px 32px 64px;
        }
        .profile-banner.banner-orange::before {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .profile-banner.banner-orange::after {
            content: ''; position: absolute; bottom: -20px; left: 30%;
            width: 120px; height: 120px; border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        /* AI 生成图 Banner */
        .profile-banner.banner-ai {
            background: #111; padding: 0;
        }
        .profile-banner.banner-ai img.banner-bg {
            width: 100%; height: 100%; object-fit: cover;
            position: absolute; inset: 0;
        }
        /* 生成按钮浮层 */
        .banner-gen-btn {
            position: absolute; bottom: 10px; right: 12px; z-index: 10;
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(0,0,0,0.55); border: 1px solid rgba(255,255,255,0.3);
            color: #fff; font-size: 11px; font-weight: 500;
            padding: 5px 12px; border-radius: 20px; cursor: pointer;
            backdrop-filter: blur(6px); transition: background 0.2s;
        }
        .banner-gen-btn:hover { background: rgba(255,140,0,0.75); }
        .banner-gen-btn i { font-size: 13px; }

        .banner-title { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.7); letter-spacing: 0.5px; margin-bottom: 4px; position: relative; z-index: 1; }
        .banner-sub   { font-size: 22px; font-weight: 600; color: #fff; position: relative; z-index: 1; }

        /* Avatar */
        .overlap-row { padding: 0 32px; margin-top: -44px; margin-bottom: 24px; display: flex; align-items: flex-end; justify-content: space-between; }
        .avatar-wrap { position: relative; }
        .avatar-box { width: 88px; height: 88px; border-radius: 18px; background: linear-gradient(135deg, #FF8C00, #FF5E00); border: 4px solid #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 600; color: #fff; box-shadow: 0 4px 16px rgba(255,100,0,0.25); object-fit: cover; overflow: hidden; }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
        .cam-btn { position: absolute; bottom: -4px; right: -4px; width: 28px; height: 28px; border-radius: 50%; background: #FF8C00; border: 2px solid #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        .cam-btn i { font-size: 12px; }
        .super-badge { display: inline-flex; align-items: center; gap: 5px; background: #fff4e6; border: 1px solid #ffe0b2; color: #e65c00; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 20px; }

        /* Identity */
        .identity-block { padding: 0 32px 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 24px; }
        .identity-name  { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 2px; }
        .identity-email { font-size: 13px; color: #999; }

        /* Stats */
        .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; padding: 0 32px 24px; }
        .stat-card { background: #fff8f0; border: 1px solid #ffe5c2; border-radius: 14px; padding: 14px 12px; text-align: center; }
        .stat-val  { font-size: 20px; font-weight: 600; color: #FF8C00; margin-bottom: 3px; }
        .stat-lbl  { font-size: 11px; color: #bbb; letter-spacing: 0.3px; }

        .success-alert { display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font-size: 13px; padding: 10px 16px; border-radius: 12px; margin: 0 32px 24px; }

        /* Form */
        .form-wrap     { padding: 0 32px; }
        .section-label { font-size: 11px; font-weight: 600; color: #bbb; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px; }
        .form-grid     { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid label { display: block; font-size: 12px; font-weight: 600; color: #888; margin-bottom: 7px; }
        .form-grid input[type="text"], .form-grid input[type="email"] { width: 100%; padding: 10px 14px; background: #fafafa; border: 1px solid #e8e8e8; border-radius: 10px; color: #1a1a1a; font-size: 14px; outline: none; transition: border-color 0.2s, background 0.2s; font-family: inherit; }
        .form-grid input:focus { border-color: #FF8C00; background: #fff; box-shadow: 0 0 0 3px rgba(255,140,0,0.1); }
        .form-grid input[readonly] { color: #aaa; background: #f5f5f5; cursor: default; }
        .brand-pill { display: inline-flex; align-items: center; gap: 7px; background: #fff4e6; border: 1px solid #ffe0b2; color: #e65c00; padding: 10px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; width: 100%; }

        /* Footer */
        .card-footer-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 32px 28px; margin-top: 24px; border-top: 1px solid #f0f0f0; }
        .footer-hint { font-size: 12px; color: #bbb; display: flex; align-items: center; gap: 5px; }
        .footer-hint i { font-size: 14px; color: #FF8C00; }
        .footer-hint b { color: #888; font-weight: 500; }
        .btn-save { display: inline-flex; align-items: center; gap: 8px; background: transparent; border: 1.5px solid #FF8C00; color: #FF8C00; padding: 10px 28px; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit; transition: background 0.2s, color 0.2s, transform 0.1s; }
        .btn-save:hover  { background: #fff4e6; color: #e65c00; }
        .btn-save:active { transform: scale(0.97); }

        /* Logo upload preview */
        .logo-upload-zone { border: 2px dashed #ffe0b2; border-radius: 12px; padding: 16px; text-align: center; cursor: pointer; transition: border-color 0.2s; background: #fffbf5; }
        .logo-upload-zone:hover { border-color: #FF8C00; }
        .logo-upload-zone img { max-height: 60px; object-fit: contain; }

        /* AI Generate Logo Background Button */
        .btn-ai-generate {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; margin-top: 10px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border: 1px solid rgba(255,140,0,0.4);
            color: #fff; font-size: 13px; font-weight: 500;
            padding: 10px 18px; border-radius: 10px; cursor: pointer;
            font-family: inherit; transition: all 0.25s; letter-spacing: 0.3px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            position: relative; overflow: hidden;
        }
        .btn-ai-generate::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,140,0,0.15), transparent);
            opacity: 0; transition: opacity 0.25s;
        }
        .btn-ai-generate:hover { border-color: #FF8C00; box-shadow: 0 4px 16px rgba(255,140,0,0.25); transform: translateY(-1px); }
        .btn-ai-generate:hover::before { opacity: 1; }
        .btn-ai-generate:active { transform: translateY(0) scale(0.98); }
        .btn-ai-generate i { font-size: 15px; color: #FF8C00; }
        .btn-ai-generate .ai-label { background: linear-gradient(90deg, #FF8C00, #ffcc70); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 600; }
        .btn-ai-generate:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">

        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                        <li class="breadcrumb-item active">Account Settings</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Admin Profile</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3">
                    <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
                    <small class="text-muted"><?php 
                        if($admin_role == 1) echo 'Super Admin';
                        elseif($admin_role == 2) echo 'Admin';
                        else echo 'Brand Manager'; 
                    ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="profile-card">

            <!-- ── Banner ── -->
            <?php if ($has_brand_logo): ?>
            <div class="profile-banner banner-ai" id="profileBanner">
                <img src="<?php echo htmlspecialchars($brand_logo_web); ?>?t=<?php echo time(); ?>"
                     class="banner-bg" id="bannerBgImg" alt="Brand Banner">
                <button type="button" class="banner-gen-btn" id="genLogoBtn">
                    <i class="bi bi-stars"></i> Regenerate Banner
                </button>
            </div>
            <?php else: ?>
            <div class="profile-banner banner-orange" id="profileBanner">

                <?php if ($admin_level == 3 && $brand_info): ?>
                <button type="button" class="banner-gen-btn" id="genLogoBtn">
                    <i class="bi bi-stars"></i> Generate Brand Banner
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">

                <!-- Avatar overlap -->
                <div class="overlap-row">
                    <div class="avatar-wrap">
                        <div class="avatar-box" id="avatarBox">
                            <?php if (!empty($admin['Admin_Image']) && $admin['Admin_Image'] !== 'default_admin.png'): ?>
                                <img src="../uploads/admin/<?php echo $admin['Admin_Image']; ?>?t=<?php echo time(); ?>" id="preview">
                            <?php else: ?>
                                <span id="initials-text"><?php echo htmlspecialchars($initials); ?></span>
                                <img id="preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <label for="admin_pic" class="cam-btn">
                            <i class="bi bi-camera-fill"></i>
                        </label>
                        <input type="file" name="admin_pic" id="admin_pic" hidden accept="image/*" onchange="previewImg(this)">
                    </div>
                    <div class="super-badge">
                        <i class="bi bi-shield-check"></i>
                        <small class="text-muted"><?php 
                        if($admin_role == 1) echo 'Super Admin';
                        elseif($admin_role == 2) echo 'Admin';
                        else echo 'Brand Manager'; 
                    ?></small>
                    </div>
                </div>

                <!-- Identity -->
                <div class="identity-block">
                    <div class="identity-name"><?php echo htmlspecialchars($admin['Admin_Name']); ?></div>
                    <div class="identity-email"><?php echo htmlspecialchars($admin['Admin_Email']); ?></div>
                </div>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-val"><?php echo $orders_count !== null ? $orders_count : 'ALL'; ?></div>
                        <div class="stat-lbl">Orders managed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val"><?php echo !empty($admin['Admin_Brand']) ? htmlspecialchars($admin['Admin_Brand']) : 'ALL'; ?></div>
                        <div class="stat-lbl">Brand access</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val"><?php echo $products_count !== null ? $products_count : 'ALL'; ?></div>
                        <div class="stat-lbl">Products managed</div>
                    </div>
                </div>

                <!-- Success alert -->
                <?php if ($message): ?>
                <div class="success-alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="form-wrap">
                    <div class="section-label">Edit Information</div>
                    <div class="form-grid">
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="admin_name" value="<?php echo htmlspecialchars($admin['Admin_Name']); ?>" required>
                        </div>
                        <div>
                            <label>Email Address</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" required>
                        </div>
                        <div>
                            <label>Assigned Brand</label>
                            <div class="brand-pill">
                                <i class="bi bi-tag-fill"></i>
                                <?php echo !empty($admin['Admin_Brand']) ? htmlspecialchars($admin['Admin_Brand']) : 'ALL ACCESS'; ?>
                            </div>
                        </div>
                        <div>
                            <label>Account Level</label>
                            <input type="text" value="Level <?php echo $admin['Admin_Level']; ?> — <?php echo ($admin['Admin_Level'] == 1) ? 'Super Admin' : 'Manager'; ?>" readonly>
                        </div>

                        <?php if ($admin_level == 3 && $brand_info): ?>
                        <!-- Logo upload for AI generation -->
                        <div class="col-span-2" style="grid-column: span 2;">
                            <label>Brand Logo <span style="color:#bbb; font-weight:400;">(Upload to generate banner)</span></label>
                            <div class="logo-upload-zone" onclick="document.getElementById('logoFileInput').click()">
                                  <img id="logoPreview"
                                      src="<?php echo ($has_brand_logo ? htmlspecialchars($brand_logo_web) : ''); ?>"
                                      style="<?php echo !$has_brand_logo ? 'display:none;' : ''; ?> max-height:60px;">
                                <p id="logoUploadHint" style="<?php echo !empty($brand_info['Brand_Logo']) ? 'display:none;' : ''; ?> color:#bbb; margin:0; font-size:13px;">
                                    <i class="bi bi-cloud-upload me-1"></i> Click to upload brand logo
                                </p>
                            </div>
                            <input type="file" id="logoFileInput" hidden accept="image/*" onchange="previewLogo(this)">
                            <button type="button" class="btn-ai-generate" id="genLogoBtn2">
                                <i class="bi bi-stars"></i>
                                <span><span class="ai-label">AI Generate</span> Logo Background</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer-bar">
                    <span class="footer-hint">
                        <i class="bi bi-info-circle"></i>
                        Image saved as <b><?php echo str_replace(' ', '', $admin['Admin_Name']); ?>.jpg</b>
                    </span>
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="bi bi-floppy-fill"></i>
                        Save Profile
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>

<script>
// ── Avatar preview ─────────────────────────────────────────────
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview  = document.getElementById('preview');
            const initials = document.getElementById('initials-text');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (initials) initials.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Logo preview ───────────────────────────────────────────────
let logoBase64 = '';
let logoMime   = 'image/png';

function previewLogo(input) {
    if (input.files && input.files[0]) {
        logoMime = input.files[0].type || 'image/png';
        const reader = new FileReader();
        reader.onload = e => {
            logoBase64 = e.target.result.split(',')[1]; // 去掉 data:image/...;base64,
            const img  = document.getElementById('logoPreview');
            const hint = document.getElementById('logoUploadHint');
            img.src    = e.target.result;
            img.style.display  = 'block';
            hint.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Generate Brand Banner ─────────────────────────────────────
<?php if ($admin_level == 3 && $brand_info): ?>
const BRAND_ID   = <?php echo intval($brand_info['Brand_Id']); ?>;
const BRAND_NAME = <?php echo json_encode($brand_info['Brand_Name']); ?>;

document.getElementById('genLogoBtn')?.addEventListener('click', async function () {
    await generateBrandBanner();
});
document.getElementById('genLogoBtn2')?.addEventListener('click', async function () {
    await generateBrandBanner();
});

async function generateBrandBanner() {
    // 检查是否有 logo（新上传 or 已有）
    const existingLogoUrl = <?php echo json_encode($brand_logo_web ?? ''); ?>;

    // ── button loading state ──
    const btn1 = document.getElementById('genLogoBtn');
    const btn2 = document.getElementById('genLogoBtn2');
    [btn1, btn2].forEach(b => { if (b) { b.disabled = true; b.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Generating…</span>'; } });

    if (!logoBase64 && !existingLogoUrl) {
        Swal.fire({
            icon: 'warning',
            title: 'No Logo Found',
            text: 'Please upload your brand logo first before generating a banner.',
            confirmButtonColor: '#FF8C00'
        });
        [btn1, btn2].forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<i class="bi bi-stars"></i> <span><span class="ai-label">AI Generate</span> Logo Background</span>'; } });
        return;
    }

    // 如果没有新上传但有旧 logo，需要用 fetch 把旧图转 base64
    let b64 = logoBase64;
    let mime = logoMime;

    if (!b64 && existingLogoUrl) {
        Swal.fire({ title: 'Preparing...', text: 'Loading existing logo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const resp = await fetch(existingLogoUrl);
            const blob = await resp.blob();
            mime = blob.type || 'image/png';
            b64  = await new Promise(res => {
                const r = new FileReader();
                r.onload = () => res(r.result.split(',')[1]);
                r.readAsDataURL(blob);
            });
        } catch(e) {
            Swal.fire({ icon: 'error', title: 'Failed to load logo', text: e.message, confirmButtonColor: '#FF8C00' });
            [btn1, btn2].forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<i class="bi bi-stars"></i> <span><span class="ai-label">AI Generate</span> Logo Background</span>'; } });
            return;
        }
    }

    // 显示生成中
    Swal.fire({
        title: '✨ Generating Banner...',
        html: `<p style="color:#666;font-size:14px;">AI is creating a professional brand banner for <b>${BRAND_NAME}</b>.<br>This may take 15–30 seconds.</p>`,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const fd = new FormData();
        fd.append('action',       'generate_logo_bg');
        fd.append('brand_id',     BRAND_ID);
        fd.append('brand_name',   BRAND_NAME);
        fd.append('logo_base64',  b64);
        fd.append('logo_mime',    mime);

        const res = await fetch('admin_profile.php', { method: 'POST', body: fd });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            throw new Error('Invalid server response: ' + text.substring(0, 300));
        }

        if (data.error) {
            Swal.fire({ icon: 'error', title: 'Generation Failed', text: data.error, confirmButtonColor: '#FF8C00' });
            [btn1, btn2].forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<i class="bi bi-stars"></i> <span><span class="ai-label">AI Generate</span> Logo Background</span>'; } });
            return;
        }

        // 更新 Banner 显示
        const banner = document.getElementById('profileBanner');
        banner.classList.remove('banner-orange');
        banner.classList.add('banner-ai');

        let bgImg = document.getElementById('bannerBgImg');
        if (!bgImg) {
            bgImg = document.createElement('img');
            bgImg.id        = 'bannerBgImg';
            bgImg.className = 'banner-bg';
            bgImg.alt       = 'Brand Banner';
            banner.insertBefore(bgImg, banner.firstChild);
            // 移除橙色 banner 里的文字
            banner.querySelectorAll('.banner-title, .banner-sub').forEach(el => el.remove());
        }
        bgImg.src = data.img_url;

        Swal.fire({
            icon: 'success',
            title: 'Banner Generated!',
            text: 'Your brand banner has been saved successfully.',
            confirmButtonColor: '#FF8C00',
            timer: 2500,
            showConfirmButton: false
        });
        [btn1, btn2].forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<i class="bi bi-stars"></i> <span><span class="ai-label">AI Regenerate</span> Logo Background</span>'; } });

    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: err.message, confirmButtonColor: '#FF8C00' });
        [btn1, btn2].forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<i class="bi bi-stars"></i> <span><span class="ai-label">AI Generate</span> Logo Background</span>'; } });
    }
}
<?php endif; ?>
</script>

</body>
</html>