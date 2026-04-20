<?php
/**
 * STEALTH SPORT SHOES - PREMIUM REGISTRATION UI
 * Optimized for high conversion and clean aesthetics.
 */

// 1. Session & Resource Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Auth Guard
if (isset($_SESSION['user_id'])) {
    $dashboard_link = (isset($_SESSION['role']) && ($_SESSION['role'] === 'Admin')) ? '../Module C/admin_dashboard.php' : 'user_dashboard.php';
    header("Location: $dashboard_link");
    exit();
}

$error = "";

// 3. Form Submission Logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_btn'])) {
    
    // Sanitize and collect data
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone_input = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = trim($_POST['dob']);
    $address = trim($_POST['address']);
    $postcode = trim($_POST['postcode']);
    $state = trim($_POST['state']);

    // --- Validation Suite ---
    $trusted_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'];
    $domain = substr(strrchr($email, "@"), 1);
    
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);
    $phone_valid = ( (substr($clean_phone, 0, 2) === '60' && strlen($clean_phone) >= 11) || 
                     (substr($clean_phone, 0, 2) === '01' && strlen($clean_phone) >= 10) );

    if (!in_array($domain, $trusted_domains)) {
        $error = "Please use a standard email provider.";
    } elseif (!$phone_valid) {
        $error = "Invalid Malaysian phone number format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // --- Database Integrity Check ---
        $checkStmt = $conn->prepare("SELECT User_Email FROM `USER` WHERE User_Email = ? OR User_Phone = ?");
        $checkStmt->bind_param("ss", $email, $clean_phone);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "This account already exists.";
        } else {
            // --- OTP & Session Storage ---
            $otp = rand(100000, 999999);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $_SESSION['temp_user'] = [
                'full_name' => $full_name, 'email' => $email, 'phone' => $clean_phone,
                'password' => $hashed_password, 'dob' => $dob, 'address' => $address,
                'postcode' => $postcode, 'state' => $state, 'otp' => $otp,
                'expiry' => strtotime("+5 minutes")
            ];

            // PHPMailer logic here...
            header("Location: verify_otp.php");
            exit();
        }
    }
}

$page_title = "Join Stealth - Premium Registration";
include_once '../includes/header.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    :root {
        --stealth-orange: #FF6B00;
        --soft-white: #FFFFFF;
        --border-color: #E2E8F0;
    }

    body { 
        background-color: #F8FAFC; 
        font-family: 'Plus Jakarta Sans', sans-serif; 
    }

    .reg-wrapper {
        max-width: 520px;
        margin: 60px auto;
    }

    .reg-card {
        background: var(--soft-white);
        padding: 45px;
        border-radius: 24px;
        border: 1px solid var(--border-color);
        box-shadow: 0 20px 40px rgba(0,0,0,0.03);
    }

    /* Modern Progress Tracker (Visual Only) */
    .steps-container {
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
    }
    .step-item {
        flex: 1;
        height: 4px;
        background: #EDF2F7;
        margin: 0 4px;
        border-radius: 2px;
    }
    .step-active { background: var(--stealth-orange); }

    /* Refined Input Fields */
    .form-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #64748B;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .input-group-text {
        background: transparent;
        border-right: none;
        color: #94A3B8;
        border-radius: 12px 0 0 12px;
    }

    .form-control, .form-select {
        border-left: none;
        height: 50px;
        border-radius: 0 12px 12px 0;
        border-color: var(--border-color);
        font-size: 0.95rem;
        color: #1E293B;
    }
    
    /* Fixing the first field of a group */
    .no-group-radius { border-radius: 12px !important; border-left: 1px solid var(--border-color) !important; }

    .form-control:focus, .form-select:focus {
        box-shadow: none;
        border-color: var(--stealth-orange);
    }

    /* Section Styling */
    .section-tag {
        color: var(--stealth-orange);
        font-weight: 800;
        font-size: 0.7rem;
        background: rgba(255, 107, 0, 0.08);
        padding: 4px 12px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 15px;
    }

    .btn-stealth-prime {
        background: var(--stealth-orange);
        color: white;
        border: none;
        height: 56px;
        border-radius: 16px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        margin-top: 20px;
    }

    .btn-stealth-prime:hover {
        background: #E66000;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(255, 107, 0, 0.2);
        color: white;
    }
</style>

<div class="container">
    <div class="reg-wrapper">
        <div class="reg-card">
            
            <div class="text-center mb-4">
                <h2 class="fw-extrabold mb-1">Come to Join Us!</h2>
                <p class="text-muted small">Access exclusive drops at Stealth Shoes.</p>
            </div>

            <div class="steps-container">
                <div class="step-item step-active"></div>
                <div class="step-item step-active"></div>
                <div class="step-item step-active"></div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius: 12px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <span class="section-tag">Identity</span>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="full_name" class="form-control" required placeholder="John Doe">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control no-group-radius" placeholder="01x-xxxxxxx" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Date of Birthday</label>
                        <input type="date" name="dob" class="form-control no-group-radius" required>
                    </div>
                </div>

                <span class="section-tag">Shipping</span>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" name="address" class="form-control" placeholder="Street Name & Unit" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Postcode</label>
                        <input type="number" name="postcode" class="form-control no-group-radius" placeholder="75450" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">State</label>
                        <select name="state" class="form-select no-group-radius" required>
                            <option value="">Select</option>
                            <?php 
                            $states = ["Johor", "Melaka", "Selangor", "Kuala Lumpur", "Penang", "Perak", "Kedah", "Pahang", "Negeri Sembilan", "Terengganu", "Kelantan", "Perlis", "Sabah", "Sarawak"];
                            foreach($states as $s) echo "<option value='$s'>$s</option>";
                            ?>
                        </select>
                    </div>
                </div>

                <span class="section-tag">Security</span>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required placeholder="user@gmail.com">
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control no-group-radius" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control no-group-radius" required>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="register_btn" class="btn btn-stealth-prime">Verify Account</button>
                </div>
            </form>

            <p class="text-center mt-4 mb-0 small">
                Already a member? <a href="login.php" class="fw-bold text-decoration-none ms-1" style="color: var(--stealth-orange);">Log In</a>
            </p>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>