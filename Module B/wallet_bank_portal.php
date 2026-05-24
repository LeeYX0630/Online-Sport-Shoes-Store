<?php
// module_a/wallet_bank_portal.php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['topup_temp_data'])) {
    header("Location: wallet.php");
    exit();
}
$amount = $_SESSION['topup_temp_data']['amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FPX Topup Portal Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #333; }
        .portal-card { width: 100%; max-width: 420px; background: white; border-radius: 20px; overflow: hidden; }
    </style>
</head>
<body>
<div class="portal-card shadow-lg">
    <div class="p-4 bg-dark text-white text-center">
        <h5 class="mb-1 fw-bold text-warning">FPX Online Gateway</h5>
        <p class="mb-0 small text-white-50">Top-up Amount: <strong>RM <?php echo number_format($amount, 2); ?></strong></p>
    </div>
    <div class="p-4">
        <form action="wallet_bank_auth.php" method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Select Gateway Bank</label>
                <select name="bank_name" class="form-select" required>
                    <option value="Maybank2u">Maybank2u (MB)</option>
                    <option value="CIMB Clicks">CIMB Clicks (CIMB)</option>
                    <option value="Public Bank">Public Bank (PBB)</option>
                    <option value="RHB Now">RHB Now (RHB)</option>
                    <option value="AmBank">AmBank (AMB)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Online Banking ID</label>
                <input type="text" name="fpx_user" class="form-control" placeholder="Enter bank username" required autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Password</label>
                <input type="password" name="fpx_pass" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark">SECURE LOGIN</button>
            <div class="text-center mt-3"><a href="wallet.php" class="text-decoration-none text-muted small">Cancel Payment</a></div>
        </form>
    </div>
</div>
</body>
</html>