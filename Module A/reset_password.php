<?php
/**
 * STEALTH SPORT SHOES - ADVANCED RESET PASSWORD
 * Features: Password toggler, Real-time strength meter, Professional UI.
 */
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

$error = "";
$token_valid = false;

// 1. [AUTH CHECK] Ensure the user is authorized to be on this page
if (isset($_SESSION['reset_email']) && isset($_SESSION['reset_otp'])) {
    $token_valid = true;
}

// 2. [LOGIC] Handle Password Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $entered_otp = trim($_POST['otp']);
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    // Validation Chain
    if ($entered_otp != $_SESSION['reset_otp']) {
        $error = "The OTP code you entered is incorrect.";
    } elseif ($pass1 !== $pass2) {
        $error = "Confirmation password does not match.";
    } elseif (strlen($pass1) < 8) {
        $error = "Security requirement: Password must be at least 8 characters.";
    } else {
        // [DB SYNC] Match with your database fields: User_Password & User_Email
        $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];
        
        $update = $conn->prepare("UPDATE `USER` SET User_Password = ? WHERE User_Email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        
        if ($update->execute()) {
            // Success: Clear sensitive sessions
            session_unset();
            session_destroy();
            
            echo "<script>alert('Password updated successfully!'); window.location.href='login.php';</script>";
            exit();
        } else {
            $error = "Server Error: Unable to update database.";
        }
    }
}

include_once '../includes/header.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    :root { --brand-orange: #FF6B00; --soft-gray: #F1F5F9; }
    body { background-color: #F8FAFC; font-family: 'Plus Jakarta Sans', sans-serif; }

    .reset-container { max-width: 550px; margin: 80px auto; }
    .glass-card { 
        background: #FFFFFF; 
        padding: 50px; 
        border-radius: 30px; 
        box-shadow: 0 20px 40px rgba(0,0,0,0.06); 
        border: 1px solid rgba(0,0,0,0.02);
    }

    .brand-icon {
        width: 70px; height: 70px;
        background: rgba(255, 107, 0, 0.1);
        color: var(--brand-orange);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 25px; font-size: 2rem;
    }

    .form-label { font-weight: 800; font-size: 0.7rem; color: #64748B; text-transform: uppercase; letter-spacing: 1px; }
    
    .input-group-custom { 
        background: var(--soft-gray); border-radius: 15px; padding: 5px 15px; 
        display: flex; align-items: center; border: 2px solid transparent; transition: 0.3s;
    }
    .input-group-custom:focus-within { border-color: var(--brand-orange); background: #FFF; }
    
    .input-group-custom input { 
        border: none; background: transparent; padding: 12px; width: 100%; outline: none; font-weight: 600;
    }

    .btn-update { 
        background: var(--brand-orange); color: white; border: none; padding: 18px; 
        border-radius: 15px; font-weight: 800; width: 100%; transition: 0.3s; margin-top: 20px;
    }
    .btn-update:hover { background: #E66000; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(255,107,0,0.2); }

    .strength-meter { font-size: 0.75rem; margin-top: 8px; font-weight: 700; }
    .tip-item { font-size: 0.65rem; color: #94A3B8; display: block; font-weight: 400; }
</style>

<div class="container">
    <div class="reset-container">
        <div class="glass-card">
            <div class="brand-icon"><i class="bi bi-shield-lock"></i></div>
            <div class="text-center mb-5">
                <h2 class="fw-extrabold" style="letter-spacing:-1px;">New Password</h2>
                <p class="text-muted small">Enter the code sent to your Gmail to proceed.</p>
            </div>

            <?php if (!$token_valid): ?>
                <div class="alert alert-light text-center border-0 shadow-sm">
                    <p class="mb-3">Session expired.</p>
                    <a href="forgot_password.php" class="btn btn-sm btn-dark px-4 rounded-pill">Restart Process</a>
                </div>
            <?php else: ?>

                <?php if($error): ?>
                    <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius: 12px;"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" id="resetForm">
                    <div class="mb-4">
                        <label class="form-label">Verification Code</label>
                        <div class="input-group-custom">
                            <i class="bi bi-hash"></i>
                            <input type="text" name="otp" maxlength="6" placeholder="6-digit OTP" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">New Secure Password</label>
                        <div class="input-group-custom">
                            <i class="bi bi-key"></i>
                            <input type="password" name="password" id="main-pwd" placeholder="Min 8 characters" required>
                            <i class="bi bi-eye-slash toggle-pwd" style="cursor:pointer" data-target="main-pwd"></i>
                        </div>
                        <div id="pwd-feedback" class="mt-2">
                            <span id="strength-label" class="strength-meter"></span>
                            <div id="guidance-list"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Repeat Password</label>
                        <div class="input-group-custom">
                            <i class="bi bi-check2-circle"></i>
                            <input type="password" name="confirm_password" id="confirm-pwd" placeholder="Confirm password" required>
                            <i class="bi bi-eye-slash toggle-pwd" style="cursor:pointer" data-target="confirm-pwd"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-update" id="submitBtn">SAVE NEW PASSWORD</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// 1. Password Visibility Toggle (Function to see password)
document.querySelectorAll('.toggle-pwd').forEach(icon => {
    icon.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            input.type = 'password';
            this.classList.replace('bi-eye', 'bi-eye-slash');
        }
    });
});

// 2. Advanced Strength Logic (With instructions)
const pwdInput = document.getElementById('main-pwd');
const label = document.getElementById('strength-label');
const list = document.getElementById('guidance-list');

pwdInput.addEventListener('input', function() {
    const val = this.value;
    if (!val) { label.innerHTML = ""; list.innerHTML = ""; return; }

    let missing = [];
    if (val.length < 8) missing.push("• Use 8 or more characters");
    if (!/[A-Z]/.test(val)) missing.push("• Add an uppercase letter (A-Z)");
    if (!/[0-9]/.test(val)) missing.push("• Add a number (0-9)");
    if (!/[^A-Za-z0-9]/.test(val)) missing.push("• Add a symbol (!@#$)");

    if (missing.length >= 3) {
        label.innerHTML = "WEAK 🔴"; label.style.color = "#EF4444";
    } else if (missing.length > 0) {
        label.innerHTML = "MEDIUM 🟡"; label.style.color = "#F59E0B";
    } else {
        label.innerHTML = "STRONG 🟢"; label.style.color = "#10B981";
    }

    list.innerHTML = missing.map(m => `<span class="tip-item">${m}</span>`).join('');
});

// 3. Loading State (Prevents multiple clicks)
document.getElementById('resetForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    btn.disabled = true;
});
</script>

<?php include_once '../includes/footer.php'; ?>