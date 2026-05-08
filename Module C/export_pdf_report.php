<?php
// export_pdf_report.php
session_start();
require_once '../includes/db_connection.php';

// 1. Safety Check
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access. Please login first.");
}

$admin_id = $_SESSION['admin_id'];
$admin_level = $_SESSION['admin_level'] ?? $_SESSION['role']; 
$username = $_SESSION['username'] ?? 'Admin';

// 2. Date & Weekly Logic
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_date = new DateTime($selected_date);
$day_of_week = $current_date->format('N'); 

$monday = clone $current_date;
$monday->modify("-" . ($day_of_week - 1) . " days");
$start_of_week = $monday->format('Y-m-d');

$sunday = clone $monday;
$sunday->modify("+6 days");
$end_of_week = $sunday->format('Y-m-d');

// 3. Permission Logic
$display_brand = "All Brands";
$brand_filter = "";

if ($admin_level == '3') {
    $brand_query = "SELECT Brand_Id, Brand_Name FROM brand WHERE Admin_Id = '$admin_id' LIMIT 1";
    $brand_res = $conn->query($brand_query);
    
    if ($brand_res && $brand_row = $brand_res->fetch_assoc()) {
        $my_brand_id = $brand_row['Brand_Id'];
        $display_brand = $brand_row['Brand_Name'];
        $brand_filter = " AND p.Brand_Id = '$my_brand_id' ";
    } else {
        $brand_filter = " AND 1=0 "; 
        $display_brand = "No Brand Assigned";
    }
}

// 4. SQL Query
$sql = "SELECT 
            o.Order_Id, 
            o.Order_Date, 
            u.User_Name, 
            o.Order_Status, 
            SUM(od.Order_Subtotal) as Display_Amount 
        FROM `order` o
        LEFT JOIN user u ON o.User_Id = u.User_Id
        JOIN order_detail od ON o.Order_Id = od.Order_Id
        JOIN product p ON od.Pro_Id = p.Pro_Id
        WHERE o.Order_Date BETWEEN '$start_of_week 00:00:00' AND '$end_of_week 23:59:59'
        $brand_filter
        GROUP BY o.Order_Id
        ORDER BY o.Order_Date DESC";

$result = $conn->query($sql);
$total_orders_count = ($result) ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Weekly Report - <?php echo $display_brand; ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.6; background: #fff; }
        
        .no-print { 
            display: flex; 
            flex-direction: column; 
            align-items: flex-end; 
            gap: 15px;
            margin-bottom: 40px;
        }

        .action-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .back-button-container { display: flex; justify-content: flex-end; margin-bottom: 15px; padding: 0 10px; }
        .btn-back-header { text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; transition: all 0.2s; border: 1px solid #e2e8f0; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .btn-back-header:hover { background-color: #fff; color: #FA8A34; border-color: #FA8A34; transform: translateX(-3px); }

        .calendar-trigger {position: relative; display: auto; align-items: center; gap: 10px; padding: 12px 18px; 
        background: #fff; border: 2px solid #eee; border-radius: 10px; cursor: pointer; font-weight: 600;font-size: 14px; line-height: 1.2; 
        min-height: 48px; box-sizing: border-box; 
        }
        
        .flatpickr-calendar.static {
            top: 100% !important; 
            right: 0 !important;   
            margin-top: 28px;      
        }

        .calendar-trigger:hover { border-color: #FA8A34; color: #FA8A34; }

        .btn-print { 
            display: auto; align-items: center; gap: 8px; padding: 12px 22px; background: #FA8A34; 
            color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px;
            line-height: 1.2; min-height: 48px; box-shadow: 0 4px 10px rgba(250, 138, 52, 0.2);transition: all 0.3s;box-sizing: border-box;
        }

        .btn-print:hover { background: #e67e22; transform: translateY(-2px); }

        /* 报表样式 */
        .report-header { text-align: center; border-bottom: 3px solid #FA8A34; padding-bottom: 20px; margin-bottom: 30px; }
        .report-header img { height: 75px; margin-bottom: 15px; }
        .report-header h1 { margin: 0; color: #FA8A34; font-size: 28px; text-transform: uppercase; letter-spacing: 1px; }
        
        .info-section { display: flex; justify-content: space-between; margin-bottom: 25px; font-size: 14px; color: #555; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #FA8A34; color: white; padding: 15px 12px; text-align: left; }
        td { padding: 15px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .amount { font-weight: bold; text-align: right; }
        
        .grand-total { background: #fff8f2; font-weight: bold; }
        .total-count-box { color: #FA8A34; font-weight: 800; border-right: 2px solid #ffe4cc; }

        /* PDF 导出时的背景处理 */
        #report-content { background: #fff; padding: 10px; }

        @media print { .no-print-area { display: none; } }
    </style>
</head>
<body>

    <div class="no-print-area">
        <div class="back-button-container">
            <a href="admin_manage_products.php" class="btn-back-header"><i class="bi bi-arrow-left"></i> Back to Products</a>
        </div>

        <div class="action-row">
            <div class="calendar-trigger" id="datePickerBtn">
                <i class="bi bi-calendar-event"></i>
                <span>Week: <?php echo date('d M', strtotime($start_of_week)) . " - " . date('d M Y', strtotime($end_of_week)); ?></span>
            </div>

            <input type="text" id="weekPicker" style="display:none;">

            <button onclick="downloadPDF()" class="btn-print" id="dlBtn">
                <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF Report
            </button>
        </div>
    </div>

    <div id="report-content">
        <div class="report-header">
            <img src="../images/picture/Logo 2.png" alt="Store Logo" onerror="this.style.display='none'">
            <h1>Sales Weekly Report</h1>
            <p>Online Sport Shoes Store</p>
        </div>

        <div class="info-section">
            <div>
                <strong>Generated By:</strong> <?php echo htmlspecialchars($username); ?><br>
                <strong>Report Scope:</strong> <?php echo $display_brand; ?><br>
                <strong>Period:</strong> <?php echo date('d/m/Y', strtotime($start_of_week)); ?> to <?php echo date('d/m/Y', strtotime($end_of_week)); ?>
            </div>
            <div style="text-align: right;">
                <strong>Print Date:</strong> <?php echo date('Y-m-d H:i'); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Customer Name</th>
                    <th>Status</th>
                    <th style="text-align: right;">Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_sum = 0;
                if ($result && $result->num_rows > 0): 
                    while ($row = $result->fetch_assoc()): 
                        $total_sum += $row['Display_Amount'];
                        $formatted_id = "#ORD-" . str_pad($row['Order_Id'], 5, "0", STR_PAD_LEFT);
                ?>
                    <tr>
                        <td><strong><?php echo $formatted_id; ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($row['Order_Date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['User_Name'] ?? 'Guest'); ?></td>
                        <td><?php echo $row['Order_Status']; ?></td>
                        <td class="amount"><?php echo number_format($row['Display_Amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr class="grand-total">
                        <td class="total-count-box">Total Order: <?php echo $total_orders_count; ?></td>
                        <td colspan="3" style="text-align: right; text-transform: uppercase;">Weekly Grand Total:</td>
                        <td class="amount" style="color: #FA8A34; font-size: 18px;">RM <?php echo number_format($total_sum, 2); ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 60px; color: #999;">No sales records found for this week.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        // 日历初始化
        document.getElementById('datePickerBtn').addEventListener('click', () => fp.open());
        const fp = flatpickr("#weekPicker", {
            defaultDate: "<?php echo $selected_date; ?>",
            maxDate: "today",
            appendTo: document.getElementById('datePickerBtn'),
            static: true, 
            position: "below", 
            onChange: function(selectedDates, dateStr) {
                window.location.href = "export_pdf_report.php?date=" + dateStr;
            }
        });

        // 核心下载逻辑 (完全复刻自 generate_receipt.php)
        function downloadPDF() {
            const element = document.getElementById('report-content');
            const btn = document.getElementById('dlBtn');
            
            // 按钮状态反馈
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';

            const opt = {
                margin: 10,
                filename: 'Weekly_Report_<?php echo $start_of_week; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf()
                .set(opt)
                .from(element)
                .save()
                .then(() => {
                    // 恢复按钮
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }
    </script>
</body>
</html>