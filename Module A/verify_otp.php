<?php
/**
 * STEALTH SPORT SHOES - OTP VERIFICATION
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';

// Check if temporary user data exists
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
        $error = "The verification code you entered is incorrect.";
    } elseif (isset($user_data['expiry']) && $current_time > $user_data['expiry']) {
        $error = "This code has expired. Please register again.";
    } else {

        // ✅ DATA MAPPING: Match session keys to Database Columns
        $name     = $user_data['full_name'];
        $email    = $user_data['email'];
        $password = $user_data['password'];
        $phone    = $user_data['phone'];
        $address  = $user_data['address'] ?? '';
        $postcode = $user_data['postcode'] ?? 0;
        $state    = $user_data['state'] ?? '';
        $dob      = $user_data['dob'] ?? null;

        // ✅ SQL FIX: Exact column names from your screenshot
        $stmt = $conn->prepare("
            INSERT INTO user 
            (User_Name, User_Email, User_Password, User_Phone, User_Address, User_Postcode, User_State, User_DateOfBirth) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            die("SQL Prepare Error: " . $conn->error);
        }

        // "ssssssis" = string, string, string, string, string, integer, string, string
        $stmt->bind_param("sssssiss", $name, $email, $password, $phone, $address, $postcode, $state, $dob);

        if ($stmt->execute()) {
            unset($_SESSION['temp_user']);
            echo "<script>
                alert('Account Verified Successfully! Welcome to the Squad.');
                window.location.href='login.php';
            </script>";
            exit();
        } else {
            $error = "Database Error: " . $stmt->error;
        }
    }
}
include_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    #stealth-auth-layout {
        min-height: 85vh; 
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 50px 20px;
        background-color: #F8FAFC;
        background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    #stealth-auth-layout .otp-card {
        background: #FFFFFF;
        width: 100%;
        max-width: 450px;
        padding: 50px 40px;
        border-radius: 32px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    #stealth-auth-layout .hero-title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 2.2rem;
        font-weight: 700;
        color: #0F172A;
        letter-spacing: -1px;
        margin-top: 15px;
    }

    #stealth-auth-layout .hero-title span { color: #FF6B00; }

    #stealth-auth-layout .email-badge {
        background: rgba(255, 107, 0, 0.1);
        color: #FF6B00;
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 700;
        display: inline-block;
        margin-bottom: 25px;
        font-size: 0.85rem;
    }

    #stealth-auth-layout .otp-input-field {
        letter-spacing: 12px;
        font-size: 2.5rem;
        font-weight: 800;
        text-align: center;
        border-radius: 20px;
        background: #F8FAFC;
        border: 2px solid #E2E8F0;
        height: 80px;
        transition: all 0.3s ease;
        color: #0F172A;
    }

    #stealth-auth-layout .otp-input-field:focus {
        border-color: #FF6B00;
        box-shadow: 0 0 0 5px rgba(255, 107, 0, 0.1);
        background: white;
        outline: none;
    }

    #stealth-auth-layout .btn-stealth-verify {
        background: #FF6B00;
        color: white;
        border: none;
        height: 60px;
        border-radius: 18px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        width: 100%;
        transition: 0.3s;
        margin-top: 10px;
    }

    #stealth-auth-layout .btn-stealth-verify:hover {
        background: #E66000;
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(255, 107, 0, 0.2);
    }
</style>

<div id="stealth-auth-layout">
    <div class="otp-card">
        <div class="mb-4">
            <i class="bi bi-shield-lock-fill" style="font-size: 3.5rem; color: #FF6B00;"></i>
            <h2 class="hero-title">Verify <span>OTP.</span></h2>
            <p class="text-muted small">Enter the 6-digit code sent to your inbox.</p>
        </div>

        <div class="email-badge">
            <i class="bi bi-envelope-at me-2"></i><?php echo htmlspecialchars($user_data['email']); ?>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 small py-2 mb-4" style="border-radius: 12px;">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <input type="text" name="otp_input" 
                       class="form-control otp-input-field" 
                       maxlength="6" 
                       placeholder="••••••"
                       required 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>

            <button type="submit" name="verify_btn" class="btn btn-stealth-verify">
                Confirm Access
            </button>
        </form>

        <div class="pt-4 border-top mt-4">
            <p class="small text-muted mb-0">
                Entered the wrong email? <br>
                <a href="register.php" style="color: #FF6B00; font-weight: 700; text-decoration: none;">Return to Registration</a>
            </p>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        document.getElementsByName('otp_input')[0].focus();
    };
</script>

<?php include_once '../includes/footer.php'; ?>