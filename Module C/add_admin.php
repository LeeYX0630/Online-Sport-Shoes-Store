<<?php
// admin/add_admin.php
session_start();
require_once '../includes/db_connection.php';

// 1. 权限检查
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>alert('Permission Denied.'); window.location.href='admin_dashboard.php';</script>";
    exit();
}

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

    // --- 核心逻辑修改开始 ---

    // A. 基础必填：名字、邮箱前缀、密码必须填
    if (empty($real_name) || empty($email_prefix) || empty($password)) {
        $msg = "Error: All fields are required.";
    } 
    // B. 规则：只有 Normal Admin (2) 必须填 Brand，Super Admin (1) 可以空着
    elseif ($admin_level == "2" && empty($brand_name)) {
        $msg = "Error: Brand Name is required for Normal Admin.";
    }
    // C. 检查两次密码一致
    elseif ($password !== $confirm_password) {
        $msg = "Error: Passwords do not match.";
    } 
    else {
        // D. 移除了对密码复杂度的 preg_match，允许 abc
        // 仅保留对名字的基本格式检查
        if (preg_match('/[0-9]/', $real_name) || preg_match('/[^A-Za-z\s]/', $real_name)) {
            $msg = "Error: Admin Name cannot contain numbers or symbols.";
        } else {
            $can_proceed = true;
            // 如果填了品牌名，才检查是否重复
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
                // 使用 password_hash 加密
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();
                try {
                    // 插入 Admin 表 (使用 $hashed_password)
                    $stmt_admin = $conn->prepare("INSERT INTO admin (Admin_Name, Admin_Email, Admin_Password, Admin_Level, Admin_Status) VALUES (?, ?, ?, ?, 'Active')");
                    $stmt_admin->bind_param("ssss", $real_name, $admin_email, $hashed_password, $admin_level);
                    $stmt_admin->execute();
                    
                    $new_admin_id = $conn->insert_id;

                    // 如果品牌名不为空才插入（Super Admin 没填时会自动跳过）
                    if (!empty($brand_name)) {
                        $stmt_brand = $conn->prepare("INSERT INTO brand (Brand_Name, Admin_Id) VALUES (?, ?)");
                        $stmt_brand->bind_param("si", $brand_name, $new_admin_id);
                        $stmt_brand->execute();
                    }

                    $conn->commit();
                    echo "<script>alert('Admin created successfully!'); window.location.href='../Module C/admin_manage_admins.php';</script>";
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
        :root { --sidebar-width: 260px; --primary-orange: #FF6B00; }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
        .main-wrapper { margin-left: var(--sidebar-width); min-height: 100vh; width: calc(100% - var(--sidebar-width)); }
        
        .top-bar {
            padding: 18px 40px;
            background-color: #FFFFFF; border-bottom: 1px solid #edf2f7;
            position: sticky; top: 0; z-index: 100;
            display: flex; justify-content: space-between; align-items: center;
        }
        .top-bar-left h2 { margin: 0; font-size: 22px; color: #212529; font-weight: 600; }
        .top-bar-subtitle { font-size: 13px; color: #94a3b8; margin-top: 2px; }

        .btn-back-header {
            text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px;
            display: flex; align-items: center; gap: 8px; padding: 8px 16px;
            border-radius: 8px; transition: all 0.2s; border: 1px solid #e2e8f0;
        }
        .btn-back-header:hover { background-color: #f1f5f9; color: var(--primary-orange); border-color: var(--primary-orange); }

        .form-container { max-width: 800px; margin: 40px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
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
        .hint-item.met { display: none; }

        .btn-submit { background-color: var(--primary-orange); color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; width: 100%; margin-top: 20px; transition: 0.2s; }
        .btn-submit:hover { background-color: #e66000; }

        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="top-bar">
            <div class="top-bar-left">
                <h2>Add New Admin</h2>
                <div class="top-bar-subtitle">Create and configure credentials for new administrative personnel.</div>
            </div>
            <a href="../Module C/admin_manage_admins.php" class="btn-back-header">
                <i class="bi bi-arrow-left"></i> Back to Admins
            </a>
        </header>

        <div class="container-fluid">
            <div class="form-container">
                <?php if($msg): ?>
                    <div class="alert alert-danger"><?php echo $msg; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label">Admin Name</label>
                            <input type="text" name="real_name" class="form-control" 
                                   placeholder="Enter full name (No numbers/symbols)" 
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
                                       value="<?php echo htmlspecialchars($email_prefix); ?>"
                                       oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '')" required>
                                <span class="input-group-text">@sport.com</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="passwordInput" class="form-control" required>
                            <div class="strength-meter">
                                <div id="strengthBar" class="strength-bar"></div>
                            </div>
                            <div class="password-hints" id="passwordHints">
                                <span id="hint-num">*number</span>
                                <span id="hint-low">, *lowercase letters</span>
                                <span id="hint-up">, *uppercase letter</span>
                                <span id="hint-sym">, *Symbols</span>
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
        const passwordInput = document.getElementById('passwordInput');
        const strengthBar = document.getElementById('strengthBar');
        const hints = {
            num: document.getElementById('hint-num'),
            low: document.getElementById('hint-low'),
            up: document.getElementById('hint-up'),
            sym: document.getElementById('hint-sym')
        };

        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let strength = 0;

            const hasNum = /\d/.test(val);
            const hasLow = /[a-z]/.test(val);
            const hasUp = /[A-Z]/.test(val);
            const hasSym = /[^A-Za-z0-9]/.test(val);

            hasNum ? hints.num.classList.add('met') : hints.num.classList.remove('met');
            hasLow ? hints.low.classList.add('met') : hints.low.classList.remove('met');
            hasUp ? hints.up.classList.add('met') : hints.up.classList.remove('met');
            hasSym ? hints.sym.classList.add('met') : hints.sym.classList.remove('met');

            if(hasNum) strength += 25;
            if(hasLow) strength += 25;
            if(hasUp) strength += 25;
            if(hasSym) strength += 25;

            strengthBar.style.width = strength + '%';
            strengthBar.className = 'strength-bar';
            if (strength <= 25) strengthBar.classList.add('strength-weak');
            else if (strength <= 75) strengthBar.classList.add('strength-medium');
            else strengthBar.classList.add('strength-strong');
        });
    </script>
</body>
</html>