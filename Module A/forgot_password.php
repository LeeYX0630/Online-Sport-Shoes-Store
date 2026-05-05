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
require '../includes/mail_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use the database connection established in previous modules
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

$error = "";
// Ensure the session key matches what you set in your forgot_password logic
$token_valid = isset($_SESSION['reset_user_id']) || isset($_SESSION['reset_data']['email']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    if ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass1) < 8) {
        $error = "Password must be at least 8 characters.";
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
                $mail->Username   = SMTP_EMAIL; 
                $mail->Password   = SMTP_PASS; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Sender & Receiver Settings
                $mail->setFrom('sportshoes.system@gmail.com', 'SS Sport Shoes');
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
            $error = "Database update failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Stealth Sport Shoes</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root { 
            --brand-orange: #FF6B00; 
            --soft-gray: #F1F5F9; 
            --danger: #EF4444;
            --warning: #F59E0B;
            --success: #10B981;
        }
        body { 
            background-color: #F8FAFC; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0;
        }

        .reset-container { max-width: 500px; width: 100%; padding: 20px; }
        .glass-card { 
            background: #FFFFFF; padding: 45px; border-radius: 35px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.08); 
            border: 1px solid rgba(0,0,0,0.03);
        }

        .brand-icon {
            width: 60px; height: 60px; background: rgba(255, 107, 0, 0.1);
            color: var(--brand-orange); border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 1.8rem;
        }

        .form-label { font-weight: 800; font-size: 0.75rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px; }
        
        .input-group-custom { 
            background: var(--soft-gray); border-radius: 15px; padding: 5px 15px; 
            display: flex; align-items: center; border: 2px solid transparent; transition: 0.3s;
        }
        .input-group-custom:focus-within { border-color: var(--brand-orange); background: #FFF; }
        .input-group-custom input { border: none; background: transparent; padding: 12px; width: 100%; outline: none; font-weight: 600; }

        .btn-update { 
            background: var(--brand-orange); color: white; border: none; padding: 18px; 
            border-radius: 15px; font-weight: 800; width: 100%; transition: 0.4s; margin-top: 10px;
            text-align: center; display: block; text-decoration: none;
        }
        .btn-update:hover {
            background: #E66000;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(255, 107, 0, 0.2);
            color: white;
        }

        .strength-meter { font-size: 0.8rem; font-weight: 800; display: block; margin-top: 12px; }
        .guidance-box {
            background: #F8FAFC; border-radius: 15px; padding: 15px; margin-top: 10px; border: 1px solid #EDF2F7;
        }
        .tip-item { font-size: 0.75rem; color: #64748B; display: block; margin-bottom: 5px; transition: 0.3s; }
        .tip-item.met { color: var(--success); font-weight: 600; }
        .tip-item.met i { margin-right: 5px; }

        /* Style for smaller custom buttons at the bottom */
        .btn-small-link {
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748B;
            text-decoration: none;
            transition: color 0.2s;
            margin-top: 15px;
        }
        .btn-small-link:hover {
            color: var(--brand-orange);
        }
    </style>
</head>
<body>

<div class="reset-container">
    <div class="glass-card">
        <div class="brand-icon"><i class="bi bi-shield-lock"></i></div>
        <div class="text-center mb-4">
            <h2 class="fw-bold">New Password</h2>
            <p class="text-muted small">Update your credentials below.</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 small text-center mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
            <div class="mb-4">
                <label class="form-label">Create Password</label>
                <div class="input-group-custom">
                    <i class="bi bi-key-fill text-muted"></i>
                    <input type="password" name="password" id="main-pwd" placeholder="Type password..." required autofocus>
                </div>
                
                <div id="strength-label" class="strength-meter"></div>
                
                <div class="guidance-box">
                    <span class="tip-item" id="tip-len"><i class="bi bi-circle"></i> At least 8 characters</span>
                    <span class="tip-item" id="tip-upper"><i class="bi bi-circle"></i> One uppercase letter (A-Z)</span>
                    <span class="tip-item" id="tip-num"><i class="bi bi-circle"></i> One number (0-9)</span>
                    <span class="tip-item" id="tip-sym"><i class="bi bi-circle"></i> One special character (@$!%)</span>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group-custom">
                    <i class="bi bi-check-circle-fill text-muted"></i>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="btn-update w-100" id="submitBtn">SAVE CHANGES</button>
            
            <div class="text-center d-flex justify-content-between align-items-center mt-2">
                <a href="password_assistant.php" class="btn-small-link"><i class="bi bi-arrow-left"></i> Password Assistant</a>
                <a href="login.php" class="btn-small-link">Back to Login</a>
            </div>
        </form>
    </div>
</div>

<script>
const pwdInput = document.getElementById('main-pwd');
const strengthLabel = document.getElementById('strength-label');
const tips = {
    len: document.getElementById('tip-len'),
    upper: document.getElementById('tip-upper'),
    num: document.getElementById('tip-num'),
    sym: document.getElementById('tip-sym')
};

function updateTip(element, isMet) {
    if (isMet) {
        element.classList.add('met');
        element.querySelector('i').className = 'bi bi-check-circle-fill';
    } else {
        element.classList.remove('met');
        element.querySelector('i').className = 'bi bi-circle';
    }
}

pwdInput.addEventListener('input', function() {
    const val = this.value;
    const hasLen = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum = /[0-9]/.test(val);
    const hasSym = /[^A-Za-z0-9]/.test(val);

    updateTip(tips.len, hasLen);
    updateTip(tips.upper, hasUpper);
    updateTip(tips.num, hasNum);
    updateTip(tips.sym, hasSym);

    const score = [hasLen, hasUpper, hasNum, hasSym].filter(Boolean).length;

    if (val === "") {
        strengthLabel.innerText = "";
    } else if (score <= 1) {
        strengthLabel.innerText = "STRENGTH: WEAK 🔴";
        strengthLabel.style.color = "#EF4444";
    } else if (score <= 3) {
        strengthLabel.innerText = "STRENGTH: MEDIUM 🟡";
        strengthLabel.style.color = "#F59E0B";
    } else {
        strengthLabel.innerText = "STRENGTH: STRONG 🟢";
        strengthLabel.style.color = "#10B981";
    }
});
</script>
</body>
</html>