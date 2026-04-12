<?php
// 1. Start Session & Buffer
ob_start();
session_start();
require_once '../includes/db_connection.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // 匹配数据库真实的表名 `USER` 和字段 `User_Email`
    $stmt = $conn->prepare("SELECT User_Id, User_Name FROM `USER` WHERE User_Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // 生成 6 位数 OTP 验证码存入 Session
        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_name'] = $row['User_Name'];
        
        // ==========================================
        // 邮件发送区 (如果你有配置 PHPMailer)
        // 如果只做前端跳转测试，这部分可以保持注释
        // ==========================================
        /* require_once '../includes/mail_config.php';
        $mail->addAddress($email, $row['User_Name']);
        $mail->Subject = 'Password Reset OTP - Sport Shoes Store';
        $mail->Body    = 'Hello ' . $row['User_Name'] . ',<br><br>Your OTP for password reset is: <b>' . $otp . '</b>.<br>Please do not share this code with anyone.';
        $mail->send(); 
        */

        // [修复点] 成功找到邮箱后，跳转到专用的重置密码页面，而不是注册页面
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "We could not find an account with that email address.";
    }
    $stmt->close();
}
ob_end_flush();

$page_title = "Forgot Password | Sport Shoes Store";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8"> 
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5"> 
                    
                    <div class="text-center mb-5">
                        <h2 class="fw-bold text-dark display-6">Forgot Password?</h2>
                        <p class="text-muted">Enter your email to receive a reset OTP.</p> 
                    </div>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger text-center rounded-3 py-3 mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-5">
                            <label class="form-label fw-bold small text-secondary">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-envelope fs-5"></i></span>
                                <input type="email" name="email" class="form-control form-control-lg bg-light border-0 py-3" 
                                       placeholder="Enter your registered email" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-dark btn-lg py-3 rounded-3 fw-bold shadow-sm">Send Reset OTP</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-5 pt-3 border-top">
                        <p class="text-muted mb-2">Remembered your password? 
                            <a href="login.php" class="text-warning fw-bold text-decoration-none">Back to Login</a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>