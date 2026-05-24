<?php
// module_a/wallet_bank_auth.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['topup_temp_data']) || !isset($_POST['bank_name'])) {
    header("Location: wallet.php");
    exit();
}

$uid = $_SESSION['user_id'];
$amount = floatval($_SESSION['topup_temp_data']['amount']);
$bank = htmlspecialchars($_POST['bank_name']);
$fpx_user = htmlspecialchars($_POST['fpx_user']);
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_fpx_topup'])) {
    $conn->begin_transaction();
    try {
        $conn->query("UPDATE `USER` SET User_Balance = User_Balance + $amount WHERE User_Id = '$uid'");
        $description = "Top-up via FPX [$bank] (ID: $fpx_user)";
        $conn->query("INSERT INTO WALLET_TRANSACTION (User_Id, Amount, Type, Description) VALUES ('$uid', '$amount', 'Top-up', '$description')");
        
        $conn->commit();
        unset($_SESSION['topup_temp_data']);
        header("Location: wallet.php?status=success&amt=$amount");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Transaction failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authorize Bank Transfer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light" style="min-height: 100vh; display: flex; align-items: center; justify-content: center;">
<div class="card shadow" style="width: 440px; border-radius: 16px;">
    <div class="p-4 bg-primary text-white text-center" style="border-radius: 16px 16px 0 0; background: #006c64 !important;">
        <h5 class="fw-bold mb-0">Secure Financial Authorization</h5>
    </div>
    <div class="card-body p-4 text-center">
        <p class="text-muted small">Please verify details below for <strong><?php echo $bank; ?></strong></p>
        <div class="p-3 bg-light rounded-3 mb-4 text-start small">
            <div class="d-flex justify-content-between mb-1"><span>Merchant:</span><strong>SS SPORT SHOES</strong></div>
            <div class="d-flex justify-content-between mb-1"><span>Account ID:</span><strong><?php echo $fpx_user; ?></strong></div>
            <div class="d-flex justify-content-between text-danger"><span>Amount:</span><strong>RM <?php echo number_format($amount, 2); ?></strong></div>
        </div>

        <form method="POST">
            <input type="hidden" name="bank_name" value="<?php echo $bank; ?>">
            <input type="hidden" name="fpx_user" value="<?php echo $fpx_user; ?>">
            
            <div class="form-check text-start mb-4">
                <input class="form-check-input" type="checkbox" id="check" required>
                <label class="form-check-label small" for="check">I authorize FPX to debit RM <?php echo number_format($amount, 2); ?> from my account.</label>
            </div>
            
            <button type="submit" name="confirm_fpx_topup" class="btn w-100 text-white py-2 fw-bold" style="background: #006c64;">Confirm &amp; Discharge</button>
            <a href="wallet.php" class="btn btn-link link-secondary btn-sm w-100 mt-2">Abort Payment</a>
        </form>
    </div>
</div>
</body>
</html>