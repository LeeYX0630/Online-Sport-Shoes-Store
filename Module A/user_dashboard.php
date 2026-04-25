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

// --- 3. Handle User Booking Cancellation (Original Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_my_booking'])) {
    $cancel_id = intval($_POST['booking_id_to_cancel']);
    $today_date = date("Y-m-d");
    
    $check_sql = "SELECT booking_id FROM bookings 
                  WHERE booking_id = '$cancel_id' 
                  AND user_id = '$user_id' 
                  AND booking_status = 'confirmed'
                  AND check_in_date >= '$today_date'";
                  
    $check_res = $conn->query($check_sql);
    
    if ($check_res && $check_res->num_rows > 0) {
        $conn->query("UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = '$cancel_id'");
        $msg = "Booking #$cancel_id has been cancelled successfully.";
        $msg_type = "success";
    } else {
        $msg = "Unable to cancel. Order not found, already cancelled, or date passed.";
        $msg_type = "danger";
    }
}

// 4. Handle profile update (Original Logic)
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

    $trusted_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'];

    if (in_array($domain, $trusted_domains)) {
        $domain_valid = true;
    } elseif (strpos($domain, '.edu') !== false) {
        $domain_valid = true;
    }

    // --- EXECUTE CHECKS ---
    if (!$phone_valid) {
        $msg = "Update Failed: Invalid Malaysia Number. " . $err_details;
        $msg_type = "danger";
    } elseif (!$domain_valid) {
        $msg = "Update Failed: Invalid Email Domain.";
        $msg_type = "danger";
    } else {
        $check_email = $conn->query("SELECT User_Id FROM `USER` WHERE User_Email='$new_email' AND User_Id != '$user_id'");
        
        if ($check_email && $check_email->num_rows > 0) {
            $msg = "Update Failed: This email is already used.";
            $msg_type = "danger";
        } else {
            $new_phone = $clean_phone;
            $conn->query("UPDATE `USER` SET User_Name='$new_name', User_Phone='$new_phone', User_Email='$new_email' WHERE User_Id='$user_id'");
            
            $_SESSION['user_name'] = $new_name; 
            $msg = "Profile Updated Successfully!";
            $msg_type = "success";

            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $filename = time() . "_" . basename($_FILES["profile_image"]["name"]);
                if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $filename)) {
                    $conn->query("UPDATE `USER` SET User_Image='$filename' WHERE User_Id='$user_id'");
                }
            }
        }
    }
}

$user_res = $conn->query("SELECT * FROM `USER` WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();
$profile_pic = !empty($user['User_Image']) ? "uploads/".$user['User_Image'] : "uploads/default.png";

// ADDED: Shop Theme Variables
$page_title = "Online Sport Shoes Store | Dashboard";
include '../includes/header.php'; 
?>

<style>
    :root {
        --brand-orange: #FF6B00;
        --pure-white: #FFFFFF;
    }
    body { background-color: #F8F9FA; font-family: 'Plus Jakarta Sans', sans-serif; }
    .profile-img-large {
        width: 150px; height: 150px; object-fit: cover; border-radius: 25px;
        border: 4px solid var(--brand-orange); margin: 0 auto; display: block;
    }
    .card { border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .btn-orange { background-color: var(--brand-orange); color: white; font-weight: 800; border-radius: 12px; transition: 0.3s; }
    .btn-orange:hover { background-color: #E66000; color: white; transform: translateY(-2px); }
    .nav-tabs .nav-link.active { border-bottom: 3px solid var(--brand-orange); color: var(--brand-orange); font-weight: bold; }
    .shoe-badge { background: rgba(255, 107, 0, 0.1); color: var(--brand-orange); border-radius: 8px; padding: 4px 10px; font-weight: bold; font-size: 0.8rem; }
</style>

<div class="container py-5">
    
    <div class="mb-5 border-bottom pb-3 d-flex justify-content-between align-items-end">
        <div>
            <h1 class="fw-800" style="color: #000;">Online Sport <span style="color: var(--brand-orange);">Shoes Store.</span></h1>
            <p class="text-muted">Personal locker for performance and elite footwear.</p>
        </div>
        <div class="text-end">
            <h5 id="live-clock" class="fw-bold mb-0">00:00:00 AM</h5>
            <small class="text-muted" id="live-date">Loading date...</small>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card p-4 text-center">
                <div class="mb-3 position-relative">
                    <img src="<?php echo $profile_pic; ?>" alt="Profile" class="profile-img-large">
                </div>
                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($user['User_Name']); ?></h4>
                <div class="mb-3">
                    <span class="shoe-badge">ELITE MEMBER</span>
                </div>
                
                <div class="mt-2 p-3 rounded-4" style="background-color: #FFF5EE; border: 1px solid #FFE4D3;">
                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.65rem;">Account Balance</small>
                    <h3 class="fw-800" style="color: var(--brand-orange);">RM <?php echo number_format($user['User_Balance'], 2); ?></h3>
                    <a href="../Module B/wallet.php" class="btn btn-sm btn-orange w-100 mt-2">Manage Funds</a>
                </div>
                
                <div class="mt-4 pt-3 border-top text-start">
                    <p class="mb-2"><i class="bi bi-envelope-at me-2"></i> <?php echo $user['User_Email']; ?></p>
                    <p class="mb-0"><i class="bi bi-phone me-2"></i> +<?php echo $user['User_Phone']; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4 mb-4">
                <?php if($msg): ?>
                    <div class="alert alert-<?php echo $msg_type; ?> rounded-4 mb-4">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <ul class="nav nav-tabs border-0 mb-4" id="dashboardTabs">
                    <li class="nav-item"><a class="nav-link active border-0" href="#profile" data-bs-toggle="tab">Identity Settings</a></li>
                    <li class="nav-item"><a class="nav-link border-0" href="#history" data-bs-toggle="tab">Purchase History</a></li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="profile">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small text-muted">Full Name</label>
                                    <input type="text" class="form-control bg-light py-2 border-0" name="full_name" value="<?php echo htmlspecialchars($user['User_Name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small text-muted">Phone Number</label>
                                    <input type="text" class="form-control bg-light py-2 border-0" name="phone" value="<?php echo $user['User_Phone']; ?>" oninput="validatePhone(this)" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Email Address</label>
                                <input type="email" class="form-control bg-light py-2 border-0" name="email" value="<?php echo $user['User_Email']; ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted">New Profile Avatar</label>
                                <input type="file" class="form-control bg-light border-0" name="profile_image" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-orange px-5 py-2">Save Profile Changes</button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="history">
                        <p class="text-muted">Viewing your recent sport shoe orders and bookings...</p>
                        </div>
                </div>
            </div>

            <div class="card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-tag-fill me-2 text-warning"></i>Store Vouchers</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="p-3 rounded-4" style="background: white; border: 2px dashed var(--brand-orange);">
                            <h6 class="fw-bold mb-1">STEALTH10</h6>
                            <p class="small text-muted mb-0">10% OFF performance sneakers.</p>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="p-3 rounded-4" style="background: white; border: 2px dashed #ccc;">
                            <h6 class="fw-bold mb-1 text-muted">NEWJOINER</h6>
                            <p class="small text-muted mb-0 text-decoration-line-through">Already claimed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('live-clock').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('live-date').textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
    }
    setInterval(updateClock, 1000);
    updateClock();

    function validatePhone(input) {<?php
// ... (Your existing session and DB connection code) ...

// 1. Fetch Featured Shoes from 'product' table
$featured_shoes = $conn->query("SELECT p.*, b.Brand_Name 
                                FROM product p 
                                JOIN brand b ON p.Brand_Id = b.Brand_Id 
                                WHERE p.Product_Status = 'Available' 
                                LIMIT 3");

// 2. Original User Data Fetching
$user_res = $conn->query("SELECT * FROM `user` WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();
?>

<div class="row mt-4">
    <div class="col-md-4 mb-4">
        <div class="shoes-card p-4 h-100">
            <h6 class="fw-800 mb-3"><i class="bi bi-rulers me-2 text-orange"></i>My Fit Profile</h6>
            <div class="p-3 rounded-4 bg-light">
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-muted">Standard Size:</span>
                    <span class="fw-bold">UK 9.5</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="small text-muted">Arch Type:</span>
                    <span class="fw-bold">Neutral</span>
                </div>
            </div>
            <p class="small text-muted mt-3 mb-0">Your fit profile helps us suggest shoes that feel perfect on your first run.</p>
        </div>
    </div>

    <div class="col-md-8 mb-4">
        <div class="shoes-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-800 mb-0"><i class="bi bi-stars me-2 text-orange"></i>New Arrivals For You</h6>
                <a href="shop.php" class="small text-orange fw-bold text-decoration-none">View All</a>
            </div>
            
            <div class="row g-3">
                <?php while($shoe = $featured_shoes->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="border rounded-4 p-2 text-center position-relative">
                        <img src="products/<?php echo $shoe['Product_Image']; ?>" class="img-fluid rounded-3 mb-2" alt="Shoe">
                        <h6 class="small fw-bold mb-0 text-truncate"><?php echo $shoe['Product_Name']; ?></h6>
                        <span class="text-orange fw-bold small">RM <?php echo number_format($shoe['Product_Price'], 2); ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<div class="shoes-card p-4 mt-2 mb-5" style="background: linear-gradient(to right, #000, #333); color: white;">
    <div class="row align-items-center">
        <div class="col-md-2 text-center text-orange display-4">
            <i class="bi bi-shield-shaded"></i>
        </div>
        <div class="col-md-7">
            <h5 class="fw-800">Pro-Tip: Sneaker Care</h5>
            <p class="small mb-0 opacity-75">To keep your high-performance shoes lasting longer, never machine wash them. Use a soft brush and lukewarm water.</p>
        </div>
        <div class="col-md-3 text-end">
            <button class="btn btn-orange btn-sm">Read Full Guide</button>
        </div>
    </div>
</div>
        input.value = input.value.replace(/[^0-9]/g, '');
        if(input.value.length > 13) input.value = input.value.slice(0, 13);
    }
</script>

<?php include '../includes/footer.php'; ?>