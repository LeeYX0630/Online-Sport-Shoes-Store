<?php
// send_receipt_handler.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';
require '../includes/mail_config.php';

/**
 * 发送详细的 HTML 订单收据邮件（含 Promo 折扣明细）
 */
function sendOrderReceiptEmail($order_id, $conn) {
    date_default_timezone_set('Asia/Kuala_Lumpur');

    // 1. 获取订单总表与用户资料
    $sql_order = "SELECT o.*, u.User_Name, u.User_Email, u.User_Phone 
                  FROM `ORDER` o 
                  JOIN USER u ON o.User_Id = u.User_Id 
                  WHERE o.Order_Id = '$order_id'";
    $order_res = $conn->query($sql_order);
    if (!$order_res || $order_res->num_rows == 0) return false;

    $order        = $order_res->fetch_assoc();
    $user_email   = $order['User_Email'];
    $order_date   = date('d M Y, h:i A', strtotime($order['Order_Date']));
    $tracking_num = htmlspecialchars($order['Order_Tracking_Num']);
    $pay_method   = htmlspecialchars($order['Payment_Method'] ?? 'N/A');

    // 清理 shipping address（去掉 Tel: 部分用于展示）
    $clean_shipping_addr = preg_replace('/\. Tel:.*$/i', '', $order['Order_Shipping_Addr']);
    $clean_shipping_addr = nl2br(htmlspecialchars($clean_shipping_addr));

    // 2. 获取商品明细
    $sql_items = "SELECT od.*, p.Pro_Name, p.Pro_Price 
                  FROM ORDER_DETAIL od 
                  JOIN product p ON od.Pro_Id = p.Pro_Id 
                  WHERE od.Order_Id = '$order_id'";
    $items_res = $conn->query($sql_items);

    // 邮件中的尺码转换表
    $email_size_matrix = [
        "3"   => ["US-M" => "4",   "US-F" => "5",   "EUR" => "36"],
        "3.5" => ["US-M" => "4.5", "US-F" => "5.5", "EUR" => "36.5"],
        "4"   => ["US-M" => "5",   "US-F" => "6",   "EUR" => "37"],
        "4.5" => ["US-M" => "5.5", "US-F" => "6.5", "EUR" => "37.5"],
        "5"   => ["US-M" => "6",   "US-F" => "7",   "EUR" => "38"],
        "5.5" => ["US-M" => "6.5", "US-F" => "7.5", "EUR" => "38.5"],
        "6"   => ["US-M" => "7",   "US-F" => "8",   "EUR" => "39"],
        "6.5" => ["US-M" => "7.5", "US-F" => "8.5", "EUR" => "40"],
        "7"   => ["US-M" => "8",   "US-F" => "9",   "EUR" => "40.5"],
        "7.5" => ["US-M" => "8.5", "US-F" => "9.5", "EUR" => "41"],
        "8"   => ["US-M" => "9",   "US-F" => "10",  "EUR" => "42"],
        "8.5" => ["US-M" => "9.5", "US-F" => "10.5", "EUR" => "42.5"],
        "9"   => ["US-M" => "10",  "US-F" => "11",  "EUR" => "43"],
        "9.5" => ["US-M" => "10.5", "US-F" => "11.5", "EUR" => "43.5"],
        "10"  => ["US-M" => "11",  "US-F" => "12",  "EUR" => "44"],
        "10.5" => ["US-M" => "11.5", "US-F" => "12.5", "EUR" => "44.5"],
        "11"  => ["US-M" => "12",  "US-F" => "13",  "EUR" => "45"],
        "11.5" => ["US-M" => "12.5", "US-F" => "13.5", "EUR" => "45.5"],
        "12"  => ["US-M" => "13",  "US-F" => "14",  "EUR" => "46"],
        "12.5" => ["US-M" => "13.5", "US-F" => "14.5", "EUR" => "46.5"],
        "13"  => ["US-M" => "14",  "US-F" => "15",  "EUR" => "47"],
    ];

    $items_html        = "";
    $subtotal_amount   = 0.00;
    while ($item = $items_res->fetch_assoc()) {
        $unit_price       = $item['Order_Subtotal'] / $item['Order_Qty'];
        $subtotal_amount += floatval($item['Order_Subtotal']);
        
        // 尺码转换显示
        $user_size_system = $_SESSION['size_system'] ?? 'UK';
        $item_size = $item['Pro_Size'] ?? '';
        $display_email_size = "UK " . htmlspecialchars($item_size);
        
        if ($user_size_system !== 'UK' && isset($email_size_matrix[$item_size][$user_size_system])) {
            $display_email_size = $user_size_system . " " . htmlspecialchars($email_size_matrix[$item_size][$user_size_system]);
        }
        
        $items_html .= "
            <tr>
                <td style='padding:14px 12px; border-bottom:1px solid #f0f0f0; font-size:14px; color:#333;'>
                    <strong>" . htmlspecialchars($item['Pro_Name']) . "</strong><br>
                    <span style='font-size:11px; color:#999;'>SKU: #" . intval($item['Pro_Id']) . " | Size: " . $display_email_size . " | Color: " . htmlspecialchars($item['Pro_Colour'] ?? 'Default') . "</span>
                </td>
                <td style='padding:14px 12px; border-bottom:1px solid #f0f0f0; text-align:center; font-size:14px; color:#555;'>
                    RM " . number_format($unit_price, 2) . "
                </td>
                <td style='padding:14px 12px; border-bottom:1px solid #f0f0f0; text-align:center; font-size:14px; color:#555;'>
                    " . intval($item['Order_Qty']) . "
                </td>
                <td style='padding:14px 12px; border-bottom:1px solid #f0f0f0; text-align:right; font-size:14px; font-weight:bold; color:#222;'>
                    RM " . number_format($item['Order_Subtotal'], 2) . "
                </td>
            </tr>";
    }

    // 3. 获取 Promo 信息
    $promo_html           = "";
    $promo_discount_amount = 0.00;
    $promo_id             = intval($order['Promo_Id'] ?? 0);

    if ($promo_id > 0) {
        $promo_res = $conn->query("SELECT Promo_Name, Promo_Code, Promo_Type, Promo_Value FROM promo WHERE Promo_Id = $promo_id");
        if ($promo_res && $promo_res->num_rows > 0) {
            $promo = $promo_res->fetch_assoc();
            $promo_label = htmlspecialchars($promo['Promo_Code'] ?: $promo['Promo_Name']);

            if (strcasecmp($promo['Promo_Type'], 'Percentage') === 0) {
                $promo_discount_amount = round($subtotal_amount * floatval($promo['Promo_Value']) / 100, 2);
                $promo_desc = number_format($promo['Promo_Value'], 0) . '% off';
            } else {
                $promo_discount_amount = floatval($promo['Promo_Value']);
                $promo_desc = 'Fixed discount';
            }

            $promo_html = "
                <tr>
                    <td colspan='3' style='padding:10px 12px; text-align:right; font-size:13px; color:#555;'>
                        Subtotal:
                    </td>
                    <td style='padding:10px 12px; text-align:right; font-size:13px; color:#555;'>
                        RM " . number_format($subtotal_amount, 2) . "
                    </td>
                </tr>
                <tr>
                    <td colspan='3' style='padding:10px 12px; text-align:right; font-size:13px; color:#27ae60;'>
                        🏷️ Promo Applied: <strong>$promo_label</strong> <span style='color:#aaa;font-size:11px;'>($promo_desc)</span>
                    </td>
                    <td style='padding:10px 12px; text-align:right; font-size:13px; font-weight:bold; color:#27ae60;'>
                        &minus; RM " . number_format($promo_discount_amount, 2) . "
                    </td>
                </tr>";
        }
    }

    // 4. 总计行
    $total_paid = number_format($order['Order_Amount'], 2);

    $total_row_html = "
        <tr>
            <td colspan='4' style='padding:0;'>
                <div style='height:2px; background:linear-gradient(to right,#FF6B00,#ff9a00); margin:8px 12px;'></div>
            </td>
        </tr>
        <tr>
            <td colspan='3' style='padding:14px 12px; text-align:right; font-size:16px; font-weight:bold; color:#222;'>
                TOTAL PAID (MYR):
            </td>
            <td style='padding:14px 12px; text-align:right; font-size:18px; font-weight:bold; color:#FF6B00;'>
                RM $total_paid
            </td>
        </tr>";

    // 5. 组装完整 Email HTML
    $year      = date('Y');
    $email_body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
    <body style='margin:0;padding:0;background-color:#f4f4f4;font-family:\"Segoe UI\",Helvetica,Arial,sans-serif;'>

    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f4;padding:30px 0;'>
    <tr><td align='center'>
    <table width='640' cellpadding='0' cellspacing='0' style='max-width:640px;width:100%;'>

        <!-- ── HEADER ── -->
        <tr>
            <td style='background:#000;padding:32px 40px;border-radius:10px 10px 0 0;text-align:center;'>
                <div style='font-size:22px;font-weight:900;color:#FF6B00;letter-spacing:3px;text-transform:uppercase;'>
                    STRYDEX SPORT SHOES STORE
                </div>
                <div style='color:#999;font-size:12px;margin-top:6px;'>
                    Multimedia University, Melaka, Malaysia &nbsp;|&nbsp; sportshoes.system@gmail.com
                </div>
                <div style='display:inline-block;background:#FF6B00;color:#fff;font-size:11px;font-weight:700;
                            letter-spacing:2px;padding:5px 16px;border-radius:20px;margin-top:14px;text-transform:uppercase;'>
                    ✓ &nbsp;Payment Confirmed
                </div>
            </td>
        </tr>

        <!-- ── BODY ── -->
        <tr>
            <td style='background:#fff;padding:36px 40px;'>

                <!-- Greeting -->
                <p style='font-size:15px;color:#444;margin:0 0 6px;'>Hi <strong>" . htmlspecialchars($order['User_Name']) . "</strong>,</p>
                <p style='font-size:14px;color:#666;margin:0 0 28px;line-height:1.7;'>
                    Thank you for your purchase! Your payment has been received and your order is now being processed.
                    Below is your official receipt for reference.
                </p>

                <!-- ── Order Meta ── -->
                <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:28px;'>
                    <tr>
                        <td style='width:50%;vertical-align:top;'>
                            <div style='font-size:11px;text-transform:uppercase;font-weight:700;color:#999;margin-bottom:6px;'>Billed To</div>
                            <div style='font-size:14px;color:#333;line-height:1.7;'>
                                <strong>" . htmlspecialchars($order['User_Name']) . "</strong><br>
                                " . htmlspecialchars($order['User_Email']) . "<br>
                                " . htmlspecialchars($order['User_Phone']) . "
                            </div>
                        </td>
                        <td style='width:50%;vertical-align:top;text-align:right;'>
                            <table cellpadding='0' cellspacing='0' style='margin-left:auto;'>
                                <tr>
                                    <td style='font-size:13px;color:#888;padding:2px 10px 2px 0;'>Receipt No:</td>
                                    <td style='font-size:13px;font-weight:700;color:#222;'>#$tracking_num</td>
                                </tr>
                                <tr>
                                    <td style='font-size:13px;color:#888;padding:2px 10px 2px 0;'>Date:</td>
                                    <td style='font-size:13px;color:#333;'>$order_date</td>
                                </tr>
                                <tr>
                                    <td style='font-size:13px;color:#888;padding:2px 10px 2px 0;'>Payment:</td>
                                    <td style='font-size:13px;color:#333;'>$pay_method</td>
                                </tr>
                                <tr>
                                    <td style='font-size:13px;color:#888;padding:2px 10px 2px 0;'>Status:</td>
                                    <td style='font-size:13px;font-weight:700;color:#27ae60;'>✓ PAID</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Shipping Address -->
                <div style='background:#fafafa;border-left:4px solid #FF6B00;border-radius:4px;padding:14px 18px;margin-bottom:28px;'>
                    <div style='font-size:11px;text-transform:uppercase;font-weight:700;color:#999;margin-bottom:5px;'>
                        📦 &nbsp;Shipping Address
                    </div>
                    <div style='font-size:13px;color:#444;line-height:1.7;'>$clean_shipping_addr</div>
                </div>

                <!-- ── Items Table ── -->
                <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin-bottom:10px;'>
                    <thead>
                        <tr style='background:#f7f7f7;'>
                            <th style='padding:12px;text-align:left;font-size:12px;text-transform:uppercase;color:#888;border-bottom:2px solid #eee;'>Description</th>
                            <th style='padding:12px;text-align:center;font-size:12px;text-transform:uppercase;color:#888;border-bottom:2px solid #eee;'>Unit Price</th>
                            <th style='padding:12px;text-align:center;font-size:12px;text-transform:uppercase;color:#888;border-bottom:2px solid #eee;'>Qty</th>
                            <th style='padding:12px;text-align:right;font-size:12px;text-transform:uppercase;color:#888;border-bottom:2px solid #eee;'>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        $items_html
                    </tbody>
                    <tfoot>
                        $promo_html
                        $total_row_html
                    </tfoot>
                </table>

                <!-- ── Promo Notice Banner (only if promo used) ──";

    if ($promo_discount_amount > 0) {
        $email_body .= "
                <div style='background:#f0fff4;border:1px solid #b7ebc8;border-radius:6px;padding:12px 18px;margin-top:18px;font-size:13px;color:#27ae60;'>
                    🎉 &nbsp;A promo discount of <strong>RM " . number_format($promo_discount_amount, 2) . "</strong> was applied to this order.
                    That's why the item price and total may differ — you saved money! 💚
                </div>";
    }

    $email_body .= "
                <!-- ── Footer Note ── -->
                <div style='text-align:center;margin-top:36px;padding-top:24px;border-top:1px solid #f0f0f0;'>
                    <p style='font-size:13px;color:#888;margin:0 0 6px;'>
                        Questions? Contact us at
                        <a href='mailto:sportshoes.system@gmail.com' style='color:#FF6B00;text-decoration:none;'>sportshoes.system@gmail.com</a>
                    </p>
                    <p style='font-size:11px;color:#bbb;margin:0;'>
                        This is a computer-generated receipt. No signature is required.
                    </p>
                </div>
            </td>
        </tr>

        <!-- ── FOOTER ── -->
        <tr>
            <td style='background:#111;padding:22px 40px;border-radius:0 0 10px 10px;text-align:center;'>
                <p style='margin:0;font-size:12px;color:#666;'>
                    &copy; $year STRYDEX Sport Shoes Store &nbsp;|&nbsp; Multimedia University, Melaka
                </p>
                <p style='margin:6px 0 0;font-size:11px;color:#444;'>
                    You received this email because you placed an order on our platform.
                </p>
            </td>
        </tr>

    </table>
    </td></tr>
    </table>
    </body>
    </html>";

    // 6. 配置 PHPMailer 并发送
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

        $mail->setFrom('sportshoes.system@gmail.com', 'STRYDEX Sport Shoes Store');
        $mail->addAddress($user_email, $order['User_Name']);

        $mail->isHTML(true);
        $mail->Subject = "✅ Official Receipt – Order #$tracking_num | STRYDEX";
        $mail->Body    = $email_body;

        // Plain-text fallback
        $mail->AltBody = "Hi {$order['User_Name']},\n\nYour payment for Order #$tracking_num on $order_date has been confirmed.\nTotal Paid: RM $total_paid\n\nThank you for shopping with STRYDEX Sport Shoes Store!";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}