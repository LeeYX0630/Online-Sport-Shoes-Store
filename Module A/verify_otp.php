<?php
/**
 * STEALTH SPORT SHOES - OTP VERIFICATION
 * Module A: User Permissions & Profile
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Strictly using relative paths as per project instructions [cite: 32]
require_once '../includes/db_connection.php'; 

// Check if there is a pending registration session
if (!isset($_SESSION['temp_user'])) {
    header("Location: register.php");
    exit();
}

$error = "";
$user_data = $_SESSION['temp_user'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_btn'])) {
    $input_otp = trim($_POST['otp_input']);
    $current_time = time();

    // Verification Logic
    if ($input_otp != $user_data['otp']) {
        $error = "The verification code you entered is incorrect.";
    } elseif (isset($user_data['expiry']) && $current_time > $user_data['expiry']) {
        $error = "This code has expired. Please register again to receive a new one.";
    } else {
        // Database Preparation based on project schema 
        // Note: Using the exact fields defined in your database structure
        $full_name = $user_data['full_name'];
        $email     = $user_data['email'];
        $password  = $user_data['password'];
        $phone     = $user_data['phone'];
        $role      = $user_data['role']; // From registration logic

        $stmt = $conn->prepare("INSERT INTO USER (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $email, $password, $phone, $role);

        if ($stmt->execute()) {
            // Clear temporary data upon successful registration
            unset($_SESSION['temp_user']);
            
            // Success feedback using project's specified green tone 
            echo "<script>
                alert('Account Verified Successfully! Welcome to Stealth.');
                window.location.href='login.php';
            </script>";
            exit();
        } else {
            $error = "System Error: Unable to create account. Please contact support.";
        }
    }
}

include_once '../includes/header.php'; 
?>

<style>
    :root { 
        --brand-orange: #FF6B00; 
        --deep-slate: #0F172A;
    }
    body { background-color: #F8FAFC; }
    
    .otp-card {
        background: #FFFFFF;
        border-radius: 32px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.02);
        overflow: hidden;
    }
    
    .otp-header {
        background: var(--deep-slate);
        color: white;
        padding: 40px 20px;
    }
    
    .otp-input-field {
        letter-spacing: 12px;
        font-size: 2.5rem !important;
        border-radius: 16px !important;
        background: #F1F5F9 !important;
        border: 2px solid transparent !important;
        transition: all 0.3s ease;
        color: var(--deep-slate);
    }
    
    .otp-input-field:focus {
        border-color: var(--brand-orange) !important;
        background: #FFFFFF !important;
        box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1) !important;
    }
    
    .btn-verify {
        background: var(--brand-orange);
        border: none;
        height: 64px;
        border-radius: 18px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        transition: 0.3s ease;
    }
    
    .btn-verify:hover {
        background: #E66000;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(255, 107, 0, 0.2);
    }
</style>

<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="otp-card">
                <div class="otp-header text-center">
                    <i class="bi bi-shield-lock mb-3" style="font-size: 3rem; color: var(--brand-orange);"></i>
                    <h2 class="fw-bold m-0">Final Step</h2>
                </div>
                
                <div class="card-body p-5 text-center">
                    <p class="text-muted mb-4">
                        Secure verification for: <br>
                        <strong class="text-dark"><?php echo htmlspecialchars($user_data['email']); ?></strong>
                    </p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger border-0 small py-3 mb-4" style="border-radius: 12px;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-muted" style="letter-spacing: 1px;">Enter 6-Digit Code</label>
                            <input type="text" name="otp_input" 
                                   class="form-control otp-input-field text-center fw-bold" 
                                   placeholder="••••••" maxlength="6" required autocomplete="one-time-code">
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="verify_btn" class="btn btn-verify text-white">
                                Complete Access
                            </button>
                        </div>
                    </form>
                    
                    <p class="mt-4 mb-0 small text-muted">
                        Didn't get the code? <a href="register.php" class="text-decoration-none fw-bold" style="color: var(--brand-orange);">Try Again</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>