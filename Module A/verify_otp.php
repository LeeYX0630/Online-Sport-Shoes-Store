<?php
session_start();
require_once '../includes/db_connection.php'; // 引入数据库连接 [cite: 170]

// 1. 守卫逻辑：如果没有临时用户信息，直接踢回注册页
if (!isset($_SESSION['temp_user'])) {
    header("Location: register.php");
    exit();
}

$error = "";
$user_data = $_SESSION['temp_user'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_btn'])) {
    $input_otp = trim($_POST['otp_input']);
    $current_time = time();

    // 2. 验证逻辑：比对验证码和是否过期 [cite: 87, 89]
    if ($input_otp != $user_data['otp']) {
        $error = "Invalid verification code. Please check again.";
    } elseif ($current_time > $user_data['expiry']) {
        $error = "The code has expired. Please register again.";
    } else {
        // 3. 验证通过：将数据从 Session 写入数据库 
        $full_name = $user_data['full_name'];
        $email = $user_data['email'];
        $phone = $user_data['phone'];
        $password = $user_data['password'];
        $role = 'Customer'; // 默认角色 [cite: 65]

        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $email, $password, $phone, $role);

        if ($stmt->execute()) {
            // 4. 清理 Session 并提示成功 
            unset($_SESSION['temp_user']);
            echo "<script>alert('Registration Successful! You can now login.'); window.location.href='login.php';</script>";
            exit();
        } else {
            $error = "Database Error: Unable to complete registration.";
        }
    }
}

$page_title = "Verify OTP - Homestay";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5 text-center">
                    <h2 class="fw-bold mb-3">Verify Your Email</h2>
                    <p class="text-muted mb-4">We've sent a 6-digit code to <br><strong><?php echo htmlspecialchars($user_data['email']); ?></strong></p>

                    <?php if($error): ?>
                        <div class="alert alert-danger rounded-3"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <input type="text" name="otp_input" class="form-control form-control-lg text-center fw-bold fs-3 py-3 bg-light border-0" 
                                   placeholder="000000" maxlength="6" required
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" name="verify_btn" class="btn btn-dark btn-lg py-3 fw-bold rounded-3">
                                Verify & Create Account
                            </button>
                        </div>
                    </form>

                    <p class="text-muted small">Didn't receive the code? <a href="register.php" class="text-warning fw-bold text-decoration-none">Try again</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>