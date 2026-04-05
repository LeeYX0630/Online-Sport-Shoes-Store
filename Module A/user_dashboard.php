<?php
// Module A/user_dashboard.php

// 1. set timezone Malaysia
date_default_timezone_set("Asia/Kuala_Lumpur");

session_start();
require_once '../includes/db_connection.php';

// 2. Safety check: must be Customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = ""; 

// --- 3. Handle User Booking Cancellation (新增功能) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_my_booking'])) {
    $cancel_id = intval($_POST['booking_id_to_cancel']);
    
    // 安全检查：
    // 1. 订单必须属于当前用户 (AND user_id = '$user_id')
    // 2. 订单状态必须是 confirmed
    // 3. 入住日期必须 >= 今天 (不能取消以前的历史订单)
    $today_date = date("Y-m-d");
    
    $check_sql = "SELECT booking_id FROM bookings 
                  WHERE booking_id = '$cancel_id' 
                  AND user_id = '$user_id' 
                  AND booking_status = 'confirmed'
                  AND check_in_date >= '$today_date'";
                  
    $check_res = $conn->query($check_sql);
    
    if ($check_res->num_rows > 0) {
        // 执行取消
        $conn->query("UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = '$cancel_id'");
        $msg = "Booking #$cancel_id has been cancelled successfully.";
        $msg_type = "success";
    } else {
        $msg = "Unable to cancel. Order not found, already cancelled, or date passed.";
        $msg_type = "danger";
    }
}

// 4. Handle profile update (原有逻辑)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['cancel_my_booking'])) {
    $new_name = $conn->real_escape_string(substr(trim($_POST['full_name']), 0, 100));
    $phone_input = trim($_POST['phone']);
    $new_email = strtolower(trim($_POST['email'])); 

    // --- PHONE VALIDATION ---
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);
    $phone_valid = false;
    $err_details = "";

    if (substr($clean_phone, 0, 2) === '60') {
        if (strlen($clean_phone) >= 11 && strlen($clean_phone) <= 12) $phone_valid = true;
        else $err_details = "Format 60... must be 11-12 digits.";
    } elseif (substr($clean_phone, 0, 2) === '01') {
        if (strlen($clean_phone) >= 10 && strlen($clean_phone) <= 11) $phone_valid = true;
        else $err_details = "Format 01... must be 10-11 digits.";
    } else {
        $err_details = "Must start with '60' or '01'.";
    }

    // --- EMAIL DOMAIN VALIDATION ---
    $email_parts = explode('@', $new_email);
    $domain = end($email_parts);
    $domain_valid = false;

    $trusted_domains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 
        'icloud.com', 'live.com', 'aol.com', 'protonmail.com', 
        'ymail.com', 'msn.com'
    ];

    if (in_array($domain, $trusted_domains)) {
        $domain_valid = true;
    } elseif (strpos($domain, '.edu') !== false || strpos($domain, '.ac.') !== false || strpos($domain, '.sch') !== false || strpos($domain, 'student') !== false) {
        $domain_valid = true;
    }

    // --- EXECUTE CHECKS ---
    if (!$phone_valid) {
        $msg = "Update Failed: Invalid Malaysia Number. " . $err_details;
        $msg_type = "danger";
    } elseif (!$domain_valid) {
        $msg = "Update Failed: Invalid Email Domain. Use trusted (Gmail, Yahoo) or Education emails.";
        $msg_type = "danger";
    } else {
        $check_email = $conn->query("SELECT user_id FROM users WHERE email='$new_email' AND user_id != '$user_id'");
        
        if ($check_email->num_rows > 0) {
            $msg = "Update Failed: This email is already used by another account.";
            $msg_type = "danger";
        } else {
            $new_phone = $clean_phone;
            $conn->query("UPDATE users SET full_name='$new_name', phone='$new_phone', email='$new_email' WHERE user_id='$user_id'");
            
            $_SESSION['user_name'] = $new_name; 
            $msg = "Profile Updated Successfully!";
            $msg_type = "success";

            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                
                $filename = time() . "_" . basename($_FILES["profile_image"]["name"]);
                $target_file = $target_dir . $filename;
                $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
                
                if($check !== false) {
                    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                        $conn->query("UPDATE users SET profile_image='$filename' WHERE user_id='$user_id'");
                    } else {
                        $msg = "Error uploading file."; $msg_type = "danger";
                    }
                } else {
                    $msg = "File is not an image."; $msg_type = "danger";
                }
            }
        }
    }
}

$user_res = $conn->query("SELECT * FROM users WHERE user_id='$user_id'");
$user = $user_res->fetch_assoc();
$profile_pic = !empty($user['profile_image']) ? "uploads/".$user['profile_image'] : "uploads/default.png";

$page_title = "My Dashboard";
include '../includes/header.php'; 
?>

<style>
    .profile-img-large {
        width: 150px; height: 150px; object-fit: cover; border-radius: 50%;
        border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.15); 
        margin: 0 auto; display: block;
    }
    .card { overflow: hidden; }
</style>

<div class="container-fluid px-md-5 mt-5 mb-5">
  
  <div class="d-flex justify-content-between align-items-center welcome-header border-bottom pb-3 mb-4">
     <div>
        <h2 class="welcome-text fw-bold text-dark">Welcome, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h2>
        <p class="text-muted mb-0">Manage your profile and view your latest bookings here.</p>
     </div>
     <div class="text-end d-none d-md-block">
        <h5 class="mb-0 fw-bold text-secondary" id="live-clock"><?php echo date("h:i:s A"); ?></h5>
        <small class="text-muted" id="live-date"><?php echo date("l, d F Y"); ?></small>
     </div>
  </div>

  <div class="row justify-content-center">
    
    <div class="col-md-4 mb-4">
        <div class="card text-center p-4 shadow-sm border-0 h-100 rounded-4">
            <div class="mb-3">
                <img src="<?php echo $profile_pic; ?>" alt="Profile Image" class="profile-img-large">
            </div>
            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
            <p class="badge bg-secondary"><?php echo $user['role']; ?></p>
            <p class="text-muted small"><?php echo $user['email']; ?></p>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card p-4 shadow-sm border-0 mb-4 rounded-4">
            <h4 class="mb-4 fw-bold">Edit Profile</h4>
            
            <?php if($msg): ?>
                <div class="alert alert-<?php echo $msg_type; ?>" role="alert">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Full Name</label>
                        <input type="text" class="form-control bg-light border-0 py-2" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-secondary">Phone Number</label>
                        <input type="text" class="form-control bg-light border-0 py-2" 
                               name="phone" 
                               value="<?php echo $user['phone']; ?>" 
                               required 
                               maxlength="13"
                               placeholder="e.g. 60123456789 or 0123456789"
                               oninput="validatePhone(this)">
                        <small class="text-muted" style="font-size: 0.8rem;">Allowed: 601... or 01...</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Email Address</label>
                    <input type="email" class="form-control bg-light border-0 py-2" 
                           name="email" 
                           value="<?php echo $user['email']; ?>" 
                           required>
                    <small class="text-muted" style="font-size: 0.8rem;">Trusted domains only (Gmail, Yahoo, School, etc.)</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-secondary">Upload New Profile Picture</label>
                    <input type="file" class="form-control bg-light border-0" name="profile_image" accept="image/*">
                </div>

                <button type="submit" class="btn btn-warning text-dark fw-bold px-4 py-2">
                    Update Profile
                </button>
            </form>
        </div>
        
        <div class="card p-4 shadow-sm border-0 mb-4 rounded-4">
            <h5 class="mb-3 fw-bold"><i class="bi bi-ticket-perforated me-2"></i>My Vouchers</h5>
            
            <?php
            $coupon_sql = "SELECT uc.*, c.code, c.discount_value, c.discount_type, c.min_spend, c.expiry_date 
                           FROM user_coupons uc 
                           JOIN coupons c ON uc.coupon_id = c.coupon_id 
                           WHERE uc.user_id = '$user_id' AND uc.status = 'active' 
                           AND c.expiry_date >= CURDATE()";
            $coupon_res = $conn->query($coupon_sql);
            ?>

            <div class="row">
                <?php if ($coupon_res && $coupon_res->num_rows > 0): ?>
                    <?php while($voucher = $coupon_res->fetch_assoc()): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-warning border-2" style="background-color: #fff9e6;">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1"><?php echo $voucher['code']; ?></h5>
                                        <p class="text-muted small mb-1">
                                            <?php 
                                            if($voucher['discount_type'] == 'percent') {
                                                echo intval($voucher['discount_value']) . "% OFF";
                                            } else {
                                                echo "RM " . number_format($voucher['discount_value'], 0) . " OFF";
                                            }
                                            ?>
                                            (Min spend: RM <?php echo $voucher['min_spend']; ?>)
                                        </p>
                                        <small class="text-danger">Expires: <?php echo $voucher['expiry_date']; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning text-dark">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-muted">You have no active vouchers.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card p-4 shadow-sm border-0 rounded-4">
            <h5 class="mb-3 fw-bold">Booking History</h5>
            <?php
            $book_sql = "SELECT b.*, r.room_name, r.room_image 
                         FROM bookings b 
                         JOIN rooms r ON b.room_id = r.room_id 
                         WHERE b.user_id = '$user_id' 
                         ORDER BY b.booking_id DESC";
            $book_res = $conn->query($book_sql);
            ?>
            <?php if ($book_res && $book_res->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Room</th>
                                <th>Dates</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th class="text-end">Action</th> </tr>
                        </thead>
                        <tbody>
                            <?php while($booking = $book_res->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="text-muted">#<?php echo $booking['booking_id']; ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php $r_img = !empty($booking['room_image']) ? "../uploads/".$booking['room_image'] : "../images/placeholder.jpg"; ?>
                                            <img src="<?php echo $r_img; ?>" style="width:50px; height:40px; object-fit:cover; border-radius:4px; margin-right:10px;">
                                            <span class="fw-bold"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="d-block text-success"><?php echo $booking['check_in_date']; ?></small>
                                        <small class="d-block text-danger"><?php echo $booking['check_out_date']; ?></small>
                                    </td>
                                    <td class="fw-bold">RM <?php echo number_format($booking['total_price'], 2); ?></td>
                                    <td>
                                        <?php 
                                            $status = $booking['booking_status'];
                                            $badgeClass = 'bg-secondary';
                                            if ($status == 'confirmed') $badgeClass = 'bg-success';
                                            elseif ($status == 'cancelled') $badgeClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php 
                                            $is_confirmed = ($booking['booking_status'] == 'confirmed');
                                            $is_future = ($booking['check_in_date'] >= date("Y-m-d"));
                                            
                                            if ($is_confirmed && $is_future): 
                                        ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to CANCEL this booking? This cannot be undone.');">
                                                <input type="hidden" name="booking_id_to_cancel" value="<?php echo $booking['booking_id']; ?>">
                                                <button type="submit" name="cancel_my_booking" class="btn btn-sm btn-outline-danger">Cancel</button>
                                            </form>
                                        <?php elseif ($booking['booking_status'] == 'cancelled'): ?>
                                            <span class="text-muted small">Cancelled</span>
                                        <?php else: ?>
                                            <span class="text-muted small">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No bookings yet.</p>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div> 

<script>
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const dateString = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('live-clock').textContent = timeString;
        document.getElementById('live-date').textContent = dateString;
    }
    setInterval(updateClock, 1000);
    updateClock();

    function validatePhone(input) {
        input.value = input.value.replace(/[^0-9]/g, '');
        if(input.value.length > 13) {
            input.value = input.value.slice(0, 13);
        }
    }
</script>

<?php 
include '../includes/footer.php'; 
?>