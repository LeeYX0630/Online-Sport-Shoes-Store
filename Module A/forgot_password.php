<?php
/**
 * STEALTH SPORT SHOES - FORGOT PASSWORD WITH GMAIL LINK
 */

// 1. Declare namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Corrected path: files are directly under the PHPMailer directory
require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';

// --- AUTO-CREATE TABLE CODE ---
// This checks if the password_resets table exists. If not, it creates it automatically.
$table_check = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($table_check && $table_check->num_rows === 0) {
    $create_table_sql = "
        CREATE TABLE password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    if (!$conn->query($create_table_sql)) {
        die("Error automatically creating password_resets table: " . $conn->error);
    }
}
// ------------------------------

$error = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_btn'])) {
    
    $email = strtolower(trim($_POST['email']));

    // 1. Check if the email exists in the user table
    $checkUser = $conn->prepare("SELECT * FROM user WHERE User_Email = ?");
    $checkUser->bind_param("s", $email);
    $checkUser->execute();
    $result = $checkUser->get_result();

    if ($result->num_rows === 0) {
        $error = "This email is not registered in our system.";
    } else {
        // Generate a secure token and a 15-minute expiration
        $token = bin2hex(random_bytes(32));
        $expires_at = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        // 2. Clear any old reset requests for this email first
        $delete_old = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $delete_old->bind_param("s", $email);
        $delete_old->execute();
        $delete_old->close();

        // 3. Insert the brand new reset token
        $stmt_reset = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");

        if ($stmt_reset) {
            $stmt_reset->bind_param("sss", $email, $token, $expires_at);
            $stmt_reset->execute();
            $stmt_reset->close();

            // Dynamically build the exact reset URL link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            // 4. Call PHPMailer to send the reset link
            $mail = new PHPMailer(true);

            try {
                // SMTP Configurations
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'onlinesportshoesstore@gmail.com'; 
                $mail->Password   = 'brbg fbrs qwyh erkb'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Sender & Receiver Settings
                $mail->setFrom('onlinesportshoesstore@gmail.com', 'Stealth Sport Shoes');
                $mail->addAddress($email);

                // Email Content Template
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Reset Request';
                $mail->Body    = "
                    <div style='font-family: sans-serif; text-align: center; padding: 25px; border: 1px solid #E2E8F0; border-radius: 16px; max-width: 500px; margin: 0 auto;'>
                        <h2 style='color: #FF6B00; font-size: 24px;'>Account Access Recovery</h2>
                        <p style='color: #64748B; font-size: 15px; line-height: 1.5;'>To complete your security update, please confirm this recovery request by clicking the button below.</p>
                        <div style='margin: 30px 0;'>
                            <a href='$reset_link' style='background-color: #FF6B00; color: #FFFFFF; padding: 14px 32px; font-weight: 700; text-decoration: none; border-radius: 12px; display: inline-block; text-transform: uppercase; font-size: 14px; letter-spacing: 0.5px;'>Confirm Reset Request</a>
                        </div>
                        <p style='color: #94A3B8; font-size: 12px;'>If you didn't initiate this password reset, please ignore this email. This link is valid for 15 minutes.</p>
                    </div>";

                if ($mail->send()) {
                    $success_message = "Your request was processed! Please check your recovery email inbox.";
                }

            } catch (Exception $e) {
                $error = "Mail could not be sent. Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Database preparation failed. Error: " . $conn->error;
        }
    }
    $checkUser->close();
}

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
        max-width: 600px; 
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
        font-size: 2.5rem;
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

    .form-control { 
        border-left: none; 
        height: 52px; 
        border-radius: 0 12px 12px 0; 
        border-color: #E2E8F0; 
        background: #F8FAFC; 
    }

    .form-control:focus { 
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

    .back-link {
        display: inline-block;
        margin-top: 22px;
        color: #64748B;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .back-link:hover { 
        color: var(--brand-orange); 
    }
</style>

<div class="container">
    <div class="reg-wrapper">
        <div class="reg-card">
            <div class="row align-items-center mb-5">
                <div class="col-md-9">
                    <h2 class="hero-title">Access <br><span>Recovery</span></h2>
                    <p class="text-muted mt-3 mb-0">We'll send reset instructions directly to your email inbox.</p>
                </div>
                <div class="col-md-3 text-end d-none d-md-block">
                    <i class="bi bi-shield-lock" style="color: var(--brand-orange); font-size: 4rem; opacity: 0.15;"></i>
                </div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius: 12px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if($success_message): ?>
                <div class="alert alert-success border-0 small text-center mb-4" style="border-radius: 12px;"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <span class="section-tag">Security Identity</span>
                <div class="mb-4">
                    <label class="form-label">Registered Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required placeholder="username@example.com">
                    </div>
                </div>

                <div class="mt-4 mb-2">
                    <button type="submit" name="verify_btn" class="btn btn-stealth-prime">Verify Identity</button>
                </div>
            </form>

            <div class="text-center">
                <a href="login.php" class="back-link">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>