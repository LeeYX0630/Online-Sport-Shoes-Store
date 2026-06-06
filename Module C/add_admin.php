<?php
// admin/add_admin.php
session_start();
require_once '../includes/db_connection.php';

// 引入邮件发送库和配置
require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php'; // 确保这个文件里定义了 SMTP 配置
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

// 1. 权限检查
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>alert('Permission Denied.'); window.location.href='admin_dashboard.php';</script>";
    exit();
}

// 获取 Header 所需的管理员信息
$admin_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';

$msg = "";
$real_name = "";
$admin_email = "";
$admin_level = "2";

// 随机密码生成函数 (10位，包含字母、数字和特殊符号)
function generateRandomPassword($length = 10) {
    $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*()-_=+';
    $password = '';
    
    // 确保每种类型至少包含一个
    $password .= $letters[rand(0, strlen($letters) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $symbols[rand(0, strlen($symbols) - 1)];
    
    // 补齐剩余的位数
    $all = $letters . $numbers . $symbols;
    for ($i = 3; $i < $length; $i++) {
        $password .= $all[rand(0, strlen($all) - 1)];
    }
    
    // 随机打乱密码字符顺序
    return str_shuffle($password);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $real_name = trim($_POST['real_name']);     
    $admin_email = trim($_POST['admin_email']);
    $admin_level = $_POST['admin_level'];

    if (empty($real_name) || empty($admin_email)) {
        $msg = "Error: All fields are required.";
    } 
    else {
        if (preg_match('/[0-9]/', $real_name) || preg_match('/[^A-Za-z\s]/', $real_name)) {
            $msg = "Error: Admin Name cannot contain numbers or symbols.";
        } else {
            // 检查邮箱是否已经存在
            $check_email = $conn->prepare("SELECT Admin_Id FROM admin WHERE Admin_Email = ?");
            $check_email->bind_param("s", $admin_email);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $msg = "Error: This email is already registered.";
            } else {
                // 生成 10 位数随机密码
                $generated_password = generateRandomPassword(10);
                $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
                
                try {
                    // 直接插入 Admin (去除了 Brand)，Admin Level 保留 1 和 2
                    $stmt_admin = $conn->prepare("INSERT INTO admin (Admin_Name, Admin_Email, Admin_Password, Admin_Level, Admin_Status) VALUES (?, ?, ?, ?, 'Active')");
                    $stmt_admin->bind_param("ssss", $real_name, $admin_email, $hashed_password, $admin_level);
                    
                if ($stmt_admin->execute()) {
                    // 1. 发送邮件逻辑
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       =  'smtp.gmail.com'; // 来自 mail_config.php
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_EMAIL;
                        $mail->Password   = SMTP_PASS;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('sportshoes.system@gmail.com', 'SS SPORT SHOES STORE');
                        $mail->addAddress($admin_email); // 发送给新创建的管理员邮箱

                        $mail->isHTML(true);
                        $mail->Subject = 'New Admin Account Created';
                        $mail->Body    = "
                            <div style='font-family: sans-serif; padding: 20px;'>
                                <h2 style='color: #FF6B00;'>Your New Admin Account</h2>
                                <p>Hello,</p>
                                <p>An administrator account has been created for you at SS Sport Shoes Store.</p>
                                <p><b>Email:</b> $admin_email</p>
                                <p><b>Password:</b> $generated_password</p>
                                <p style='color: red;'>Please change your password after your first login.</p>
                            </div>";

                        $mail->send();
                    } catch (Exception $e) {
                        // 如果邮件发送失败，可以在此处记录日志，但不影响管理员创建结果
                        error_log("Mail Error: " . $mail->ErrorInfo);
                    }

                    // 2. 将数据存入 Session，用于跳转到管理页面显示弹窗
                    $_SESSION['new_admin_data'] = [
                        'email' => $admin_email,
                        'password' => $generated_password
                    ];
                    
                    header("Location: admin_manage_admins.php");
                    exit();
                }

                } catch (Exception $e) {
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
        body { background-color: #f8fafc; font-family: 'Segoe UI', 'Inter', sans-serif;, sans-serif; margin: 0; }
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
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Admin Name</label>
            <input type="text" name="real_name" class="form-control" placeholder="Enter full name" value="<?php echo htmlspecialchars($real_name); ?>" required>
        </div>

        <div class="col-12">
            <label class="form-label">Email Address</label>
            <input type="email" name="admin_email" class="form-control" placeholder="example@domain.com" value="<?php echo htmlspecialchars($admin_email); ?>" required>
        </div>

        <div class="col-12">
            <label class="form-label">Admin Level</label>
            <select name="admin_level" class="form-select" required>
                <option value="1" <?php if ($admin_level == "1") echo "selected"; ?>>Super Admin</option>
                <option value="2" <?php if ($admin_level == "2") echo "selected"; ?>>Normal Admin</option>
            </select>
        </div>

        <div class="col-12 mt-2">
            <div class="alert alert-info border-0" style="border-radius: 10px; font-size: 14px; background-color: #F0F9FF; color: #0284C7; margin-bottom: 0;">
                <i class="bi bi-info-circle-fill me-1"></i> A secure 10-character password (letters, numbers, symbols) will be automatically generated upon creation.
            </div>
        </div>
    </div>

    <button type="submit" class="btn-submit mt-4" style="width: 100%;">Create Admin Account</button>
</form>

    <script>
        // 保持原有的 JavaScript 逻辑[cite: 2]
// 如果页面中不存在 passwordInput，就不执行密码强度检查逻辑
        const passwordInput = document.getElementById('passwordInput');
        if (passwordInput) {
            const strengthBar = document.getElementById('strengthBar');
            passwordInput.addEventListener('input', function() {
        // ... 原有逻辑
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