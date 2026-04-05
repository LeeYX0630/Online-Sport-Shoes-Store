<?php
// for user login
// 1. Start Session & Buffer
ob_start();
// Set session to last for 24 hours
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

session_start();
require_once '../includes/db_connection.php';

// 2. Redirect if already logged in (Gatekeeper)
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: ../Module C/admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    
    // Fetch user info based on email
    $stmt = $conn->prepare("SELECT user_id, full_name, password, role, status, profile_image FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // 3. Admin Interceptor 
        if ($row['role'] === 'Admin') {
            $error = "<strong>Admin Access Denied</strong><br>Admins must login via the <a href='admin_login.php' class='alert-link'>Admin Portal</a>.";
        } else {
            // 4. Password Verification
            if (password_verify($password, $row['password'])) {
                
                // 5. Block Check 
                if ($row['status'] === 'Blocked') {
                    $error = "⛔ Your account has been suspended.<br>Please contact admin for assistance.";
                } else {

                    // LOGIN SUCCESS
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['user_name'] = $row['full_name'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['profile_image'] = !empty($row['profile_image']) ? $row['profile_image'] : 'default.png';

                    // Remember Me Logic
                    if (isset($_POST['remember'])) {
                        // Set Cookie for 30 days
                        setcookie('remember_email', $email, time() + (86400 * 30), "/");
                    } else {
                        // Clear Cookie
                        if (isset($_COOKIE['remember_email'])) {
                            setcookie('remember_email', '', time() - 3600, "/");
                        }
                    }

                    // Redirect based on role (Double Check)
                    if ($row['role'] === 'Admin') {
                        header("Location: ../Module C/admin_dashboard.php");
                    } else {
                        header("Location: user_dashboard.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid Password.";
            }
        }
    } else {
        $error = "User not found or Invalid Credentials.";
    }
    $stmt->close();
}
ob_end_flush();

$page_title = "Login - Homestay";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        
        <div class="col-md-11 col-lg-10"> 
            
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5"> 
                    
                    <div class="text-center mb-5">
                        <h2 class="fw-bold text-dark display-6">Welcome Back!</h2>
                        <p class="text-muted">Customer Login Portal</p> 
                    </div>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger text-center rounded-3 py-3 mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-envelope fs-5"></i></span>
                                <input type="email" name="email" class="form-control form-control-lg bg-light border-0 py-3" 
                                       placeholder="name@example.com" required 
                                       value="<?php echo isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-lock fs-5"></i></span>
                                <input type="password" name="password" class="form-control form-control-lg bg-light border-0 py-3" placeholder="Enter your password" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" 
                                       <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-muted" for="rememberMe">Remember me</label>
                            </div>
                            <a href="forgot_password.php" class="text-decoration-none text-warning small fw-bold">Forgot Password?</a>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-dark btn-lg py-3 rounded-3 fw-bold shadow-sm">Login</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-5 pt-3 border-top">
                        <p class="text-muted mb-2">Don't have an account? 
                            <a href="register.php" class="text-warning fw-bold text-decoration-none">Sign Up Here</a>
                        </p>
                        <a href="admin_login.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-shield-lock me-1"></i> Admin Portal
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>