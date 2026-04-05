<?php
// for forgot password
session_start();
require_once '../includes/db_connection.php';

$error = "";
$success_link = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    //Fetch 'status' along with user_id
    $stmt = $conn->prepare("SELECT user_id, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        //Check if user is blocked
        if ($row['status'] === 'Blocked') {
            $error = "⛔ Account Suspended. You cannot reset password.<br>Please contact admin.";
        } else {
            // Account is Active, proceed to generate token
            $token = bin2hex(random_bytes(32));
            
            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
            $update->bind_param("ss", $token, $email);
            
            if ($update->execute()) {
                $success_link = "reset_password.php?token=" . $token;
            } else {
                $error = "System error. Could not generate token.";
            }
        }
    } else {
        $error = "No account found with that email.";
    }
}

$page_title = "Forgot Password";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5"> 
                    
                    <div class="text-center mb-5">
                        <div class="mb-3"><i class="bi bi-question-circle-fill text-dark" style="font-size: 3rem;"></i></div>
                        <h2 class="fw-bold text-dark">Forgot Password?</h2>
                        <p class="text-muted">Enter your email to receive a reset link</p>
                    </div>

                    <?php if($error): ?>
                        <div class="alert alert-danger text-center rounded-3"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if($success_link): ?>
                        <div class="alert alert-success rounded-3 p-4 text-center">
                            <h5 class="alert-heading fw-bold mb-3"><i class="bi bi-check-circle-fill"></i> Link Generated!</h5>
                            <p class="mb-3">Click the button below to reset your password (Demo Mode)</p>
                            <a href="<?php echo $success_link; ?>" class="btn btn-success px-4 py-2 fw-bold">Click to Reset Password</a>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-5">
                            <label class="form-label fw-bold small text-secondary">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-envelope fs-5"></i></span>
                                <input type="email" name="email" class="form-control form-control-lg bg-light border-0 py-3" placeholder="name@example.com" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-dark btn-lg py-3 rounded-3 fw-bold">Send Reset Link</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-5 pt-3 border-top">
                        <a href="login.php" class="text-decoration-none text-muted small">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>