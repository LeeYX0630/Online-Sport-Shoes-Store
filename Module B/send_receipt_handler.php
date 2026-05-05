<?php
// send_receipt_handler.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

/**
 * 发送详细的 HTML 订单收据邮件
 */
function sendOrderReceiptEmail($order_id, $conn) {
    // 1. 获取订单总表与用户资料 (包含日期和支付状态)
    $sql_order = "SELECT o.*, u.User_Name, u.User_Email, u.User_Phone 
                  FROM `ORDER` o 
                  JOIN USER u ON o.User_Id = u.User_Id 
                  WHERE o.Order_Id = '$order_id'";
    $order_res = $conn->query($sql_order);
    if (!$order_res || $order_res->num_rows == 0) return false;
    
    $order = $order_res->fetch_assoc();
    $user_email = $order['User_Email'];
    $order_date = date('d M Y, h:i A', strtotime($order['Order_Date']));

    // 2. 获取商品明细 (加入单价计算)
    $sql_items = "SELECT od.*, p.Pro_Name, p.Pro_Price 
                  FROM ORDER_DETAIL od 
                  JOIN product p ON od.Pro_Id = p.Pro_Id 
                  WHERE od.Order_Id = '$order_id'";
    $items_res = $conn->query($sql_items);

    // 3. 构建商品表格 HTML
    $items_html = "";
    while($item = $items_res->fetch_assoc()) {
        $unit_price = $item['Order_Subtotal'] / $item['Order_Qty'];
        $items_html .= "
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #eee; font-size: 14px;'>
                    <strong style='color: #333;'>{$item['Pro_Name']}</strong><br>
                    <small style='color: #888;'>Item ID: #{$item['Pro_Id']}</small>
                </td>
                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center; font-size: 14px;'>RM " . number_format($unit_price, 2) . "</td>
                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center; font-size: 14px;'>{$item['Order_Qty']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right; font-size: 14px; font-weight: bold;'>RM " . number_format($item['Order_Subtotal'], 2) . "</td>
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
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('sportshoes.system@gmail.com', 'SS SPORT SHOES STORE');
        $mail->addAddress($user_email, $order['User_Name']);

        // 5. 设置邮件 HTML 模版
        $mail->isHTML(true);
        $mail->Subject = "Official Tax Invoice - Order #$order_id";
        
        $mail->Body = "
        <div style='font-family: \"Segoe UI\", Helvetica, Arial, sans-serif; max-width: 700px; margin: auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; color: #444;'>
            
            <div style='background-color: #000; padding: 30px; text-align: center;'>
                <h1 style='color: #FF6B00; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px;'>SS Sport Shoes Store</h1>
                <p style='color: #fff; margin: 5px 0 0; font-size: 12px; opacity: 0.8;'>Multimedia University, Melaka, Malaysia | +60 12-345 6789</p>
            </div>

            <div style='padding: 30px;'>
                <h2 style='text-align: center; color: #333; text-transform: uppercase; border-bottom: 2px solid #FF6B00; padding-bottom: 10px; margin-bottom: 25px;'>Official Receipt</h2>

                <table style='width: 100%; margin-bottom: 30px; line-height: 1.6;'>
                    <tr>
                        <td style='vertical-align: top; width: 50%;'>
                            <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase; font-weight: bold;'>Billed To:</p>
                            <p style='margin: 5px 0; font-size: 15px;'><strong>{$order['User_Name']}</strong><br>
                            {$order['User_Email']}<br>
                            {$order['User_Phone']}</p>
                        </td>
                        <td style='vertical-align: top; width: 50%; text-align: right;'>
                            <p style='margin: 0; font-size: 14px;'><strong>Order ID:</strong> #$order_id</p>
                            <p style='margin: 0; font-size: 14px;'><strong>Date:</strong> $order_date</p>
                            <p style='margin: 0; font-size: 14px;'><strong>Status:</strong> <span style='color: #198754; font-weight: bold;'>PAID</span></p>
                        </td>
                    </tr>
                </table>

                <div style='background-color: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid #FF6B00;'>
                    <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase; font-weight: bold;'>Shipping Address:</p>
                    <p style='margin: 5px 0 0; font-size: 14px; color: #333;'>{$clean_shipping_addr}</p>
                </div>

                <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                    <thead>
                        <tr style='background-color: #f2f2f2;'>
                            <th style='padding: 12px; text-align: left; font-size: 13px; color: #666;'>Description</th>
                            <th style='padding: 12px; text-align: center; font-size: 13px; color: #666;'>Rate</th>
                            <th style='padding: 12px; text-align: center; font-size: 13px; color: #666;'>Qty</th>
                            <th style='padding: 12px; text-align: right; font-size: 13px; color: #666;'>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        $items_html
                    </tbody>
                </table>

                <table style='width: 100%; margin-top: 10px;'>
                    <tr>
                        <td style='width: 60%;'></td>
                        <td style='width: 40%;'>
                            <table style='width: 100%; border-top: 2px solid #333;'>
                                <tr>
                                    <td style='padding: 15px 0; font-size: 16px; font-weight: bold;'>TOTAL PAID:</td>
                                    <td style='padding: 15px 0; text-align: right; font-size: 20px; font-weight: bold; color: #FF6B00;'>RM " . number_format($order['Order_Amount'], 2) . "</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div style='text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <p style='font-size: 13px; color: #666; margin-bottom: 5px;'>Thank you for your purchase! We hope you enjoy your new shoes.</p>
                    <p style='font-size: 11px; color: #aaa;'>This is a computer-generated receipt. No signature is required.</p>
                </div>
            </div>

            <div style='background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 12px;'>
                <p style='margin: 0; color: #999;'>&copy; " . date('Y') . " SS Sport Shoes Store. All Rights Reserved.</p>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}