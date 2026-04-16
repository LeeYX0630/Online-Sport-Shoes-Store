<?php
// for resetting user password via OTP
session_start();
require_once '../includes/db_connection.php';

$error = "";
$token_valid = false;

// 1. 验证是否合法进入 (是否有请求过重置的 Session)
if (isset($_SESSION['reset_email']) && isset($_SESSION['reset_otp'])) {
    $token_valid = true;
}

// 2. 处理 OTP 验证与新密码提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $entered_otp = trim($_POST['otp']);
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    // 验证 OTP 验证码是否正确
    if ($entered_otp != $_SESSION['reset_otp']) {
        $error = "Invalid OTP code. Please check your email.";
    } elseif ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass1) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // [修复点] 更新匹配数据库的 User_Password 和 User_Email 字段
        $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];
        
        $update = $conn->prepare("UPDATE `USER` SET User_Password = ? WHERE User_Email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        
        if ($update->execute()) {
            // 清理重置用的 Session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_name']);
            
            echo "<script>alert('Password reset successful! Please login with your new password.'); window.location.href='login.php';</script>";
            exit();
        } else {
            $error = "System error during password update.";
        }
    }
}

$page_title = "Reset Password | Sport Shoes Store";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-11 col-lg-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5"> 
                    
                    <div class="text-center mb-5">
                        <div class="mb-3"><i class="bi bi-shield-lock-fill text-dark" style="font-size: 3rem;"></i></div>
                        <h2 class="fw-bold text-dark">Reset Password</h2>
                        <p class="text-muted">Enter the OTP sent to <strong><?php echo isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : ''; ?></strong> and create your new password.</p>
                    </div>

                    <?php if (!$token_valid): ?>
                        <div class="alert alert-danger text-center rounded-3 p-4">
                            <h4 class="alert-heading fw-bold"><i class="bi bi-x-circle-fill"></i> Access Denied</h4>
                            <p>Your session has expired or is invalid.</p>
                            <hr>
                            <a href="forgot_password.php" class="btn btn-outline-danger fw-bold">Request New OTP</a>
                        </div>
                    
                    <?php else: ?>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger text-center rounded-3 mb-4"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary">6-Digit Verification Code (OTP)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-123 fs-5"></i></span>
                                    <input type="text" name="otp" class="form-control form-control-lg bg-light border-0 py-3" placeholder="000000" maxlength="6" required>
                                </div>
                            </div>

                            <div class="mb-1">
                                <label class="form-label fw-bold small text-secondary">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-key fs-5"></i></span>
                                    <input type="password" name="password" id="passwordInput" class="form-control form-control-lg bg-light border-0 py-3" placeholder="Enter new password" required>
                                </div>
                            </div>

                            <div class="mb-4 d-flex align-items-center flex-wrap mt-2">
                                <small class="text-muted me-2">Strength:</small> 
                                <span id="strengthText" class="fw-bold small text-muted">Enter password...</span>
                            </div>

                            <div class="mb-5">
                                <label class="form-label fw-bold small text-secondary">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-key-fill fs-5"></i></span>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg bg-light border-0 py-3" placeholder="Confirm your password" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-dark btn-lg py-3 rounded-3 fw-bold">Update Password</button>
                            </div>
                        </form>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('passwordInput');
    const strengthText = document.getElementById('strengthText');

    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let missing = []; 

            if (val.length < 6) missing.push("6+ Chars");
            if (!/[A-Z]/.test(val)) missing.push("Uppercase");
            if (!/[0-9]/.test(val)) missing.push("Number");
            if (!/[^A-Za-z0-9]/.test(val)) missing.push("Symbol");

            if (val.length === 0) {
                strengthText.textContent = "Enter password...";
                strengthText.className = "fw-bold small text-muted";
            } 
            else if (missing.length > 0) {
                strengthText.innerHTML = "Weak <span class='text-muted fw-normal'>(Add: " + missing.join(", ") + ")</span>";
                strengthText.className = "fw-bold small text-danger";
            } 
            else {
                strengthText.textContent = "Strong 🟢";
                strengthText.className = "fw-bold small text-success";
            }
        });
    }
</script>

<?php include_once '../includes/footer.php'; ?>