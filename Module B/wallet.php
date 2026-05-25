<?php
// module_a/wallet.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = ""; 

// ==========================================
// 1. 处理网关中转重定向逻辑
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proceed_gateway'])) {
    $amount = floatval($_POST['topup_amount']);
    $method = $_POST['payment_method'] ?? 'Credit/Debit Card';

    if ($amount <= 0 || $amount > 10000) {
        $msg = "Please enter a valid amount between RM 0.01 and RM 10,000.";
        $msg_type = "danger";
    } else {
        // 将充值金额和支付方式安全暂存于 Session 中，供外部拟真网关使用
        $_SESSION['topup_temp_data'] = [
            'amount' => $amount,
            'method' => $method
        ];

        // 根据所选支付手段，平滑路由到对应的外部拟真页面
        if ($method === 'Credit/Debit Card') {
            header("Location: wallet_card_auth.php");
        } else {
            header("Location: wallet_bank_portal.php");
        }
        exit();
    }
}

// 接收拟真网关成功支付完成后的回调参数并弹出状态提示
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $amt = floatval($_GET['amt'] ?? 0);
    $msg = "Secure payment complete! RM " . number_format($amt, 2) . " added to your wallet.";
    $msg_type = "success";
}

// 2. 提取最新账户余额与数据
$user_res = $conn->query("SELECT User_Balance, User_Name, User_Email FROM `USER` WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();

// 3. 提取当前用户的 WALLET_TRANSACTION 历史充值与消费记录
$trans_sql = "SELECT * FROM WALLET_TRANSACTION WHERE User_Id = '$user_id' ORDER BY Date DESC, WT_Id DESC";
$trans_res = $conn->query($trans_sql);

$page_title = "Secure Wallet Top-up";
include '../includes/header.php';
?>

<style>
    .wallet-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: none; }
    .balance-display { background: #f8f9fa; border-radius: 12px; padding: 20px; border: 1px solid #eee; }
    .method-card { border: 2px solid #eee; border-radius: 12px; padding: 15px; margin-bottom: 15px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 15px; }
    .method-card:hover { border-color: #FF6B00; background: #fff9f5; }
    .method-card.active { border-color: #FF6B00; background: #fff9f5; box-shadow: 0 4px 12px rgba(255,107,0,0.1); }
    .method-icon { font-size: 24px; color: #FF6B00; }
    
    .amt-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
    .btn-amt { border: 1px solid #ddd; background: #fff; padding: 10px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; color: #333; }
    .btn-amt:hover { border-color: #FF6B00; color: #FF6B00; }
    .btn-amt.active { background: #FF6B00; color: #fff; border-color: #FF6B00; }
    
    .table-responsive::-webkit-scrollbar { height: 5px; }
    .table-responsive::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
</style>

<div class="container mt-5 mb-5">
    <?php if($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> rounded-3 mb-4 shadow-sm fw-bold">
            <?php echo ($msg_type === 'success') ? '✓' : '❌'; ?> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card wallet-card p-4 h-100">
                <div class="balance-display text-center mb-4">
                    <small class="text-muted text-uppercase fw-bold">Available Balance</small>
                    <h2 class="fw-bold mt-1" style="color: #FF6B00;">RM <?php echo number_format($user['User_Balance'], 2); ?></h2>
                </div>
                
                <form method="POST" action="wallet.php" id="gatewaySetupForm">
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
                        <input type="number" name="topup_amount" id="main_amount" class="form-control border-start-0" placeholder="0.00" step="0.01" required readonly>
                    </div>

                    <label class="fw-bold mb-3">2. Select Payment Method</label>
                    <input type="hidden" name="payment_method" id="final_method" value="Credit/Debit Card">
                    
                    <div class="method-card active" onclick="setMethod('Credit/Debit Card', this)">
                        <i class="bi bi-credit-card-2-front method-icon"></i>
                        <div><h6 class="mb-0">Credit / Debit Card</h6><small class="text-muted">Visa, Mastercard, AMEX</small></div>
                    </div>
                    <div class="method-card" onclick="setMethod('FPX Online Banking', this)">
                        <i class="bi bi-bank method-icon"></i>
                        <div><h6 class="mb-0">FPX Online Banking</h6><small class="text-muted">Maybank, CIMB, PBB...</small></div>
                    </div>
                    
                    <button type="submit" name="proceed_gateway" class="btn btn-warning w-100 py-3 rounded-3 fw-bold text-dark mt-3">
                        <i class="bi bi-shield-lock-fill"></i> PROCEED TO GATEWAY
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card wallet-card p-4 h-100">
                <h5 class="fw-bold mb-4">
                    <i class="bi bi-clock-history me-2" style="color: #FF6B00;"></i>Transaction History
                </h5>
                
                <div class="table-responsive">
                    <table class="table align-middle table-hover small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%;">Amount</th>
                                <th style="width: 15%;">Type</th>
                                <th style="width: 35%;">Description</th>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 10%;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($trans_res && $trans_res->num_rows > 0): ?>
                                <?php while($t = $trans_res->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if (floatval($t['Amount']) >= 0): ?>
                                                <span class="text-success fw-bold">+RM <?php echo number_format(abs($t['Amount']), 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-danger fw-bold">-RM <?php echo number_format(abs($t['Amount']), 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo ($t['Type'] === 'Top-up') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-2 py-1">
                                                <?php echo $t['Type']; ?>
                                            </span>
                                        </td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($t['Description']); ?></td>
                                        <td class="text-muted small"><?php echo date('d M Y', strtotime($t['Date'])); ?></td>
                                        <td class="text-muted small"><?php echo date('H:i A', strtotime($t['Date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">No transaction activities found.</td>
                                endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function setQuickAmount(amt, el) {
        document.querySelectorAll('.btn-amt').forEach(b => b.classList.remove('active'));
        el.classList.add('active');

        const input = document.getElementById('main_amount');
        if (amt === 'other') {
            input.value = ''; 
            input.readOnly = false; 
            input.focus(); 
            input.placeholder = "Enter amount manually";
        } else {
            input.value = amt; 
            input.readOnly = true; 
            input.placeholder = "0.00";
        }
    }

    function setMethod(method, el) {
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('final_method').value = method;
    }

    // 表单提交阻断校验
    document.getElementById('gatewaySetupForm').addEventListener('submit', function(e) {
        const amt = document.getElementById('main_amount').value;
        if (!amt || parseFloat(amt) <= 0) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Amount Required', text: 'Please choose or enter a valid amount before proceeding.', confirmButtonColor: '#FF6B00' });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>