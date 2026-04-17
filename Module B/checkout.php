<?php
// Module B: 核心交易组 - 订单结算中心 (Checkout & Payment)
ob_start();
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/api_config.php';

// 1. 登录与购物车安全检查
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Module A/login.php");
    exit();
}
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: catalogue.php");
    exit();
}

$uid = $_SESSION['user_id'];
$error = "";
$success_msg = "";

// 2. 获取用户预设的配送信息 (来自 USER 表)
$user_sql = "SELECT User_Name, User_Phone, User_Address, User_Postcode, User_State FROM `USER` WHERE User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();

// 3. 计算购物车初始总额
$subtotal = 0;
$checkout_items = [];

foreach ($_SESSION['cart'] as $cart_key => $item) {
    $pid = $item['pro_id'];
    $sql_p = "SELECT Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id = '$pid'";
    $res_p = $conn->query($sql_p);
    if ($res_p && $res_p->num_rows > 0) {
        $p_data = $res_p->fetch_assoc();
        $p_data['qty'] = $item['qty'];
        $p_data['size'] = $item['size'];
        $p_data['color'] = isset($item['color']) ? $item['color'] : 'Default';
        $p_data['item_total'] = $p_data['Pro_Price'] * $item['qty'];
        
        $subtotal += $p_data['item_total'];
        $checkout_items[] = $p_data;
    }
}

// 4. 处理优惠码逻辑 (支持固定金额与百分比)
$discount = 0;
$applied_code = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) {
    $code = $conn->real_escape_string(trim($_POST['coupon_code']));
    // 根据截图，表名为小写 promo 
    $sql_c = "SELECT * FROM promo WHERE Promo_Code = '$code' AND Expired_Date >= CURDATE() AND Promo_Status = 'Active'";
    $res_c = $conn->query($sql_c);
    
    if ($res_c && $res_c->num_rows > 0) {
        $promo = $res_c->fetch_assoc();
        $applied_code = $code;
        $promo_value = floatval($promo['Promo_Value']);
        
        // 核心修改：检查折扣类型
        if (isset($promo['Promo_Type']) && $promo['Promo_Type'] === 'Percentage') {
            // 百分比计算
            $discount = $subtotal * ($promo_value / 100);
            $success_msg = "Coupon Applied: " . number_format($promo_value, 0) . "% OFF (-RM " . number_format($discount, 2) . ")";
        } else {
            // 固定金额计算
            $discount = $promo_value;
            $success_msg = "Coupon Applied: RM " . number_format($discount, 2) . " OFF";
        }
    } else {
        $error = "Invalid or expired coupon code.";
    }
}

$shipping = ($subtotal >= 250) ? 0 : 15.00;
$grand_total = ($subtotal + $shipping) - $discount;
if ($grand_total < 0) $grand_total = 0; // 确保总额不为负数

// 5. 最终确认下单逻辑
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $final_addr = $conn->real_escape_string($_POST['shipping_address']);
    $order_date = date('Y-m-d H:i:s');
    
    // 开启事务处理，确保数据一致性
    $conn->begin_transaction();

    try {
        // A. 写入 ORDER 总表 (注意：状态设为 'Pending' 和 'Unpaid')
        $sql_order = "INSERT INTO `ORDER` (User_Id, Order_Amount, Order_Shipping_Addr, Order_Status, Order_Date, Payment_Status) 
                      VALUES ('$uid', '$grand_total', '$final_addr', 'Pending', '$order_date', 'Unpaid')";
        $conn->query($sql_order);
        $order_id = $conn->insert_id;

        // B. 循环购物车：写入 ORDER_DETAIL 并扣减 PRODUCT_STOCK
        foreach ($_SESSION['cart'] as $item) {
            $item_pid = $item['pro_id'];
            $item_qty = $item['qty'];
            $item_size = $item['size'];
            
            // 查单价算小计
            $res_price = $conn->query("SELECT Pro_Price FROM product WHERE Pro_Id = '$item_pid'");
            $row_price = $res_price->fetch_assoc();
            $item_subtotal = $row_price['Pro_Price'] * $item_qty;

            // 写入明细
            $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal) 
                          VALUES ('$order_id', '$item_pid', '$item_qty', '$item_subtotal')");

            // 扣减对应尺码的真实库存
            $conn->query("UPDATE PRODUCT_STOCK SET Quantity = Quantity - $item_qty 
                          WHERE Pro_Id = '$item_pid' AND Pro_Size = '$item_size'");
        }

        // C. 提交事务（订单先入库）
        $conn->commit();

        // D. 调用 Stripe 生成支付会话 (Checkout Session)
        require_once '../includes/stripe/vendor/autoload.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card', 'fpx'], // 开启信用卡和马来西亚本地银行转账 (FPX)
            'line_items' => [[
              'price_data' => [
                'currency' => 'myr', // 设置币种为令吉
                'product_data' => [
                  'name' => 'Order ID: #' . $order_id, // 支付页面显示的订单号
                ],
                'unit_amount' => intval($grand_total * 100), // 重要：Stripe 的单位是"分"，RM 50.00 要传 5000
              ],
              'quantity' => 1,
            ]],
            'mode' => 'payment',
            // 把空格替换为 %20
            'success_url' => 'http://localhost/Online-Sport-Shoes-Store/Module%20B/payment_success.php?order_id=' . $order_id . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'http://localhost/Online-Sport-Shoes-Store/Module%20B/checkout.php',
        ]);

        // E. 核心步骤：重定向用户到 Stripe 的托管页面
        unset($_SESSION['cart']); // 清空购物车
        header("Location: " . $session->url);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Payment Error: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; }
    .checkout-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .checkout-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; align-items: start; }
    .step-card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
    .step-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #333; }
    .step-number { background: #333; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    
    .checkout-item { display: flex; gap: 15px; padding: 15px 0; border-bottom: 1px solid #eee; }
    .checkout-item:last-child { border-bottom: none; }
    .checkout-item img { width: 70px; height: 70px; object-fit: contain; background: #f9f9f9; border-radius: 8px; }
    
    .payment-btn { border: 2px solid #eee; border-radius: 8px; padding: 15px; width: 100%; display: flex; align-items: center; gap: 15px; cursor: pointer; transition: 0.3s; background: #fff; margin-bottom: 10px; }
    .payment-btn:hover { border-color: #008060; }
    .payment-btn.active { border-color: #008060; background: #f0f7f4; }
    .payment-btn i { font-size: 24px; color: #008060; }

    .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; color: #666; }
    .summary-total { display: flex; justify-content: space-between; border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px; font-weight: 800; font-size: 1.2rem; color: #333; }
    
    .btn-place-order { width: 100%; background: #008060; color: #fff; border: none; padding: 18px; border-radius: 8px; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 20px; }
    .btn-place-order:hover { background: #00664c; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,128,96,0.3); }

    @media (max-width: 992px) { .checkout-grid { grid-template-columns: 1fr; } }
</style>

<div class="checkout-container">
    <h2 class="fw-bold mb-4">Secure Checkout</h2>

    <div class="checkout-grid">
        <div class="checkout-main">
            <form id="mainOrderForm" method="POST" action="">
                
                <div class="step-card">
                    <div class="step-title">
                        <span class="step-number">1</span> Delivery Address
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Shipping to:</label>
                        <textarea name="shipping_address" class="form-control" rows="3" required><?php 
                            echo htmlspecialchars($user_info['User_Address'] . ", " . $user_info['User_Postcode'] . ", " . $user_info['User_State']); 
                        ?></textarea>
                        <small class="text-muted mt-2 d-block">Default address from your profile. Feel free to edit for this order.</small>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-title">
                        <span class="step-number">2</span> Payment Method
                    </div>
                    <div class="payment-methods">
                        <div class="payment-btn active" onclick="selectPayment(this)">
                            <i class="bi bi-credit-card-2-front"></i>
                            <div>
                                <div class="fw-bold">Credit / Debit Card</div>
                                <div class="small text-muted">Visa, Mastercard, AMEX</div>
                            </div>
                        </div>
                        <div class="payment-btn" onclick="selectPayment(this)">
                            <i class="bi bi-bank"></i>
                            <div>
                                <div class="fw-bold">FPX Online Banking</div>
                                <div class="small text-muted">Maybank, CIMB, PBB and more</div>
                            </div>
                        </div>
                        <div class="payment-btn" onclick="selectPayment(this)">
                            <i class="bi bi-wallet2"></i>
                            <div>
                                <div class="fw-bold">E-Wallet</div>
                                <div class="small text-muted">Touch 'n Go, GrabPay, ShopeePay</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-title">
                        <span class="step-number">3</span> Review Items
                    </div>
                    <div class="checkout-items-list">
                        <?php foreach($checkout_items as $item): ?>
                            <div class="checkout-item">
                                <img src="../uploads/<?php echo $item['Pro_Image']; ?>" onerror="this.src='../images/placeholder.png'">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo $item['Pro_Name']; ?></div>
                                    <div class="small text-muted">Size: <?php echo $item['size']; ?> | Col: <?php echo $item['color']; ?></div>
                                    <div class="small">Qty: <?php echo $item['qty']; ?></div>
                                </div>
                                <div class="fw-bold">RM <?php echo number_format($item['item_total'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <input type="hidden" name="place_order" value="1">
            </form>
        </div>

        <div class="checkout-sidebar">
            <div class="step-card">
                <h4 class="fw-bold mb-4">Order Summary</h4>
                
                <form method="POST" action="" class="mb-4">
                    <label class="form-label small fw-bold">Promo Code</label>
                    <div class="input-group">
                        <input type="text" name="coupon_code" class="form-control" placeholder="Enter code" value="<?php echo $applied_code; ?>">
                        <button type="submit" name="apply_coupon" class="btn btn-dark">Apply</button>
                    </div>
                    <?php if($error): ?> <div class="text-danger small mt-1"><?php echo $error; ?></div> <?php endif; ?>
                    <?php if($success_msg): ?> <div class="text-success small mt-1"><?php echo $success_msg; ?></div> <?php endif; ?>
                </form>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>RM <?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span class="<?php echo $shipping == 0 ? 'text-success fw-bold' : ''; ?>">
                        <?php echo $shipping == 0 ? 'FREE' : 'RM ' . number_format($shipping, 2); ?>
                    </span>
                </div>
                <?php if($discount > 0): ?>
                <div class="summary-row text-danger">
                    <span>Discount (<?php echo $applied_code; ?>)</span>
                    <span>- RM <?php echo number_format($discount, 2); ?></span>
                </div>
                <?php endif; ?>

                <div class="summary-total">
                    <span>Total</span>
                    <span>RM <?php echo number_format($grand_total, 2); ?></span>
                </div>

                <button type="button" class="btn-place-order" onclick="document.getElementById('mainOrderForm').submit();">
                    CONFIRM & PAY NOW
                </button>

                <div class="text-center mt-3 text-muted small">
                    <i class="bi bi-shield-lock-fill"></i> SSL Secure Payment
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectPayment(el) {
    document.querySelectorAll('.payment-btn').forEach(btn => btn.classList.remove('active'));
    el.classList.add('active');
}
</script>

<?php include '../includes/footer.php'; ?>