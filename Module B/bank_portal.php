<?php
// bank_portal.php
session_start();
$bank = $_GET['bank'] ?? 'MAYBANK';
$amt = $_GET['amt'] ?? '0.00';

// 银行 UI 配置表
$bank_styles = [
    'MAYBANK' => ['name' => 'Maybank2u', 'bg' => '#ffcc00', 'text' => '#000'],
    'CIMB' => ['name' => 'CIMB Clicks', 'bg' => '#ff0000', 'text' => '#fff'],
    'PUBLIC' => ['name' => 'PBE Bank', 'bg' => '#003399', 'text' => '#fff'],
    'RHB' => ['name' => 'RHB Now', 'bg' => '#0055aa', 'text' => '#fff'],
    'BIM' => ['name' => 'Bank Islam', 'bg' => '#008000', 'text' => '#fff'],
    // 默认样式
    'DEFAULT' => ['name' => 'Online Banking', 'bg' => '#333', 'text' => '#fff']
];
$style = $bank_styles[$bank] ?? $bank_styles['DEFAULT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $style['name']; ?> - Secure Authorization</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f4f4; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: Arial; }
        .bank-box { width: 400px; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .bank-header { background: <?php echo $style['bg']; ?>; color: <?php echo $style['text']; ?>; padding: 25px; text-align: center; }
        .bank-body { padding: 30px; }
        .btn-authorize { background: <?php echo $style['bg']; ?>; color: <?php echo $style['text']; ?>; border: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="bank-box">
        <div class="bank-header">
            <h3><?php echo $style['name']; ?></h3>
            <small>Merchant: SS Sport Shoes Store</small>
            <div class="h4 mt-2">RM <?php echo $amt; ?></div>
        </div>
        <div class="bank-body">
            <form action="checkout.php" method="POST">
                <!-- 关键：回传授权标识位和原本的订单数据[cite: 48] -->
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="bank_authorized" value="1">
                <?php 
                if (isset($_SESSION['temp_order_data'])) {
                    foreach ($_SESSION['temp_order_data'] as $k => $v) {
                        echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
                    }
                }
                ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Online Banking ID</label>
                    <input type="text" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn w-100 py-3 btn-authorize">Login & Authorize</button>
                <div class="text-center mt-3"><a href="checkout.php" class="text-muted small">Cancel Transaction</a></div>
            </form>
        </div>
    </div>
</body>
</html>