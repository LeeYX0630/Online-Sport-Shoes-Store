<?php
// 1. Start Session & Buffer
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connection.php';

// 引入邮件发送必需的 PHPMailer 组件
require_once '../includes/mail_config.php'; 
require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
        $user_name = $row['User_Name'];
        $_SESSION['reset_name'] = $user_name;
        
        // ==========================================
        // 真实的邮件发送逻辑 (PHPMailer)
        // ==========================================
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_EMAIL; 
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('sportshoes.system@gmail.com', 'Online Sport Shoes Store');
            $mail->addAddress($email, $user_name);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - Sport Shoes Store';
            $mail->Body    = "Hello $user_name,<br><br>Your OTP for password reset is: <b style='font-size:20px; color:#FF6B00;'>$otp</b>.<br>Please do not share this code with anyone.";

            // 发送邮件并跳转
            $mail->send();
            header("Location: reset_password.php");
            exit();
        } catch (Exception $e) {
            $error = "Email could not be sent. Error: {$mail->ErrorInfo}";
        }

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