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
    $bank_name = $_POST['bank_choice'] ?? '';
    $fpx_user = $_POST['fpx_username'] ?? '';
    
    $errors = [];

    if ($method == 'Credit/Debit Card') {
        $card_no = trim($_POST['card_no'] ?? '');
        $expiry = trim($_POST['expiry'] ?? '');
        if (strlen($card_no) !== 16) $errors[] = "Card Number must be exactly 16 digits.";
        if (!preg_match("/^[a-zA-Z\s]+$/", $card_name)) $errors[] = "Invalid Cardholder Name.";
        if (!preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expiry)) $errors[] = "Invalid Expiry format (MM/YY).";
    } else if ($method == 'FPX Online Banking') {
        if (empty($bank_name)) $errors[] = "Please select a bank.";
        if (empty($fpx_user)) $errors[] = "Please enter your Online Banking ID.";
    }

    if (empty($errors) && $amount > 0 && $amount <= 10000) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE `USER` SET User_Balance = User_Balance + $amount WHERE User_Id = '$user_id'");
            $detail = ($method == 'FPX Online Banking') ? "via $bank_name (ID: $fpx_user)" : "via Card ($card_name)";
            $description = "Top-up $detail";
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
    
    /* 快捷金额按钮样式 */
    .amt-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
    .btn-amt { border: 1px solid #ddd; background: #fff; padding: 10px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; color: #333; }
    .btn-amt:hover { border-color: #FF6B00; color: #FF6B00; }
    .btn-amt.active { background: #FF6B00; color: #fff; border-color: #FF6B00; }

    .payment-details-panel { background: #25293C; color: #fff; border-radius: 20px; padding: 30px; display: none; }
    .form-label-custom { font-size: 11px; font-weight: 700; color: #A5A8B1; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: block; }
    .input-custom, .select-custom { background: #32374F; border: 1px solid #434968; border-radius: 10px; color: #fff; padding: 12px 15px; width: 100%; outline: none; }
    .input-custom:focus, .select-custom:focus { border-color: #FF6B00; }
    .btn-complete { background: #FF6B00; color: #fff; border: none; border-radius: 12px; padding: 16px; width: 100%; font-weight: 700; transition: 0.3s; }
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
                    <label class="fw-bold mb-2">1. Select Top-up Amount</label>
                    <div class="amt-grid">
                        <button type="button" class="btn-amt" onclick="setQuickAmount(20, this)">RM 20</button>
                        <button type="button" class="btn-amt" onclick="setQuickAmount(50, this)">RM 50</button>
                        <button type="button" class="btn-amt" onclick="setQuickAmount(100, this)">RM 100</button>
                        <button type="button" class="btn-amt" onclick="setQuickAmount(150, this)">RM 150</button>
                        <button type="button" class="btn-amt" onclick="setQuickAmount(300, this)">RM 300</button>
                        <button type="button" class="btn-amt" onclick="setQuickAmount(500, this)">RM 500</button>
                        <button type="button" class="btn-amt" style="grid-column: span 3;" onclick="setQuickAmount('other', this)">Other Amount</button>
                    </div>

                    <div class="input-group mb-4">
                        <span class="input-group-text bg-white border-end-0">RM</span>
                        <input type="number" id="main_amount" class="form-control border-start-0" placeholder="0.00" step="0.01" required readonly>
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
                <h3 id="panelTitle" class="mb-4">Payment Details</h3>
                <form method="POST" action="wallet.php" id="securePaymentForm">
                    <input type="hidden" name="topup_amount" id="final_amount">
                    <input type="hidden" name="payment_method" id="final_method" value="Credit/Debit Card">

                    <div id="cardFields">
                        <div class="mb-4">
                            <label class="form-label-custom">Card Number</label>
                            <input type="text" name="card_no" class="input-custom" placeholder="16-digit card number" maxlength="16" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="mb-4">
                            <label class="form-label-custom">Cardholder Name</label>
                            <input type="text" name="cardholder_name" class="input-custom" placeholder="JOHN DOE" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label-custom">Expiry Date</label>
                                <input type="text" name="expiry" id="expiry" class="input-custom" placeholder="MM / YY" maxlength="5" oninput="formatExpiry(this)">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label-custom">CVV</label>
                                <input type="password" name="cvv" class="input-custom" placeholder="123" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>
                    </div>

                    <div id="fpxFields" style="display: none;">
                        <div class="mb-4">
                            <label class="form-label-custom">Select Your Bank</label>
                            <select name="bank_choice" class="select-custom w-100 p-2 border-0 rounded-3 text-white" style="background:#32374F;">
                                <option value="" disabled selected>-- Choose Bank --</option>
                                <option value="Maybank2u">Maybank2u</option>
                                <option value="CIMB Clicks">CIMB Clicks</option>
                                <option value="Public Bank">Public Bank</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label-custom">Online Banking ID</label>
                            <input type="text" name="fpx_username" class="input-custom" placeholder="Username / ID">
                        </div>
                        <div class="mb-4">
                            <label class="form-label-custom">Password</label>
                            <input type="password" name="fpx_password" class="input-custom" placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" name="complete_payment" class="btn-complete mt-4">
                        <i class="bi bi-lock-fill"></i> <span id="btnText">Complete Secure Payment</span> RM <span id="btnAmount">0.00</span>
                    </button>
                </form>
            </div>

            <div id="emptyPanel" class="card wallet-card h-100 d-flex align-items-center justify-content-center p-5 text-center shadow-sm">
                <div>
                    <i class="bi bi-shield-check text-muted mb-3" style="font-size: 60px;"></i>
                    <h5 class="text-muted mt-2">Select an amount and method <br> to complete your secure top-up.</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 核心功能：设置快捷金额按钮
    function setQuickAmount(amt, el) {
        // 视觉反馈：切换选中状态
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        el.classList.add('active');

        const input = document.getElementById('main_amount');
        if (amt === 'other') {
            input.value = ''; // 清空
            input.readOnly = false; // 允许编辑
            input.focus(); // 自动聚焦
            input.placeholder = "Enter amount manually";
        } else {
            input.value = amt; // 设置金额
            input.readOnly = true; // 只读
            input.placeholder = "0.00";
        }
    }

    function setMethod(method, el) {
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('final_method').value = method;
        
        const cardFields = document.getElementById('cardFields');
        const fpxFields = document.getElementById('fpxFields');
        const title = document.getElementById('panelTitle');
        const btnText = document.getElementById('btnText');

        if (method === 'FPX Online Banking') {
            cardFields.style.display = 'none';
            fpxFields.style.display = 'block';
            title.innerText = "FPX Online Banking Login";
            btnText.innerText = "Login & Pay";
        } else {
            cardFields.style.display = 'block';
            fpxFields.style.display = 'none';
            title.innerText = "Payment Details";
            btnText.innerText = "Complete Secure Payment";
        }
    }

    function showPaymentDetails() {
        const amount = document.getElementById('main_amount').value;
        if (!amount || amount <= 0) {
            Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Please select or enter an amount.' });
            return;
        }
        document.getElementById('final_amount').value = amount;
        document.getElementById('btnAmount').innerText = parseFloat(amount).toFixed(2);
        
        document.getElementById('emptyPanel').classList.remove('d-flex');
        document.getElementById('emptyPanel').style.display = 'none';
        document.getElementById('paymentPanel').style.display = 'block';
    }

    function formatExpiry(input) {
        let val = input.value.replace(/\D/g, ''); 
        if (val.length >= 2) input.value = val.slice(0, 2) + '/' + val.slice(2, 4);
        else input.value = val;
    }

    async function forgotWalletPIN() {
    // 步骤 1: 发送 OTP
    Swal.fire({
        title: 'Reset Wallet PIN',
        text: "We will send an OTP to your registered email.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Send OTP',
        confirmButtonColor: '#FF6B00',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('wallet_pin_reset_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=request_otp'
            }).then(res => res.json());
        }
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            // 步骤 2: 输入 OTP 和 新 PIN
            handleOTPInput();
        }
    });
}

function handleOTPInput() {
    Swal.fire({
        title: 'Verify OTP',
        html: `
            <input type="text" id="otp_code" class="swal2-input" placeholder="6-digit OTP" maxlength="6">
            <input type="password" id="reset_pin" class="swal2-input" placeholder="Enter New 6-digit PIN" maxlength="6">
        `,
        confirmButtonText: 'Reset PIN',
        confirmButtonColor: '#17735b',
        preConfirm: () => {
            const otp = document.getElementById('otp_code').value;
            const pin = document.getElementById('reset_pin').value;
            if (!/^\d{6}$/.test(otp)) return Swal.showValidationMessage('Invalid OTP format');
            if (!/^\d{6}$/.test(pin)) return Swal.showValidationMessage('PIN must be 6 digits');
            
            let formData = new URLSearchParams();
            formData.append('action', 'verify_and_reset');
            formData.append('otp', otp);
            formData.append('new_pin', pin);

            return fetch('wallet_pin_reset_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData.toString()
            }).then(res => res.json());
        }
    }).then((result) => {
        if (result.value && result.value.success) {
            Swal.fire('Success!', 'Your Wallet PIN has been updated.', 'success').then(() => location.reload());
        } else if (result.value) {
            Swal.fire('Failed', result.value.message, 'error');
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>