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

// 2. 获取用户基础信息 (来自 USER 表)
$user_sql = "SELECT * FROM `USER` WHERE User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();

// 简单的姓名拆分逻辑
$name_parts = explode(' ', $user_info['User_Name'], 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : "";

// 3. 计算购物车总额与商品清单
$subtotal = 0;
$checkout_items = [];
foreach ($_SESSION['cart'] as $cart_key => $item) {
    $pid = $item['pro_id'];
    $sql_p = "SELECT Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id = '$pid'";
    $res_p = $conn->query($sql_p);
    if ($res_p && $res_p->num_rows > 0) {
        $p_data = $res_p->fetch_assoc();
        $p_data['qty'] = $item['qty'];
        $p_data['size'] = $item['size'];
        $p_data['color'] = $item['color'];
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

// 5. 最终下单处理
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    
    $f_name = trim($_POST['first_name']);
    $l_name = trim($_POST['last_name']);
    if ($_POST['postcode'] === 'other') {
        $postcode = trim($_POST['custom_postcode']);
    } else {
        $postcode = trim($_POST['postcode']);
    }
    $phone = trim($_POST['phone']);

    // 后端验证
    if (!preg_match("/^[a-zA-Z\s]+$/", $f_name) || !preg_match("/^[a-zA-Z\s]+$/", $l_name)) {
        $error = "Names should only contain letters.";
    } elseif (strlen($phone) > 12 || !preg_match("/^0[1-9][0-9]{7,9}$/", $phone)) {
        $error = "Invalid Malaysia phone number format (max 12 digits).";
    } elseif (!preg_match("/^[0-9]{5}$/", $postcode)) {
        $error = "Postcode must be 5 digits.";
    } else {
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
            unset($_SESSION['cart']);
            header("Location: payment_success.php?order_id=" . $order_id);
            exit();
        } catch (Exception $e) { $conn->rollback(); $error = "Order Failed: " . $e->getMessage(); }
    }
}

include '../includes/header.php';
?>

<style>
    body { background-color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; }
    .checkout-container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
    .checkout-grid { display: grid; grid-template-columns: 1fr 400px; gap: 60px; }
    @media (max-width: 992px) { .checkout-grid { grid-template-columns: 1fr; } }
    
    .section-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; color: #000; }
    .input-field { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 5px; font-size: 0.95rem; margin-bottom: 12px; transition: border 0.2s; background-color: #fff; }
    .input-field:focus { border: 2px solid #000; outline: none; }
    .row-cols-2 { display: flex; gap: 15px; }
    .row-cols-2 > div { flex: 1; }
    
    .payment-option { border: 1px solid #d9d9d9; border-radius: 5px; padding: 15px; margin-bottom: 10px; cursor: pointer; display: flex; align-items: center; gap: 15px; background: #fff; }
    .payment-option.active { border: 2px solid #17735b; background: #f4f9f8; }

    .sidebar { background: #fafafa; border-left: 1px solid #e6e6e6; padding: 20px; border-radius: 10px; }
    .cart-item { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; position: relative; }
    .item-img-wrapper { position: relative; width: 64px; height: 64px; border: 1px solid #e6e6e6; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; }
    .item-img-wrapper img { width: 90%; mix-blend-mode: multiply; }
    .qty-badge { position: absolute; top: -10px; right: -10px; background: #666; color: #fff; font-size: 12px; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    
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
                    
                    <div class="row-cols-2">
                        <div><input type="text" name="first_name" class="input-field" placeholder="First name" value="<?php echo $first_name; ?>" required oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')"></div>
                        <div><input type="text" name="last_name" class="input-field" placeholder="Last name" value="<?php echo $last_name; ?>" required oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')"></div>
                    </div>
                    
                    <input type="text" name="address" class="input-field" placeholder="Address (Street name, House No.)" value="<?php echo htmlspecialchars($user_info['User_Address']); ?>" required>
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
                             <input type="text" name="phone" id="phone_field" class="input-field" placeholder="Phone (e.g. 0123456789)" maxlength="12" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                    </div>
                    <div id="customPostcodeDiv" style="display:none;">
                        <input type="text" name="custom_postcode" id="customPostcode" class="input-field" placeholder="Enter your postcode (5 digits)" maxlength="5" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="section-title">Payment</h5>
                    <div class="payment-option active" onclick="selectPay(this)">
                        <input type="radio" name="pay_type" value="card" checked>
                        <div><div class="fw-bold">Credit / Debit Card</div><div class="small text-muted">Visa, Mastercard</div></div>
                    </div>
                    <div class="payment-option" onclick="selectPay(this)">
                        <input type="radio" name="pay_type" value="fpx">
                        <div><div class="fw-bold">FPX</div><div class="small text-muted">Online Banking</div></div>
                    </div>
                </div>

                <input type="hidden" name="place_order" value="1">
                <button type="submit" class="btn-pay-now">Pay now</button>
            </form>
        </div>

        <div class="sidebar-wrapper">
            <div class="sidebar">
                <div class="mb-4">
                    <?php foreach($checkout_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-img-wrapper">
                                <img src="../uploads/<?php echo $item['Pro_Image']; ?>" onerror="this.src='../images/placeholder.png'">
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
                        <button type="submit" name="apply_coupon" class="btn btn-dark">Apply</button>
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

function selectPay(el) {
    document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input[type="radio"]').checked = true;
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

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', initStates);
</script>

<?php include '../includes/footer.php'; ?>