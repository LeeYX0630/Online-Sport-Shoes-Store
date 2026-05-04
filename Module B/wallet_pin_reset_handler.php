<?php
// Module A/wallet_pin_reset_handler.php
session_start();
require_once '../includes/db_connection.php';
use PHPMailer\PHPMailer\PHPMailer;
require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

header('Content-Type: application/json');
$uid = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'request_otp') {
    // 1. 生成 6 位随机数 OTP
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes")); // 5分钟有效期

    // 2. 存入数据库
    $stmt = $conn->prepare("UPDATE `USER` SET User_OTP = ?, User_OTP_Expiry = ? WHERE User_Id = ?");
    $stmt->bind_param("ssi", $otp, $expiry, $uid);
    $stmt->execute();

    // 3. 获取用户邮箱并发邮件
    $user_res = $conn->query("SELECT User_Email, User_Name FROM `USER` WHERE User_Id = '$uid'");
    $user = $user_res->fetch_assoc();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_EMAIL; 
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom(SMTP_EMAIL, 'SS Sport Security');
        $mail->addAddress($user['User_Email'], $user['User_Name']);
        $mail->isHTML(true);
        $mail->Subject = "Your Wallet Reset OTP";
        $mail->Body = "<h3>Security Verification</h3><p>Your OTP for resetting Wallet PIN is: <b style='font-size:24px; color:#FF6B00;'>$otp</b></p><p>This code expires in 5 minutes.</p>";
        $mail->send();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Email failed.']);
    }
} 

elseif ($action === 'verify_and_reset') {
    $input_otp = $_POST['otp'] ?? '';
    $new_pin = $_POST['new_pin'] ?? '';

    // 校验 OTP 是否有效
    $stmt = $conn->prepare("SELECT User_Id FROM `USER` WHERE User_Id = ? AND User_OTP = ? AND User_OTP_Expiry > NOW()");
    $stmt->bind_param("is", $uid, $input_otp);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        // OTP 正确，更新 PIN (同样使用 hash 加密)[cite: 46]
        $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE `USER` SET User_PIN = ?, User_OTP = NULL, User_OTP_Expiry = NULL WHERE User_Id = ?");
        $update->bind_param("si", $hashed_pin, $uid);
        $update->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
    }
}