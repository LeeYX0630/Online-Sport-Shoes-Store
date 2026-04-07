<?php
// module_b/checkout_payment.php
session_start();
require_once '../includes/db_connections.php';

$pro_id = isset($_GET['pro_id']) ? intval($_GET['pro_id']) : 0;
$qty = isset($_GET['qty']) ? intval($_GET['qty']) : 1;
$addr = isset($_GET['addr']) ? $_GET['addr'] : '';

if ($pro_id == 0 || !isset($_SESSION['user_id'])) {
    header("Location: catalogue.php");
    exit();
}

$uid = $_SESSION['user_id'];

// 获取产品详情 
$sql_p = "SELECT * FROM PRODUCT WHERE Pro_Id = '$pro_id'";
$res_p = $conn->query($sql_p);
$product = $res_p->fetch_assoc();

$subtotal = $product['Pro_Price'] * $qty;
$discount = 0;
$applied_code = "";

// 模拟优惠码处理 (匹配 PROMO 表) 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) {
    $code = $_POST['coupon_code'];
    $sql_c = "SELECT * FROM PROMO WHERE Promo_Code = '$code' AND Expired_Date >= CURDATE()";
    $res_c = $conn->query($sql_c);
    if ($res_c->num_rows > 0) {
        $discount = 10.00; // 模拟固定扣减 RM10
        $applied_code = $code;
    }
}

// 确认支付逻辑
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {
    $final_amount = $subtotal - $discount;
    $order_date = date('Y-m-d');
    
    // 写入 ORDER 表 
    $sql_order = "INSERT INTO `ORDER` (User_Id, Order_Amount, Order_Shipping_Addr, Order_Status, Order_Date, Payment_Status) 
                  VALUES ('$uid', '$final_amount', '$addr', 'Pending', '$order_date', 'Paid')";
    
    if ($conn->query($sql_order)) {
        $order_id = $conn->insert_id;
        // 写入 ORDER_DETAIL 
        $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal) VALUES ('$order_id', '$pro_id', '$qty', '$final_amount')");
        // 更新库存
        $conn->query("UPDATE PRODUCT SET Pro_Stock_Quantity = Pro_Stock_Quantity - $qty WHERE Pro_Id = '$pro_id'");
        
        header("Location: payment_success.php?order_id=$order_id");
        exit();
    }
}
?>