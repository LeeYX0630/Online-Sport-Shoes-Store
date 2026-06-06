<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) die("Unauthorized");

// helper: send email using PHPMailer config (reuses mail_config.php)
require_once '../includes/PHPMailer/Exception.php';
require_once '../includes/PHPMailer/PHPMailer.php';
require_once '../includes/PHPMailer/SMTP.php';
require_once '../includes/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMailHtml($to, $subject, $html) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(SMTP_EMAIL, 'STRYDEX SPORT SHOES STORE');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$vendor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = $_GET['action'] ?? '';

// detect vendors status column name (Vendor_Status or Status)
$check_col = $conn->query("SHOW COLUMNS FROM `vendors` LIKE 'Vendor_Status'");
$status_field = ($check_col && $check_col->num_rows > 0) ? 'Vendor_Status' : 'Status';

if ($action === 'approve') {
    // Only on approve: migrate vendor -> admin + brand and mark vendor as Approved
    $conn->begin_transaction();
    try {
        $stmtV = $conn->prepare("SELECT * FROM vendors WHERE vendor_id = ? LIMIT 1");
        $stmtV->bind_param("i", $vendor_id);
        $stmtV->execute();
        $resV = $stmtV->get_result();
        if (!$resV || $resV->num_rows === 0) throw new Exception('Vendor not found');
        $vendor = $resV->fetch_assoc();

        // 1) insert admin (Admin_Level = 3, Admin_Status = 'Unactive')
        $insAdmin = $conn->prepare("INSERT INTO admin (Admin_Name, Admin_Email, Admin_Level, Admin_Status, Vendor_Id) VALUES (?, ?, ?, ?, ?)");
        $level = 3;
        $admin_status = 'Unactive';
        $vendor_id = $vendor['vendor_id']; // assuming vendor_id is the foreign key in admin table  
        $insAdmin->bind_param("ssisi", $vendor['business_name'], $vendor['email'], $level, $admin_status, $vendor_id);
        if (!$insAdmin->execute()) throw new Exception('Failed to create admin: ' . $conn->error);
        // 1) 插入 admin 后...
        $new_admin_id = $conn->insert_id;

        // --- 新增：生成 24 小时有效的激活 Token ---
        $token = bin2hex(random_bytes(32)); 
        $expires_at = date("Y-m-d H:i:s", strtotime("+24 hours"));

        // 假设你使用 password_resets 表存储该 token（或者建议新建一个表）
        $stmt_token = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt_token->bind_param("sss", $vendor['email'], $token, $expires_at);
        $stmt_token->execute();

        // 2) insert brand (Brand_Status = 'Active')
        $insBrand = $conn->prepare("INSERT INTO brand (Brand_Name, Admin_Id, Brand_Status) VALUES (?, ?, ?)");
        $brand_status = 'Active';
        $brand_name_val = $vendor['brand'] ?? $vendor['business_name'];
        $insBrand->bind_param("sis", $brand_name_val, $new_admin_id, $brand_status);
        if (!$insBrand->execute()) throw new Exception('Failed to create brand: ' . $conn->error);

        // 3) update vendor status to Approved
        $upd = $conn->prepare("UPDATE vendors SET `$status_field` = ? WHERE vendor_id = ?");
        $approved_label = 'Approved';
        $upd->bind_param("si", $approved_label, $vendor_id);
        if (!$upd->execute()) throw new Exception('Failed to update vendor status: ' . $conn->error);

        $conn->commit();

        // send approval email with activation link
        $to = $vendor['email'];
        $subject = 'Activate your account';
        $activateUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/active_account.php?token=" . $token;
        $html = "
<div style='font-family: sans-serif; max-width: 700px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; color: #444; text-align: center;'>
    <div style='background-color: #000; padding: 30px;'>
        <h1 style='color: #FF6B00; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;'>STRYDEX Sport Shoes Store</h1>
        <p style='color: #fff; margin: 5px 0 0; font-size: 12px; opacity: 0.8;'>Multimedia University, Melaka, Malaysia | +60 12-345 6789</p>
    </div>
    <div style='padding: 20px;'>
        <p>Thank you for joining as our partner. Please click the button below to activate your account:</p>
        <p style='margin: 20px 0;'>
            <a href='" . htmlspecialchars($activateUrl) . "' style='display: inline-block; background: #FF6B00; color: #fff; padding: 10px 16px; border-radius: 8px; text-decoration: none;'>Activate Your Account</a>
        </p>
    </div>
</div>";
        @sendMailHtml($to, $subject, $html);

        echo "<script>alert('Vendor approved and admin created.'); window.location.href='admin_manage_admins.php';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>';
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo "</head><body><script>setTimeout(function(){Swal.fire({title:'Error',text:'" . addslashes($e->getMessage()) . "',icon:'error',confirmButtonColor:'#d33'}).then(function(){window.location.href='admin_manage_admins.php';});},100);</script></body></html>";
        exit();
    }

} else {
    // Reject flow: update vendor status and send rejection email — do not create admin/brand
    try {
        $stmtV = $conn->prepare("SELECT * FROM vendors WHERE vendor_id = ? LIMIT 1");
        $stmtV->bind_param("i", $vendor_id);
        $stmtV->execute();
        $resV = $stmtV->get_result();
        if ($resV && $resV->num_rows > 0) {
            $vendor = $resV->fetch_assoc();
            $upd = $conn->prepare("UPDATE vendors SET `$status_field` = ? WHERE vendor_id = ?");
            $rej = 'Rejected';
            $upd->bind_param("si", $rej, $vendor_id);
            $upd->execute();

            // send rejection email
            $to = $vendor['email'];
            $subject = 'Partner Application - Outcome';
        $html = "
            <div style='font-family: sans-serif; max-width: 700px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; color: #444; text-align: center;'>
        <div style='background-color: #000; padding: 30px;'>
            <h1 style='color: #FF6B00; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;'>STRYDEX Sport Shoes Store</h1>
            <p style='color: #fff; margin: 5px 0 0; font-size: 12px; opacity: 0.8;'>Multimedia University, Melaka, Malaysia | +60 12-345 6789</p>
        </div>
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; text-align: center;'>
            <h3 style='color:#d33;'>Application Unsuccessful</h3>
            <p>We are sorry to inform you that your application to become a partner was not successful. Unfortunately, you do not meet our requirements at this time.</p>
            <p>Thank you for your interest.</p>
        </div>";
            @sendMailHtml($to, $subject, $html);
        }
        echo "<script>alert('Vendor rejected and notification sent.'); window.location.href='admin_manage_admins.php';</script>";
        exit();
    } catch (Exception $e) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>';
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo "</head><body><script>setTimeout(function(){Swal.fire({title:'Error',text:'" . addslashes($e->getMessage()) . "',icon:'error',confirmButtonColor:'#d33'}).then(function(){window.location.href='admin_manage_admins.php';});},100);</script></body></html>";
        exit();
    }
}

$conn->close();
?>