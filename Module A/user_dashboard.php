<?php
// Module A/user_dashboard.php
date_default_timezone_set("Asia/Kuala_Lumpur");

session_start();
require_once '../includes/db_connection.php';
require_once 'send_otp.php';

function respondJson(array $payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// ===============================
// HANDLE POST REQUESTS
// ===============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = trim($_POST['action']);

        if ($action === 'send_security_otp') {
            $email_result = $conn->query("SELECT User_Email FROM `user` WHERE User_Id='$user_id'");
            $user_row = $email_result ? $email_result->fetch_assoc() : null;

            if (!$user_row || empty($user_row['User_Email'])) {
                respondJson(['success' => false, 'message' => 'Unable to locate your registered email address.']);
            }

            $send_result = sendOTP($user_row['User_Email']);
            if ($send_result === true) {
                respondJson(['success' => true, 'message' => 'Verification code sent to your email.']);
            }

            respondJson(['success' => false, 'message' => $send_result]);
        }

        if ($action === 'verify_security_otp') {
            $otp_input = trim($_POST['otp'] ?? '');
            $current_time = time();

            if (empty($otp_input) || !preg_match('/^\d{6}$/', $otp_input)) {
                respondJson(['success' => false, 'message' => 'Please enter the 6-digit verification code.']);
            }

            if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) {
                respondJson(['success' => false, 'message' => 'No verification code was requested. Please request a new code.']);
            }

            if ($current_time - $_SESSION['otp_time'] > 300) {
                unset($_SESSION['otp'], $_SESSION['otp_time']);
                respondJson(['success' => false, 'message' => 'The verification code has expired. Please request a new one.']);
            }

            if ($otp_input !== (string)$_SESSION['otp']) {
                respondJson(['success' => false, 'message' => 'The verification code is incorrect.']);
            }

            $_SESSION['password_change_verified'] = true;
            $_SESSION['password_change_verified_time'] = $current_time;
            respondJson(['success' => true, 'message' => 'Identity verified. You may now change your password.']);
        }
    }

   // --- HANDLE PASSWORD UPDATE ---
if (isset($_POST['update_password'])) {
    $current_pass = trim($_POST['current_pass']); 
    $new_pass = trim($_POST['new_pass']);
    $confirm_pass = trim($_POST['confirm_pass']);

    // Fetch current password
    $verify_sql = $conn->query("SELECT User_Password FROM `user` WHERE User_Id='$user_id'");
    $user_data = $verify_sql->fetch_assoc();

    // 1. First check: Do the new passwords match?
    if ($new_pass !== $confirm_pass) {
        $msg = "New passwords do not match!";
        $msg_type = "danger";
    } 
    // 2. Second check: Is the new password long enough?
    elseif (strlen($new_pass) < 6) {
        $msg = "New password must be at least 6 characters!";
        $msg_type = "danger";
    }
    // 2.5. Secure check: Require OTP verification before allowing password change
    elseif (empty($_SESSION['password_change_verified']) || $_SESSION['password_change_verified'] !== true) {
        $msg = "Please verify your identity with the security code before changing your password.";
        $msg_type = "danger";
    }
    // 3. Third check: Verify user exists
    elseif (!$user_data || empty($user_data['User_Password'])) {
        $msg = "Error: Unable to retrieve password information!";
        $msg_type = "danger";
    }
    // 4. Check password - try both hashed and plain text (case-sensitive)
    elseif (password_verify($current_pass, $user_data['User_Password']) || $current_pass === trim($user_data['User_Password'])) {
        // Password is correct - update with hash
        $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE `user` SET User_Password=? WHERE User_Id=?");
        $stmt->bind_param("si", $hashed_new_pass, $user_id);
        if ($stmt->execute()) {
            $msg = "Password updated successfully!";
            $msg_type = "success";
            unset($_SESSION['password_change_verified'], $_SESSION['password_change_verified_time'], $_SESSION['otp'], $_SESSION['otp_time']);
        }
    } 
    // 5. If password doesn't match
    else {
        $msg = "Current password is incorrect!";
        $msg_type = "danger";
    }
}
    // --- HANDLE PROFILE UPDATE ---
    else if (isset($_POST['full_name'])) {
        // Sanitize and validate Name (No numbers)
        $new_name = preg_replace('/[0-9]/', '', trim($_POST['full_name']));
        $new_name = substr($new_name, 0, 100);
        
        // Sanitize Phone (Numbers only)
        $clean_phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        $new_email = strtolower(trim($_POST['email']));

        // Validation Check
        if (preg_match('/[0-9]/', $_POST['full_name'])) {
            $msg = "Name cannot contain numbers!";
            $msg_type = "danger";
        } 
        else {
            // Check duplicate email
            $check_email = $conn->query("SELECT User_Id FROM `user` WHERE User_Email='$new_email' AND User_Id != '$user_id'");
            
            if ($check_email && $check_email->num_rows > 0) {
                $msg = "Email already used by another account!";
                $msg_type = "danger";
            } else {
                // Update user details
                $conn->query("UPDATE `user` SET User_Name='$new_name', User_Phone='$clean_phone', User_Email='$new_email' WHERE User_Id='$user_id'");
                $_SESSION['user_name'] = $new_name;

                // HANDLE PROFILE IMAGE UPLOAD
                if (!empty($_FILES['profile_image']['name'])) {
                    $upload_dir = __DIR__ . "/../uploads/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $filename = time() . "_" . basename($_FILES['profile_image']['name']);
                    $target = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                        $conn->query("UPDATE `user` SET User_Image='$filename' WHERE User_Id='$user_id'");
                    } else {
                        $msg = "Error: Failed to move uploaded file.";
                        $msg_type = "danger";
                    }
                }
                
                $msg = "Profile updated successfully!";
                $msg_type = "success";
            }
        }
    }
}

// ===============================
// FETCH DATA
// ===============================
$user_res = $conn->query("SELECT * FROM `user` WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();
$passwordChangeDisabled = (empty($_SESSION['password_change_verified']) || $_SESSION['password_change_verified'] !== true) ? 'disabled' : '';
$passwordVerified = ($passwordChangeDisabled === '') ? true : false;
$available_promos = $conn->query("
    SELECT p.* FROM user_promo up
    JOIN promo p ON up.Promo_Id = p.Promo_Id 
    WHERE up.User_Id = '$user_id' 
    AND up.Is_Used = 'No' 
    AND p.Promo_Status = 'Active' 
    AND p.Expired_Date >= CURDATE() 
    ORDER BY up.Received_Date DESC
");

$profile_pic = !empty($user['User_Image']) ? "../uploads/".$user['User_Image'] : "../uploads/default.png";

include '../includes/header.php';
?>

<style>
:root { --brand-orange: #FF6B00; }
body { background-color: #F8F9FA; font-family: 'Plus Jakarta Sans', sans-serif; }
.card { border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #fff; }
.profile-img-large { 
    width: 150px; height: 150px; 
    border-radius: 25px; border: 4px solid var(--brand-orange); 
    object-fit: cover; margin: 0 auto; display: block;
}
.btn-orange { 
    background-color: var(--brand-orange); color: white; 
    font-weight: 800; border-radius: 12px; transition: 0.3s; border: none; 
}
.btn-orange:hover { background-color: #E66000; color: white; transform: translateY(-2px); }
.btn-outline-orange { color: var(--brand-orange); border: 1px solid var(--brand-orange); background: transparent; }
.btn-outline-orange:hover { background: rgba(255, 107, 0, 0.08); }
.nav-tabs { border-bottom: 1px solid #eee; }
.nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 700; padding: 10px 20px; }
.nav-tabs .nav-link.active { color: var(--brand-orange); border-bottom: 3px solid var(--brand-orange); background: none; }
.voucher-box { padding: 15px; border-radius: 15px; height: 100%; transition: 0.3s; }
.voucher-active { border: 2px dashed var(--brand-orange); background: #fff; }
.voucher-claimed { border: 2px dashed #ced4da; background: #f8f9fa; }
.voucher-title { font-weight: 800; color: #333; margin-bottom: 2px; }
.fw-800 { font-weight: 800; }
.text-orange { color: var(--brand-orange); }
</style>

<div class="container py-5">
    <div class="mb-5 border-bottom pb-3 d-flex justify-content-between align-items-end">
        <div>
            <h1 class="fw-800">Online Sport <span class="text-orange">Shoes Store.</span></h1>
            <p class="text-muted">Personal locker for performance and elite footwear.</p>
        </div>
        <div class="text-end">
            <h5 id="live-clock" class="fw-bold mb-0" style="font-size: 1.5rem;">00:00:00 PM</h5>
            <small class="text-muted fw-bold" id="live-date">Monday, April 27, 2026</small>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card p-4 text-center">
                <img src="<?php echo $profile_pic; ?>" class="profile-img-large mb-3">
                <h4 class="fw-800 mb-1"><?php echo htmlspecialchars($user['User_Name']); ?></h4>
                <div class="mb-3"><span class="badge rounded-pill bg-light text-orange border">ELITE MEMBER</span></div>
                
                <div class="mt-2 p-3 rounded-4" style="background-color: #FFF5EE; border: 1px solid #FFE4D3;">
                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.65rem;">Account Balance</small>
                    <h3 class="fw-800" style="color: var(--brand-orange);">RM <?php echo number_format($user['User_Balance'], 2); ?></h3>
                    
                    <?php if (empty($user['User_PIN'])): ?>
                        <button onclick="setupWalletPIN()" class="btn btn-sm btn-danger w-100 mt-2">Setup PIN</button>
                    <?php else: ?>
                        <a href="../Module B/wallet.php" class="btn btn-sm btn-orange w-100 mt-2">Manage Wallet</a>
                        <a href="javascript:void(0)" onclick="forgotWalletPIN()" class="d-block text-center mt-2 small text-muted">Forgot Wallet PIN?</a>
                    <?php endif; ?>
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
                    <div class="alert alert-<?php echo $msg_type; ?> rounded-4"><?php echo $msg; ?></div>
                <?php endif; ?>

                <ul class="nav nav-tabs mb-4" id="dashboardTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="identity-tab" data-bs-toggle="tab" data-bs-target="#identity">Identity Settings</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="purchased-tab" data-bs-toggle="tab" data-bs-target="#purchased">Purchased Products</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security">Security</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="identity">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="small fw-bold text-muted">Full Name</label>
                                    <input type="text" name="full_name" class="form-control bg-light border-0 py-2" 
                                           value="<?php echo htmlspecialchars($user['User_Name']); ?>" 
                                           oninput="this.value = this.value.replace(/[0-9]/g, '')" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small fw-bold text-muted">Phone Number</label>
                                    <input type="text" name="phone" class="form-control bg-light border-0 py-2" 
                                           value="<?php echo $user['User_Phone']; ?>" 
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-muted">Email Address</label>
                                <input type="email" name="email" class="form-control bg-light border-0 py-2" 
                                       value="<?php echo $user['User_Email']; ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="small fw-bold text-muted">Change Avatar</label>
                                <input type="file" name="profile_image" class="form-control bg-light border-0">
                            </div>
                            <button type="submit" class="btn btn-orange px-5 py-2">Save Profile Changes</button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="purchased">
                        <?php
                        $today = date('Y-m-d');
                        $f_id = isset($_GET['search_id']) ? mysqli_real_escape_string($conn, $_GET['search_id']) : '';
                        $f_date = isset($_GET['filter_date']) ? mysqli_real_escape_string($conn, $_GET['filter_date']) : '';
                        $f_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

                        $query_str = "SELECT o.*, p.Pro_Image, p.Pro_Name 
                                      FROM `order` o 
                                      LEFT JOIN order_detail od ON o.Order_Id = od.Order_Id 
                                      LEFT JOIN product p ON od.Pro_Id = p.Pro_Id 
                                      WHERE o.User_Id = '$user_id' AND DATE(o.Order_Date) <= '$today' 
                                      GROUP BY o.Order_Id";

                        if ($f_id != '') {
                            $clean_id = str_replace('ORD#', '', $f_id);
                            $query_str .= " AND Order_Id LIKE '%$clean_id%'";
                        }
                        if ($f_date != '') {
                            if ($f_date > $today) { $f_date = $today; }
                            $query_str .= " AND DATE(Order_Date) = '$f_date'";
                        }
                        if ($f_status != '' && $f_status != 'All Status') {
                            $query_str .= " AND Order_Status = '$f_status'";
                        }

                        $query_str .= " ORDER BY Order_Id DESC";
                        $purchased_data = $conn->query($query_str);
                        ?>

                        <form method="GET" action="user_dashboard.php">
                            <div class="row g-2 mb-4 align-items-end">
                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="showOrderId" onchange="toggleOrderIdColumn()">
                                        <label class="form-check-label small fw-bold text-muted" for="showOrderId">
                                            Show Order ID
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Search Order ID</label>
                                    <div class="input-group input-group-sm border-0 shadow-sm rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-white border-0"><i class="bi bi-hash"></i></span>
                                        <input type="text" name="search_id" class="form-control border-0" placeholder="ORD#xxxxxx" value="<?php echo htmlspecialchars($f_id); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Filter Date</label>
                                    <div class="input-group input-group-sm border-0 shadow-sm rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-white border-0"><i class="bi bi-calendar3"></i></span>
                                        <input type="date" name="filter_date" class="form-control border-0" max="<?php echo $today; ?>" value="<?php echo htmlspecialchars($f_date); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                                    <select name="status" class="form-select form-select-sm border-0 shadow-sm rounded-3">
                                        <option value="">All Status</option>
                                        <option value="Processing" <?php if($f_status == 'Processing') echo 'selected'; ?>>Processing</option>
                                        <option value="Shipping" <?php if($f_status == 'Shipping') echo 'selected'; ?>>Shipping</option>
                                        <option value="Complete" <?php if($f_status == 'Complete') echo 'selected'; ?>>Complete</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-dark btn-sm w-100 rounded-3 shadow-sm py-2 fw-bold text-uppercase" style="background: #FF6B00; border: none;">
                                        <i class="bi bi-search me-1"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive bg-white rounded-4 shadow-sm p-3">
                            <table class="table align-middle table-hover mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th class="border-0 pb-3 order-id-column" style="display: none;">ORDER ID</th>
                                        <th class="border-0 pb-3">PRODUCT IMAGE</th>
                                        <th class="border-0 pb-3">PRODUCT NAME</th>
                                        <th class="border-0 pb-3">TRANS. DATE</th>
                                        <th class="border-0 pb-3">ORDER AMOUNT</th>
                                        <th class="border-0 pb-3">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold">
                                    <?php if($purchased_data && $purchased_data->num_rows > 0): ?>
                                        <?php while($row = $purchased_data->fetch_assoc()): 
                                            $status = $row['Order_Status'] ?? 'Complete';
                                            $badge_color = ($status == 'Processing') ? "bg-warning-subtle text-warning" : (($status == 'Shipping') ? "bg-info-subtle text-info" : "bg-success-subtle text-success");
                                            $product_image = "../images/brands/placeholder.png"; 
                                            
                                            if (!empty($row['Pro_Image'])) {
                                                $path_parts = pathinfo($row['Pro_Image']);
                                                $filename = $path_parts['filename'];
                                                $extension = isset($path_parts['extension']) ? "." . $path_parts['extension'] : "";
                                                
                                                // 查找任何相关图片
                                                $found_images = glob("../uploads/{$filename}*.*");
                                                if (!empty($found_images)) {
                                                    $product_image = $found_images[0];
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td class="py-3 order-id-column" style="display: none;"><span class="text-dark">ORD#<?php echo sprintf("%06d", $row['Order_Id']); ?></span></td>
                                                <td class="py-3">
                                                    <img src="<?php echo $product_image; ?>" alt="Product" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                                </td>
                                                <td class="py-3"><?php echo htmlspecialchars($row['Pro_Name'] ?? 'N/A'); ?></td>
                                                <td><?php echo date("Y-m-d", strtotime($row['Order_Date'])); ?></td>
                                                <td>RM <?php echo number_format($row['Order_Amount'] ?? 0, 2); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?php echo $badge_color; ?> px-3 py-2">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No purchases found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="security">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-12 mb-3">
                                    <div class="p-3 rounded-4 border border-secondary-subtle bg-white">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-1">Identity Verification</h6>
                                                <p class="small text-muted mb-0">A one-time verification code is required before updating your password.</p>
                                            </div>
                                            <button type="button" id="sendSecurityOTP" class="btn btn-outline-orange btn-sm">
                                                Send OTP
                                            </button>
                                        </div>
                                        <div id="securityOtpSection" class="d-none">
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="small fw-bold text-muted text-uppercase">Verification Code</label>
                                                    <input type="text" id="security_otp" class="form-control bg-light border-0 py-2" maxlength="6" pattern="[0-9]*" placeholder="Enter 6-digit code">
                                                </div>
                                                <div class="col-md-4 align-self-end">
                                                    <button type="button" id="verifySecurityOTP" class="btn btn-secondary w-100">Verify Code</button>
                                                </div>
                                            </div>
                                            <div id="securityOtpMessage" class="mt-2 small text-muted"></div>
                                        </div>
                                        <div id="verifiedBadge" class="mt-3 small text-success fw-bold d-none">
                                            <i class="bi bi-shield-check me-1"></i>Verified. You may now change your password.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <label class="small fw-bold text-muted text-uppercase">Current Password</label>
                                    <input id="currentPassInput" type="password" name="current_pass" class="form-control bg-light border-0 py-2" <?php echo $passwordChangeDisabled; ?> required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small fw-bold text-muted text-uppercase">New Password</label>
                                    <input id="newPassInput" type="password" name="new_pass" class="form-control bg-light border-0 py-2" <?php echo $passwordChangeDisabled; ?> required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="small fw-bold text-muted text-uppercase mb-0">Password Strength</label>
                                        <small id="passwordStrengthLabel" class="text-muted fw-bold">Enter password</small>
                                    </div>
                                    <div class="progress rounded-pill" style="height: 8px;">
                                        <div id="passwordStrengthBar" class="progress-bar bg-danger rounded-pill" role="progressbar" style="width: 0%;"></div>
                                    </div>
                                    <div class="d-flex gap-2 mt-2 justify-content-between align-items-center">
                                        <small id="weakIndicator" class="fw-bold" style="font-size: 0.9rem; color: #ccc;">🔴 Weak</small>
                                        <small id="mediumIndicator" class="fw-bold" style="font-size: 0.9rem; color: #ccc;">🟡 Medium</small>
                                        <small id="strongIndicator" class="fw-bold" style="font-size: 0.9rem; color: #ccc;">🟢 Strong</small>
                                    </div>
                                    <small id="passwordStrengthHint" class="text-muted d-block mt-2">Use at least 8 characters with uppercase, number, and symbol.</small>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small fw-bold text-muted text-uppercase">Confirm New Password</label>
                                    <input id="confirmPassInput" type="password" name="confirm_pass" class="form-control bg-light border-0 py-2" <?php echo $passwordChangeDisabled; ?> required>
                                </div>
                                <div class="col-12 mt-4">
                                    <button id="updatePasswordBtn" type="submit" name="update_password" class="btn btn-orange px-5 py-2" <?php echo $passwordChangeDisabled; ?>>
                                        Update Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-4">
                <h5 class="fw-800 mb-4"><i class="bi bi-tag-fill text-warning me-2"></i>Available Promo Codes</h5>
                <div class="row">
                    <?php if ($available_promos && $available_promos->num_rows > 0): ?>
                        <?php while ($promo = $available_promos->fetch_assoc()): ?>
                            <div class="col-md-6 mb-3">
                                <div class="voucher-box voucher-active">
                                    <div class="voucher-title"><?php echo htmlspecialchars($promo['Promo_Code']); ?></div>
                                    <p class="small text-muted mb-1"><?php echo htmlspecialchars($promo['Promo_Name']); ?></p>
                                    <p class="small text-dark mb-1 fw-bold">
                                        <?php echo ($promo['Promo_Type'] === 'Percentage') 
                                            ? intval($promo['Promo_Value']) . '% OFF' 
                                            : 'RM ' . number_format($promo['Promo_Value'], 2) . ' OFF'; ?>
                                    </p>
                                    <p class="small text-muted mb-0">Expires <?php echo date('d M Y', strtotime($promo['Expired_Date'])); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="voucher-box voucher-claimed">
                                <div class="voucher-title text-muted">No Vouchers Available</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
        document.getElementById('live-clock').textContent = now.toLocaleTimeString('en-US', timeOptions);
        document.getElementById('live-date').textContent = now.toLocaleDateString('en-US', dateOptions);
    }
    setInterval(updateClock, 1000);
    updateClock();

    const passwordVerified = <?php echo $passwordVerified ? 'true' : 'false'; ?>;

    function setSecurityFormState(verified) {
        const disabled = !verified;
        document.getElementById('currentPassInput').disabled = disabled;
        document.getElementById('newPassInput').disabled = disabled;
        document.getElementById('confirmPassInput').disabled = disabled;
        document.getElementById('updatePasswordBtn').disabled = disabled;

        const verifiedBadge = document.getElementById('verifiedBadge');
        if (verified) {
            verifiedBadge.classList.remove('d-none');
            verifiedBadge.classList.add('d-inline-flex');
        } else {
            verifiedBadge.classList.add('d-none');
        }
    }

    function showOTPSection() {
        document.getElementById('securityOtpSection').classList.remove('d-none');
        document.getElementById('securityOtpMessage').textContent = 'A verification code has been sent to your email. Please enter it below.';
    }

    async function postSecurityAction(action, payload = {}) {
        const body = new URLSearchParams({ action, ...payload });
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        return response.json();
    }

    document.getElementById('sendSecurityOTP').addEventListener('click', async () => {
        const result = await postSecurityAction('send_security_otp');
        if (result.success) {
            showOTPSection();
            document.getElementById('securityOtpMessage').classList.remove('text-danger');
            document.getElementById('securityOtpMessage').classList.add('text-muted');
            document.getElementById('securityOtpMessage').textContent = result.message;
        } else {
            document.getElementById('securityOtpMessage').classList.add('text-danger');
            document.getElementById('securityOtpMessage').textContent = result.message;
            document.getElementById('securityOtpSection').classList.remove('d-none');
        }
    });

    document.getElementById('verifySecurityOTP').addEventListener('click', async () => {
        const code = document.getElementById('security_otp').value.trim();
        const result = await postSecurityAction('verify_security_otp', { otp: code });
        document.getElementById('securityOtpMessage').textContent = result.message;
        if (result.success) {
            document.getElementById('securityOtpMessage').classList.remove('text-danger');
            document.getElementById('securityOtpMessage').classList.add('text-success');
            setSecurityFormState(true);
        } else {
            document.getElementById('securityOtpMessage').classList.add('text-danger');
            document.getElementById('securityOtpMessage').classList.remove('text-success');
        }
    });

    function calculateStrength(password) {
        let score = 0;
        if (password.length >= 8) score += 1;
        if (/[A-Z]/.test(password)) score += 1;
        if (/[a-z]/.test(password)) score += 1;
        if (/[0-9]/.test(password)) score += 1;
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        return score;
    }

    function getPasswordRequirements(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            digit: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
        return requirements;
    }

    function buildPasswordHint(password, score) {
        if (!password) {
            return 'Use at least 8 characters with uppercase, number, and symbol.';
        }

        const reqs = getPasswordRequirements(password);
        const missing = [];

        if (!reqs.length) missing.push('at least 8 characters');
        if (!reqs.uppercase) missing.push('uppercase letter');
        if (!reqs.lowercase) missing.push('lowercase letter');
        if (!reqs.digit) missing.push('number');
        if (!reqs.special) missing.push('special character');

        if (missing.length === 0) {
            return 'Great! Your password is strong and secure.';
        } else if (score <= 2 || password.length < 8) {
            return 'Add: ' + missing.join(', ') + '.';
        } else {
            return 'Add: ' + missing.join(', ') + ' to reach Strong.';
        }
    }

    function updatePasswordStrength() {
    const password = document.getElementById('newPassInput').value;
    const label = document.getElementById('passwordStrengthLabel');
    const bar = document.getElementById('passwordStrengthBar');
    const hint = document.getElementById('passwordStrengthHint');
    const weakInd = document.getElementById('weakIndicator');
    const mediumInd = document.getElementById('mediumIndicator');
    const strongInd = document.getElementById('strongIndicator');

    // 1. 初始化：重置所有指示灯为灰色
    weakInd.style.color = '#ccc';
    mediumInd.style.color = '#ccc';
    strongInd.style.color = '#ccc';
    weakInd.style.textShadow = 'none';
    mediumInd.style.textShadow = 'none';
    strongInd.style.textShadow = 'none';

    // 2. 空密码状态
    if (!password) {
        label.textContent = 'Enter password';
        bar.style.width = '0%';
        bar.className = 'progress-bar bg-danger rounded-pill';
        hint.textContent = 'Use at least 8 characters with uppercase, number, and symbol.';
        return;
    }

    // 3. 计算复杂度分数 (满分 5 分)
    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    // 4. 定义变量，准备赋值
    let strengthLabel, width, barClass, hintText, currentLevel;

    // 5. 重新划分的平滑阶梯逻辑
    if (score <= 2) {
        // 分数极低 (0-2分)：绝对的 Weak
        strengthLabel = 'Weak';
        width = 33;
        barClass = 'progress-bar bg-danger rounded-pill';
        hintText = 'Weak password — add uppercase, numbers, symbols.';
        currentLevel = 'weak';
    } 
    else if (score === 3 || score === 4) {
        // 中等复杂度 (3-4分)：完美的 Medium 阶梯，进度条停在 66%
        strengthLabel = 'Medium';
        width = 66;
        barClass = 'progress-bar bg-warning rounded-pill';
        hintText = 'Medium password — add one more security element to reach Strong.';
        currentLevel = 'medium';
    } 
    else if (score === 5) {
        // 满分 (5分)：终极 Strong，进度条冲满 100%
        strengthLabel = 'Strong';
        width = 100;
        barClass = 'progress-bar bg-success rounded-pill';
        hintText = 'Strong password — excellent security level.';
        currentLevel = 'strong';
    }

    // 6. 亮灯控制逻辑 (根据上面计算出的等级，精准亮起某一个灯)
    if (currentLevel === 'weak') {
        weakInd.style.color = 'red';
        weakInd.style.textShadow = '0 0 8px rgba(239,68,68,0.5)';
    } else if (currentLevel === 'medium') {
        mediumInd.style.color = 'orange';
        mediumInd.style.textShadow = '0 0 10px rgba(245,158,11,0.5)';
    } else if (currentLevel === 'strong') {
        strongInd.style.color = 'green';
        strongInd.style.textShadow = '0 0 10px rgba(16,185,129,0.5)';
    }

    // 7. 将计算结果渲染到前端页面
    label.textContent = strengthLabel;
    bar.style.width = width + '%';
    bar.className = barClass;
    hint.textContent = hintText;
}

// 绑定事件
document.getElementById('newPassInput').addEventListener('input', updatePasswordStrength);
updatePasswordStrength();

    setSecurityFormState(passwordVerified);

    function setupWalletPIN() {
        Swal.fire({
            title: 'Activate Your E-Wallet',
            text: 'Please set a 6-digit secure PIN to protect your balance.',
            input: 'password',
            inputAttributes: { maxlength: 6, autocapitalize: 'off', autocorrect: 'off', pattern: '[0-9]*', inputmode: 'numeric' },
            showCancelButton: true,
            confirmButtonText: 'Set PIN',
            confirmButtonColor: '#FF6B00',
            inputValidator: (value) => {
                if (!/^\d{6}$/.test(value)) { return 'PIN must be exactly 6 digits!'; }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../Module B/update_pin_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `new_pin=${result.value}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) { Swal.fire('Activated!', 'Your wallet is now ready.', 'success').then(() => location.reload()); }
                });
            }
        });
    }

    async function forgotWalletPIN() {
        Swal.fire({
            title: 'Reset Wallet PIN',
            text: "We will send an OTP to your registered email.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Send OTP',
            confirmButtonColor: '#FF6B00',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('../Module B/wallet_pin_reset_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=request_otp'
                }).then(res => res.json());
            }
        }).then((result) => {
            if (result.isConfirmed && result.value.success) {
                handleOTPInput();
            }
        });
    }

    function handleOTPInput() {
        Swal.fire({
            title: 'Verify OTP',
            html: `
                <input type="text" id="otp_code" class="swal2-input" placeholder="6-digit OTP" maxlength="6">
                <input type="password" id="reset_pin" class="swal2-input" placeholder="Enter New 6-digit PIN" maxlength="6">
            `,
            confirmButtonText: 'Reset PIN',
            confirmButtonColor: '#17735b',
            preConfirm: () => {
                const otp = document.getElementById('otp_code').value;
                const pin = document.getElementById('reset_pin').value;
                if (!/^\d{6}$/.test(otp)) return Swal.showValidationMessage('Invalid OTP format');
                if (!/^\d{6}$/.test(pin)) return Swal.showValidationMessage('PIN must be 6 digits');
                
                let formData = new URLSearchParams();
                formData.append('action', 'verify_and_reset');
                formData.append('otp', otp);
                formData.append('new_pin', pin);

                return fetch('../Module B/wallet_pin_reset_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: formData.toString()
                }).then(res => res.json());
            }
        }).then((result) => {
            if (result.value && result.value.success) {
                Swal.fire('Success!', 'Your Wallet PIN has been updated.', 'success').then(() => location.reload());
            } else if (result.value) {
                Swal.fire('Failed', result.value.message, 'error');
            }
        });
    }

    function toggleOrderIdColumn() {
        const checkbox = document.getElementById('showOrderId');
        const columns = document.querySelectorAll('.order-id-column');
        const display = checkbox.checked ? 'table-cell' : 'none';
        columns.forEach(col => {
            col.style.display = display;
        });
    }
</script>

<?php include '../includes/footer.php'; ?>