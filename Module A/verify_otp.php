<?php
session_start();
require_once '../includes/db_connection.php'; 

if (!isset($_SESSION['temp_user'])) {
    header("Location: register.php");
    exit();
}

$error = "";
$user_data = $_SESSION['temp_user'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_btn'])) {
    $input_otp = trim($_POST['otp_input']);
    $current_time = time();

    if ($input_otp != $user_data['otp']) {
        $error = "Invalid verification code.";
    } elseif ($current_time > $user_data['expiry']) {
        $error = "The code has expired. Please register again.";
    } else {
        // 获取所有注册字段 (包含了我们新加的地址和生日等)
        $full_name = $user_data['full_name'];
        $email = $user_data['email'];
        $phone = $user_data['phone'];
        $password = $user_data['password'];
        $dob = isset($user_data['dob']) ? $user_data['dob'] : null;
        $address = isset($user_data['address']) ? $user_data['address'] : null;
        $postcode = isset($user_data['postcode']) ? $user_data['postcode'] : null;
        $state = isset($user_data['state']) ? $user_data['state'] : null;

        // 彻底移除 role，完全匹配你真实的 USER 表架构的 8 个字段
        $stmt = $conn->prepare("INSERT INTO `USER` (User_Name, User_Email, User_Password, User_Phone, User_Address, User_Postcode, User_State, User_DateOfBirth) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $full_name, $email, $password, $phone, $address, $postcode, $state, $dob);

        if ($stmt->execute()) {
            unset($_SESSION['temp_user']);
            echo "<script>alert('Registration Successful! Please login.'); window.location.href='login.php';</script>";
            exit();
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}

$page_title = "Verify OTP - Sport Shoes Store";
include_once '../includes/header.php'; 
?>
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5 text-center">
                    <h2 class="fw-bold mb-3">Verify Email</h2>
                    <p class="text-muted mb-4">We've sent a code to: <strong><?php echo htmlspecialchars($user_data['email']); ?></strong></p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <input type="text" name="otp_input" class="form-control form-control-lg text-center fw-bold fs-3 py-3" placeholder="000000" maxlength="6" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="verify_btn" class="btn btn-lg py-3 fw-bold text-white" style="background-color: #FF6B00;">Verify & Register</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once '../includes/footer.php'; ?>