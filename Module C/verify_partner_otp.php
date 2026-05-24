<?php
//==== verify_partner_otp.php ====
session_start();
require_once '../includes/db_connection.php';

// Debug: log session start
error_log('[DEBUG] verify_partner_otp: session started. SESSION=' . print_r($_SESSION, true));

// 🚨 安全拦截：如果用户没有在 partner_with_us.php 填过资料（没有暂存 Session），直接踢回去
if (!isset($_SESSION['partner_temp_data']) || !isset($_SESSION['partner_otp'])) {
    // Debug: log why verification can't proceed
    error_log('[DEBUG] verify_partner_otp: missing session data. SESSION=' . print_r(
        isset($_SESSION) ? $_SESSION : [], true
    ));
    header("Location: partner_with_us.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_btn'])) {
    $user_entered_otp = trim($_POST['otp_code']);
    
    // 1. 检查 OTP 是否过期 (5分钟限制)
    if (time() > $_SESSION['partner_otp_expiry']) {
        $error = "OTP has expired. Please register again.";
        error_log('[DEBUG] verify_partner_otp: OTP expired. SESSION=' . print_r($_SESSION, true));
    } 
    // 2. 检查 OTP 是否正确
    else if ($user_entered_otp != $_SESSION['partner_otp']) {
        $error = "Invalid OTP code. Please try again.";
        error_log('[DEBUG] verify_partner_otp: OTP mismatch. Entered='. $user_entered_otp . ' SESSION_OTP=' . ($_SESSION['partner_otp'] ?? '')); 
    } 
    // 3. 🌟 OTP 验证成功，准备进 Database！
    else {
        // 从 Session 里拿出之前填的所有资料
        $data = $_SESSION['partner_temp_data'];

        // 执行 Insert Database 操作 -> 插入到 vendors 表（与前端提交流程保持一致）
        $status = 'pending';
        $created_at = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO vendors (brand, business_name, email, phone, reg_number, auth_doc_path, bank_name, bank_acc_no, bank_statement_path, warehouse_address, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssssssssss",
            $data['brand'],
            $data['business_name'],
            $data['email'],
            $data['phone'],
            $data['reg_number'],
            $data['auth_doc_path'],
            $data['bank_name'],
            $data['bank_acc_no'],
            $data['bank_doc_path'],
            $data['address'],
            $status,
            $created_at
        );

        if ($stmt->execute()) {
            // ✅ 注册彻底成功！清除 Session 中的敏感与暂存数据
            unset($_SESSION['partner_temp_data']);
            unset($_SESSION['partner_otp']);
            unset($_SESSION['partner_otp_expiry']);

            // 使用 JavaScript 弹窗提示并跳转到首页或你指定的成功页面
            echo <<<HTML
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'Verification successful!',
                        text: 'Your partner application has been submitted.',
                        confirmButtonText: 'OK',
                        timer: 4000,
                        timerProgressBar: true
                    }).then(function () {
                        window.location.href = '../index.php';
                    });
                });
            </script>
            HTML;
            exit();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

// 包含你的网站 Header
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
        max-width: 550px; /* OTP 验证界面不需要像注册页面那么宽，这样更好看 */
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
        font-size: 2.8rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -1.5px;
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
        font-size: 1.5rem; /* 让 OTP 输入的数字显得更大更明显 */
        letter-spacing: 8px;
    }
    .form-control:focus { 
        box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1); 
        border-color: var(--brand-orange); 
        background: #FFFFFF; 
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
        margin-top: 20px;
    }
    .btn-stealth-prime:hover { 
        background: #E66000; 
        transform: translateY(-2px); 
        box-shadow: 0 15px 30px rgba(255, 107, 0, 0.2); 
        color: white; 
    }
    .email-display {
        background: #F1F5F9;
        padding: 15px;
        border-radius: 12px;
        text-align: center;
        font-weight: 600;
        color: #334155;
        margin-bottom: 30px;
        border-left: 4px solid var(--brand-orange);
    }
</style>

<div class="container">
    <div class="reg-wrapper">
        <div class="reg-card">
            
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock" style="color: var(--brand-orange); font-size: 4rem;"></i>
                <h2 class="hero-title mt-3">Verify <span>OTP</span></h2>
                <p class="text-muted mt-2">Enter the verification code sent to your email.</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 small text-center mb-4" style="border-radius: 12px;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="email-display">
                <i class="bi bi-envelope-check me-2"></i> 
                <?php echo htmlspecialchars($_SESSION['partner_temp_data']['email'] ?? 'your email'); ?>
            </div>

            <form method="POST">
                <div class="form-group mb-4">
                    <label class="form-label">6-Digit Verification Code</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="text" 
                               name="otp_code" 
                               class="form-control text-center fw-bold" 
                               maxlength="6" 
                               inputmode="numeric" 
                               placeholder="------" 
                               required 
                               autofocus>
                    </div>
                </div>

                <button type="submit" name="verify_btn" class="btn btn-stealth-prime">
                    Verify & Submit
                </button>
            </form>
            
        </div>
    </div>
</div>

<script>
// --- OTP 纯数字限制 ---
// 防止用户粘贴英文字母或符号进 OTP 框
const otpInput = document.querySelector('input[name="otp_code"]');
if (otpInput) {
    otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
    });
}
</script>

<?php include_once '../includes/footer.php'; ?>