<?php
/**
 * STEALTH SPORT SHOES - RESET PASSWORD
 * Module A: User Permissions & Profile
 */
session_start();
ob_start();

if (file_exists('../includes/db_connection.php')) {
    require_once '../includes/db_connection.php';
} else {
    require_once '../includes/db_connections.php';
}

$error = "";
$token_valid = isset($_SESSION['reset_user_id']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    if ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
        $user_id = $_SESSION['reset_user_id'];

        $update = $conn->prepare("UPDATE USER SET User_Password = ? WHERE User_ID = ?");
        $update->bind_param("si", $hashed_password, $user_id);

        if ($update->execute()) {
            session_unset();
            session_destroy();
            echo "<script>alert('Password updated successfully!'); window.location.href='login.php';</script>";
            exit();
        } else {
            $error = "Database update failed.";
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
            height: 100vh; display: flex; align-items: center; justify-content: center;
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
        }

        /* Strength UI */
        .strength-meter { font-size: 0.8rem; font-weight: 800; display: block; margin-top: 12px; }
        .guidance-box {
            background: #F8FAFC; border-radius: 15px; padding: 15px; margin-top: 10px; border: 1px solid #EDF2F7;
        }
        .tip-item { font-size: 0.75rem; color: #64748B; display: block; margin-bottom: 5px; transition: 0.3s; }
        /* Style for when a requirement is met */
        .tip-item.met { color: var(--success); font-weight: 600; }
        .tip-item.met i { margin-right: 5px; }
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

            <button type="submit" class="btn-update" id="submitBtn">SAVE CHANGES</button>
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
    
    // Check requirements
    const hasLen = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum = /[0-9]/.test(val);
    const hasSym = /[^A-Za-z0-9]/.test(val);

    // Update checklist visuals
    updateTip(tips.len, hasLen);
    updateTip(tips.upper, hasUpper);
    updateTip(tips.num, hasNum);
    updateTip(tips.sym, hasSym);

    // Calculate score for Label
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