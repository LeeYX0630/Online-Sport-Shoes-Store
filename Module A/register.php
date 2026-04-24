<?php
/**
 * STEALTH SPORT SHOES - ADVANCED PREMIUM REGISTRATION UI
 * Design Profile: Wide, High-End, Orange & White Only
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';
require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_SESSION['user_id'])) {
    $dashboard_link = (isset($_SESSION['role']) && ($_SESSION['role'] === 'Admin')) ? '../Module C/admin_dashboard.php' : 'user_dashboard.php';
    header("Location: $dashboard_link");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_btn'])) {
    
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone_input = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = trim($_POST['dob']);
    $address = trim($_POST['address']);
    $postcode = trim($_POST['postcode']);
    $state = trim($_POST['state']);

    // 1. ✅ GMAIL ONLY VALIDATION
    

    // 2. ✅ DOB & AGE VALIDATION (16 - 100 Years)
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;

    if ($birthDate > $today) {
        $error = "Date of birth cannot be in the future.";
    } elseif ($age > 100) {
        $error = "Date of birth cannot exceed 100 years.";
    } elseif ($age < 16) {
        $error = "You must be at least 16 years old to register.";
    }

    // 3. ✅ PHONE VALIDATION (011 = 11 digits, Others = 10 digits)
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);
    if (!str_starts_with($clean_phone, '01')) {
        $error = "Phone number must start with 01.";
    } elseif (str_starts_with($clean_phone, '011')) {
        if (strlen($clean_phone) != 11) $error = "011 format must be 11 digits.";
    } else {
        if (strlen($clean_phone) != 10) $error = "Phone must be 10 digits (012-019).";
    }

    // 4. ✅ POSTCODE VALIDATION
    if (!preg_match('/^[0-9]{5}$/', $postcode)) {
        $error = "Postcode must be exactly 5 digits.";
    }

    if (!$error) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $checkStmt = $conn->prepare("SELECT User_Email FROM `USER` WHERE User_Email = ? OR User_Phone = ?");
            $checkStmt->bind_param("ss", $email, $clean_phone);
            $checkStmt->execute();

            if ($checkStmt->get_result()->num_rows > 0) {
                $error = "This account already exists.";
            } else {
                $otp = rand(100000, 999999);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 暂存所有数据到 Session
                $_SESSION['temp_user'] = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $clean_phone,
                    'password' => $hashed_password,
                    'dob' => $dob,
                    'address' => $address,
                    'postcode' => $postcode,
                    'state' => $state,
                    'otp' => $otp,
                    'expiry' => strtotime("+5 minutes")
                ];

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_EMAIL; 
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('sportshoes.system@gmail.com', 'Online Sport Shoes Store');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Registration';
                    $mail->Body    = "Hello $full_name, your OTP is: <b style='font-size:20px; color:#FF6B00;'>$otp</b>. Valid for 5 minutes.";

                    $mail->send();
                    header("Location: verify_otp.php");
                    exit();
            } catch (Exception $e) {
                $error = "Email could not be sent. Error: {$mail->ErrorInfo}";
            }
        }
    }
}}

$page_title = "Join Stealth - Premium Registration";
include_once '../includes/header.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    :root { 
        --brand-orange: #FF6B00; 
        --pure-white: #FFFFFF; 
    }

    body { 
        background-color: #F8FAFC; 
        font-family: 'Plus Jakarta Sans', sans-serif; 
    }

    .reg-wrapper { 
        max-width: 850px; /* Wider layout for premium aesthetic */
        margin: 60px auto; 
    }

    .reg-card { 
        background: var(--pure-white); 
        padding: 50px 60px; 
        border-radius: 32px; 
        border: 1px solid rgba(0,0,0,0.05); 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08); 
    }

    .hero-title {
        font-family: 'Space Grotesk', sans-serif; 
        font-size: 3.2rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -2px;
        color: #0F172A;
    }

    .hero-title span { color: var(--brand-orange); }

    .form-label { 
        font-size: 0.75rem; 
        font-weight: 800; 
        color: #64748B; 
        margin-bottom: 8px; 
        text-transform: uppercase; 
        letter-spacing: 1px; 
    }

    .input-group-text { 
        background: #F8FAFC; 
        border-right: none; 
        color: #94A3B8; 
        border-radius: 12px 0 0 12px; 
    }

    .form-control, .form-select { 
        border-left: none; 
        height: 52px; 
        border-radius: 0 12px 12px 0; 
        border-color: #E2E8F0; 
        background: #F8FAFC; 
    }

    .no-group-radius { 
        border-radius: 12px !important; 
        border-left: 1px solid #E2E8F0 !important; 
    }

    .form-control:focus, .form-select:focus { 
        box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1); 
        border-color: var(--brand-orange); 
        background: #FFFFFF; 
    }

    .section-tag { 
        color: var(--brand-orange); 
        font-weight: 800; 
        font-size: 0.7rem; 
        background: rgba(255, 107, 0, 0.08); 
        padding: 5px 15px; 
        border-radius: 50px; 
        display: inline-block; 
        margin-bottom: 18px; 
        margin-top: 10px; 
    }

    .btn-stealth-prime { 
        background: var(--brand-orange); 
        color: white; 
        border: none; 
        height: 60px; 
        border-radius: 18px; 
        font-weight: 800; 
        text-transform: uppercase; 
        letter-spacing: 2px; 
        transition: all 0.3s ease; 
        width: 100%; 
    }

    .btn-stealth-prime:hover { 
        background: #E66000; 
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(255, 107, 0, 0.2); 
        color: white;
    }
</style>

<div class="container">
    <div class="reg-wrapper">
        <div class="reg-card">
            <div class="row align-items-center mb-5">
                <div class="col-md-8">
                    <h2 class="hero-title">Come to <br>Join <span>Us.</span></h2>
                    <p class="text-muted mt-3 mb-0">Experience the future of performance with Stealth Sport Shoes.</p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <i class="bi bi-shield-check" style="color: var(--brand-orange); font-size: 4.5rem; opacity: 0.15;"></i>
                </div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius: 12px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <span class="section-tag">Identity</span>
                <div class="mb-4">
                    <label class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter your full name">
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control no-group-radius" placeholder="01x-xxxxxxx" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date of Birthday</label>
                        <input type="date" name="dob" class="form-control no-group-radius" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <span class="section-tag">Shipping</span>
                <div class="mb-4">
                    <label class="form-label">Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" name="address" class="form-control" placeholder="House number, building, street name" required>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Postcode</label>
                        <input type="text" id="postcode" name="postcode" class="form-control no-group-radius" maxlength="5" placeholder="75450" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">State</label>
                        <select name="state" class="form-select no-group-radius" required>
                            <option value="">Select State</option>
                            <option>Johor</option><option>Kedah</option><option>Kelantan</option>
                            <option>Melaka</option><option>Negeri Sembilan</option><option>Pahang</option>
                            <option>Penang</option><option>Perak</option><option>Perlis</option>
                            <option>Sabah</option><option>Sarawak</option><option>Selangor</option>
                            <option>Terengganu</option><option>Kuala Lumpur</option>
                        </select>
                    </div>
                </div>

                <span class="section-tag">Security</span>
                <div class="mb-4">
                    <label class="form-label">Email (@gmail.com only)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required placeholder="example@gmail.com">
                    </div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="pwd" class="form-control no-group-radius" required>
                        <small id="strength" style="display:block; margin-top:5px;"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control no-group-radius" required>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="register_btn" class="btn btn-stealth-prime">Verify Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 1. Phone Auto-format (Malaysia Logic)
document.getElementById('phone').addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, ''); 
    if (v.length > 0 && !v.startsWith('01')) v = '01' + v.replace(/^0+/, '');
    
    let maxDigits = v.startsWith('011') ? 11 : 10;
    v = v.substring(0, maxDigits);

    if (v.length > 3) {
        e.target.value = v.substring(0, 3) + '-' + v.substring(3);
    } else {
        e.target.value = v;
    }
});

// 2. Postcode Constraint (Digits only)
document.getElementById('postcode').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});

// 3. Password Strength
document.getElementById('pwd').addEventListener('input', function(e) {
    let v = e.target.value;
    let txt = document.getElementById('strength');
    let score = (v.length >= 6) + (/[A-Z]/.test(v)) + (/[0-9]/.test(v));
    
    if (v.length === 0) txt.innerHTML = "";
    else if (score < 2) { txt.innerHTML = "Weak 🔴"; txt.style.color = "red"; }
    else if (score < 3) { txt.innerHTML = "Medium 🟡"; txt.style.color = "orange"; }
    else { txt.innerHTML = "Strong 🟢"; txt.style.color = "green"; }
});
</script>

<?php include_once '../includes/footer.php'; ?>