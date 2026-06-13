<?php
// card_auth.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Module A/login.php');
    exit();
}
if (!isset($_SESSION['card_temp_data'])) {
    header('Location: checkout.php');
    exit();
}

function generateTrackingNum($conn) {
    do {
        $track_num = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT Order_Id FROM `ORDER` WHERE Order_Tracking_Num = '$track_num'");
    } while ($check->num_rows > 0);
    return $track_num;
}

$uid = $_SESSION['user_id'];
$error = '';
$cardData = $_SESSION['card_temp_data'];
$grand_total = floatval($cardData['grand_total']);

// 【核心处理流程：完整继承原验证规则并执行事务落库】
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_card_pay'])) {
    $card_no = trim($_POST['card_no'] ?? '');
    $card_name = trim($_POST['cardholder_name'] ?? '');
    $expiry = trim($_POST['expiry'] ?? '');
    $cvv = trim($_POST['cvv'] ?? '');
    
    // 严格保留原有的全部验证规则
    if (empty($card_no) || strlen($card_no) !== 16 || !ctype_digit($card_no)) {
        $error = "Card Number must be exactly 16 digits.";
    } elseif (empty($card_name) || !preg_match("/^[a-zA-Z\s]+$/", $card_name)) {
        $error = "Invalid Cardholder Name. Only letters and spaces are allowed.";
    } elseif (empty($expiry) || !preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expiry)) {
        $error = "Invalid Expiry format. Use MM/YY format.";
    } elseif (empty($cvv) || strlen($cvv) !== 3 || !ctype_digit($cvv)) {
        $error = "CVV must be exactly 3 digits.";
    } else {
        // 验证信用卡是否过期，并限制在今天起10年内
        list($month, $year) = explode('/', $expiry);
        $currentYear = intval(date('y'));
        $currentMonth = intval(date('m'));
        $expiryYear = intval($year);
        $expiryMonth = intval($month);
        $currentFullYear = intval(date('Y'));
        $expiryFullYear = 2000 + $expiryYear;
        $maxExpiryFullYear = $currentFullYear + 10;

        if ($expiryFullYear < $currentFullYear || ($expiryFullYear == $currentFullYear && $expiryMonth < $currentMonth)) {
            $error = "Card has expired.";
        } elseif ($expiryFullYear > $maxExpiryFullYear || ($expiryFullYear == $maxExpiryFullYear && $expiryMonth > $currentMonth)) {
            $error = "Expiry must be within 10 years from this year.";
        }
    }

    // 验证通过，开始处理订单落库
    if (empty($error)) {
        $first_name = trim($cardData['first_name'] ?? '');
        $last_name = trim($cardData['last_name'] ?? '');
        $address = $conn->real_escape_string($cardData['address'] ?? '');
        $apartment = $conn->real_escape_string($cardData['apartment'] ?? '');
        $city = $conn->real_escape_string($cardData['city'] ?? '');
        $state = $conn->real_escape_string($cardData['state'] ?? '');
        $contact_phone = $conn->real_escape_string($cardData['phone'] ?? '');
        $raw_postcode = $cardData['postcode'] ?? '';
        $postcode = ($raw_postcode === 'other') ? trim($cardData['custom_postcode'] ?? '') : trim($raw_postcode);
        $postcode = $conn->real_escape_string($postcode);

        $final_addr = "$first_name $last_name, $address" . ($apartment ? " ($apartment)" : "") . ", $postcode, $city, $state";
        $order_date = date('Y-m-d H:i:s');

        $tracking_no = generateTrackingNum($conn);
        $pay_method_display = "Credit / Debit Card";
        $promo_id_to_save = isset($_SESSION['final_applied_promo_id']) ? $_SESSION['final_applied_promo_id'] : "NULL";

        $conn->begin_transaction();
        try {
            // 完美对齐：1.User_Id  2.Order_Amount  3.Order_Shipping_Addr  4.Order_Status  5.Order_Date  6.Order_Tracking_Num  7.Promo_Id  8.Payment_Status  9.Payment_Method
            $sql_order = "INSERT INTO `ORDER` (User_Id, Order_Amount, Order_Shipping_Addr, Order_Status, Order_Date, Order_Tracking_Num, Promo_Id, Payment_Status, Payment_Method) 
                          VALUES ('$uid', '$grand_total', '$final_addr', 'Processing', '$order_date', '$tracking_no', $promo_id_to_save, 'Paid', '$pay_method_display')";
            
            $conn->query($sql_order);
            $order_id = $conn->insert_id;


            // 2. 循环处理购物车项
            foreach ($_SESSION['cart'] as $item) {
                $item_pid = $item['pro_id'];
                $item_qty = $item['qty'];
                $item_size = $conn->real_escape_string($item['size']);
                $item_color = !empty($item['custom_preview']) ? 'Custom Design' : ($item['color'] ?? 'Default');
                $item_color = $conn->real_escape_string($item_color);

                $res_p = $conn->query("SELECT Pro_Price FROM product WHERE Pro_Id = '$item_pid'");
                $row_p = $res_p->fetch_assoc();
                $unit_price = isset($item['price']) ? $item['price'] : $row_p['Pro_Price'];
                $item_sub = floatval($unit_price) * intval($item_qty);

                $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal, Pro_Size, Pro_Colour, Custom_Preview) 
                              VALUES ('$order_id', '$item_pid', '$item_qty', '$item_sub', '$item_size', '$item_color', '" . ($item['custom_preview'] ?? '') . "')");
                
                $db_color_key = ($item_pid == 16 || $item_pid == 17) ? 'Default' : $item_color;
                $conn->query("UPDATE PRODUCT_STOCK SET Quantity = Quantity - $item_qty WHERE Pro_Id = '$item_pid' AND Pro_Size = '$item_size' AND Pro_Colour = '$db_color_key'");
            }

            // 核销关联优惠券
            if (isset($_SESSION['final_applied_user_promo_id']) && $_SESSION['final_applied_user_promo_id'] !== "NULL") {
                            $user_promo_id = intval($_SESSION['final_applied_user_promo_id']);
                            $conn->query("UPDATE user_promo SET Is_Used = 'Yes' WHERE User_Promo_Id = '$user_promo_id'");
                        }
                        
                        // 顺便清理临时放入的 Session 变量
                        unset($_SESSION['final_applied_promo_id']);
                        unset($_SESSION['final_applied_user_promo_id']);

            $conn->commit();

            // 发送邮件并清理缓存
            require_once 'send_receipt_handler.php';
            sendOrderReceiptEmail($order_id, $conn);

            unset($_SESSION['cart']);
            unset($_SESSION['card_temp_data']);

            header("Location: payment_success.php?order_id=$order_id");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Transaction failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Card Payment - STRYDEX Sport Shoe Store</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #0b0f19; color: #f8fafc; font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .payment-wrapper { width: 100%; max-width: 500px; background: #121824; border: 1px solid rgba(255,255,255,0.14); border-radius: 24px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
        .secure-header { text-align: center; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.14); padding-bottom: 15px; }
        
        /* 拟态卡片视觉 */
        .credit-card-view { background: linear-gradient(135deg, #1d293d 0%, #25354c 100%); border-radius: 16px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.14); position: relative; overflow: hidden; }
        .card-chip { width: 40px; height: 30px; background: #d4af37; border-radius: 6px; margin-bottom: 20px; background: linear-gradient(135deg, #e5c158 0%, #b89730 100%); }
        .card-number-view { font-size: 1.45rem; font-family: monospace; letter-spacing: 3px; margin-bottom: 15px; color: #f8fafc; text-shadow: 1px 1px 2px rgba(0,0,0,0.5); }
        .card-logo { position: absolute; top: 20px; right: 20px; font-size: 1.8rem; font-style: italic; font-weight: bold; color: rgba(255,255,255,0.9); }

        .form-label, .text-muted { color: #d1d5db !important; }
        .input-dark { background: #1a2333; border: 1px solid rgba(255,255,255,0.18); color: #f8fafc; border-radius: 10px; padding: 12px; }
        .input-dark::placeholder { color: rgba(248,250,252,0.68); }
        .input-dark:focus { background: #1e293b; border-color: #3b82f6; color: #f8fafc; box-shadow: none; }
        .btn-pay { background: #17735b; color: #fff; border: none; font-weight: bold; padding: 14px; border-radius: 12px; transition: 0.3s; }
        .btn-pay:hover { background: #125c49; }
    </style>
</head>
<body>

<div class="payment-wrapper">
    <div class="secure-header">
        <div class="small text-uppercase text-muted tracking-wider"><i class="bi bi-shield-lock-fill text-success"></i> Secure Checkout</div>
        <h4 class="fw-bold mt-1">Credit / Debit Card</h4>
        <div class="text-warning fw-bold small">Payable Amount: RM <?php echo number_format($grand_total, 2); ?></div>
    </div>

    <div class="credit-card-view">
        <div class="card-logo" id="cardLogo"><i class="bi bi-credit-card"></i></div>
        <div class="card-chip"></div>
        <div class="card-number-view" id="viewCardNo">•••• •••• •••• ••••</div>
        <div class="d-flex justify-content-between text-uppercase small text-muted">
            <div>
                <div style="font-size: 0.65rem;">Cardholder</div>
                <div class="fw-bold text-white" id="viewName">YOUR NAME</div>
            </div>
            <div class="text-end">
                <div style="font-size: 0.65rem;">Expires</div>
                <div class="fw-bold text-white" id="viewExpiry">MM/YY</div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 small mb-3" style="background: rgba(220,53,69,0.2); color: #fecaca; border-radius: 10px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">CARD NUMBER</label>
            <input type="text" name="card_no" id="cardNoInput" class="form-control input-dark" placeholder="16-digit card number" maxlength="16" required autocomplete="off">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">CARDHOLDER NAME</label>
            <input type="text" name="cardholder_name" id="cardNameInput" class="form-control input-dark" placeholder="e.g. JOHN DOE" required autocomplete="off">
        </div>
        <div class="row mb-4">
            <div class="col-6">
                <label class="form-label small fw-bold text-muted">EXPIRY DATE</label>
                <input type="text" name="expiry" id="expiryInput" class="form-control input-dark text-center" placeholder="MM/YY" maxlength="5" required autocomplete="off">
            </div>
            <div class="col-6">
                <label class="form-label small fw-bold text-muted">CVV</label>
                <input type="password" name="cvv" class="form-control input-dark text-center" placeholder="•••" maxlength="3" required autocomplete="off">
            </div>
        </div>

        <button type="submit" name="submit_card_pay" class="btn btn-pay w-100 py-3 shadow"><i class="bi bi-lock-fill"></i> Authorize RM <?php echo number_format($grand_total, 2); ?></button>
        <div class="text-center mt-3"><a href="checkout.php" class="text-decoration-none text-muted small">Cancel &amp; Return</a></div>
    </form>
</div>

<script>
// 动态联动实时脚本
document.getElementById('cardNoInput').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
    let num = this.value;
    
    // 识别卡种标志
    const logo = document.getElementById('cardLogo');
    if(num.startsWith('4')) logo.innerHTML = '<i class="bi bi-credit-card-2-front text-info"></i> Visa';
    else if(num.startsWith('5')) logo.innerHTML = '<i class="bi bi-credit-card-2-back text-danger"></i> Master';
    else logo.innerHTML = '<i class="bi bi-credit-card"></i>';

    let formatted = num.replace(/(\d{4})/g, '$1 ').trim();
    document.getElementById('viewCardNo').innerText = formatted || '•••• •••• •••• ••••';
});

document.getElementById('cardNameInput').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
    document.getElementById('viewName').innerText = this.value.toUpperCase() || 'YOUR NAME';
});

document.getElementById('expiryInput').addEventListener('input', function(e) {
    let val = this.value.replace(/\D/g, '');
    if (val.length >= 2) {
        this.value = val.slice(0, 2) + '/' + val.slice(2, 4);
    } else {
        this.value = val;
    }
    document.getElementById('viewExpiry').innerText = this.value || 'MM/YY';
});
</script>
</body>
</html>