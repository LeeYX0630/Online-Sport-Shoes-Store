<?php
// admin_login.php
session_start();
require_once '../includes/db_connection.php';


// 1. 拦截器：如果已经登录，直接跳转回主页
if (isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 获取前端表单提交的数据
    $email = trim($_POST['email']); 
    $password = trim($_POST['password']);

    // SQL 查询：重新加入了 Admin_Status 字段进行检查
    $stmt = $conn->prepare("SELECT Admin_Id, Admin_Name, Admin_Password, Admin_Level, Admin_Status FROM admin WHERE Admin_Email = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // 2. 验证密码
            if (password_verify($password, $row['Admin_Password'])) {
                
                // --- 新增：登录拦截逻辑 ---
                // 检查 Admin_Status 是否为 Banned
                if (isset($row['Admin_Status']) && $row['Admin_Status'] === 'Banned') {
                    echo "<script>
                        alert('Your account has been banned.');
                        window.location.href = 'admin_login.php';
                    </script>";
                    exit();
                }

                // 3. 存储 Session 数据
                $_SESSION['admin_id'] = $row['Admin_Id'];
                $_SESSION['username'] = $row['Admin_Name'];
                $_SESSION['role'] = $row['Admin_Level'];

                // 4. 跳转到 Dashboard
                header("Location: ../Module C/admin_dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Admin account not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - STRYDEX Sport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #ff8c002c;
            
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }
        .brand-section {
            background: #66666612;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .form-section {
            padding: 50px;
            background: white;
        }
        .btn-dark {
            background: #FF8C00;
            border: none;
            padding: 12px;
        }
        .btn-dark:hover {
            background: #222;
        }
    </style>
</head>
<body>

<div class="login-container container">
    <div class="row w-100 justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <div class="card shadow-lg">
                <div class="row g-0">
                    <div class="col-md-5 brand-section text-center">
                        <img src="../images/picture/STRYDEX_Logo.jpeg" alt="Logo" style="width: 120px;" class="mb-4">
                        <h2 class="fw-bold text-black  mb-3">STRYDEX Sport</h2>
                        <p class="text-black-50">Management Dashboard Access</p>
                        <div class="mt-auto small text-black-50">
                            &copy; 2026 STRYDEX Sport Co.
                        </div>
                    </div>
                    
                    <div class="col-md-7 form-section">
                        <div class="mb-4">
                            <h3 class="fw-bold text-dark">Welcome back!</h3>
                            <p class="text-muted">Please enter your credentials to login.</p>
                        </div>

                        <?php if($error): ?>
                            <div class="alert alert-danger border-0 shadow-sm mb-4">
                                <i class="bi bi-exclamation-circle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-envelope fs-5"></i></span>
                                    <input type="email" name="email" class="form-control form-control-lg bg-light border-0 py-3" placeholder="admin@sport.com" required>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label fw-bold small text-secondary">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-key fs-5"></i></span>
                                    <input type="password" name="password" class="form-control form-control-lg bg-light border-0 py-3" placeholder="Enter password" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-dark btn-lg py-3 rounded-3 fw-bold shadow-sm">
                                    Login to Dashboard
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-5 pt-3 border-top">
                            <a href="../Module A/login.php" class="text-decoration-none text-muted small">
                                <i class="bi bi-arrow-left me-1"></i> Back to Customer Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
</body>
</html>