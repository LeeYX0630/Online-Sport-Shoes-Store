<?php
// module_a/wallet_card_auth.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['topup_temp_data'])) {
    header("Location: wallet.php");
    exit();
}

$uid = $_SESSION['user_id'];
$topup = $_SESSION['topup_temp_data'];
$amount = floatval($topup['amount']);
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_topup_card'])) {
    $card_no = trim($_POST['card_no']);
    $card_name = trim($_POST['cardholder_name']);
    $expiry = trim($_POST['expiry']);
    $cvv = trim($_POST['cvv']);

    if (strlen($card_no) !== 16 || !ctype_digit($card_no)) {
        $error = "Card Number must be exactly 16 digits.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $card_name)) {
        $error = "Invalid Cardholder Name. Letters only.";
    } elseif (!preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expiry)) {
        $error = "Invalid Expiry format (MM/YY).";
    } elseif (strlen($cvv) !== 3 || !ctype_digit($cvv)) {
        $error = "CVV must be exactly 3 digits.";
    } else {
        list($m, $y) = explode('/', $expiry);
        $currentFullYear = intval(date('Y'));
        $currentMonth = intval(date('m'));
        $expiryFullYear = 2000 + intval($y);
        $expiryMonth = intval($m);
        $maxExpiryFullYear = $currentFullYear + 10;

        if ($expiryFullYear < $currentFullYear || ($expiryFullYear == $currentFullYear && $expiryMonth < $currentMonth)) {
            $error = "The card has expired.";
        } elseif ($expiryFullYear > $maxExpiryFullYear || ($expiryFullYear == $maxExpiryFullYear && $expiryMonth > $currentMonth)) {
            $error = "Expiry must be within 10 years from today.";
        }
    }

    if (empty($error)) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE `USER` SET User_Balance = User_Balance + $amount WHERE User_Id = '$uid'");
            $description = "Top-up via Card ($card_name)";
            $conn->query("INSERT INTO WALLET_TRANSACTION (User_Id, Amount, Type, Description) VALUES ('$uid', '$amount', 'Top-up', '$description')");
            
            $conn->commit();
            unset($_SESSION['topup_temp_data']);
            header("Location: wallet.php?status=success&amt=$amount");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gateway Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Topup Card Authorization</title>
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
<div class="payment-wrapper shadow-lg">
    <div class="text-center mb-4">
        <div class="small text-uppercase text-muted"><i class="bi bi-shield-lock-fill text-success"></i> Topup Gateway</div>
        <h4 class="fw-bold mt-1">Card Authorization</h4>
        <div class="text-danger fw-bold fs-5">Amount: RM <?php echo number_format($amount, 2); ?></div>
    </div>

    <div class="credit-card-view mb-4">
        <div class="position-absolute top-0 end-0 p-3 fs-3 text-muted" id="cardLogo"><i class="bi bi-credit-card"></i></div>
        <div class="card-chip"></div>
        <div class="card-number-view" id="viewCardNo">•••• •••• •••• ••••</div>
        <div class="d-flex justify-content-between text-uppercase small text-muted">
            <div><small class="d-block" style="font-size:0.6rem;">Holder</small><strong class="text-white" id="viewName">YOUR NAME</strong></div>
            <div class="text-end"> Masa<small class="d-block" style="font-size:0.6rem;">Expiry</small><strong class="text-white" id="viewExpiry">MM/YY</strong></div>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger p-2 small border-0 text-center" style="background:rgba(220,53,69,0.2); color:#ffcdd2;"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">CARD NUMBER</label>
            <input type="text" name="card_no" id="cardNo" class="form-control input-dark" placeholder="16-digit card number" maxlength="16" required autocomplete="off">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">CARDHOLDER NAME</label>
            <input type="text" name="cardholder_name" id="cardName" class="form-control input-dark" placeholder="e.g. JOHN DOE" required autocomplete="off">
        </div>
        <div class="row mb-4">
            <div class="col-6">
                <label class="form-label small fw-bold text-muted">EXPIRY DATE</label>
                <input type="text" name="expiry" id="expiry" class="form-control input-dark text-center" placeholder="MM/YY" maxlength="5" required autocomplete="off">
            </div>
            <div class="col-6">
                <label class="form-label small fw-bold text-muted">CVV</label>
                <input type="password" name="cvv" class="form-control input-dark text-center" placeholder="•••" maxlength="3" required autocomplete="off">
            </div>
        </div>
        <button type="submit" name="submit_topup_card" class="btn w-100 py-3 fw-bold" style="background:#ff6b00; color:#html; border:none; border-radius:10px; color:#fff;">Load RM <?php echo number_format($amount, 2); ?> Instantly</button>
        <div class="text-center mt-3"><a href="wallet.php" class="text-decoration-none text-muted small">Aborted and Return</a></div>
    </form>
</div>

<script>
    document.getElementById('cardNo').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        const logo = document.getElementById('cardLogo');
        if(this.value.startsWith('4')) logo.innerHTML = 'Visa';
        else if(this.value.startsWith('5')) logo.innerHTML = 'Master';
        else logo.innerHTML = '<i class="bi bi-credit-card"></i>';
        document.getElementById('viewCardNo').innerText = this.value.replace(/(\d{4})/g, '$1 ').trim() || '•••• •••• •••• ••••';
    });
    document.getElementById('cardName').addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        document.getElementById('viewName').innerText = this.value.toUpperCase() || 'YOUR NAME';
    });
    document.getElementById('expiry').addEventListener('input', function() {
        let val = this.value.replace(/\D/g, '');
        this.value = (val.length >= 2) ? val.slice(0,2) + '/' + val.slice(2,4) : val;
        document.getElementById('viewExpiry').innerText = this.value || 'MM/YY';
    });
</script>
</body>
</html>