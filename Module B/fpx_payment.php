<?php
// Module B: FPX Payment Handler
ob_start();
session_start();
require_once '../includes/db_connection.php';

// 1. 验证用户登录和订单
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Module A/login.php");
    exit();
}

if (!isset($_GET['order_id']) || !isset($_GET['bank'])) {
    header("Location: checkout.php");
    exit();
}

$uid = $_SESSION['user_id'];
$order_id = intval($_GET['order_id']);
$bank = $conn->real_escape_string($_GET['bank']);

// 验证订单是否属于该用户
$order_check = $conn->query("SELECT * FROM `ORDER` WHERE Order_Id = '$order_id' AND User_Id = '$uid'");
if ($order_check->num_rows === 0) {
    header("Location: checkout.php");
    exit();
}

$error = "";
$success_msg = "";

// 2. 处理 FPX 支付表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_fpx'])) {
    $fpx_id = trim($_POST['fpx_id']);
    $fpx_password = trim($_POST['fpx_password']);
    $fpx_bank = $conn->real_escape_string($_POST['fpx_bank']);
    
    // 验证输入
    if (empty($fpx_id) || empty($fpx_password)) {
        $error = "Please enter both ID and Password.";
    } else {
        // 这里可以添加实际的 FPX API 集成
        // 现在我们模拟支付成功
        
        // 更新订单支付状态为已支付
        $update_sql = "UPDATE `ORDER` SET Payment_Status = 'Paid' WHERE Order_Id = '$order_id'";
        if ($conn->query($update_sql)) {
            // 记录 FPX 支付交易
            $fpx_ref = "FPX_" . date('YmdHis') . "_" . $order_id;
            $insert_sql = "INSERT INTO WALLET_TRANSACTION (User_Id, Amount, Type, Description) 
                          VALUES ('$uid', (SELECT Order_Amount FROM `ORDER` WHERE Order_Id = '$order_id'), 'FPX_Payment', 'FPX Payment Order #$order_id - Bank: $fpx_bank - Ref: $fpx_ref')";
            $conn->query($insert_sql);
            
            // 跳转到支付成功页面
            header("Location: payment_success.php?order_id=" . $order_id . "&method=fpx");
            exit();
        } else {
            $error = "Payment processing failed. Please try again.";
        }
    }
}

include '../includes/header.php';
?>

<style>
    body { background-color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; }
    .fpx-container { max-width: 600px; margin: 40px auto; padding: 20px; }
    .fpx-card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 10px; padding: 30px; }
    .fpx-header { text-align: center; margin-bottom: 30px; }
    .fpx-header h2 { color: #17735b; margin-bottom: 10px; }
    .fpx-header p { color: #666; font-size: 0.95rem; }
    
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 5px; font-size: 0.95rem; box-sizing: border-box; }
    .form-control:focus { border: 2px solid #17735b; outline: none; }
    
    .info-box { background: #f0f4f3; border-left: 4px solid #17735b; padding: 15px; margin: 20px 0; border-radius: 4px; }
    .info-box strong { color: #17735b; }
    
    .btn-container { display: flex; gap: 10px; margin-top: 30px; }
    .btn { flex: 1; padding: 12px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; font-size: 1rem; }
    .btn-primary { background: #17735b; color: #fff; }
    .btn-primary:hover { background: #0f5644; }
    .btn-secondary { background: #e0e0e0; color: #333; }
    .btn-secondary:hover { background: #d0d0d0; }
    
    .error-message { color: #d9534f; background: #f2dede; border: 1px solid #ebccd1; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
    .success-message { color: #3c763d; background: #dff0d8; border: 1px solid #d6e9c6; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
    
    .order-summary { background: #fff; border: 1px solid #e0e0e0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    .order-summary h4 { margin-top: 0; color: #333; }
    .summary-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
    .summary-item:last-child { border-bottom: none; }
    .summary-total { font-weight: 600; font-size: 1.1rem; color: #17735b; padding-top: 10px; }
</style>

<div class="fpx-container">
    <div class="fpx-card">
        <div class="fpx-header">
            <h2>FPX Payment</h2>
            <p>Complete your payment using FPX Online Banking</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success_msg): ?>
            <div class="success-message"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <!-- Order Summary -->
        <?php 
        $order_sql = "SELECT Order_Amount, Order_Date FROM `ORDER` WHERE Order_Id = '$order_id'";
        $order_res = $conn->query($order_sql);
        if ($order_res && $order_res->num_rows > 0) {
            $order_data = $order_res->fetch_assoc();
        ?>
        <div class="order-summary">
            <h4>Order Summary</h4>
            <div class="summary-item">
                <span>Order ID:</span>
                <strong>#<?php echo $order_id; ?></strong>
            </div>
            <div class="summary-item">
                <span>Selected Bank:</span>
                <strong><?php echo $bank; ?></strong>
            </div>
            <div class="summary-item summary-total">
                <span>Total Amount:</span>
                <span>RM <?php echo number_format($order_data['Order_Amount'], 2); ?></span>
            </div>
        </div>
        <?php } ?>
        
        <div class="info-box">
            <strong>ℹ️ Note:</strong> You will be redirected to your bank's FPX page after entering your credentials. 
            Please ensure you are on a secure connection.
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Bank</label>
                <input type="text" class="form-control" value="<?php echo $bank; ?>" disabled>
                <input type="hidden" name="fpx_bank" value="<?php echo $bank; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">FPX ID / Username</label>
                <input type="text" name="fpx_id" class="form-control" placeholder="Enter your FPX ID" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="fpx_password" class="form-control" placeholder="Enter your password" required>
            </div>
            
            <input type="hidden" name="process_fpx" value="1">
            
            <div class="btn-container">
                <button type="button" class="btn btn-secondary" onclick="window.history.back();">Cancel</button>
                <button type="submit" class="btn btn-primary">Complete Payment</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
