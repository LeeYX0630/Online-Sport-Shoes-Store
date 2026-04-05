<?php
// for admin login
session_start();
require_once '../includes/db_connection.php';

// 1. Gatekeeper: Redirect if already logged in as Admin
if (isset($_SESSION['role']) && ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'admin')) {
    header("Location: ../Module C/admin_dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']); 
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT admin_id, full_name, password, role, username, status FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verify Password
        if (password_verify($password, $row['password'])) {
            // Check account status before setting session
            if (isset($row['status']) && $row['status'] === 'inactive') {
                echo "<script>alert('Your account is deactivated. Contact Super Admin.'); window.location.href = 'admin_login.php';</script>";
                exit();
            }

            // Session Setting
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['username'] = $row['username']; 
            $_SESSION['role'] = $row['role'];         
            
            // Redirect to Admin Dashboard
            header("Location: ../Module C/admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid Password.";
        }
    } else {
        $error = "Access Denied. Admin email not found.";
    }
    $stmt->close();
}

$page_title = "Admin Login";
include_once '../includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5"> 
                    
                    <div class="text-center mb-5">
                        <div class="mb-3">
                            <i class="bi bi-shield-lock-fill text-dark" style="font-size: 3.5rem;"></i>
                        </div>
                        <h2 class="fw-bold text-dark display-6">Admin Portal</h2>
                        <p class="text-muted">System Management Access</p>
                    </div>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger text-center rounded-3 py-2 mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Admin Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-envelope fs-5"></i></span>
                                <input type="email" name="email" class="form-control form-control-lg bg-light border-0 py-3" placeholder="admin@homestay.com" required>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold small text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 px-3"><i class="bi bi-key fs-5"></i></span>
                                <input type="password" name="password" class="form-control form-control-lg bg-light border-0 py-3" placeholder="Enter password" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-dark btn-lg py-3 rounded-3 fw-bold shadow-sm">
                                Login to Dashboard
                            </button>
                        </div>

                    </form>
                    
                    <div class="text-center mt-5 pt-3 border-top">
                        <a href="../Module A/login.php" class="text-decoration-none text-muted small">
                            <i class="bi bi-arrow-left me-1"></i> Back to Customer Login
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include_once '../includes/footer.php'; 
?>