<?php
// bank_auth.php
session_start();
$bank = $_POST['bank'] ?? 'MAYBANK';
$amt = $_POST['amt'] ?? '0.00';

$bank_config = [
    'MAYBANK' => [
        'name' => 'Maybank2u',
        'gradient' => 'linear-gradient(135deg, #ffcc00 0%, #ffd733 100%)',
        'accent' => '#111',
        'card' => '#fff9d8',
        'text' => '#111'
    ],
    'CIMB' => [
        'name' => 'CIMB Clicks',
        'gradient' => 'linear-gradient(135deg, #d10100 0%, #ff5353 100%)',
        'accent' => '#fff',
        'card' => '#ffe8e8',
        'text' => '#111'
    ],
    'PUBLIC' => [
        'name' => 'Public Bank',
        'gradient' => 'linear-gradient(135deg, #0043a4 0%, #0072d4 100%)',
        'accent' => '#fff',
        'card' => '#e8f0ff',
        'text' => '#111'
    ],
    'RHB' => [
        'name' => 'RHB Bank',
        'gradient' => 'linear-gradient(135deg, #db0000 0%, #ff4d4d 100%)',
        'accent' => '#fff',
        'card' => '#ffe8e8',
        'text' => '#111'
    ],
    'AMBANK' => [
        'name' => 'AmBank',
        'gradient' => 'linear-gradient(135deg, #ff8200 0%, #ffb44d 100%)',
        'accent' => '#111',
        'card' => '#fff3d9',
        'text' => '#111'
    ],
    'AFFIN' => [
        'name' => 'Affin Bank',
        'gradient' => 'linear-gradient(135deg, #007a33 0%, #42b676 100%)',
        'accent' => '#fff',
        'card' => '#e7f7ee',
        'text' => '#111'
    ],
    'DEFAULT' => [
        'name' => 'FPX Authorization',
        'gradient' => 'linear-gradient(135deg, #333 0%, #4a4a4a 100%)',
        'accent' => '#fff',
        'card' => '#f3f3f3',
        'text' => '#111'
    ]
];
$style = $bank_config[$bank] ?? $bank_config['DEFAULT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authorize Payment - <?php echo $style['name']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body style="background: radial-gradient(circle at top left, rgba(255,255,255,0.9), transparent 30%), radial-gradient(circle at bottom right, rgba(0,0,0,0.08), transparent 22%), <?php echo $style['gradient']; ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div class="text-center p-0 shadow-lg rounded-4" style="width: 470px; overflow: hidden;">
        <div class="p-4 text-center" style="background: <?php echo $style['gradient']; ?>; color: <?php echo $style['accent']; ?>;">
            <div style="font-size: 0.85rem; letter-spacing: 0.14em; text-transform: uppercase; opacity: 0.9;"><?php echo $style['name']; ?></div>
            <h3 class="mt-3 fw-bold">Review &amp; Confirm</h3>
            <p class="mb-0" style="opacity: 0.9;">Confirm the payment details securely with <?php echo $style['name']; ?>.</p>
        </div>
        <div class="p-4" style="background: <?php echo $style['card']; ?>; color: <?php echo $style['text']; ?>;">
            <div class="bg-white rounded-4 p-4 mb-4" style="box-shadow: 0 20px 45px rgba(0,0,0,0.08);">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-uppercase small text-muted">From</div>
                        <div class="fw-bold">STRYDEX SPORT SHOES STORE</div>
                        <div class="small text-secondary">Merchant Account</div>
                    </div>
                    <div class="text-end">
                        <div class="text-uppercase small text-muted">Bank</div>
                        <div class="fw-bold"><?php echo $style['name']; ?></div>
                    </div>
                </div>
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted small">Amount</span><strong>RM <?php echo htmlspecialchars($amt); ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted small">Reference</span><strong>FPX-<?php echo time(); ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted small">Date</span><strong><?php echo date('d M Y, h:i A'); ?></strong></div>
                </div>
            </div>
            <div class="p-4 rounded-4 mb-4" style="background: rgba(255,255,255,0.92); border: 1px solid rgba(0,0,0,0.05);">
                <div class="mb-3 text-start">
                    <div class="fw-bold">Security Alert</div>
                    <div class="small text-muted">You are about to authorize a secure FPX transaction. Please confirm all details are correct.</div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="confirmCheck">
                    <label class="form-check-label small" for="confirmCheck">I have reviewed the transaction details and authorize this payment.</label>
                </div>
            </div>
            <form action="checkout.php" method="POST">
            <!-- 关键：发送支付成功标识，告诉 checkout.php 可以下单了[cite: 25] -->
            <input type="hidden" name="place_order" value="1">
            <input type="hidden" name="pay_type" value="fpx">
            <input type="hidden" name="fpx_success" value="1">
            
            <button type="submit" class="btn w-100 py-3 fw-bold shadow-sm" style="background: <?php echo $style['gradient']; ?>; color: <?php echo $style['accent']; ?>; border: none;">Confirm &amp; Pay Now</button>
        </form>
        <p class="mt-3 small text-muted" style="opacity: 0.9;"><i class="bi bi-shield-lock-fill"></i> Secure 256-bit encrypted connection</p>
    </div>
</div>
    <script>
        document.querySelector('form').addEventListener('submit', function(event) {
            const confirmed = document.getElementById('confirmCheck');
            if (confirmed && !confirmed.checked) {
                event.preventDefault();
                alert('Please confirm the transaction details before proceeding.');
            }
        });
    </script>
</body>
</html>