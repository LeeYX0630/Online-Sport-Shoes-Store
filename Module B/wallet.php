<?php
// module_a/wallet.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = ""; 

// ==========================================
// 后端验证与提交逻辑
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_payment'])) {
    $amount = floatval($_POST['topup_amount']);
    $method = $_POST['payment_method'] ?? 'Unknown'; 
    $card_name = trim($_POST['cardholder_name'] ?? '');
    $expiry = trim($_POST['expiry'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $card_no = trim($_POST['card_no'] ?? '');
    
    $errors = [];

    if (strlen($card_no) !== 16) {
        $errors[] = "Card Number must be exactly 16 digits.";
    }

    if (!preg_match("/^[a-zA-Z\s]+$/", $card_name)) {
        $errors[] = "Cardholder Name must only contain letters and spaces.";
    }

    // 验证地址有效性
    if (empty($state) || empty($city) || empty($zip)) {
        $errors[] = "Please select a valid State, City, and ZIP Code.";
    }

    if (!preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expiry, $matches)) {
        $errors[] = "Expiry Date must be in MM/YY format.";
    } else {
        $exp_month = intval($matches[1]);
        $exp_year = intval("20" . $matches[2]); 
        $curr_month = intval(date('m'));
        $curr_year = intval(date('Y'));
        if ($exp_year < $curr_year || ($exp_year == $curr_year && $exp_month < $curr_month)) {
            $errors[] = "The credit card has expired.";
        }
    }

    if (empty($errors) && $amount > 0 && $amount <= 10000) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE `USER` SET User_Balance = User_Balance + $amount WHERE User_Id = '$user_id'");
            $description = "Top-up via $method (Verified: $card_name, Location: $city, $state)";
            $conn->query("INSERT INTO WALLET_TRANSACTION (User_Id, Amount, Type, Description) VALUES ('$user_id', '$amount', 'Top-up', '$description')");
            $conn->commit();
            $msg = "Secure payment complete! RM " . number_format($amount, 2) . " added to your wallet.";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Payment Failed: " . $e->getMessage();
            $msg_type = "danger";
        }
    } else {
        $msg = implode("<br>", $errors);
        $msg_type = "danger";
    }
}

$user_res = $conn->query("SELECT User_Balance, User_Name, User_Email FROM `USER` WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();
$trans_sql = "SELECT * FROM WALLET_TRANSACTION WHERE User_Id = '$user_id' ORDER BY Date DESC";
$trans_res = $conn->query($trans_sql);
$page_title = "Secure Wallet Top-up";
include '../includes/header.php';
?>

<style>
    .wallet-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: none; }
    .balance-display { background: #f8f9fa; border-radius: 12px; padding: 20px; border: 1px solid #eee; }
    .method-card { border: 2px solid #eee; border-radius: 12px; padding: 15px; margin-bottom: 15px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 15px; }
    .method-card:hover { border-color: #FF6B00; background: #fff9f5; }
    .method-card.active { border-color: #FF6B00; background: #fff9f5; box-shadow: 0 4px 12px rgba(255,107,0,0.1); }
    .method-icon { font-size: 24px; color: #FF6B00; }
    .payment-details-panel { background: #25293C; color: #fff; border-radius: 20px; padding: 30px; display: none; animation: slideIn 0.5s ease; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .form-label-custom { font-size: 11px; font-weight: 700; color: #A5A8B1; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: block; }
    .input-custom, .select-custom { background: #32374F; border: 1px solid #434968; border-radius: 10px; color: #fff; padding: 12px 15px; width: 100%; outline: none; transition: 0.3s; appearance: none; }
    .input-custom:focus, .select-custom:focus { border-color: #FF6B00; background: #3B415E; }
    .select-custom { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 15px center; }
    .btn-complete { background: #FF6B00; color: #fff; border: none; border-radius: 12px; padding: 16px; width: 100%; font-weight: 700; font-size: 16px; margin-top: 25px; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-complete:hover { background: #E56000; transform: translateY(-2px); }
</style>

<div class="container mt-5 mb-5">
    <?php if($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> rounded-3 mb-4 shadow-sm"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card wallet-card p-4 h-100">
                <div class="balance-display text-center mb-4">
                    <small class="text-muted text-uppercase fw-bold">Available Balance</small>
                    <h2 class="fw-bold mt-1" style="color: #FF6B00;">RM <?php echo number_format($user['User_Balance'], 2); ?></h2>
                </div>
                <form id="topupSetupForm">
                    <label class="fw-bold mb-2">1. Enter Top-up Amount</label>
                    <div class="input-group mb-4">
                        <span class="input-group-text bg-white border-end-0">RM</span>
                        <input type="number" id="main_amount" class="form-control border-start-0" placeholder="0.00" step="0.01" required>
                    </div>
                    <label class="fw-bold mb-3">2. Select Payment Method</label>
                    <div class="method-card active" onclick="setMethod('Credit/Debit Card', this)">
                        <i class="bi bi-credit-card-2-front method-icon"></i>
                        <div><h6 class="mb-0">Credit / Debit Card</h6><small class="text-muted">Visa, Mastercard, AMEX</small></div>
                    </div>
                    <div class="method-card" onclick="setMethod('FPX Online Banking', this)">
                        <i class="bi bi-bank method-icon"></i>
                        <div><h6 class="mb-0">FPX Online Banking</h6><small class="text-muted">Maybank, CIMB, PBB...</small></div>
                    </div>
                    <button type="button" class="btn btn-dark w-100 py-3 rounded-3 fw-bold mt-2" onclick="showPaymentDetails()">PROCEED TO DETAILS</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div id="paymentPanel" class="payment-details-panel shadow-lg">
                <h3 class="panel-title" style="font-size: 22px; font-weight: 600; margin-bottom: 25px;">Payment Details</h3>
                <form method="POST" action="wallet.php" id="securePaymentForm">
                    <input type="hidden" name="topup_amount" id="final_amount">
                    <input type="hidden" name="payment_method" id="final_method" value="Credit/Debit Card">

                    <div class="mb-4">
                        <label class="form-label-custom">Email Address</label>
                        <input type="email" name="email" class="input-custom" value="<?php echo $user['User_Email']; ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label-custom">Card Number</label>
                        <input type="text" name="card_no" class="input-custom" placeholder="1234 5678 9012 3456" maxlength="16" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>

                    <div class="mb-4">
                        <label class="form-label-custom">Cardholder Name</label>
                        <input type="text" name="cardholder_name" id="card_name" class="input-custom" placeholder="JOHN DOE" required oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label-custom">Expiry Date</label>
                            <input type="text" name="expiry" id="expiry" class="input-custom" placeholder="MM / YY" maxlength="5" required oninput="formatExpiry(this)">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label-custom">CVV</label>
                            <input type="password" name="cvv" class="input-custom" placeholder="123" maxlength="3" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                    </div>

                    <hr style="border-color: #434968; margin: 10px 0 25px 0;">
                    <h5 class="mb-4" style="font-size: 16px; font-weight: 500;">Billing Address (Malaysia)</h5>
                    
                    <div class="mb-4">
                        <label class="form-label-custom">Street Address</label>
                        <input type="text" name="address" class="input-custom" placeholder="Street Address" required>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label-custom">State</label>
                            <select name="state" id="state" class="select-custom" required onchange="populateCities()">
                                <option value="" disabled selected>Select State</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label-custom">City</label>
                            <select name="city" id="city" class="select-custom" required onchange="updateZip()">
                                <option value="" disabled selected>Select City</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label-custom">ZIP Code</label>
                            <select name="zip" id="zip" class="select-custom" required>
                                <option value="" disabled selected>ZIP</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="complete_payment" class="btn-complete">
                        <i class="bi bi-lock-fill"></i> Complete Secure Payment RM <span id="btnAmount">0.00</span>
                    </button>
                </form>
            </div>

            <div id="emptyPanel" class="card wallet-card h-100 d-flex align-items-center justify-content-center p-5 text-center">
                <i class="bi bi-shield-check text-muted mb-3" style="font-size: 50px;"></i>
                <h5 class="text-muted">Enter an amount and select a method <br> to complete your secure top-up.</h5>
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================
    // 马来西亚城市与邮编大数据集     // ==========================================
    const malaysiaData = {
        "Kuala Lumpur": {
            "Kuala Lumpur City": ["50000", "50050", "50100"],
            "Bukit Bintang": ["55100"],
            "Cheras": ["56000", "56100"],
            "Kepong": ["52100"],
            "Setapak": ["53300"]
        },
        "Selangor": {
            "Petaling Jaya": ["46000", "46100", "46200"],
            "Shah Alam": ["40000", "40100", "40150"],
            "Subang Jaya": ["47500", "47600"],
            "Klang": ["41000", "41050", "41200"],
            "Puchong": ["47100", "47110"]
        },
        "Johor": {
            "Johor Bahru": ["80000", "81100", "81200"],
            "Batu Pahat": ["83000"],
            "Muar": ["84000"],
            "Kluang": ["86000"],
            "Skudai": ["81300"]
        },
        "Pulau Pinang": {
            "Georgetown": ["10000", "10100", "10200"],
            "Bayan Lepas": ["11900", "11920"],
            "Butterworth": ["12000", "12100"],
            "Bukit Mertajam": ["14000"]
        },
        "Melaka": {
            "Melaka City": ["75000", "75100", "75200"],
            "Alor Gajah": ["78000"],
            "Jasin": ["77000"]
        },
        "Negeri Sembilan": {
            "Seremban": ["70000", "70100", "70300"],
            "Nilai": ["71800"],
            "Port Dickson": ["71000"]
        },
        "Perak": {
            "Ipoh": ["30000", "30100", "31400"],
            "Taiping": ["34000"],
            "Sitiawan": ["32000"]
        },
        "Kedah": {
            "Alor Setar": ["05000", "05100"],
            "Sungai Petani": ["08000"],
            "Langkawi": ["07000"]
        },
        "Pahang": {
            "Kuantan": ["25000", "25100", "25200"],
            "Temerloh": ["28000"],
            "Bentong": ["28700"]
        },
        "Kelantan": {
            "Kota Bharu": ["15000", "15100", "15200"],
            "Pasir Mas": ["17000"]
        },
        "Terengganu": {
            "Kuala Terengganu": ["20000", "20100"],
            "Kemaman": ["24000"]
        },
        "Sabah": {
            "Kota Kinabalu": ["88000", "88100"],
            "Sandakan": ["90000"],
            "Tawau": ["91000"]
        },
        "Sarawak": {
            "Kuching": ["93000", "93100"],
            "Miri": ["98000"],
            "Sibu": ["96000"]
        },
        "Putrajaya": {
            "Putrajaya": ["62000", "62100", "62250"]
        }
    };

    // 初始化 State 选项
    const stateSelect = document.getElementById('state');
    for (let state in malaysiaData) {
        stateSelect.options[stateSelect.options.length] = new Option(state, state);
    }

    function populateCities() {
        const citySelect = document.getElementById('city');
        const zipSelect = document.getElementById('zip');
        const selectedState = stateSelect.value;
        
        citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
        zipSelect.innerHTML = '<option value="" disabled selected>ZIP</option>';
        
        if (selectedState && malaysiaData[selectedState]) {
            for (let city in malaysiaData[selectedState]) {
                citySelect.options[citySelect.options.length] = new Option(city, city);
            }
        }
    }

    function updateZip() {
        const citySelect = document.getElementById('city');
        const zipSelect = document.getElementById('zip');
        const selectedState = stateSelect.value;
        const selectedCity = citySelect.value;
        
        zipSelect.innerHTML = '<option value="" disabled selected>ZIP</option>';
        
        if (selectedState && selectedCity && malaysiaData[selectedState][selectedCity]) {
            malaysiaData[selectedState][selectedCity].forEach(zip => {
                zipSelect.options[zipSelect.options.length] = new Option(zip, zip);
            });
            // 默认选择第一个
            if (zipSelect.options.length > 1) {
                zipSelect.selectedIndex = 1;
            }
        }
    }

    // 原有的格式化逻辑
    function formatExpiry(input) {
        let val = input.value.replace(/\D/g, ''); 
        if (val.length >= 2) {
            input.value = val.slice(0, 2) + '/' + val.slice(2, 4);
        } else {
            input.value = val;
        }
    }

    function setMethod(method, el) {
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('final_method').value = method;
    }

    function showPaymentDetails() {
        const amount = document.getElementById('main_amount').value;
        if (!amount || amount <= 0) {
            Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Please enter a positive amount to top-up.' });
            return;
        }
        document.getElementById('final_amount').value = amount;
        document.getElementById('btnAmount').innerText = parseFloat(amount).toFixed(2);
        document.getElementById('emptyPanel').style.display = 'none';
        document.getElementById('paymentPanel').style.display = 'block';
    }

    document.getElementById('securePaymentForm').addEventListener('submit', function(e) {
        const expiry = document.getElementById('expiry').value;
        const [month, year] = expiry.split('/').map(num => parseInt(num));
        const now = new Date();
        const currYear = parseInt(now.getFullYear().toString().slice(-2));
        const currMonth = now.getMonth() + 1;

        if (year < currYear || (year === currYear && month < currMonth)) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Card Expired', text: 'The expiry date cannot be in the past!' });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>