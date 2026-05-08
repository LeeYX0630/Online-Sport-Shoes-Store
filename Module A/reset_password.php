<?php
/**
 * STEALTH SPORT SHOES - SECURITY RESET
 * SUCCESS STATE: Soft Green Pill Alert (Matches Reference Image)
 */
session_start();
ob_start();

require_once '../includes/db_connection.php';

$error = "";
$success = false; 
$token_valid = isset($_SESSION['reset_user_id']) || isset($_SESSION['reset_data']['email']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    if ($pass1 !== $pass2) {
        $error = "Security mismatch: Password confirmation failed.";
    } elseif (strlen($pass1) < 8) {
        $error = "Validation error: Minimum 8 characters required.";
    } else {
        $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
        
        if (isset($_SESSION['reset_user_id'])) {
            $user_id = $_SESSION['reset_user_id'];
            $update = $conn->prepare("UPDATE user SET User_Password = ? WHERE User_Id = ?");
            $update->bind_param("si", $hashed_password, $user_id);
        } else {
            $email = $_SESSION['reset_data']['email'];
            $update = $conn->prepare("UPDATE user SET User_Password = ? WHERE User_Email = ?");
            $update->bind_param("ss", $hashed_password, $email);
        }

        if ($update->execute()) {
            $success = true;
            session_unset(); 
            session_destroy();
            header("refresh:4;url=login.php");
        } else {
            $error = "System synchronization error: " . $conn->error;
        }
    }
}
include_once '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Portal | Sole 2 Soul</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root { 
            --brand-orange: #FF6B00; 
            --brand-blue: #0F172A;
            --bg-canvas: #F1F5F9;
            --success-bg: #D1E7DD; /* Matches your image */
            --success-text: #0F5132;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-canvas); }

        .modular-wrapper {
            max-width: 1000px;
            margin: 60px auto;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
            padding: 0 20px;
        }

        .card-module {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .section-title { font-weight: 800; font-size: 0.8rem; color: #64748B; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: block; }

        /* Left Side Status */
        .status-pill {
            display: flex; align-items: center; gap: 12px; padding: 14px 18px;
            border-radius: 16px; margin-bottom: 10px; background: #F8FAFC; border: 2px solid transparent;
        }
        .active-weak { background: #FEF2F2 !important; border-color: #FECACA !important; color: #B91C1C !important; }
        .active-medium { background: #FFFBEB !important; border-color: #FDE68A !important; color: #92400E !important; }
        .active-strong { background: #F0FDF4 !important; border-color: #BBF7D0 !important; color: #065F46 !important; }

        /* Form Styling */
        .form-card { padding: 50px; }
        .form-header { margin-bottom: 30px; border-bottom: 2px solid #F1F5F9; padding-bottom: 20px; }
        .form-header h2 { font-weight: 800; color: var(--brand-blue); display: flex; align-items: center; gap: 12px; }
        .header-icon { color: var(--brand-orange); font-size: 2.2rem; }
        
        .field-wrapper {
            background: #F8FAFC; border-radius: 16px; padding: 4px 18px;
            display: flex; align-items: center; border: 2px solid #E2E8F0; transition: 0.3s;
        }
        .field-wrapper:focus-within { border-color: var(--brand-orange); background: white; }
        .field-wrapper input { border: none; background: transparent; padding: 14px; width: 100%; outline: none; font-weight: 600; }

        .btn-main {
            background: var(--brand-orange); color: white; border: none; padding: 18px;
            border-radius: 16px; font-weight: 800; width: 100%; text-transform: uppercase;
            letter-spacing: 1px; transition: 0.4s; margin-top: 10px;
        }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(255, 107, 0, 0.2); }

        /* IMAGE STYLE SUCCESS BOX */
        .success-pill-box {
            background-color: var(--success-bg);
            color: var(--success-text);
            padding: 22px 30px;
            border-radius: 18px;
            text-align: center;
            margin-bottom: 35px;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.5;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .assistance-link {
            display: inline-block; margin-top: 25px; color: var(--brand-blue);
            font-weight: 700; font-size: 0.85rem; text-decoration: none; opacity: 0.6; transition: 0.3s;
        }
        .assistance-link:hover { opacity: 1; color: var(--brand-orange); }

        @media (max-width: 900px) { .modular-wrapper { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="modular-wrapper">
    <div class="side-column">
        <div class="card-module mb-4" style="background: #FFF9F5; border: 1px solid #FFEDD5;">
            <span class="section-title"><i class="bi bi-shield-lock-fill"></i> Security Guide</span>
            <p style="font-size: 0.85rem; color: #9A3412; line-height: 1.6; font-weight: 500;">
                Creating a robust password protects your account from unauthorized access. Mix letters, numbers, and symbols.
            </p>
        </div>

        <div class="card-module">
            <span class="section-title">Strength Analysis</span>
            <div class="status-pill" id="lvl-weak"><i class="bi bi-shield-x"></i><span>WEAK</span></div>
            <div class="status-pill" id="lvl-medium"><i class="bi bi-shield-minus"></i><span>MEDIUM</span></div>
            <div class="status-pill" id="lvl-strong"><i class="bi bi-shield-check"></i><span>STRONG</span></div>
        </div>
    </div>

    <div class="form-column">
        <div class="card-module form-card">
            
            <div class="form-header">
                <h2><i class="bi bi-shield-lock header-icon"></i> New Credentials</h2>
                <p class="text-muted small mb-0">Established your new account access keys below.</p>
            </div>

            <?php if($success): ?>
                <div class="success-pill-box">
                    Your password was updated successfully! <br> 
                    Please wait while we redirect you to the login portal.
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 small py-2 mb-4" style="border-radius:12px;">
                    <i class="bi bi-exclamation-octagon me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="resetForm" <?php if($success) echo 'style="opacity: 0.3; pointer-events: none;"'; ?>>
                <div class="mb-4">
                    <label class="fw-bold small text-secondary mb-2 d-block">New Password</label>
                    <div class="field-wrapper">
                        <i class="bi bi-key text-muted"></i>
                        <input type="password" name="password" id="main-pwd" placeholder="Min. 8 characters" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="fw-bold small text-secondary mb-2 d-block">Verify Password</label>
                    <div class="field-wrapper" id="verify-border">
                        <i class="bi bi-check2-all text-muted"></i>
                        <input type="password" name="confirm_password" id="confirm-pwd" placeholder="Confirm your entry" required>
                    </div>
                    <div id="match-status" style="font-weight:800; font-size:0.7rem; margin-top:10px; display:none;"></div>
                </div>

                <button type="submit" class="btn-main">Save Changes</button>

                <div class="text-center">
                    <a href="password_assistant.php" class="assistance-link">
                        <i class="bi bi-patch-question me-1"></i> Need Password Assistance?
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const pwdInput = document.getElementById('main-pwd');
const confInput = document.getElementById('confirm-pwd');
const matchStatus = document.getElementById('match-status');
const verifyBorder = document.getElementById('verify-border');

const levels = {
    weak: document.getElementById('lvl-weak'),
    medium: document.getElementById('lvl-medium'),
    strong: document.getElementById('lvl-strong')
};

function monitorSecurity() {
    if(!pwdInput) return;
    const p1 = pwdInput.value;
    const p2 = confInput.value;

    const score = [p1.length >= 8, /[A-Z]/.test(p1), /[0-9]/.test(p1), /[^A-Za-z0-9]/.test(p1)].filter(Boolean).length;

    Object.values(levels).forEach(l => l.classList.remove('active-weak', 'active-medium', 'active-strong'));

    if (p1 !== "") {
        if (score <= 1) levels.weak.classList.add('active-weak');
        else if (score <= 3) levels.medium.classList.add('active-medium');
        else levels.strong.classList.add('active-strong');
    }

    if (p2 === "") {
        matchStatus.style.display = "none";
        verifyBorder.style.borderColor = "#E2E8F0";
    } else if (p1 === p2) {
        matchStatus.innerText = "IDENTITY VERIFIED ✓";
        matchStatus.style.color = "#10B981";
        matchStatus.style.display = "block";
        verifyBorder.style.borderColor = "#10B981";
    } else {
        matchStatus.innerText = "SECURITY MISMATCH ✗";
        matchStatus.style.color = "#EF4444";
        matchStatus.style.display = "block";
        verifyBorder.style.borderColor = "#EF4444";
    }
}

if(pwdInput){
    pwdInput.addEventListener('input', monitorSecurity);
    confInput.addEventListener('input', monitorSecurity);
}
</script>
</body>
</html>
<?php include_once '../includes/footer.php'; ?>