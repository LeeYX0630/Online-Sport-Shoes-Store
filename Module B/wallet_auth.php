<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Module A/login.php');
    exit();
}

if (!isset($_SESSION['wallet_temp_data'])) {
    header('Location: checkout.php');
    exit();
}

        // wallet_auth.php 顶部
function generateTrackingNum($conn) {
    do {
        $track_num = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT Order_Id FROM `ORDER` WHERE Order_Tracking_Num = '$track_num'");
    } while ($check->num_rows > 0);
    return $track_num;
}

$uid = $_SESSION['user_id'];
$error = '';
$success = '';
$walletData = $_SESSION['wallet_temp_data'];

$user_sql = "SELECT * FROM `USER` WHERE User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();
$current_balance = floatval($user_info['User_Balance']);
$user_email = htmlspecialchars($user_info['User_Email']);
$user_name = htmlspecialchars($user_info['User_Name']);
$full_amount = number_format(floatval($walletData['grand_total'] ?? 0), 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet_pin'])) {
    $pin = trim($_POST['wallet_pin']);

    if (!preg_match('/^[0-9]{6}$/', $pin)) {
        $error = 'Wallet PIN must be exactly 6 digits.';
    } else {
        $pin_check = $conn->query("SELECT User_PIN, User_Balance FROM `USER` WHERE User_Id = '$uid'");
        $pin_row = $pin_check->fetch_assoc();

        if (!$pin_row || empty($pin_row['User_PIN'])) {
            $error = 'Wallet is not activated. Please set a PIN in your dashboard.';
        } elseif (!password_verify($pin, $pin_row['User_PIN'])) {
            $error = 'Incorrect PIN. Please try again.';
        } elseif (floatval($pin_row['User_Balance']) < floatval($walletData['grand_total'])) {
            $error = 'Insufficient wallet balance to complete this purchase.';
        } else {
            $first_name = trim($walletData['first_name'] ?? '');
            $last_name = trim($walletData['last_name'] ?? '');
            $address = $conn->real_escape_string($walletData['address'] ?? '');
            $apartment = $conn->real_escape_string($walletData['apartment'] ?? '');
            $city = $conn->real_escape_string($walletData['city'] ?? '');
            $state = $conn->real_escape_string($walletData['state'] ?? '');
            $contact_phone = $conn->real_escape_string($walletData['phone'] ?? '');
            $raw_postcode = $walletData['postcode'] ?? '';
            $postcode = ($raw_postcode === 'other') ? trim($walletData['custom_postcode'] ?? '') : trim($raw_postcode);
            $postcode = $conn->real_escape_string($postcode);

            $final_addr = "$first_name $last_name, $address" . ($apartment ? " ($apartment)" : "") . ", $postcode, $city, $state. Tel: $contact_phone";
            $order_date = date('Y-m-d H:i:s');
            $grand_total = floatval($walletData['grand_total']);

            $tracking_no = generateTrackingNum($conn);
            $pay_method_display = "Store Wallet";
            $promo_id_to_save = isset($_SESSION['applied_user_promo_id']) ? intval($_SESSION['applied_user_promo_id']) : "NULL";

            $conn->begin_transaction();
            try {
                // 【核心修复】：增加 Order_Tracking_Num, Promo_Id, Payment_Method 三个字段
                $sql_order = "INSERT INTO `ORDER` (User_Id, Order_Amount, Order_Shipping_Addr, Order_Status, Order_Date, Payment_Status, Order_Tracking_Num, Promo_Id, Payment_Method) 
                              VALUES ('$uid', '$grand_total', '$final_addr', 'Processing', '$order_date', 'Paid', '$tracking_no', $promo_id_to_save, '$pay_method_display')";
                
                $conn->query($sql_order);
                $order_id = $conn->insert_id;

                $conn->query("UPDATE `USER` SET User_Balance = User_Balance - $grand_total WHERE User_Id = '$uid'");
                $trans_desc = "Wallet Payment - Order #$order_id";
                $conn->query("INSERT INTO WALLET_TRANSACTION (User_Id, Amount, Type, Description) VALUES ('$uid', '-$grand_total', 'Payment', '$trans_desc')");

                foreach ($_SESSION['cart'] as $item) {
                    $item_pid = $item['pro_id'];
                    $item_qty = $item['qty'];
                    $item_size = $conn->real_escape_string($item['size']);
                    $item_color = $conn->real_escape_string($item['color'] ?? 'Default');

                    $res_p = $conn->query("SELECT Pro_Price FROM product WHERE Pro_Id = '$item_pid'");
                    $row_p = $res_p->fetch_assoc();
                    $item_sub = floatval($row_p['Pro_Price']) * intval($item_qty);
                    $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal) VALUES ('$order_id', '$item_pid', '$item_qty', '$item_sub')");
                    $db_color_key = ($item_pid == 16 || $item_pid == 17) ? 'Default' : $item_color;
                    $conn->query("UPDATE PRODUCT_STOCK SET Quantity = Quantity - $item_qty WHERE Pro_Id = '$item_pid' AND Pro_Size = '$item_size' AND Pro_Colour = '$db_color_key'");
                }
                $conn->commit();

                require_once 'send_receipt_handler.php';
                sendOrderReceiptEmail($order_id, $conn);

                unset($_SESSION['cart']);
                unset($_SESSION['wallet_temp_data']);

                header("Location: payment_success.php?order_id=$order_id");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Payment failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Authorization - SS Sport Shoes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at top left, rgba(18, 105, 255, 0.18), transparent 24%), radial-gradient(circle at bottom right, rgba(255, 110, 49, 0.22), transparent 30%), #050b1b; color: #edf2ff; font-family: Inter, system-ui, sans-serif; overflow-x: hidden; }
        body::before { content: ''; position: fixed; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,0.05), transparent 55%); pointer-events: none; }
        .hero-shell { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        .auth-panel { width: min(100%, 680px); background: rgba(10, 20, 52, 0.9); border: 1px solid rgba(255,255,255,0.12); border-radius: 28px; backdrop-filter: blur(18px); box-shadow: 0 40px 120px rgba(0,0,0,0.35); overflow: hidden; position: relative; }
        .auth-panel::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 20%, rgba(82, 117, 255, 0.18), transparent 18%), radial-gradient(circle at 80% 30%, rgba(255, 90, 96, 0.16), transparent 20%), radial-gradient(circle at 50% 85%, rgba(62, 211, 213, 0.12), transparent 22%); pointer-events: none; }
        .auth-head { position: relative; padding: 3rem 2.5rem 2rem; background: linear-gradient(135deg, rgba(20,65,160,0.95), rgba(26,37,91,0.96)); }
        .auth-head h1 { font-size: clamp(2.2rem, 4vw, 3rem); margin: 0 0 0.6rem; letter-spacing: -0.04em; line-height: 1.02; }
        .auth-head p { margin: 0; color: rgba(237,242,255,0.82); font-size: 1rem; }
        .auth-head .chip { display: inline-flex; align-items: center; gap: 0.6rem; margin-top: 1.4rem; color: rgba(237,242,255,0.9); font-size: 0.92rem; }
        .auth-head .chip i { font-size: 1.2rem; color: #a5b4fc; }
        .auth-body { position: relative; z-index: 1; padding: 2rem; }
        .status-card { display: grid; grid-template-columns: 1fr auto; gap: 1rem; padding: 1.35rem 1.5rem; border-radius: 22px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); margin-bottom: 1.5rem; }
        .status-card .brand { letter-spacing: 0.16em; text-transform: uppercase; font-size: 0.8rem; color: #93c5fd; }
        .status-card .email { color: #e2e8f0; font-size: 0.95rem; }
        .status-card .amount { text-align: right; font-size: 1.75rem; font-weight: 700; color: #fef3c7; }
        .status-card .tag { display: inline-flex; gap: 0.35rem; align-items: center; padding: 0.55rem 0.9rem; border-radius: 999px; background: rgba(249, 115, 22, 0.14); color: #ffedd5; font-size: 0.88rem; }
        .pin-box { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 22px; padding: 1.4rem; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.02); }
        .pin-title { font-size: 1rem; color: #c7d2fe; margin-bottom: 0.8rem; }
        .pin-input { width: 100%; padding: 1.25rem 1rem; border-radius: 18px; border: 1px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.04); color: #fff; font-size: 1.1rem; letter-spacing: 0.35rem; text-align: center; outline: none; transition: border-color 0.2s ease, transform 0.2s ease; }
        .pin-input:focus { border-color: #60a5fa; transform: translateY(-1px); }
        .pin-hint { color: rgba(241,245,249,0.76); margin-top: 0.75rem; font-size: 0.95rem; }
        .cta-row { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; margin-top: 1.5rem; }
        .btn-hero { border-radius: 16px; border: none; padding: 1rem 1.3rem; background: linear-gradient(135deg, #60a5fa, #f97316); color: #020617; font-weight: 700; box-shadow: 0 24px 60px rgba(249,115,22,0.18); cursor: pointer; transition: transform 0.2s ease, filter 0.2s ease; }
        .btn-hero:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .support-text { color: rgba(237,242,255,0.7); font-size: 0.92rem; }
        .alert-box { margin-top: 1rem; padding: 1rem 1.1rem; border-radius: 16px; background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.2); color: #fecaca; }
        .orbit { position: absolute; border-radius: 50%; opacity: 0.15; filter: blur(1px); animation: orbit 13s linear infinite; }
        .orbit:nth-child(1) { width: 150px; height: 150px; background: #3b82f6; top: 12%; left: 10%; }
        .orbit:nth-child(2) { width: 220px; height: 220px; background: #fb7185; bottom: 8%; right: 6%; animation-duration: 18s; }
        .orbit:nth-child(3) { width: 90px; height: 90px; background: #22d3ee; top: 40%; right: 8%; animation-duration: 12s; }
        @keyframes orbit { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(14px, -22px) scale(1.08); } 100% { transform: translate(0, 0) scale(1); } }
        @media (max-width: 768px) { .auth-body { padding: 1.5rem; } .cta-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="hero-shell">
        <div class="auth-panel">
            <span class="orbit"></span>
            <span class="orbit"></span>
            <span class="orbit"></span>
            <div class="auth-head">
                <div class="chip"><i class="bi bi-shield-lock-fill"></i> Wallet Authorization</div>
                <h1>Complete payment with</h1>
                <h1>SS Sport Wallet</h1>
                <p>Secure transaction verification for your order. Enter your wallet PIN to finish payment and get your receipt instantly.</p>
            </div>
            <div class="auth-body">
                <div class="status-card">
                    <div>
                        <div class="brand">SS SPORT SHOES STORE</div>
                        <div class="email"><?php echo $user_email; ?></div>
                    </div>
                    <div>
                        <div class="tag"><i class="bi bi-wallet-fill"></i> Wallet pay</div>
                        <div class="amount">RM <?php echo $full_amount; ?></div>
                    </div>
                </div>

                <div class="pin-box">
                    <div class="pin-title">Enter your 6-digit Wallet PIN to confirm</div>
                    <form method="POST" id="walletForm" autocomplete="off">
                        <input type="password" name="wallet_pin" id="walletPin" class="pin-input" maxlength="6" inputmode="numeric" placeholder="●●●●●●" required pattern="[0-9]{6}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        <div class="pin-hint">Do not share your PIN with anyone. Your payment is protected by bank-grade encryption.</div>
                        <?php if ($error): ?>
                            <div class="alert-box"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <div class="cta-row">
                            <button type="submit" class="btn-hero">Authorize Payment</button>
                            <div class="support-text"><i class="bi bi-envelope-fill"></i> Email: <?php echo $user_email; ?></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const walletForm = document.getElementById('walletForm');
        walletForm.addEventListener('submit', function (event) {
            const pin = document.getElementById('walletPin').value.trim();
            if (!/^\d{6}$/.test(pin)) {
                event.preventDefault();
                alert('Please enter a valid 6-digit wallet PIN.');
                return;
            }
            const button = walletForm.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Authorizing...';
        });

    </script>
</body>
</html>
