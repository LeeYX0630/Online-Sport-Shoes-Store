<?php
/**
 * STEALTH SPORT SHOES - FINAL FIXED LAYOUT
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';

$error = "";
$success_message = "";
$token_valid = isset($_SESSION['reset_user_id']) || isset($_SESSION['reset_data']['email']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];
    $email = $_SESSION['reset_data']['email'] ?? '';

    if ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass1) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $token = bin2hex(random_bytes(32));
        $expires_at = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $delete_old = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $delete_old->bind_param("s", $email);
        $delete_old->execute();

        $stmt_reset = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        if ($stmt_reset) {
            $stmt_reset->bind_param("sss", $email, $token, $expires_at);
            $stmt_reset->execute();
            
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_EMAIL; 
                $mail->Password = SMTP_PASS; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom('sportshoes.system@gmail.com', 'SS Sport Shoes');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Reset Request';
                $mail->Body = "Confirm your request: <a href='$reset_link'>$reset_link</a>";

                if ($mail->send()) {
                    $success_message = "Success! Please check your recovery email inbox.";
                }
            } catch (Exception $e) {
                $error = "Mail error: {$mail->ErrorInfo}";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Password | Sole 2 Soul</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root { --sole-orange: #FF6B00; }
        
        body { 
            background-color: #f4f7f6; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            /* Allow the page to scroll if the card is tall */
            min-height: 100vh;
        }
        
        .page-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            min-height: 90vh;
        }

        .auth-card { 
            background: #ffffff; 
            padding: 30px; /* Reduced padding to save vertical space */
            border-radius: 24px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            text-align: center;
        }

        .icon-header { font-size: 2.5rem; color: var(--sole-orange); margin-bottom: 10px; display: block; }
        h2 { font-weight: 800; color: #111; margin-bottom: 5px; font-size: 1.5rem; }
        .subtitle { color: #666; margin-bottom: 20px; font-size: 0.9rem; }

        .form-group { text-align: left; margin-bottom: 15px; }
        .label-text { font-weight: 800; font-size: 0.7rem; text-transform: uppercase; color: #333; margin-bottom: 6px; display: block; }
        
        .input-box { 
            background: #F1F4F9; border-radius: 12px; display: flex; align-items: center; 
            padding: 0 15px; border: 2px solid transparent; 
        }
        .input-box:focus-within { border-color: var(--sole-orange); background: #fff; }
        .input-box input { border: none; background: transparent; padding: 12px; width: 100%; outline: none; font-weight: 600; }

        .rules-container { background: #F8FAFC; padding: 12px; border-radius: 12px; margin-top: 8px; border: 1px solid #E2E8F0; }
        .rule { font-size: 0.8rem; color: #94A3B8; display: block; margin-bottom: 4px; font-weight: 600; }
        .rule.valid { color: #10B981; }

        .btn-save { 
            background: var(--sole-orange); color: white; border: none; padding: 14px; 
            border-radius: 12px; font-weight: 800; width: 100%; margin-top: 15px;
            text-transform: uppercase; letter-spacing: 1px; transition: 0.2s;
        }
        .btn-save:hover { background: #e65a00; transform: translateY(-1px); }

        /* FIXED NAVIGATION BUTTONS AREA */
        .bottom-nav { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 25px; 
            padding-top: 15px; 
            border-top: 1px solid #eee; 
        }
        .nav-item { 
            font-size: 0.8rem; 
            font-weight: 800; 
            color: #888; 
            text-decoration: none; 
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .nav-item:hover { color: var(--sole-orange); }
    </style>
</head>
<body>

<?php include_once '../includes/header.php'; ?>

<div class="page-container">
    <div class="auth-card">
        <i class="bi bi-shield-lock-fill icon-header"></i>
        <h2>New Password</h2>
        <p class="subtitle">Update your security settings</p>

        <?php if($error): ?>
            <div class="alert alert-danger py-2 small fw-bold mb-3"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success_message): ?>
            <div class="alert alert-success py-2 small fw-bold mb-3"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <span class="label-text">Create Password</span>
                <div class="input-box">
                    <i class="bi bi-key-fill"></i>
                    <input type="password" name="password" id="pwd" placeholder="Min. 8 characters" required>
                </div>
                
                <div class="rules-container">
                    <span class="rule" id="rule-len"><i class="bi bi-check-circle"></i> 8+ Characters</span>
                    <span class="rule" id="rule-up"><i class="bi bi-check-circle"></i> One Uppercase Letter</span>
                </div>
            </div>

            <div class="form-group">
                <span class="label-text">Confirm Password</span>
                <div class="input-box">
                    <i class="bi bi-shield-check"></i>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="btn-save">Save Changes</button>
            
            <div class="bottom-nav">
                <a href="password_assistant.php" class="nav-item">
                    <i class="bi bi-question-circle-fill"></i> Password Assistant
                </a>
                <a href="login.php" class="nav-item">
                    Back to Login <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const pwd = document.getElementById('pwd');
const ruleLen = document.getElementById('rule-len');
const ruleUp = document.getElementById('rule-up');

pwd.addEventListener('input', function() {
    const val = this.value;
    if(val.length >= 8) ruleLen.classList.add('valid');
    else ruleLen.classList.remove('valid');
    
    if(/[A-Z]/.test(val)) ruleUp.classList.add('valid');
    else ruleUp.classList.remove('valid');
});
</script>

<?php include_once '../includes/footer.php'; ?>
</body>
</html>