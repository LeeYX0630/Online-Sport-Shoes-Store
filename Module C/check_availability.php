<?php
// module_c/check_availability.php
session_start();
require_once '../includes/db_connection.php';

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if ($room_id == 0 && $category_id > 0) {
    $cat_sql = "SELECT room_id FROM categories WHERE category_id = '$category_id'";
    $cat_res = $conn->query($cat_sql);
    if ($cat_res->num_rows > 0) {
        $cat_row = $cat_res->fetch_assoc();
        $room_id = $cat_row['room_id'];
    }
}

if (isset($_SESSION['admin_id'])) {
    echo "<script>alert('Admins cannot book rooms.'); window.location.href='../Module C/admin_dashboard.php';</script>";
    exit();
}

if (!isset($_SESSION['user_id'])) {
    $params = ($category_id > 0) ? "?category_id=$category_id" : "?room_id=$room_id";
    $return_url = urlencode("../Module C/check_availability.php" . $params);
    echo "<script>alert('Please login first!'); window.location.href='../Module A/login.php?redirect=$return_url';</script>";
    exit();
}

$msg = "";
$room_details = null;
$max_guests = 4;
$display_price = 0;
$display_name = "";

if ($room_id) {
    $sql = "SELECT * FROM rooms WHERE room_id = $room_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $room_details = $result->fetch_assoc();
        $display_name = $room_details['room_name'];
        $display_price = $room_details['price_per_night']; 
        $max_guests = isset($room_details['capacity']) ? $room_details['capacity'] : 4;

        if ($category_id > 0) {
            $c_sql = "SELECT * FROM categories WHERE category_id = '$category_id'";
            $c_res = $conn->query($c_sql);
            if ($c_res->num_rows > 0) {
                $cat_data = $c_res->fetch_assoc();
                $display_name = $room_details['room_name'] . " (" . $cat_data['category_name'] . ")";
                $display_price = $cat_data['price_per_night']; 
                $max_guests = $cat_data['max_pax'];
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $room_id = intval($_POST['room_id']); 
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $guests = $_POST['guests'];
    
    $d1 = new DateTime($check_in);
    $d2 = new DateTime($check_out);
    $interval = $d1->diff($d2);
    $days = $interval->days;
    
    $one_year_later = new DateTime();
    $one_year_later->modify('+1 year');

    if (empty($check_in) || empty($check_out)) {
         $msg = "<div class='alert error'>Please select dates from the calendar.</div>";
    } elseif ($days > 29) {
         $msg = "<div class='alert error'>Maximum booking duration is 29 nights.</div>";
    } elseif ($d2 > $one_year_later) {
         $msg = "<div class='alert error'>Bookings can only be made up to 1 year in advance.</div>";
    } else {
        $check_sql = "SELECT * FROM bookings 
                      WHERE room_id = '$room_id' 
                      AND booking_status != 'cancelled' 
                      AND ((check_in_date < '$check_out' AND check_out_date > '$check_in'))";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $msg = "<div class='alert error'>Date unavailable! Please check the calendar.</div>";
        } else {
            $user_conflict = false;
            $conflict_msg = "";
            if (isset($_SESSION['user_id'])) {
                $uid = $_SESSION['user_id'];
                $u_sql = "SELECT r.room_name, b.check_in_date, b.check_out_date FROM bookings b JOIN rooms r ON b.room_id = r.room_id WHERE b.user_id = '$uid' AND b.booking_status != 'cancelled' AND (b.check_in_date < '$check_out' AND b.check_out_date > '$check_in')";
                $u_result = $conn->query($u_sql);
                if ($u_result->num_rows > 0) {
                    $user_conflict = true;
                    $eb = $u_result->fetch_assoc();
                    $conflict_msg = "You already have a booking for " . $eb['room_name'] . ".";
                }
            }

            $next_url = "checkout_payment.php?room_id=$room_id&category_id=$category_id&check_in=$check_in&check_out=$check_out&guests=$guests";

            if ($user_conflict) {
                echo "<script>if(confirm('⚠️ $conflict_msg Continue?')){window.location.href = '$next_url';}</script>";
            } else {
                header("Location: $next_url");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Dates</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fa; }
        .container-flex { display: flex; flex-wrap: wrap; max-width: 1000px; margin: 40px auto; gap: 20px; padding: 0 20px; }
        .calendar-section { flex: 2; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); min-width: 300px; }
        .form-section { flex: 1; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); height: fit-content; min-width: 250px; }
        .legend { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; font-size: 14px; }
        .dot { width: 12px; height: 12px; display: inline-block; border-radius: 2px; margin-right: 5px; }
        .dot-gray { background: #808080; } .dot-blue { background: #3788d8; } .dot-green { background: #28a745; }
        .btn-book { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 15px; transition: 0.3s; }
        .btn-book:hover:not(:disabled) { background: #218838; }
        .btn-book:disabled { background: #ccc; cursor: not-allowed; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { background-color: #f8d7da; color: #721c24; }
        input[readonly] { background-color: #e9ecef; cursor: not-allowed; border: 1px solid #ced4da; }
        input[type=number] { border: 1px solid #ced4da; }

        .progressbar { counter-reset: step; padding: 0; display: flex; justify-content: space-between; list-style: none; position: relative; }
        .progressbar li { width: 33.33%; position: relative; text-align: center; font-size: 13px; color: #ccc; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .progressbar li:before { content: counter(step); counter-increment: step; width: 30px; height: 30px; line-height: 28px; border: 2px solid #e0e0e0; background: #fff; display: block; text-align: center; margin: 0 auto 10px auto; border-radius: 50%; color: #ccc; font-weight: bold; z-index: 2; position: relative; }
        .progressbar li:after { content: ''; position: absolute; width: 100%; height: 3px; background: #e0e0e0; top: 15px; left: -50%; z-index: 0; }
        .progressbar li:first-child:after { content: none; }
        .progressbar li.active { color: #333; }
        .progressbar li.active:before { border-color: #28a745; background: #fff; color: #28a745; box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1); }
        .progressbar li.completed { color: #28a745; }
        .progressbar li.completed:before { content: '✔'; border-color: #28a745; background: #28a745; color: #fff; }
        .progressbar li.completed + li:after { background: #28a745; }
    </style>
</head>
<body>

<nav style="background:#333; padding:15px;">
    <div style="max-width:1000px; margin:0 auto;">
        <a href="../index.php" style="color:white; text-decoration:none;">&larr; Back to Home</a>
    </div>
</nav>

<div style="max-width: 600px; margin: 30px auto;">
    <ul class="progressbar">
        <li class="completed">View Details</li>
        <li class="active">Select Dates</li>
        <li>Payment</li>
    </ul>
</div>

<div class="container-flex">
    
    <div class="calendar-section">
        <h3>Availability Calendar</h3>
        <div class="legend">
            <span><span class="dot dot-gray"></span>Booked</span>
            <span><span class="dot dot-blue"></span>Cleaning</span>
            <span><span class="dot dot-green"></span>Selected</span>
        </div>
        <div id='calendar'></div>
    </div>

    <div class="form-section">
        <?php if ($room_details): ?>
            <h3><?php echo htmlspecialchars($display_name); ?></h3>
            <p style="color:#e74c3c; font-weight:bold;">RM <?php echo number_format($display_price, 2); ?> / night</p>
            
            <?php echo $msg; ?>

            <form method="POST" id="bookingForm">
                <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Check-in</label>
                    <input type="date" name="check_in" id="check_in" required min="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Check-out</label>
                    <input type="date" name="check_out" id="check_out" required min="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Guests</label>
                    <input type="number" name="guests" value="1" min="1" max="<?php echo $max_guests; ?>" style="width:100%; padding:8px;">
                    <small class="text-muted">Max: <?php echo $max_guests; ?></small>
                </div>

                <p style="font-size:13px; color:#666;">
                    <i class="bi bi-info-circle"></i> Max stay duration: 29 nights.<br>
                </p>

                <button type="submit" class="btn-book" id="bookBtn" disabled>Proceed to Checkout</button>
            </form>
        <?php else: ?>
            <p>Room not found.</p>
        <?php endif; ?>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var checkInInput = document.getElementById('check_in');
        var checkOutInput = document.getElementById('check_out');
        var bookBtn = document.getElementById('bookBtn');

        // --- 1. 定义核心验证函数 (Validater) ---
        function validateAndUpdate() {
            var startStr = checkInInput.value;
            var endStr = checkOutInput.value;

            if (!startStr || !endStr) {
                bookBtn.disabled = true;
                bookBtn.innerHTML = "Select Dates";
                return;
            }

            var startDate = new Date(startStr);
            var endDate = new Date(endStr);
            var today = new Date();
            today.setHours(0,0,0,0); // 重置时间，只比日期

            // 计算差距 (毫秒 -> 天)
            var diffTime = endDate - startDate;
            var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            // 计算一年后
            var oneYearLater = new Date(today);
            oneYearLater.setFullYear(today.getFullYear() + 1);

            // --- 规则检查 ---
            if (startDate < today) {
                alert("Check-in date cannot be in the past.");
                checkInInput.value = "";
                bookBtn.disabled = true;
            } 
            else if (endDate <= startDate) {
                // 如果结束日期早于开始日期，不做提示，只是禁用按钮（用户可能还在输）
                bookBtn.disabled = true;
                bookBtn.innerHTML = "Invalid Range";
            }
            else if (diffDays > 29) {
                Swal.fire({
                    title: 'Limit Exceeded',
                    text: 'You can only book up to 29 nights per stay.',
                    icon: 'warning',
                    confirmButtonColor: '#28a745'
                });
                checkOutInput.value = ""; // 清空结束日期
                bookBtn.disabled = true;
            }
            else if (endDate > oneYearLater) {
                Swal.fire({
                    title: 'Too far ahead',
                    text: 'Bookings can only be made up to 1 year in advance.',
                    icon: 'warning',
                    confirmButtonColor: '#28a745'
                });
                checkOutInput.value = "";
                bookBtn.disabled = true;
            }
            else {
                // --- 一切正常 ---
                bookBtn.disabled = false;
                bookBtn.innerHTML = "Book " + startStr + " to " + endStr;
                
                // (可选) 如果用户手动输入了日期，让日历跳转到那个月份
                if(calendar) {
                    calendar.gotoDate(startStr);
                }
            }
        }

        // --- 2. 监听输入框变化 (Manual Input) ---
        checkInInput.addEventListener('change', validateAndUpdate);
        checkOutInput.addEventListener('change', validateAndUpdate);

        // --- 3. 初始化日历 (FullCalendar) ---
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            selectable: true, 
            validRange: { start: '<?php echo date("Y-m-d"); ?>' },
            events: 'api_get_bookings.php?room_id=<?php echo $room_id; ?>',
            
            // 当用户在日历上拖拽选择时
            select: function(info) {
                // 填充输入框
                checkInInput.value = info.startStr;
                checkOutInput.value = info.endStr;
                
                // 触发验证逻辑
                validateAndUpdate();
            },
            
            eventDidMount: function(info) {
                // 禁止点击已有预订背景
                if (info.event.display === 'background') {
                    info.el.style.pointerEvents = 'none'; 
                }
            }
        });
        calendar.render();
    });
</script>

</body>
</html>