<?php
// Module B: 核心交易组 - 订单结算中心 (Checkout & Payment)
ob_start();
session_start();
require_once '../includes/db_connection.php';


// 1. 登录与购物车安全检查
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Module A/login.php");
    exit();
}
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: catalogue.php");
    exit();
}


$uid = $_SESSION['user_id'];
$error = "";
$success_msg = "";

$user_sql = "SELECT * FROM `USER` WHERE User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();
$current_balance = floatval($user_info['User_Balance']);

// 提取用户基础资料
$user_phone = $user_info['User_Phone'];
$user_address = $user_info['User_Address'];
$user_state = $user_info['User_State'];
$user_postcode = $user_info['User_Postcode'];
// 假设城市信息也存在，若不存在则留空
$user_city = isset($user_info['User_City']) ? $user_info['User_City'] : "";

$name_parts = explode(' ', $user_info['User_Name'], 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : "";

// 3. 计算购物车总额与商品清单
// 3. 计算购物车总额与商品清单
// 3. 计算购物车总额与商品清单
$subtotal = 0;
$checkout_items = [];
foreach ($_SESSION['cart'] as $cart_key => $item) {
    $pid = $item['pro_id'];
    // 这里的 SQL 保持不变
    $sql_p = "SELECT Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id = '$pid'";
    $res_p = $conn->query($sql_p);
    
    if ($res_p && $res_p->num_rows > 0) {
        $p_data = $res_p->fetch_assoc();
        $p_data['qty'] = $item['qty'];
        $p_data['size'] = $item['size'];
        $p_data['color'] = $item['color'] ?? 'Default';
        
        // --- 【核心修复：采用与 cart.php 一致的智能搜索逻辑】 ---
        if (!empty($item['custom_preview'])) {
            // 1. 如果是 3D 定制作品，直接使用 Base64 快照
            $p_data['display_image'] = $item['custom_preview'];
        } else {
            // 2. 普通商品或默认款：根据基本名称搜索文件夹
            $base_img = $p_data['Pro_Image']; 
            $path_parts = pathinfo($base_img);
            $base_name = preg_replace('/_\d+$/', '', $path_parts['filename']); // 去掉末尾数字
            
            // 在 uploads 文件夹中寻找所有匹配的文件
            $found_files = glob("../uploads/{$base_name}*.*");
            
            if (!empty($found_files)) {
                // 默认取搜索到的第一张
                $final_img = $found_files[0]; 
                
                // 如果用户选了特定颜色，尝试匹配颜色关键字
                if ($p_data['color'] !== 'Default' && $p_data['color'] !== 'Custom Design') {
                    $color_slug = strtolower(str_replace(' ', '_', $p_data['color']));
                    foreach ($found_files as $file) {
                        if (strpos(strtolower($file), $color_slug) !== false) {
                            $final_img = $file;
                            break;
                        }
                    }
                }
                $p_data['display_image'] = $final_img;
            } else {
                $p_data['display_image'] = "../images/placeholder.png"; // 没搜到则显示占位图
            }
        }
        // ----------------------------------------------------

        $p_data['item_total'] = $p_data['Pro_Price'] * $item['qty'];
        $subtotal += $p_data['item_total'];
        $checkout_items[] = $p_data;
    }
}

// 4. 处理优惠码逻辑
$discount = 0;
$applied_code = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) {
    $code = $conn->real_escape_string(trim($_POST['coupon_code']));
    $sql_c = "SELECT * FROM promo WHERE Promo_Code = '$code' AND Expired_Date >= CURDATE() AND Promo_Status = 'Active'";
    $res_c = $conn->query($sql_c);
    if ($res_c && $res_c->num_rows > 0) {
        $promo = $res_c->fetch_assoc();
        $applied_code = $code;
        if ($promo['Promo_Type'] === 'Percentage') {
            $discount = $subtotal * (floatval($promo['Promo_Value']) / 100);
            $success_msg = "Applied " . intval($promo['Promo_Value']) . "% OFF";
        } else {
            $discount = floatval($promo['Promo_Value']);
            $success_msg = "Applied RM " . number_format($discount, 2) . " OFF";
        }
    } else { $error = "Invalid or expired code."; }
}

$shipping = ($subtotal >= 250) ? 0 : 15.00;
$grand_total = max(0, ($subtotal + $shipping) - $discount);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    
    $f_name = trim($_POST['first_name']);
    if ($_POST['postcode'] === 'other') {
        $postcode = trim($_POST['custom_postcode']);
    } else {
        $postcode = trim($_POST['postcode']);
    }
    $phone = trim($_POST['phone']);

    // 后端验证
    if (!preg_match("/^[a-zA-Z\s]+$/", $f_name)) {
        $error = "Names should only contain letters.";
    } elseif (strlen($phone) > 12 || !preg_match("/^0[1-9][0-9]{7,9}$/", $phone)) {
        $error = "Invalid Malaysia phone number format (max 12 digits).";
    } elseif (!preg_match("/^[0-9]{5}$/", $postcode)) {
        $error = "Postcode must be 5 digits.";
    } elseif ($_POST['pay_type'] === 'fpx' && (empty($_POST['fpx_bank']) || $_POST['fpx_bank'] === '')) {
        $error = "Please select a bank for FPX payment.";
    } elseif ($_POST['pay_type'] === 'wallet' && $current_balance < $grand_total) {
        $error = "Insufficient wallet balance. Please select another payment method.";
    } elseif ($_POST['pay_type'] === 'card') {
        $card_no = trim($_POST['card_no'] ?? '');
        $card_name = trim($_POST['cardholder_name'] ?? '');
        $expiry = trim($_POST['expiry'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');
        
        if (empty($card_no) || strlen($card_no) !== 16 || !ctype_digit($card_no)) {
            $error = "Card Number must be exactly 16 digits.";
        } elseif (empty($card_name) || !preg_match("/^[a-zA-Z\s]+$/", $card_name)) {
            $error = "Invalid Cardholder Name. Only letters are allowed.";
        } elseif (empty($expiry) || !preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expiry)) {
            $error = "Invalid Expiry format. Use MM/YY format.";
        } elseif (empty($cvv) || strlen($cvv) !== 3 || !ctype_digit($cvv)) {
            $error = "CVV must be exactly 3 digits.";
        } else {
            // Validate expiry date is not in the past
            list($month, $year) = explode('/', $expiry);
            $currentYear = intval(date('y'));
            $currentMonth = intval(date('m'));
            $expiryYear = intval($year);
            $expiryMonth = intval($month);
            
            if ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
                $error = "Card has expired.";
            }
        }
    }
    
    if (empty($error)) {
        $email = $conn->real_escape_string($_POST['contact_email']);
        $addr = $conn->real_escape_string($_POST['address']);
        $apt = $conn->real_escape_string($_POST['apartment']);
        $city = $conn->real_escape_string($_POST['city']);
        $state = $conn->real_escape_string($_POST['state']);
        
        $final_addr = "$f_name $l_name, $addr" . ($apt ? " ($apt)" : "") . ", $postcode, $city, $state. Tel: $phone";
        $order_date = date('Y-m-d H:i:s');
        
        $conn->begin_transaction();
        try {
            $sql_order = "INSERT INTO `ORDER` (User_Id, Order_Amount, Order_Shipping_Addr, Order_Status, Order_Date, Payment_Status) 
                          VALUES ('$uid', '$grand_total', '$final_addr', 'Processing', '$order_date', 'Paid')";
            $conn->query($sql_order);
            $order_id = $conn->insert_id;

            // B. 【关键逻辑】执行钱包扣款与记录
            $pay_type = $_POST['pay_type'];
            if ($pay_type === 'wallet') {
                // 扣除余额
                $conn->query("UPDATE `USER` SET User_Balance = User_Balance - $grand_total WHERE User_Id = '$uid'");
                
                // 插入一条负数的交易流水记录
                $trans_desc = "Purchased Order #$order_id";
                $conn->query("INSERT INTO WALLET_TRANSACTION (User_Id, Amount, Type, Description) VALUES ('$uid', '-$grand_total', 'Payment', '$trans_desc')");
            }

            foreach ($_SESSION['cart'] as $item) {
                $item_pid = $item['pro_id'];
                $item_qty = $item['qty'];
                $item_size = $item['size'];
                $res_p = $conn->query("SELECT Pro_Price FROM product WHERE Pro_Id = '$item_pid'");
                $row_p = $res_p->fetch_assoc();
                $item_sub = $row_p['Pro_Price'] * $item_qty;
                $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal) VALUES ('$order_id', '$item_pid', '$item_qty', '$item_sub')");
                $conn->query("UPDATE PRODUCT_STOCK SET Quantity = Quantity - $item_qty WHERE Pro_Id = '$item_pid' AND Pro_Size = '$item_size'");
            }
            $conn->commit();
            
            require_once 'send_receipt_handler.php'; 
            sendOrderReceiptEmail($order_id, $conn);
        
            unset($_SESSION['cart']);
            
            header("Location: payment_success.php?order_id=" . $order_id);
            exit();
        } catch (Exception $e) { $conn->rollback(); $error = "Order Failed: " . $e->getMessage(); }
    }
}

include '../includes/header.php';
?>

<!-- 在页面顶部显示错误或成功消息 -->
<?php if($error || $success_msg): ?>
<div style="max-width: 1100px; margin: 20px auto 0; padding: 0 20px;">
    <?php if($error): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #f5c6cb; margin-bottom: 20px;">
        <strong>❌ 出错：</strong> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if($success_msg): ?>
    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #c3e6cb; margin-bottom: 20px;">
        <strong>✓ 成功：</strong> <?php echo htmlspecialchars($success_msg); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
    body { background-color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; }
    .checkout-container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
    .checkout-grid { display: grid; grid-template-columns: 1fr 400px; gap: 60px; }
    @media (max-width: 992px) { .checkout-grid { grid-template-columns: 1fr; } }
    
    .section-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; color: #000; }
    .input-field { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 5px; font-size: 0.95rem; margin-bottom: 12px; transition: border 0.2s; background-color: #fff; box-sizing: border-box; height: 48px; line-height: 1.4;  }
    .input-field:focus { border: 2px solid #000; outline: none; }
    .row-cols-2 { display: flex; gap: 15px; align-items: baseline; }
    .row-cols-2 > div { flex: 1; }
    
    .payment-option { border: 1px solid #d9d9d9; border-radius: 5px; padding: 15px; margin-bottom: 10px; cursor: pointer; display: flex; align-items: center; gap: 15px; background: #fff; }
    .payment-option.active { border: 2px solid #17735b; background: #f4f9f8; }

    .save-postcode { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .save-postcode input { margin-top: 0; }
    .save-postcode label { margin: 0; }

    .sidebar { background: #fafafa; border-left: 1px solid #e6e6e6; padding: 20px; border-radius: 10px; }
    .cart-item { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; position: relative; }
    .item-img-wrapper { position: relative; width: 64px; height: 64px; border: 1px solid #e6e6e6; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; }
    .item-img-wrapper img { width: 90%; mix-blend-mode: multiply; }
    .qty-badge { position: absolute; top: -10px; right: -10px; background: #666; color: #fff; font-size: 12px; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    
    .btn-apply { height: 46px; align-self: center; border-radius: 12px; font-weight: 700; transition: 0.3s; }
    .btn-pay-now { width: 100%; background: #17735b; color: #fff; border: none; padding: 18px; border-radius: 5px; font-weight: 600; font-size: 1.1rem; cursor: pointer; margin-top: 25px; transition: background 0.3s; }
</style>

<div class="checkout-container">
    <div class="checkout-grid">
        <div class="main-form">
            <form id="orderForm" method="POST" action="">
                <div class="mb-5">
                    <h5 class="section-title">Contact</h5>
                    <input type="email" name="contact_email" class="input-field" placeholder="Email" value="<?php echo htmlspecialchars($user_info['User_Email']); ?>" required>
                </div>

                <div class="mb-5">
                    <h5 class="section-title">Delivery</h5>
                    
                    <div>
                        <div><input type="text" name="first_name" class="input-field" placeholder="First name" value="<?php echo $first_name; ?>" required oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')"></div>
                    </div>
                    
                    <input type="text" name="address" class="input-field" placeholder="Address" 
       value="<?php echo htmlspecialchars($user_address); ?>" required>
                    <input type="text" name="apartment" class="input-field" placeholder="Apartment, suite, unit etc. (optional)">
                    
                    <div class="row-cols-2">
                        <div>
                            <select name="state" id="stateSelect" class="input-field" required onchange="updateCities()">
                                <option value="" disabled selected>Select State</option>
                            </select>
                        </div>
                        <div>
                            <select name="city" id="citySelect" class="input-field" required onchange="updatePostcodes()">
                                <option value="" disabled selected>Select City</option>
                            </select>
                        </div>
                    </div>
                    <div class="row-cols-2">
                        <div>
                            <select name="postcode" id="postcodeSelect" class="input-field" required onchange="toggleCustomPostcode()">
                                <option value="" disabled selected>Postcode</option>
                            </select>
                        </div>
                        <div>
                            <input type="text" name="phone" id="phone_field" class="input-field" placeholder="Phone (e.g. 0123456789)" value="<?php echo htmlspecialchars($user_phone); ?>" required oninput="...">
                        </div>
                    </div>
                    <div id="customPostcodeDiv" style="display:none;">
                        <input type="text" name="custom_postcode" id="customPostcode" class="input-field" placeholder="Enter your postcode (5 digits)" maxlength="5" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    
                    <div class="save-postcode">
                        <input type="checkbox" name="save_postcode" id="save_postcode" class="form-check-input" <?php echo (isset($_COOKIE['saved_postcode']) && $_COOKIE['saved_postcode'] === $user_info['User_Postcode']) ? 'checked' : ''; ?>>
                        <label for="save_postcode" class="form-check-label small text-muted">Save this postcode for future orders</label>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="section-title">Payment</h5>
                    <div class="payment-option <?php echo ($current_balance < $grand_total) ? 'disabled' : 'active'; ?>" 
                        onclick="<?php echo ($current_balance < $grand_total) ? 'return false;' : 'selectPay(this)'; ?>"
                        style="<?php echo ($current_balance < $grand_total) ? 'opacity: 0.6; cursor: not-allowed;' : ''; ?>">
    
                    <input type="radio" name="pay_type" value="wallet" 
                        <?php echo ($current_balance >= $grand_total) ? 'checked' : 'disabled'; ?>>
                    
                    <div class="flex-grow-1">
                        <div class="fw-bold"><i class="bi bi-wallet2 me-2"></i>Store Wallet</div>
                        <div class="small text-muted">Balance: <strong>RM <?php echo number_format($current_balance, 2); ?></strong></div>
                    </div>

                    <?php if($current_balance < $grand_total): ?>
                        <span class="badge bg-danger">Insufficient Funds</span>
                    <?php endif; ?>
                    </div>
                    <div class="payment-option active" onclick="selectPay(this)">
                        <input type="radio" name="pay_type" value="card" checked>
                        <div><div class="fw-bold">Credit / Debit Card</div><div class="small text-muted">Visa, Mastercard</div></div>
                    </div>
                    <div id="cardFieldsDiv" style="display:none; margin-left: 15px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                        <div>
                            <div class="col-12">
                                <label class="small fw-bold">Card Number</label>
                                <input type="text" name="card_no" class="input-field" placeholder="16-digit card number" maxlength="16" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>
                        <div>
                            <div class="col-12">
                                <label class="small fw-bold">Cardholder Name</label>
                                <input type="text" name="cardholder_name" class="input-field" placeholder="JOHN DOE" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            </div>
                        </div>
                        <div style="display: block; gap: 15px;">
                            <div style="block: 1;">
                                <label class="small fw-bold">Expiry Date</label>
                                <input type="text" name="expiry" id="expiry" class="input-field" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
                            </div>
                            <div style="block: 1;">
                                <label class="small fw-bold">CVV</label>
                                <input type="password" name="cvv" class="input-field" placeholder="123" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>
                    </div>
                    <div class="payment-option" onclick="selectPay(this)">
                        <input type="radio" name="pay_type" value="fpx">
                        <div><div class="fw-bold">FPX</div><div class="small text-muted">Online Banking</div></div>
                    </div>
                    <div id="fpxBankDiv" style="display:none; margin-left: 15px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <div class="mb-3">
                        <label for="fpxBank" class="small fw-bold">Select Your Bank</label>
                        <select name="fpx_bank" id="fpxBank" class="input-field" required>
                            <option value="" disabled selected>Choose Bank</option>
                            <option value="MAYBANK">Maybank (MB)</option>
                            <option value="CIMB">CIMB (CIMB)</option>
                            <option value="PUBLIC">Public Bank (PB)</option>
                            <option value="RHB">RHB Bank (RHB)</option>
                            <option value="AMBANK">AmBank (AB)</option>
                            <option value="AFFIN">AFFIN Bank (AF)</option>
                            <option value="ALLIANCE">Alliance Bank (AB)</option>
                            <option value="BOOST">Boost (MY)</option>
                            <option value="UOB">UOB (UOB)</option>
                            <option value="OCBC">OCBC Bank (OCBC)</option>
                            <option value="HSBC">HSBC (HB)</option>
                            <option value="SCB">Standard Chartered (SCB)</option>
                            <option value="DBS">DBS (DB)</option>
                            <option value="BIM">Bank Islam (BI)</option>
                            <option value="IMM">Islamic Bank Mal (IM)</option>
                            <option value="BANK_MUAMALAT">Bank Muamalat (MB)</option>
                        </select>
                    </div>

                    <div class="mb-3">
        <label class="small fw-bold">Online Banking ID</label>
        <input type="text" name="fpx_user" id="fpxUser" class="input-field fpx-auth-input" placeholder="Username / Login ID">
    </div>

    <div>
        <label class="small fw-bold">Password</label>
        <input type="password" name="fpx_pass" id="fpxPass" class="input-field fpx-auth-input" placeholder="••••••••">
    </div>
</div>

                    
                </div>

                <input type="hidden" name="place_order" value="1">
                <button type="submit" class="btn-pay-now" onclick="return validateCheckoutForm()">Pay now</button>
            </form>
        </div>

        <div class="sidebar-wrapper">
            <div class="sidebar">
                <div class="mb-4">
                    <?php foreach($checkout_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-img-wrapper">
                                <img src="<?php echo $item['display_image']; ?>" onerror="this.src='../images/placeholder.png'">
                                <span class="qty-badge"><?php echo $item['qty']; ?></span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold small"><?php echo $item['Pro_Name']; ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;"><?php echo $item['size']; ?> / <?php echo $item['color']; ?></div>
                            </div>
                            <div class="fw-bold small">RM <?php echo number_format($item['item_total'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" action="" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="coupon_code" class="form-control" placeholder="Discount code" value="<?php echo $applied_code; ?>">
                        <button type="submit" name="apply_coupon" class="btn btn-dark btn-apply">Apply</button>
                    </div>
                    <?php if($error): ?><div class="text-danger small mt-2"><?php echo $error; ?></div><?php endif; ?>
                    <?php if($success_msg): ?><div class="text-success small mt-2"><?php echo $success_msg; ?></div><?php endif; ?>
                </form>

                <div class="total-line d-flex justify-content-between h4 fw-bold">
                    <span>Total</span>
                    <span>RM <?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ===============================================================
// 马来西亚 州属、城市、邮编 完整数据库 (按行政区划整理)
// ===============================================================
const locationData = {
    "Johor": {
        "Johor Bahru": ["80000", "81100", "81200", "81300"],
        "Batu Pahat": ["83000", "83010", "83040"],
        "Kluang": ["86000", "86100"],
        "Muar": ["84000", "84150"],
        "Segamat": ["85000", "85100"],
        "Pontian": ["82000", "82100"],
        "Kota Tinggi": ["81900"],
        "Mersing": ["86800"],
        "Tangkak": ["84900"],
        "Kulai": ["81000"]
    },
    "Kedah": {
        "Alor Setar": ["05000", "05100", "05200"],
        "Sungai Petani": ["08000", "08100"],
        "Kulim": ["09000", "09100"],
        "Langkawi": ["07000"],
        "Kubang Pasu": ["06000", "06100"],
        "Baling": ["09100"],
        "Sik": ["08200"],
        "Yan": ["06900"],
        "Pendang": ["06700"]
    },
    "Kelantan": {
        "Kota Bharu": ["15000", "15100", "16100"],
        "Pasir Mas": ["17000"],
        "Tumpat": ["16200"],
        "Tanah Merah": ["17500"],
        "Gua Musang": ["18300"],
        "Machang": ["18500"],
        "Kuala Krai": ["18000"]
    },
    "Melaka": {
        "Melaka City": ["75000", "75100", "75200", "75300", "75400"],
        "Alor Gajah": ["78000", "78100", "78300"],
        "Jasin": ["77000", "77100", "77300"]
    },
    "Negeri Sembilan": {
        "Seremban": ["70000", "70100", "70200", "70300", "70450"],
        "Port Dickson": ["71000", "71010"],
        "Nilai": ["71800"],
        "Jempol": ["72100"],
        "Tampin": ["73000"],
        "Kuala Pilah": ["72000"]
    },
    "Pahang": {
        "Kuantan": ["25000", "25100", "25200"],
        "Temerloh": ["28000"],
        "Bentong": ["28700"],
        "Mekan": ["26600"],
        "Raub": ["27600"],
        "Jerantut": ["27000"],
        "Cameron Highlands": ["39000", "39100"]
    },
    "Perak": {
        "Ipoh": ["30000", "30100", "31400"],
        "Taiping": ["34000", "34010"],
        "Teluk Intan": ["36000"],
        "Manjung/Siawan": ["32000", "32200"],
        "Kuala Kangsar": ["33000"],
        "Tapah": ["35000"],
        "Batu Gajah": ["31000"]
    },
    "Perlis": {
        "Kangar": ["01000"],
        "Arau": ["02600"],
        "Kuala Perlis": ["02000"],
        "Padang Besar": ["02100"]
    },
    "Pulau Pinang": {
        "Georgetown": ["10000", "10100", "10200", "10300", "10450"],
        "Bayan Lepas": ["11900", "11920", "11950"],
        "Butterworth": ["12000", "12100", "13400"],
        "Bukit Mertajam": ["14000", "14020"],
        "Kepala Batas": ["13200"],
        "Nibong Tebal": ["14300"]
    },
    "Sabah": {
        "Kota Kinabalu": ["88000", "88100", "88200", "88300"],
        "Sandakan": ["90000"],
        "Tawau": ["91000"],
        "Lahad Datu": ["91100"],
        "Penampang": ["89500"],
        "Keningau": ["89000"],
        "Putatan": ["88200"]
    },
    "Sarawak": {
        "Kuching": ["93000", "93100", "93200", "93300"],
        "Miri": ["98000", "98100"],
        "Sibu": ["96000"],
        "Bintulu": ["97000"],
        "Samarahan": ["94300"],
        "Sri Aman": ["95000"],
        "Limbang": ["98700"]
    },
    "Selangor": {
        "Shah Alam": ["40000", "40100", "40150", "40170", "40460"],
        "Petaling Jaya": ["46000", "46100", "46200", "47300", "47301", "47400"],
        "Klang": ["41000", "41050", "41100", "41200", "42100"],
        "Subang Jaya": ["47500", "47600", "47610"],
        "Puchong": ["47100", "47110", "47160"],
        "Cyberjaya": ["63000", "63100", "63200"],
        "Kajang": ["43000"],
        "Rawang": ["48000"],
        "Semenyih": ["43500"],
        "Sepang": ["43900"]
    },
    "Terengganu": {
        "Kuala Terengganu": ["20000", "20100", "21000"],
        "Kemaman": ["24000"],
        "Dungun": ["23000"],
        "Besut": ["22200"],
        "Marang": ["21600"],
        "Hulu Terengganu": ["21700"]
    },
    "Kuala Lumpur": {
        "KL City": ["50000", "50100", "50250", "50450", "50480"],
        "Cheras": ["56000", "56100"],
        "Kepong": ["52100", "52200"],
        "Wangsa Maju": ["53300"],
        "Setapak": ["53000", "53100"],
        "Bangsar": ["59000", "59100"],
        "Old Klang Road": ["58000", "58200"],
        "Sentul": ["51000", "51100"]
    },
    "Putrajaya": {
        "Putrajaya": ["62000", "62007", "62100", "62250"]
    },
    "Labuan": {
        "Labuan": ["87000", "87008", "87010"]
    }
};

// 初始化 State 下拉框
function initStates() {
    const stateSelect = document.getElementById('stateSelect');
    Object.keys(locationData).sort().forEach(state => {
        let option = document.createElement('option');
        option.value = state;
        option.text = state;
        stateSelect.add(option);
    });
}

function updateCities() {
    const state = document.getElementById('stateSelect').value;
    const citySelect = document.getElementById('citySelect');
    const postcodeSelect = document.getElementById('postcodeSelect');
    
    citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
    postcodeSelect.innerHTML = '<option value="" disabled selected>Postcode</option>';
    
    if (locationData[state]) {
        Object.keys(locationData[state]).sort().forEach(city => {
            let option = document.createElement('option');
            option.value = city;
            option.text = city;
            citySelect.add(option);
        });
    }
}

function updatePostcodes() {
    const state = document.getElementById('stateSelect').value;
    const city = document.getElementById('citySelect').value;
    const postcodeSelect = document.getElementById('postcodeSelect');
    
    postcodeSelect.innerHTML = '<option value="" disabled selected>Postcode</option>';
    
    if (locationData[state] && locationData[state][city]) {
        locationData[state][city].forEach(postcode => {
            let option = document.createElement('option');
            option.value = postcode;
            option.text = postcode;
            postcodeSelect.add(option);
        });
    }
    // Add "Other" option
    let otherOption = document.createElement('option');
    otherOption.value = 'other';
    otherOption.text = 'Other';
    postcodeSelect.add(otherOption);
}

// 核心修复：支付方式切换逻辑
// 核心修复：切换支付方式逻辑
function selectPay(el) {
    if (!el || el.classList.contains('disabled')) return;

    // 1. 切换视觉 active 状态
    document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    
    // 2. 勾选隐藏的单选框
    const radio = el.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    const payType = radio ? radio.value : '';
    
    // 3. 切换输入框显示/隐藏
    const fpxDiv = document.getElementById('fpxBankDiv');
    const cardDiv = document.getElementById('cardFieldsDiv');
    
    if (fpxDiv) fpxDiv.style.display = (payType === 'fpx') ? 'block' : 'none';
    if (cardDiv) cardDiv.style.display = (payType === 'card') ? 'block' : 'none';

    // 4. 清除/设置必填项，防止逻辑冲突
    document.querySelectorAll('.fpx-auth-input, #fpxBank, .card-input').forEach(input => {
        input.removeAttribute('required');
    });

    if (payType === 'fpx') {
        document.getElementById('fpxBank').setAttribute('required', 'true');
        document.querySelectorAll('.fpx-auth-input').forEach(i => i.setAttribute('required', 'true'));
    }
}

function validateCheckoutForm() {
    const selectedRadio = document.querySelector('input[name="pay_type"]:checked');
    if (!selectedRadio) {
        alert('Please select a payment method.');
        return false;
    }
    
    const payType = selectedRadio.value;

    // 验证基本配送信息
    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const address = document.querySelector('input[name="address"]').value.trim();
    if (!firstName || !address) {
        alert('Please fill in your delivery details.');
        return false;
    }

    if (payType === 'fpx') {
        const bank = document.getElementById('fpxBank').value;
        const user = document.getElementById('fpxUser').value.trim();
        const pass = document.getElementById('fpxPass').value.trim();

        if (!bank || !user || !pass) {
            alert('Please fill in all FPX banking details.');
            return false;
        }
    }

    if (payType === 'card') {
        const cardNo = document.querySelector('input[name="card_no"]').value;
        if (!cardNo || cardNo.length < 16) {
            alert('Please enter a valid 16-digit card number.');
            return false;
        }
    }

    return true; // 验证通过，允许提交表单
}

document.addEventListener('DOMContentLoaded', function() {
    initStates(); // 初始化所有州属

    // 获取数据库传来的资料
    const dbState = "<?php echo $user_state; ?>";
    const dbPostcode = "<?php echo $user_postcode; ?>";
    const dbCity = "<?php echo $user_city; ?>"; // 如果数据库有存城市名

    // 1. 自动选择州属
    if (dbState) {
        const stateSelect = document.getElementById('stateSelect');
        stateSelect.value = dbState;
        updateCities(); // 触发城市下拉框更新

        // 2. 自动选择城市 (如果有匹配项)
        if (dbCity) {
            const citySelect = document.getElementById('citySelect');
            citySelect.value = dbCity;
            updatePostcodes(); // 触发邮编下拉框更新

            // 3. 自动选择邮编
            if (dbPostcode) {
                const postcodeSelect = document.getElementById('postcodeSelect');
                // 检查邮编是否存在于下拉列表中
                let found = Array.from(postcodeSelect.options).some(opt => opt.value === dbPostcode);
                if (found) {
                    postcodeSelect.value = dbPostcode;
                } else {
                    // 如果列表里没有，选择 'other' 并填入自定义框
                    postcodeSelect.value = 'other';
                    toggleCustomPostcode();
                    document.getElementById('customPostcode').value = dbPostcode;
                }
            }
        }
    }

    // 初始化支付方式
    const activeOption = document.querySelector('.payment-option.active');
    if (activeOption) {
        selectPay(activeOption);
    }
});

function formatExpiry(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length >= 2) {
        input.value = val.slice(0, 2) + '/' + val.slice(2, 4);
    } else {
        input.value = val;
    }
}

function toggleCustomPostcode() {
    const postcodeSelect = document.getElementById('postcodeSelect');
    const customDiv = document.getElementById('customPostcodeDiv');
    const customInput = document.getElementById('customPostcode');
    if (postcodeSelect.value === 'other') {
        customDiv.style.display = 'block';
        customInput.required = true;
        customInput.focus();
    } else {
        customDiv.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

// 表单验证函数
function validateCheckoutForm() {
    const payType = document.querySelector('input[name="pay_type"]:checked').value;
    
    // 基本信息验证
    if (!document.querySelector('input[name="first_name"]').value) {
        alert('Please enter your first name');
        return false;
    }
    if (!document.querySelector('input[name="address"]').value) {
        alert('Please enter your address');
        return false;
    }
    if (!document.querySelector('select[name="state"]').value) {
        alert('Please select a state');
        return false;
    }
    if (!document.querySelector('select[name="city"]').value) {
        alert('Please select a city');
        return false;
    }
    if (!document.querySelector('select[name="postcode"]').value) {
        alert('Please select a postcode');
        return false;
    }
    if (document.querySelector('select[name="postcode"]').value === 'other' && !document.getElementById('customPostcode').value) {
        alert('Please enter the postcode');
        return false;
    }
    const phone = document.querySelector('input[name="phone"]').value;
    if (!phone || phone.length < 9 || phone.length > 12) {
        alert('Please enter a valid phone number (9-12 digits)');
        return false;
    }
    
    // 支付方式特定验证
    if (payType === 'card') {
        const cardNo = document.querySelector('input[name="card_no"]').value;
        const cardName = document.querySelector('input[name="cardholder_name"]').value;
        const expiry = document.querySelector('input[name="expiry"]').value;
        const cvv = document.querySelector('input[name="cvv"]').value;
        
        if (!cardNo || cardNo.length !== 16) {
            alert('Please enter a valid 16-digit card number');
            return false;
        }
        if (!cardName) {
            alert('Please enter the cardholder name');
            return false;
        }
        if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
            alert('Please enter a valid expiry date (MM/YY format)');
            return false;
        }
        if (!cvv || cvv.length !== 3) {
            alert('Please enter a valid 3-digit CVV');
            return false;
        }
    } else if (payType === 'fpx') {
        const fpxBank = document.querySelector('select[name="fpx_bank"]').value;
        if (!fpxBank) {
            alert('Please select a bank');
            return false;
        }
    }
    
    
    return true;
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function() {
    initStates();
    
    // 初始化支付方式的显示/隐藏状态
    const payType = document.querySelector('input[name="pay_type"]:checked').value;
    const fpxDiv = document.getElementById('fpxBankDiv');
    const cardDiv = document.getElementById('cardFieldsDiv');
    
    if (payType === 'card') {
        cardDiv.style.display = 'block';
        // 设置卡支付字段为必填
        const cardFields = ['card_no', 'cardholder_name', 'expiry', 'cvv'];
        cardFields.forEach(fieldId => {
            const field = document.querySelector(`[name="${fieldId}"]`);
            if (field) field.required = true;
        });
    } else if (payType === 'fpx') {
        fpxDiv.style.display = 'block';
        const fpxBank = document.getElementById('fpxBank');
        if (fpxBank) fpxBank.required = true;
    }
});
</script>

<?php include '../includes/footer.php'; ?>