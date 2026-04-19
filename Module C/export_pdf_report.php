<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) { exit; }

$admin_role = $_SESSION['role'];
$admin_brand = $_SESSION['admin_brand'] ?? 'ALL';

// --- 获取数据 ---
$query = "SELECT DISTINCT o.Order_Id, o.Order_Date, o.Order_Amount, o.Order_Status, u.User_Name 
          FROM `order` o 
          LEFT JOIN user u ON o.User_Id = u.User_Id";

if ($admin_role != 1) {
    $query .= " LEFT JOIN order_details od ON o.Order_Id = od.Order_Id 
                LEFT JOIN product p ON od.Product_Id = p.Product_Id 
                WHERE p.Product_Brand = '$admin_brand'";
}
$query .= " ORDER BY o.Order_Id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Report - Online Sport Shoes</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; color: #333; }
        
        /* 报表头部样式 */
        .report-header {
            text-align: center;
            border-bottom: 3px solid #FA8A34;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .report-header img {
            width: 80px;
            margin-bottom: 10px;
        }
        .report-header h1 {
            margin: 0;
            color: #FA8A34;
            font-size: 28px;
            letter-spacing: 1px;
        }
        .report-header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }

        /* 表格样式 */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #FA8A34;
            color: white;
            padding: 12px;
            text-align: left;
            text-transform: uppercase;
            font-size: 13px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        tr:nth-child(even) { background-color: #fafafa; }

        .amount { font-weight: bold; color: #000; }
        
        /* 打印设置：隐藏浏览器默认的页眉页脚 */
        @media print {
            @page { margin: 1cm; }
            body { padding: 0; }
            .no-print { display: none; }
        }

        /* 打印按钮 */
        .print-controls {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #FA8A34;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="no-print print-controls">
        <p>Report preview has been generated. Click the button below to save as PDF.</p>
        <button class="btn-print" onclick="window.print()">Confirm & Save PDF</button>
    </div>

    <div class="report-header">
        <img src="../uploads/logo1.png" alt="Logo">
        <h1>ONLINE SPORT SHOES STORE</h1>
        <p>Official Sales Order Report</p>
        <p style="font-size: 12px;">Generated on: <?php echo date('d M Y, h:i A'); ?> | Brand: <?php echo $admin_brand; ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Customer Name</th>
                <th>Status</th>
                <th>Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $row['Order_Id']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['Order_Date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['User_Name'] ?? 'Guest'); ?></td>
                    <td><?php echo $row['Order_Status']; ?></td>
                    <td class="amount">RM <?php echo number_format($row['Order_Amount'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No data found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; text-align: center; font-size: 11px; color: #999;">
        <p>This is a computer-generated report. No signature required.</p>
        <p>&copy; <?php echo date('Y'); ?> Online Sport Shoes Store</p>
    </div>

    <script>
        // 页面加载完成后 1 秒自动弹出打印，提升体验
        window.onload = function() {
            setTimeout(function() {
                // window.print(); 
            }, 1000);
        };
    </script>
</body>
</html>