<?php
// active_account.php
session_start();
require_once '../includes/db_connection.php';

// 1. 接收 token
$token = $_GET['token'] ?? null;
$message = "";
$messageType = "";

if (!$token) { 
    die("<div style='text-align:center; padding:50px; font-family: Arial, sans-serif; color: #d33;'><h2>Invalid request. No token provided.</h2></div>"); 
}

// 2. 验证 Token
$check_token = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
$check_token->bind_param("s", $token);
$check_token->execute();
$result = $check_token->get_result();

if ($result->num_rows === 0) {
    die("<div style='text-align:center; padding:50px; font-family: Arial, sans-serif; color: #d33;'><h2>This activation link is invalid or has expired.</h2></div>");
}

$row = $result->fetch_assoc();
$email = $row['email'];
$check_token->close();

// 3. 处理表单提交 (后端严格验证)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Security mismatch: Passwords do not match!";
        $messageType = "error";
    } elseif (strlen($password) < 8) {
        $message = "Validation error: Minimum 8 characters required.";
        $messageType = "error";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $message = "Validation error: Password must include at least one uppercase letter.";
        $messageType = "error";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $message = "Validation error: Password must include at least one number.";
        $messageType = "error";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $message = "Validation error: Password must include at least one special character.";
        $messageType = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin SET Admin_Password = ?, Admin_Status = 'Active' WHERE Admin_Email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            $del_token = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $del_token->bind_param("s", $token);
            $del_token->execute();
            $del_token->close();

            $message = "Account activated successfully! You will be redirected to the login page.";
            $messageType = "success";
        } else {
            $message = "Error updating account.";
            $messageType = "error";
        }
        $stmt->close();
    }
}

include_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    body {
        font-family: Arial, "Segoe UI", sans-serif;
        background-color: #f8fafc; 
        margin: 0; padding: 0;
    }
    
    /* 核心居中容器 */
    .activation-wrapper {
        display: flex; justify-content: center; align-items: center;
        min-height: 80vh; padding: 20px;
    }
    
    .activation-card {
        background: #ffffff; width: 100%; max-width: 450px;
        padding: 40px 50px; border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); text-align: center; 
    }

    .activation-card h2 { color: #FF8C00; font-size: 26px; font-weight: bold; margin: 0 0 10px 0; }
    .subtitle { color: #64748B; font-size: 14px; margin-bottom: 25px; }

    .form-group { position: relative; margin-bottom: 20px; text-align: left; }
    
    /* 左侧图标 */
    .form-group i.input-icon { position: absolute; left: 15px; top: 15px; color: #94A3B8; font-size: 18px; }
    
    /* 右侧查看密码小眼睛图标 */
    .form-group i.toggle-password {
        position: absolute; right: 15px; top: 15px; 
        color: #94A3B8; font-size: 18px; cursor: pointer; transition: color 0.3s ease;
        z-index: 10;
    }
    .form-group i.toggle-password:hover { color: #FF8C00; }

    .form-control {
        width: 100%; 
        padding: 14px 45px 14px 45px; /* 左右两侧都留出45px空间给图标 */
        border: 1px solid #E2E8F0; border-radius: 10px; font-size: 15px;
        box-sizing: border-box; transition: all 0.3s ease; background-color: #F8FAFC;
    }
    .form-control:focus {
        background-color: #FFFFFF; border-color: #FF8C00; outline: none;
        box-shadow: 0 0 0 4px rgba(255, 140, 0, 0.1);
    }

    /* === 密码分析器样式（无边框，紧凑布局） === */
    .analyzer-container { text-align: left; margin-top: -10px; margin-bottom: 20px; padding: 0 5px; }
    .progress-bar-container { height: 6px; background: #E2E8F0; border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
    .progress-bar-fill { height: 100%; width: 0%; background: #EF4444; transition: width 0.3s ease, background 0.3s ease; }
    .strength-text { font-size: 12px; font-weight: bold; color: #64748B; margin-bottom: 10px; text-transform: uppercase; }
    
    .req-list { list-style: none; padding: 0; margin: 0; font-size: 13px; color: #64748B; }
    .req-list li { margin-bottom: 6px; display: flex; align-items: center; transition: color 0.3s ease; }
    .req-list li i { margin-right: 8px; font-size: 15px; }
    
    .req-list li.valid { color: #FF8C00; font-weight: bold; }
    .req-list li.valid i { color: #FF8C00; }
    .req-list li.valid i::before { content: "\F26E"; } /* bi-check-circle-fill */
    .req-list li.invalid i::before { content: "\F623"; } /* bi-x-circle */
    .req-list li.invalid i { color: #94A3B8; }

    .match-status { font-size: 13px; font-weight: bold; text-align: right; margin-top: 5px; display: none; }

    .btn-activate {
        background-color: #FF8C00; color: white; width: 100%; padding: 15px;
        border: none; border-radius: 10px; font-size: 16px; font-weight: bold;
        cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px;
        margin-top: 10px;
    }
    .btn-activate:hover { background-color: #e67e00; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 140, 0, 0.2); }
</style>

<div class="activation-wrapper">
    <div class="activation-card">
        
        <h2>Set Admin Password</h2>
        <p class="subtitle">Secure your newly activated account.</p>
        
        <?php if ($messageType !== 'success'): ?>
        <form id="activationForm" method="POST" action="active_account.php?token=<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="password" id="passInput" class="form-control" placeholder="New Password" required>
                <i class="bi bi-eye-slash toggle-password" data-target="passInput"></i>
            </div>

            <div class="analyzer-container">
                <div class="progress-bar-container">
                    <div id="strengthBar" class="progress-bar-fill"></div>
                </div>
                <div id="strengthText" class="strength-text">Password Strength</div>
                
                <ul class="req-list">
                    <li id="req-length" class="invalid"><i class="bi bi-x-circle"></i> 8+ Characters</li>
                    <li id="req-upper" class="invalid"><i class="bi bi-x-circle"></i> 1 Uppercase Letter</li>
                    <li id="req-number" class="invalid"><i class="bi bi-x-circle"></i> 1 Number</li>
                    <li id="req-special" class="invalid"><i class="bi bi-x-circle"></i> 1 Special Symbol</li>
                </ul>
            </div>

            <div class="form-group" style="margin-bottom: 5px;">
                <i class="bi bi-shield-check input-icon"></i>
                <input type="password" name="confirm_password" id="confirmInput" class="form-control" placeholder="Confirm New Password" required>
                <i class="bi bi-eye-slash toggle-password" data-target="confirmInput"></i>
            </div>
            <div id="matchStatus" class="match-status"></div><br>

            <button type="submit" id="submitBtn" class="btn-activate">Save & Activate</button>
            
        </form>
        <?php else: ?>
            <div style="text-align:center; padding: 20px 0; color: #10B981;">
                <i class="bi bi-check-circle" style="font-size: 60px;"></i>
                <p style="margin-top:15px; font-weight:bold;">Activation Completed</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const passInput = document.getElementById('passInput');
    const confirmInput = document.getElementById('confirmInput');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const matchStatus = document.getElementById('matchStatus');

    // 1. 小眼睛点击查看密码逻辑
    const toggleIcons = document.querySelectorAll('.toggle-password');
    toggleIcons.forEach(icon => {
        icon.addEventListener('click', function() {
            // 获取目标 input 的 id
            const targetId = this.getAttribute('data-target');
            const inputField = document.getElementById(targetId);
            
            // 切换 password / text 属性
            if (inputField.type === 'password') {
                inputField.type = 'text';
                this.classList.remove('bi-eye-slash');
                this.classList.add('bi-eye'); // 变成睁眼图标
                this.style.color = '#FF8C00'; // 睁开时变成主色调
            } else {
                inputField.type = 'password';
                this.classList.remove('bi-eye');
                this.classList.add('bi-eye-slash'); // 变成闭眼图标
                this.style.color = '#94A3B8'; // 恢复灰色
            }
        });
    });

    const reqs = {
        length: document.getElementById('req-length'),
        upper: document.getElementById('req-upper'),
        number: document.getElementById('req-number'),
        special: document.getElementById('req-special')
    };

    function validatePassword() {
        if (!passInput) return;
        const val = passInput.value;
        let score = 0;

        // 验证逻辑
        if (val.length >= 8) { reqs.length.className = 'valid'; score++; } 
        else { reqs.length.className = 'invalid'; }

        if (/[A-Z]/.test(val)) { reqs.upper.className = 'valid'; score++; } 
        else { reqs.upper.className = 'invalid'; }

        if (/[0-9]/.test(val)) { reqs.number.className = 'valid'; score++; } 
        else { reqs.number.className = 'invalid'; }

        if (/[^A-Za-z0-9]/.test(val)) { reqs.special.className = 'valid'; score++; } 
        else { reqs.special.className = 'invalid'; }

        // 进度条与文字UI更新
        if (val.length === 0) {
            strengthBar.style.width = '0%';
            strengthText.innerText = 'Password Strength';
            strengthText.style.color = '#64748B';
        } else if (score <= 2) {
            strengthBar.style.width = '33%';
            strengthBar.style.background = '#EF4444'; 
            strengthText.innerText = 'Weak';
            strengthText.style.color = '#EF4444';
        } else if (score === 3) {
            strengthBar.style.width = '66%';
            strengthBar.style.background = '#F59E0B'; 
            strengthText.innerText = 'Medium';
            strengthText.style.color = '#F59E0B';
        } else if (score === 4) {
            strengthBar.style.width = '100%';
            strengthBar.style.background = '#10B981'; 
            strengthText.innerText = 'Strong - Ready to go!';
            strengthText.style.color = '#10B981';
        }
        
        checkMatch();
    }

    function checkMatch() {
        if (!passInput || !confirmInput) return;
        const p1 = passInput.value;
        const p2 = confirmInput.value;

        if (p2.length === 0) {
            matchStatus.style.display = "none";
        } else if (p1 === p2) {
            matchStatus.innerHTML = "PASSWORD VERIFIED ✓";
            matchStatus.style.color = "#10B981";
            matchStatus.style.display = "block";
        } else {
            matchStatus.innerHTML = "PASSWORD MISMATCH ✗";
            matchStatus.style.color = "#EF4444";
            matchStatus.style.display = "block";
        }
    }

    if (passInput) passInput.addEventListener('input', validatePassword);
    if (confirmInput) confirmInput.addEventListener('input', checkMatch);

    // 提交拦截验证
    const form = document.getElementById('activationForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            const val = passInput.value;
            const p2 = confirmInput.value;
            let isValid = val.length >= 8 && /[A-Z]/.test(val) && /[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val) && val === p2;

            if (!isValid) {
                event.preventDefault(); 
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Password',
                    text: 'Please ensure all password requirements are checked (green) and passwords match.',
                    confirmButtonColor: '#FF8C00'
                });
            }
        });
    }
});
</script>

<?php if ($message): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?php echo $messageType; ?>',
            title: '<?php echo $messageType === "success" ? "Success!" : "Validation Error"; ?>',
            text: '<?php echo addslashes($message); ?>',
            confirmButtonColor: '#FF8C00'
        }).then((result) => {
            <?php if ($messageType === 'success'): ?>
            if (result.isConfirmed || result.isDismissed) {
                window.location.href = 'admin_login.php';
            }
            <?php endif; ?>
        });
    });
</script>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>