<?php
// Module C/payment_success.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['booking_id'])) {
    header("Location: ../index.php");
    exit();
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

$sql = "SELECT b.*, r.room_name, u.full_name, u.email, u.phone 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.room_id 
        JOIN users u ON b.user_id = u.user_id 
        WHERE b.booking_id = '$booking_id' AND b.user_id = '$user_id'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Booking not found or access denied.");
}

$data = $result->fetch_assoc();

$d1 = new DateTime($data['check_in_date']);
$d2 = new DateTime($data['check_out_date']);
$days = $d1->diff($d2)->days;
if($days == 0) $days = 1;

$qr_content = "BOOKING RECEIPT\n" .
              "ID: #" . $booking_id . "\n" .
              "Name: " . $data['full_name'] . "\n" .
              "Room: " . $data['room_name'] . "\n" .
              "Check-In: " . $data['check_in_date'] . "\n" .
              "Paid: RM " . $data['total_price'];
              
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_content);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt #<?php echo $booking_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; padding-bottom: 50px; }
        
        .receipt-container {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            position: relative;
            background-image: linear-gradient(135deg, #ffffff 25%, transparent 25%), linear-gradient(225deg, #ffffff 25%, transparent 25%);
            background-position: top center;
        }

        .brand-header { border-bottom: 2px dashed #eee; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .brand-header h2 { color: #d35400; font-weight: bold; margin: 0; }
        .receipt-title { font-size: 24px; font-weight: bold; color: #333; text-align: center; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 2px; }

        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 15px; }
        .info-label { color: #777; font-weight: 600; }
        .info-val { color: #333; font-weight: bold; }

        .total-box { 
            background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 20px; 
            display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; 
        }
        .total-label { font-size: 18px; font-weight: bold; color: #333; }
        .total-amount { font-size: 24px; font-weight: bold; color: #28a745; }

        .qr-section { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px dashed #eee; }
        .qr-img { border: 5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 10px; }
        .qr-desc { font-size: 12px; color: #999; margin-top: 10px; }

        .action-bar { max-width: 700px; margin: 0 auto; display: flex; gap: 15px; justify-content: center; }
    </style>
</head>
<body>

    <div class="text-center mt-4 mb-2">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
        <h2 class="fw-bold mt-2">Payment Successful!</h2>
        <p class="text-muted">Your booking has been confirmed. Please save this receipt.</p>
    </div>

    <div class="receipt-container" id="receiptContent">
        <div class="brand-header">
            <div>
                <h2>Teh Tarik No Tarik</h2>
                <small class="text-muted">Homestay Reservation System</small>
            </div>
            <div class="text-end">
                <div class="badge bg-success fs-6">PAID</div>
                <div class="small text-muted mt-1"><?php echo date("d M Y"); ?></div>
            </div>
        </div>

        <div class="receipt-title">Official Receipt</div>

        <div class="row">
            <div class="col-md-6">
                <div class="info-row">
                    <span class="info-label">Booking ID</span>
                    <span class="info-val">#<?php echo $booking_id; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Guest Name</span>
                    <span class="info-val"><?php echo $data['full_name']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-val"><?php echo $data['email']; ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-row">
                    <span class="info-label">Room Type</span>
                    <span class="info-val"><?php echo $data['room_name']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Check-In</span>
                    <span class="info-val"><?php echo $data['check_in_date']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Check-Out</span>
                    <span class="info-val"><?php echo $data['check_out_date']; ?></span>
                </div>
            </div>
        </div>

        <div class="total-box">
            <span class="total-label">TOTAL AMOUNT PAID</span>
            <span class="total-amount">RM <?php echo number_format($data['total_price'], 2); ?></span>
        </div>

        <div class="qr-section">
            <img src="<?php echo $qr_url; ?>" alt="QR Code" class="qr-img">
            <p class="qr-desc">Scan to verify booking details</p>
        </div>
        
        <div class="text-center mt-4 text-muted small">
            Thank you for choosing us! We hope you have a pleasant stay.<br>
            <i>Note: Please present this receipt upon check-in.</i>
        </div>
    </div>

    <div class="action-bar no-print">
        <a href="../Module A/user_dashboard.php" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-house-door-fill me-2"></i>Back to Dashboard
        </a>
        <button onclick="downloadPDF()" class="btn btn-primary btn-lg shadow">
            <i class="bi bi-download me-2"></i>Download Receipt
        </button>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('receiptContent');
            const btn = document.querySelector('.action-bar');
            
            const opt = {
                margin:       10,
                filename:     'Receipt_<?php echo $booking_id; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save();
        }
    </script>

</body>
</html>