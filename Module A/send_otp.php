<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sendOTP($recipientEmail) {
    // 1. 生成 6 位随机数字 OTP
    $otp = rand(100000, 999999);
    
    // 2. 将 OTP 存入 Session 并记录生成时间（用于过期校验，如 5 分钟内有效）
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();

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

        // --- 内容 ---
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Verification Code';
        $mail->Body    = "
            <div style='font-family: sans-serif; border: 1px solid #eee; padding: 20px; border-radius: 10px;'>
                <h2 style='color: #FF6B00;'>Security Verification</h2>
                <p>Hello,</p>
                <p>Your one-time password (OTP) for account verification is:</p>
                <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; color: #333; letter-spacing: 5px; border: 1px dashed #FF6B00;'>
                    $otp
                </div>
                <p style='margin-top: 20px; font-size: 12px; color: #888;'>This code will expire in 5 minutes. If you did not request this, please ignore this email.</p>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

// Only handle direct POST requests when this file is executed directly,
// not when it's included via require/include (avoids accidental echoes).
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && isset($_POST['email'])) {
    $result = sendOTP($_POST['email']);
    if ($result === true) {
        echo "OTP sent successfully!";
    } else {
        echo "Error: " . $result;
    }
}