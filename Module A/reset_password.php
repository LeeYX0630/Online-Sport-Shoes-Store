<?php
// for resetting user password
session_start();
require_once '../includes/db_connection.php';

$error = "";
$token = "";
$token_valid = false; // remark whether token is valid

// 1. Token Validation (GET Request)
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check status along with token validity
    $stmt = $conn->prepare("SELECT user_id, status FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // If blocked, mark token as invalid
        if ($row['status'] === 'Blocked') {
            $error = "⛔ Account Suspended. You cannot reset password.";
            $token_valid = false;
        } else {
            $token_valid = true;
        }
    } else {
        $error = "This password reset link is invalid or has expired.";
    }
} 

// 2. Process new password (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token'])) {
    $token = $_POST['token'];
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    // Check token validity again (Security against forced POST)
    $stmt = $conn->prepare("SELECT user_id, status FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Double check status before updating
        if ($row['status'] === 'Blocked') {
            $error = "⛔ Account Suspended. Action denied.";
            $token_valid = false; 
        } else {
            $token_valid = true; 

            if ($pass1 !== $pass2) {
                $error = "Passwords do not match.";
            } elseif (strlen($pass1) < 6) {
                $error = "Password must be at least 6 characters.";
            } else {
                $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
                
                // Update password and clear token
                $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
                $update->bind_param("ss", $hashed_password, $token);
                
                if ($update->execute()) {
                    echo "<script>alert('Password reset successful! Please login.'); window.location.href='login.php';</script>";
                    exit();
                } else {
                    $error = "System error.";
                }
            }
        }
    } else {
        $error = "Invalid or expired token.";
        $token_valid = false;
    }
}

$page_title = "Reset Password";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-11 col-lg-10">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5"> 
                    
                    <div class="text-center mb-5">
                        <div class="mb-3"><i class="bi bi-lock-fill text-dark" style="font-size: 3rem;"></i></div>
                        <h2 class="fw-bold text-dark">Reset Password</h2>
                        <p class="text-muted">Create a new strong password</p>
                    </div>

                    <?php if (!$token_valid): ?>
                        <div class="alert alert-danger text-center rounded-3 p-4">
                            <h4 class="alert-heading fw-bold"><i class="bi bi-x-circle-fill"></i> Access Denied</h4>
                            <p><?php echo $error ? $error : "This password reset link is invalid or has expired."; ?></p>
                            <hr>
                            <a href="forgot_password.php" class="btn btn-outline-danger fw-bold">Request New Link</a>
                        </div>
                    
                    <?php else: ?>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger text-center rounded-3 mb-4"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                            <div class="mb-1">
                                <label class="form-label fw-bold small text-secondary">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-key fs-5"></i></span>
                                    <input type="password" name="password" id="passwordInput" class="form-control form-control-lg bg-light border-0 py-3" required>
                                </div>
                            </div>

                            <div class="mb-4 d-flex align-items-center flex-wrap mt-2">
                                <small class="text-muted me-2">Strength:</small> 
                                <span id="strengthText" class="fw-bold small text-muted">Enter password...</span>
                            </div>

                            <div class="mb-5">
                                <label class="form-label fw-bold small text-secondary">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-key-fill fs-5"></i></span>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg bg-light border-0 py-3" required>
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
            let missing = []; // Array to store missing requirements

            // 1. Check what is missing
            if (val.length < 6) missing.push("6+ Chars");
            if (!/[A-Z]/.test(val)) missing.push("Uppercase");
            if (!/[0-9]/.test(val)) missing.push("Number");
            if (!/[^A-Za-z0-9]/.test(val)) missing.push("Symbol");

            // 2. Logic to display hint vs success
            if (val.length === 0) {
                strengthText.textContent = "Enter password...";
                strengthText.className = "fw-bold small text-muted";
            } 
            else if (missing.length > 0) {
                // If something is missing, list it out
                strengthText.innerHTML = "Weak <span class='text-muted fw-normal'>(Add: " + missing.join(", ") + ")</span>";
                strengthText.className = "fw-bold small text-danger";
            } 
            else {
                // If nothing missing -> Strong
                strengthText.textContent = "Strong 🟢";
                strengthText.className = "fw-bold small text-success";
            }
        });
    }
</script>

<?php include_once '../includes/footer.php'; ?>