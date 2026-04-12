<?php
// admin/add_admin.php
session_start();
require_once '../includes/db_connection.php';

// 1. 权限检查：必须是 Level 1 (Super Admin) 才能进入
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo "<script>alert('Access Denied. Super Admin only.'); window.location.href='admin_dashboard.php';</script>";
    exit();
}

$msg = "";
$sweetAlertCode = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 严格对应 SQL 字段名
    $admin_name = trim($_POST['admin_name']);
    $admin_email = trim($_POST['admin_email']); 
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 默认新创建的为普通管理员 Level 2
    $admin_level = 2; 

    // 基础验证
    if (empty($admin_name) || empty($admin_email) || empty($password)) {
        $msg = "<div class='alert error'>All fields are required.</div>";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='alert error'>Invalid email format.</div>";
    } 
    // 限制后台管理员邮箱后缀
    elseif (substr($admin_email, -14) !== '@shoestore.com') {
        $msg = "<div class='alert error'>Restricted Domain: Admin email must end with <b>@shoestore.com</b></div>";
    }
    elseif (strlen($password) < 6) {
        $msg = "<div class='alert error'>Password must be at least 6 characters long.</div>";
    } elseif ($password !== $confirm_password) {
        $msg = "<div class='alert error'>Passwords do not match.</div>";
    } else {
        // 2. 检查 Admin_Email 是否已存在 (表名 admin)
        $check_sql = "SELECT Admin_Id FROM admin WHERE Admin_Email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $admin_email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $msg = "<div class='alert error'>Email already registered.</div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->close(); 

            // 3. 插入数据 (对应 SQL 中的字段)
            $insert_sql = "INSERT INTO admin (Admin_Name, Admin_Email, Admin_Password, Admin_Level) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssi", $admin_name, $admin_email, $hashed_password, $admin_level);

            if ($stmt->execute()) {
                $sweetAlertCode = "
                Swal.fire({
                    title: 'Admin Added!',
                    text: 'New Admin ($admin_name) created successfully.',
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'Go to Dashboard'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'admin_dashboard.php';
                    }
                });";
            } else {
                $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        }
        $stmt->close();
    }
}

// 引入公共 Header
$page_title = "Add New Admin | Shoe Store";
include_once '../includes/header.php'; 
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .form-container { max-width: 450px; margin: 60px auto; padding: 40px; background: white; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
    .form-group input { width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 14px; transition: all 0.2s; }
    .form-group input:focus { border-color: #28a745; outline: none; box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1); }
    
    /* 提交按钮：绿色高亮 */
    .btn-submit { width: 100%; padding: 14px; background-color: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; margin-top: 10px; transition: 0.3s; }
    .btn-submit:hover { background-color: #218838; transform: translateY(-1px); }
    
    /* 返回按钮：明显的边框样式 */
    .btn-back { display: flex; align-items: center; justify-content: center; width: 100%; padding: 12px; margin-top: 15px; background-color: transparent; color: #6c757d; border: 2px solid #6c757d; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; }
    .btn-back:hover { background-color: #6c757d; color: white; text-decoration: none; }
    .btn-back i { margin-right: 8px; }

    .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-size: 14px; }
    .error { background-color: #fff5f5; color: #e03131; border: 1px solid #ffc9c9; }

    .strength-container { margin-top: 8px; height: 4px; background-color: #e9ecef; border-radius: 2px; overflow: hidden; }
    .strength-bar { height: 100%; width: 0%; transition: width 0.3s ease; }
    .strength-weak { background-color: #fa5252; }
    .strength-medium { background-color: #fab005; }
    .strength-strong { background-color: #40c057; }
</style>

<div class="form-container">
    <div class="text-center mb-4">
        <h2 class="fw-bold text-dark">Add New Admin</h2>
        <p class="text-muted small">Create an additional administrator account</p>
    </div>

    <?php echo $msg; ?>

    <form method="POST">
        <div class="form-group">
            <label>Admin Name</label>
            <input type="text" name="admin_name" required placeholder="Full Name" value="<?php echo isset($_POST['admin_name']) ? htmlspecialchars($_POST['admin_name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="admin_email" id="emailInput" required placeholder="name@shoestore.com" value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : ''; ?>">
            <small class="text-muted" style="font-size: 0.75rem;">* Hint: Type '@' to auto-complete domain</small>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" id="passwordInput" required placeholder="Min. 6 characters">
            <div class="strength-container">
                <div class="strength-bar" id="strengthBar"></div>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required placeholder="Re-type password">
        </div>

        <button type="submit" class="btn-submit">Create Admin Account</button>
        
        <a href="admin_dashboard.php" class="btn-back">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<script>
    // 自动补全域名
    const emailInput = document.getElementById('emailInput');
    emailInput.addEventListener('input', function(e) {
        if (e.target.value.endsWith('@')) {
            e.target.value += 'shoestore.com';
        }
    });

    // 简易密码强度视觉反馈
    const passwordInput = document.getElementById('passwordInput');
    const strengthBar = document.getElementById('strengthBar');

    passwordInput.addEventListener('input', function() {
        const val = passwordInput.value;
        strengthBar.className = 'strength-bar';
        if (val.length === 0) strengthBar.style.width = '0%';
        else if (val.length < 6) {
            strengthBar.style.width = '30%';
            strengthBar.classList.add('strength-weak');
        } else if (val.length < 10) {
            strengthBar.style.width = '60%';
            strengthBar.classList.add('strength-medium');
        } else {
            strengthBar.style.width = '100%';
            strengthBar.classList.add('strength-strong');
        }
    });

    <?php if (!empty($sweetAlertCode)) { echo $sweetAlertCode; } ?>
</script>

<?php include_once '../includes/footer.php'; ?>