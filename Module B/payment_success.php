<?php
// module_b/payment_success.php
session_start();
require_once '../includes/db_connection.php';

$order_id = intval($_GET['order_id']);
$uid = $_SESSION['user_id'];

// 联合查询生成收据 
$sql = "SELECT o.*, p.Pro_Name, u.User_Name 
        FROM `ORDER` o 
        JOIN ORDER_DETAIL od ON o.Order_Id = od.Order_Id
        JOIN PRODUCT p ON od.Pro_Id = p.Pro_Id
        JOIN USER u ON o.User_Id = u.User_Id
        WHERE o.Order_Id = '$order_id' AND o.User_Id = '$uid'";

$data = $conn->query($sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .receipt-card { max-width: 600px; margin: 50px auto; border: 2px solid #FF6B00; border-radius: 15px; padding: 30px; }
        .brand { color: #FF6B00; font-weight: bold; }
    </style>
</head>
<body>
    <div class="receipt-card bg-white shadow">
        <div class="text-center mb-4">
            <h2 class="brand">SPORT SHOES STORE</h2>
            <p class="text-muted">Official Purchase Receipt</p>
        </div>
        <hr>
        <div class="row mb-3">
            <div class="col-6"><strong>Order ID:</strong> #<?php echo $order_id; ?></div>
            <div class="col-6 text-end"><strong>Date:</strong> <?php echo $data['Order_Date']; ?></div>
        </div>
        <p><strong>Customer:</strong> <?php echo $data['User_Name']; ?></p>
        <p><strong>Product:</strong> <?php echo $data['Pro_Name']; ?></p>
        <p><strong>Shipping To:</strong><br><?php echo nl2br($data['Order_Shipping_Addr']); ?></p>
        <div class="bg-light p-3 rounded d-flex justify-content-between">
            <span class="h5 mb-0">Total Amount Paid</span>
            <span class="h5 mb-0 text-success">RM <?php echo number_format($data['Order_Amount'], 2); ?></span>
        </div>
        <div class="text-center mt-4">
            <a href="../index.php" class="btn btn-warning">Back to Home</a>
        </div>
    </div>
</body>
</html>