<?php
/** 
 * Design: STRYDEX Sport Shoes - Compact Light Aesthetic
 * Logic: Role-based redirection (@sport for Admin)
 */
ob_start();
session_start();
require_once '../includes/db_connection.php';

$login_error = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password']; 

    $sql = "SELECT * FROM `USER` WHERE User_Email = '$email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Try both password_verify (hashed) and plain text comparison
        if (password_verify($password, $user['User_Password']) || $password === trim($user['User_Password'])) {
            
            // Check if user is banned
            if (isset($user['User_Status']) && $user['User_Status'] === 'Banned') {
                $login_error = "BANNED";
            } else {
            $_SESSION['user_id'] = $user['User_Id'];
            $_SESSION['user_name'] = $user['User_Name'];
            
            // --- NEW REDIRECTION LOGIC ---
            // Check if the email contains '@sport' to identify admin status
            if (strpos($email, '@sport') !== false) {
                // Admin login - fetch admin details
                $admin_sql = "SELECT * FROM `admin` WHERE Admin_Email = '$email' LIMIT 1";
                $admin_result = $conn->query($admin_sql);
                
                if ($admin_result && $admin_result->num_rows > 0) {
                    $admin_user = $admin_result->fetch_assoc();
                    $_SESSION['admin_id'] = $admin_user['Admin_Id'];
                    $_SESSION['username'] = $admin_user['Admin_Name'];
                    $_SESSION['role'] = $admin_user['Role_Id'] ?? '1';
                } else {
                    // If no admin record found, create one or set default
                    $_SESSION['admin_id'] = $user['User_Id'];
                    $_SESSION['username'] = $user['User_Name'];
                    $_SESSION['role'] = '1'; // Default to Level 1
                }
                
                $_SESSION['is_admin'] = true;
                header("Location: ../Module C/admin_dashboard.php");
                exit();
            } else {
                $_SESSION['role'] = 'Customer';
                header("Location: ../Module B/catalogue.php"); // Path to customer catalogue
                exit();
            }
            // ------------------------------
            
            } // end if not Banned
            
        } else {
            $login_error = "Security Key (Password) is incorrect.";
        }
    } else {
        $login_error = "Email Handle not found in our records.";
    }
}

include_once '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Access | STRYDEX Sport Shoes Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --brand-orange: #FF6B00;
            --soft-pink: #FDF2F8; 
            --deep-slate: #0F172A;
        }

        .login-page-wrapper {
            background-color: var(--soft-pink);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 85vh; 
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
            position: relative;
            z-index: 1;
        }

        .login-page-wrapper::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?q=80&w=2000'); 
            background-size: 45%;
            background-repeat: no-repeat;
            background-position: -10% 50%;
            opacity: 0.1;
            filter: grayscale(10%) sepia(20%) hue-rotate(300deg);
            z-index: -1;
        }

        .master-container {
            width: 900px; 
            height: 540px; 
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            border-radius: 32px; 
            display: flex;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .branding-panel {
            flex: 1;
            padding: 60px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .branding-panel h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 4.2rem; 
            font-weight: 900;
            line-height: 0.9;
            color: var(--deep-slate);
        }

        .branding-panel h1 span { color: var(--brand-orange); }

        .form-panel {
            flex: 1;
            padding: 60px; 
            background: #FFFFFF; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: -15px 0 40px rgba(0,0,0,0.02);
        }

        .form-label {
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94A3B8;
        }

        .form-control {
            height: 48px; 
            border-radius: 12px;
            border: 2px solid #F1F5F9;
            background: #F8FAFC;
            font-weight: 600;
            margin-bottom: 18px;
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--brand-orange);
            background: #fff;
            box-shadow: 0 8px 16px rgba(255, 107, 0, 0.05);
        }

        .btn-access {
            background: var(--brand-orange);
            color: white;
            height: 54px;
            border-radius: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border: none;
            transition: 0.3s;
            margin-top: 5px;
        }

        .btn-access:hover {
            background: #E66000;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(255, 107, 0, 0.15);
            color: white;
        }

        @media (max-width: 992px) {
            .branding-panel { display: none; }
            .master-container { width: 95%; max-width: 400px; height: auto; padding: 20px 0; }
        }
    </style>
</head>
<body>

<div class="login-page-wrapper">
    <div class="master-container">
        <div class="branding-panel">
            <span class="badge rounded-pill mb-3" style="background: rgba(255,107,0,0.1); color: var(--brand-orange); width: fit-content; font-weight: 800; padding: 6px 14px; font-size: 0.7rem;">LITE COLLECTION</span>
            <h1>Welcome<br>Back<span>.</span></h1>
            <p class="text-muted mt-2 small">Sign in to access your dashboard.</p>
        </div>

        <div class="form-panel">
            <div class="mb-4">
                <h3 style="font-weight: 800;">LONG TIME NO SEE!</h3>
                <p class="text-muted small">Please sign in to continue.</p>
            </div>

            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger py-2 mb-3" style="border-radius: 10px; font-size: 0.8rem; font-weight: 600; border: none; background: rgba(220, 53, 69, 0.08); color: #dc3545;">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo $login_error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-2">
                    <label class="form-label">Email Handle</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label class="form-label">Security Key</label>
                        <a href="forgot_password.php" class="text-decoration-none extra-small fw-bold" style="color: var(--brand-orange); font-size: 0.7rem;">FORGOT PASSWORD?</a>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-access w-100">Access Account</button>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted">New member? <a href="register.php" class="fw-bold text-decoration-none" style="color: var(--brand-orange);">Join Now</a></p>
            </div>
        </div>
    </div>
</div>

<?php if ($login_error === 'BANNED'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Account Suspended',
            html: `Your account has been <strong>banned</strong> and cannot be used to log in.<br><br>
                   <span style="font-size:13px; color:#666;">If you have any questions, please contact:<br>
                   <a href="mailto:admin@sport.com" style="color:#FF6B00; font-weight:700;">admin@sport.com</a></span>`,
            confirmButtonColor: '#FF6B00',
            confirmButtonText: 'OK',
            allowOutsideClick: false
        });
    });
</script>
<?php endif; ?>

</body>
</html>

<?php include_once '../includes/footer.php'; ?>