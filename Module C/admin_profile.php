<?php
session_start();
require_once '../includes/db_connection.php';

// 1. 安全检查 - 必须是 Admin [参考 admin_manage_users.php 逻辑]
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';

// 2. 获取当前管理员资料
$sql = "SELECT * FROM admin WHERE Admin_Id = $admin_id";
$result = $conn->query($sql);
$admin = $result->fetch_assoc();

// 3. 处理更新逻辑
$message = "";
if (isset($_POST['update_profile'])) {
    $new_name = $conn->real_escape_string($_POST['admin_name']);
    $new_email = $conn->real_escape_string($_POST['admin_email']);
    
    $image_name = $admin['Admin_Image']; 

    if (!empty($_FILES['admin_pic']['name'])) {
        $upload_dir = "../uploads/admin/";
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        // 命名规则：AdminName.jpg
        $image_name = str_replace(' ', '', $new_name) . ".jpg"; 
        $target = $upload_dir . $image_name;
        
        if (move_uploaded_file($_FILES['admin_pic']['tmp_name'], $target)) {
            $_SESSION['admin_image'] = $image_name;
        }
    }

    $update_sql = "UPDATE admin SET Admin_Name='$new_name', Admin_Email='$new_email', Admin_Image='$image_name' WHERE Admin_Id=$admin_id";
    
    if ($conn->query($update_sql)) {
        $message = "Profile updated successfully!";
        // 刷新变量
        $admin['Admin_Name'] = $new_name;
        $admin['Admin_Email'] = $new_email;
        $admin['Admin_Image'] = $image_name;
        $_SESSION['username'] = $new_name;
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
    
    <style>
        /* 核心布局样式 - 与 admin_manage_users.php 保持高度一致 */
        :root { --orange-primary: #FF8C00; --sidebar-width: 260px; }
        body { background-color: #f8f9fa; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .wrapper { display: flex; }
        
        /* 主内容区域：预留侧边栏宽度 */
        .main-content { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
        }

        /* 顶部 Header 样式同步 */
        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }
        .admin-profile-img { 
            width: 42px; height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

        /* Profile 内容专用样式 */
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            border: none;
        }
        .profile-banner {
            height: 120px;
            background: linear-gradient(135deg, #FF8C00 0%, #FFA500 100%);
        }
        .profile-body {
            padding: 0 40px 40px 40px;
            margin-top: -50px;
        }
        .avatar-container {
            position: relative;
            display: inline-block;
        }
        .profile-main-img {
            width: 120px; height: 120px;
            border-radius: 20px;
            border: 5px solid white;
            background: white;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .upload-icon-label {
            position: absolute;
            bottom: -5px; right: -5px;
            background: white;
            width: 34px; height: 34px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--orange-primary);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
            cursor: pointer;
        }
        .brand-display {
            background: #fff4e6;
            color: var(--orange-primary);
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid #ffe8cc;
        }
        .btn-orange-save {
            background-color: var(--orange-primary);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: bold;
        }
        .btn-orange-save:hover { background-color: #e67e00; color: white; }
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
                    <small class="text-muted"><?php echo ($admin['Admin_Level'] == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="profile-card">
            <div class="profile-banner"></div>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="profile-body">
                    <div class="d-flex align-items-end mb-4">
                        <div class="avatar-container">
                            <img src="../uploads/admin/<?php echo !empty($admin['Admin_Image']) ? $admin['Admin_Image'] : 'default_admin.png'; ?>?t=<?php echo time(); ?>" 
                                 class="profile-main-img" id="preview">
                            <label for="admin_pic" class="upload-icon-label">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" name="admin_pic" id="admin_pic" hidden accept="image/*" onchange="previewImg(this)">
                        </div>
                        <div class="ms-4 mb-2">
                            <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($admin['Admin_Name']); ?></h3>
                            <p class="text-muted mb-0 small">Admin ID: #<?php echo $admin['Admin_Id']; ?></p>
                        </div>
                    </div>

                    <?php if($message): ?>
                        <div class="alert alert-success border-0 rounded-4 shadow-sm py-2"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <div class="row g-4 mt-2">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">FULL NAME</label>
                            <input type="text" name="admin_name" class="form-control shadow-sm border-light" value="<?php echo htmlspecialchars($admin['Admin_Name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">EMAIL ADDRESS</label>
                            <input type="email" name="admin_email" class="form-control shadow-sm border-light" value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">ASSIGNED BRAND</label>
                            <div>
                                <div class="brand-display">
                                    <i class="bi bi-tag-fill me-2"></i>
                                    <?php echo !empty($admin['Admin_Brand']) ? $admin['Admin_Brand'] : 'ALL ACCESS'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">ACCOUNT LEVEL</label>
                            <input type="text" class="form-control bg-light border-0 shadow-none" value="Level <?php echo $admin['Admin_Level']; ?>" readonly>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-top d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i> Image will be renamed to <b><?php echo str_replace(' ', '', $admin['Admin_Name']); ?>.jpg</b>
                        </span>
                        <button type="submit" name="update_profile" class="btn btn-orange-save shadow-sm">
                            Save Profile
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 实时预览上传图片
function previewImg(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>