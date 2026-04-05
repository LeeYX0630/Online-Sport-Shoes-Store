<?php
// 1. 初始化 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 引入必要文件 (请确保路径正确)
require_once '../includes/db_connection.php'; 
require '../includes/PHPMailer/src/Exception.php';
require '../includes/PHPMailer/src/PHPMailer.php';
require '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. 登录守卫：已登录用户禁止访问注册页
if (isset($_SESSION['user_id'])) {
    $dashboard_link = (isset($_SESSION['role']) && ($_SESSION['role'] === 'Admin')) ? '../Module C/admin_dashboard.php' : 'user_dashboard.php';
    header("Location: $dashboard_link");
    exit();
}

$error = "";

// 4. 处理注册表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_btn'])) {
    
    // 清理输入数据
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone_input = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- A. 验证逻辑 --- [cite: 87, 180]
    
    // 1. 邮箱域名验证
    $trusted_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'];
    $domain = substr(strrchr($email, "@"), 1);
    $is_edu = (strpos($domain, '.edu') !== false || strpos($domain, '.ac.') !== false);
    
    // 2. 电话号码验证 (马来西亚格式)
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);
    $phone_valid = ( (substr($clean_phone, 0, 2) === '60' && strlen($clean_phone) >= 11) || 
                     (substr($clean_phone, 0, 2) === '01' && strlen($clean_phone) >= 10) );

    if (!in_array($domain, $trusted_domains) && !$is_edu) {
        $error = "We only accept trusted providers (Gmail, etc.) or Education emails.";
    } elseif (!$phone_valid) {
        $error = "Strictly for Malaysian numbers only (e.g. 0123456789).";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // --- B. 数据库查重 --- 
        $checkStmt = $conn->prepare("SELECT email FROM users WHERE email = ? OR phone = ?");
        $checkStmt->bind_param("ss", $email, $clean_phone);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "Email or Phone number already registered!";
        } else {
            // --- C. 生成 OTP 并发送邮件 --- 
            $otp = rand(100000, 999999);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 暂存数据到 Session
            $_SESSION['temp_user'] = [
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $clean_phone,
                'password' => $hashed_password,
                'otp' => $otp,
                'expiry' => strtotime("+5 minutes")
            ];

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'leeyunxiang123@gmail.com'; // 填入你的邮箱
                $mail->Password   = 'ngzxhtdxzftaznip';    // 填入你的应用专用密码
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('leeyunxiang123@gmail.com', 'Keropok Sedap Homestay');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Registration';
                $mail->Body    = "Hello $full_name, your OTP is: <b>$otp</b>. Valid for 5 minutes.";

                $mail->send();
                header("Location: verify_otp.php");
                exit();
            } catch (Exception $e) {
                $error = "Email could not be sent. Error: {$mail->ErrorInfo}";
            }
        }
    }
}

$page_title = "Register - Homestay";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">Create Account</h2>
                        <p class="text-muted">A verification code will be sent to your email</p>
                    </div>

                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control bg-light py-3" required placeholder="John Doe">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Phone Number (MY Only)</label>
                            <input type="text" name="phone" class="form-control bg-light py-3" placeholder="0123456789" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control bg-light py-3" required placeholder="name@gmail.com">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" name="password" id="passwordInput" class="form-control bg-light py-3" required>
                            <small id="strengthText" class="text-muted">Strength: Enter password...</small>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control bg-light py-3" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="register_btn" class="btn btn-dark btn-lg py-3 fw-bold">
                                Get Verification Code
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p>Already have an account? <a href="login.php" class="text-warning fw-bold">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 密码强度检查脚本
    const passwordInput = document.getElementById('passwordInput');
    const strengthText = document.getElementById('strengthText');
    passwordInput.addEventListener('input', function() {
        const val = passwordInput.value;
        let missing = []; 
        if (val.length < 6) missing.push("6+ Chars");
        if (!/[A-Z]/.test(val)) missing.push("Uppercase");
        if (!/[0-9]/.test(val)) missing.push("Number");
        
        if (val.length === 0) {
            strengthText.textContent = "Enter password...";
            strengthText.className = "text-muted";
        } else if (missing.length > 0) {
            strengthText.textContent = "Weak (Add: " + missing.join(", ") + ")";
            strengthText.className = "text-danger";
        } else {
            strengthText.textContent = "Strong 🟢";
            strengthText.className = "text-success";
        }
    });
</script>

<?php include_once '../includes/footer.php'; ?>