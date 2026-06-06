<?php
// ==== send_partner_otp.php ====
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 把函数名改成 sendPartnerOTP，专门给 partner 流程用
function sendPartnerOTP($recipientEmail) {
    // 1. 生成 6 位随机数字 OTP
    $otp = sprintf("%06d", mt_rand(1, 999999)); // 使用 sprintf 确保首位是 0 时也能保持 6 位
    
    // 2. 🌟 关键修改：存入特定的 Partner Session，并设置 5 分钟 (300秒) 的过期时间戳
    $_SESSION['partner_otp'] = $otp;
    $_SESSION['partner_otp_expiry'] = time() + 300;
    // 立即写入并释放 session 锁，确保在短时间内可用（避免在重定向/并发请求中丢失）
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Debug: log masked OTP for troubleshooting (remove in production)
    error_log("[DEBUG] Partner OTP generated for {$recipientEmail}: {$otp}");

    $mail = new PHPMailer(true);

    try {
        // --- 服务器设置 ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // --- 收件人 ---
        $mail->setFrom('sportshoes.system@gmail.com', 'STRYDEX SPORT SHOES STORE');
        $mail->addAddress($recipientEmail);

        // --- 内容 (🌟 修改为 Partner 专属的话术) ---
        $mail->isHTML(true);
        $mail->Subject = 'Partner Application OTP Verification';
        $mail->Body    = "
            <div style='font-family: 'Segoe UI', 'Inter', sans-serif; border: 1px solid #eee; padding: 20px; border-radius: 10px;'>
                <h2 style='color: #FF8C00;'>Partner Application Verification</h2>
                <p>Hello,</p>
                <p>Thank you for applying to partner with STRYDEX Sport Shoes Store.</p>
                <p>Your one-time password (OTP) to verify your application is:</p>
                <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; color: #333; letter-spacing: 5px; border: 1px dashed #FF6B00;'>
                    $otp
                </div>
                <p style='margin-top: 20px; font-size: 12px; color: #888;'>This code will expire in 5 minutes. If you did not request this, please ignore this email.</p>
            </div>";

        $mail->send();
        return true; // 发送成功返回 true
    } catch (Exception $e) {
        return false; // 发送失败返回 false
    }
}