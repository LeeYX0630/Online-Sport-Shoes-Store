<?php
// send_receipt_handler.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

function sendOrderReceiptEmail($order_id, $conn) {
    // 1. 获取订单与用户信息
    $sql_order = "SELECT o.*, u.User_Name, u.User_Email 
                  FROM `ORDER` o 
                  JOIN USER u ON o.User_Id = u.User_Id 
                  WHERE o.Order_Id = '$order_id'";
    $order_res = $conn->query($sql_order);
    $order = $order_res->fetch_assoc();
    $user_email = $order['User_Email'];

    // 2. 获取商品明细
    $sql_items = "SELECT od.*, p.Pro_Name 
                  FROM ORDER_DETAIL od 
                  JOIN product p ON od.Pro_Id = p.Pro_Id 
                  WHERE od.Order_Id = '$order_id'";
    $items_res = $conn->query($sql_items);

    // 3. 构建 HTML 表格内容
    $items_html = "";
    while($item = $items_res->fetch_assoc()) {
        $items_html .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$item['Pro_Name']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$item['Order_Qty']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>RM ".number_format($item['Order_Subtotal'], 2)."</td>
            </tr>";
    }

    // 4. 配置 PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL; 
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('sportshoes.system@gmail.com', 'Sport Shoes Store');
        $mail->addAddress($user_email, $order['User_Name']);

        // 5. 设置邮件 HTML 模版 (模仿专业收据)
        $mail->isHTML(true);
        $mail->Subject = "Official Receipt - Order #$order_id";
        $mail->Body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #FF6B00;'>Thank you for your order!</h2>
            <p>Hi <b>{$order['User_Name']}</b>, your payment was successful. Here is your receipt.</p>
            
            <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                <thead>
                    <tr style='background: #f8f9fa;'>
                        <th style='padding: 10px; text-align: left;'>Item</th>
                        <th style='padding: 10px; text-align: center;'>Qty</th>
                        <th style='padding: 10px; text-align: right;'>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    $items_html
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='2' style='padding: 15px 10px; font-weight: bold; text-align: right;'>Grand Total:</td>
                        <td style='padding: 15px 10px; font-weight: bold; text-align: right; color: #FF6B00; font-size: 18px;'>RM ".number_format($order['Order_Amount'], 2)."</td>
                    </tr>
                </tfoot>
            </table>
            
            <div style='background: #fdf2f8; padding: 15px; border-radius: 8px;'>
                <p style='margin: 0; font-size: 14px;'><b>Shipping Address:</b><br>{$order['Order_Shipping_Addr']}</p>
            </div>
            
            <p style='font-size: 12px; color: #999; margin-top: 30px; text-align: center;'>
                This is an automated receipt. You can also download a PDF version from your dashboard.
            </p>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // 如果发送失败，记录错误但不要中断下单流程
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}