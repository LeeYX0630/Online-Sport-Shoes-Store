<?php
// admin/add_admin.php
session_start();
require_once '../includes/db_connection.php';

// 1. 权限检查
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>alert('Permission Denied.'); window.location.href='admin_dashboard.php';</script>";
    exit();
}

// 获取 Header 所需的管理员信息[cite: 1]
$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';

$msg = "";
$real_name = "";
$brand_name = "";
$email_prefix = "";
$admin_level = "2";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $real_name = trim($_POST['real_name']);     
    $brand_name = trim($_POST['brand_name']);   
    $email_prefix = trim($_POST['email_prefix']);
    $admin_email = $email_prefix . "@sport.com";
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_level = $_POST['admin_level'];

    if (empty($real_name) || empty($email_prefix) || empty($password)) {
        $msg = "Error: All fields are required.";
    } 
    elseif ($admin_level == "2" && empty($brand_name)) {
        $msg = "Error: Brand Name is required for Normal Admin.";
    }
    elseif ($password !== $confirm_password) {
        $msg = "Error: Passwords do not match.";
    } 
    else {
        if (preg_match('/[0-9]/', $real_name) || preg_match('/[^A-Za-z\s]/', $real_name)) {
            $msg = "Error: Admin Name cannot contain numbers or symbols.";
        } else {
            $can_proceed = true;
            if (!empty($brand_name)) {
                $check_brand = $conn->prepare("SELECT Brand_Id FROM brand WHERE Brand_Name = ?");
                $check_brand->bind_param("s", $brand_name);
                $check_brand->execute();
                if ($check_brand->get_result()->num_rows > 0) {
                    $msg = "Error: The brand '$brand_name' already exists.";
                    $can_proceed = false;
                }
            }

            if ($can_proceed) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $conn->begin_transaction();
                try {
                    $stmt_admin = $conn->prepare("INSERT INTO admin (Admin_Name, Admin_Email, Admin_Password, Admin_Level, Admin_Status) VALUES (?, ?, ?, ?, 'Active')");
                    $stmt_admin->bind_param("ssss", $real_name, $admin_email, $hashed_password, $admin_level);
                    $stmt_admin->execute();
                    
                    $new_admin_id = $conn->insert_id;

                    if (!empty($brand_name)) {
                        $stmt_brand = $conn->prepare("INSERT INTO brand (Brand_Name, Admin_Id) VALUES (?, ?)");
                        $stmt_brand->bind_param("si", $brand_name, $new_admin_id);
                        $stmt_brand->execute();
                    }

                    $conn->commit();
                    echo "<script>alert('Admin created successfully!'); window.location.href='admin_manage_admins.php';</script>";
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $msg = "Database Error: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin | Sport Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { 
            --sidebar-width: 260px; 
            --primary-orange: #FF6B00; 
            --orange-primary: #FF8C00; 
        }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
        .main-wrapper { margin-left: var(--sidebar-width); min-height: 100vh; width: calc(100% - var(--sidebar-width)); padding: 25px; }
        
        /* Header 样式[cite: 1] */
        .admin-header { 
            background: white; 
            padding: 15px 30px; 
            border-radius: 15px; 
            margin-bottom: 20px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
        }

        .admin-profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 50%; 
            border: 2px solid var(--orange-primary); 
            object-fit: cover; 
        }

        /* Header 下方的返回按钮容器[cite: 2] */
        .back-button-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            padding: 0 10px;
        }

        .btn-back-header {
            text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
            border-radius: 10px; transition: all 0.2s; border: 1px solid #e2e8f0;
            background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .btn-back-header:hover { 
            background-color: #fff; 
            color: var(--primary-orange); 
            border-color: var(--primary-orange);
            transform: translateX(-3px);
        }

        .form-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #475569; margin-bottom: 8px; }
        .input-group-text { background-color: #f1f5f9; color: #64748b; font-weight: 600; border-radius: 0 10px 10px 0; border: 1px solid #e2e8f0; }
        .form-control, .form-select { border-radius: 10px; padding: 12px; border: 1px solid #e2e8f0; }
        .form-control:focus { border-color: var(--primary-orange); box-shadow: none; }
        
        .strength-meter { height: 4px; width: 100%; background-color: #e2e8f0; margin-top: 10px; border-radius: 2px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0; transition: 0.3s; }
        .strength-weak { background-color: #ef4444; }
        .strength-medium { background-color: #f59e0b; }
        .strength-strong { background-color: #10b981; }
        
        .password-hints { font-size: 12px; color: #94a3b8; margin-top: 5px; }

        .btn-submit { background-color: var(--primary-orange); color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; width: 100%; margin-top: 20px; transition: 0.2s; }
        .btn-submit:hover { background-color: #e66000; }

        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 15px; } }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- 1. Header 区域[cite: 1] -->
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add New Admin</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Add New Admin</h4>
            </div>

            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                    <small class="text-muted"><?php echo ($admin_role == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <!-- 2. Header 下方的 Back 按钮区域[cite: 2] -->
        <div class="back-button-container">
            <a href="admin_manage_admins.php" class="btn-back-header">
                <i class="bi bi-arrow-left"></i> Back to Admin List
            </a>
        </div>

        <!-- 3. 表单内容区域[cite: 2] -->
        <div class="container-fluid">
            <div class="form-container">
                <?php if($msg): ?>
                    <div class="alert alert-danger"><?php echo $msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-4">
                        <!-- 表单字段保持不变... -->
                        <div class="col-12">
                            <label class="form-label">Admin Name</label>
                            <input type="text" name="real_name" class="form-control" 
                                   placeholder="Enter full name" 
                                   value="<?php echo htmlspecialchars($real_name); ?>"
                                   oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Brand Name</label>
                            <input type="text" name="brand_name" class="form-control" 
                                   placeholder="e.g. Nike" 
                                   value="<?php echo htmlspecialchars($brand_name); ?>" >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Admin Level</label>
                            <select name="admin_level" class="form-select" required>
                                <option value="2" <?php echo ($admin_level == "2") ? "selected" : ""; ?>>Normal Admin</option>
                                <option value="1" <?php echo ($admin_level == "1") ? "selected" : ""; ?>>Super Admin</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <input type="text" name="email_prefix" class="form-control" 
                                       placeholder="username" 
                                       value="<?php echo htmlspecialchars($email_prefix); ?>" required>
                                <span class="input-group-text">@sport.com</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="passwordInput" class="form-control" required>
                            <div class="strength-meter">
                                <div id="strengthBar" class="strength-bar"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit shadow-sm">Create Admin Account</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 保持原有的 JavaScript 逻辑[cite: 2]
        const passwordInput = document.getElementById('passwordInput');
        const strengthBar = document.getElementById('strengthBar');
        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let strength = 0;
            if(/\d/.test(val)) strength += 25;
            if(/[a-z]/.test(val)) strength += 25;
            if(/[A-Z]/.test(val)) strength += 25;
            if(/[^A-Za-z0-9]/.test(val)) strength += 25;
            strengthBar.style.width = strength + '%';
            strengthBar.className = 'strength-bar ' + (strength <= 25 ? 'strength-weak' : (strength <= 75 ? 'strength-medium' : 'strength-strong'));
        });
    </script>
</body>
</html>