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

function normalizeOrderStatus(string $status): string {
    $status = trim($status);
    if (strcasecmp($status, 'Shipping') === 0) {
        return 'Shipped';
    }
    if (strcasecmp($status, 'Complete') === 0) {
        return 'Delivered';
    }
    if (strcasecmp($status, 'Pending') === 0) {
        return 'Pending';
    }
    if (strcasecmp($status, 'Processing') === 0) {
        return 'Processing';
    }
    if (strcasecmp($status, 'Delivered') === 0) {
        return 'Delivered';
    }
    if (strcasecmp($status, 'Shipped') === 0) {
        return 'Shipped';
    }
    return ucfirst(strtolower($status));
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// ===================================================
// 核心追加：自给自足的安全明细 API 路由 (防越权与高内聚)
// ===================================================
if (isset($_GET['fetch_items_api']) && isset($_GET['order_id'])) {
    $order_id = mysqli_real_escape_string($conn, $_GET['order_id']);

    // 严格对应你的 order_detail、product 与 order 表结构和字段（首字母大写）
    $query = "SELECT od.*, p.Pro_Name, p.Pro_Image 
              FROM order_detail od
              LEFT JOIN product p ON od.Pro_Id = p.Pro_Id
              LEFT JOIN `order` o ON od.Order_Id = o.Order_Id
              WHERE od.Order_Id = '$order_id' AND o.User_Id = '$user_id'";
              
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($item = $result->fetch_assoc()) {
            // 读取真实的数据库字段
            $prod_name = htmlspecialchars($item['Pro_Name'] ?? 'Unknown Product');
            $quantity = intval($item['Order_Qty'] ?? 1);
            $subtotal = floatval($item['Order_Subtotal'] ?? 0);
            $unit_price = $quantity > 0 ? ($subtotal / $quantity) : 0;
            
            // 动态匹配产品图片路径
            $prod_image = "../images/brands/placeholder.png";
            if (!empty($item['Pro_Image'])) {
                $path_parts = pathinfo($item['Pro_Image']);
                $filename = $path_parts['filename'];
                $found_images = glob("../uploads/{$filename}*.*");
                if (!empty($found_images)) { 
                    $prod_image = $found_images[0]; 
                }
            }
            ?>
            <div class="d-flex align-items-center gap-3 p-3 mb-2 border rounded-4 bg-white text-start shadow-sm" style="border-radius: 12px;">
                <img src="<?php echo $prod_image; ?>" alt="Product" class="rounded-3" style="width: 65px; height: 65px; object-fit: cover;">
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1 text-dark" style="font-size: 14px;"><?php echo $prod_name; ?></h6>
                    <small class="text-muted d-block" style="font-size: 12px;">Price: RM <?php echo number_format($unit_price, 2); ?> x <?php echo $quantity; ?></small>
                </div>
                <div class="text-end">
                    <span class="small fw-bold text-muted d-block" style="font-size: 10px; text-transform: uppercase;">Subtotal</span>
                    <h6 class="fw-bold mb-0" style="color: #FF6B00; font-size: 14px;">RM <?php echo number_format($subtotal, 2); ?></h6>
                </div>
            </div>
            <?php
        }
    } else {
        echo "<div class='text-center py-3 text-muted'>No items found inside this order.</div>";
    }
    exit; // 必须截止，防止将整个仪表盘的 HTML 渲染进弹窗
}

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
:root { 
    --brand-orange: #FF6B00; 
}

/* ==========================================
   1. Cloudy Design 全局画布与云雾气泡背景
   ========================================== */
body { 
    background-color: #f1f5f9 !important; /* 换成稍有呼吸感的底色，衬托毛玻璃 */
    font-family: 'Segoe UI', Arial, sans-serif; 
    position: relative;
    min-height: 100vh;
    overflow-x: hidden;
}

/* 动态流云气泡 */
body::before, body::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    filter: blur(130px);
    opacity: 0.38;
    z-index: 0;
    pointer-events: none;
    animation: cloudFloat 12s infinite alternate ease-in-out;
}
/* 气泡 1：呼应你的品牌橙 */
body::before {
    width: 450px;
    height: 450px;
    background: var(--brand-orange);
    top: 5%;
    left: -8%;
}
/* 气泡 2：现代科技蓝（撞色让磨砂更有高级感） */
body::after {
    width: 500px;
    height: 500px;
    background: #3b82f6;
    bottom: 12%;
    right: -8%;
    animation-delay: 2s;
}

@keyframes cloudFloat {
    0% { transform: translate(0px, 0px) scale(1); }
    100% { transform: translate(40px, -30px) scale(1.08); }
}

/* 确保页面所有核心内容都在云雾背景的上方 */
.container {
    position: relative;
    z-index: 10;
}

/* ==========================================
   2. 核心卡片升级为“磨砂玻璃”质感
   ========================================== */
.card { 
    border: 1px solid rgba(255, 255, 255, 0.7) !important; 
    border-radius: 20px; 
    background: rgba(255, 255, 255, 0.68) !important; /* 半透明白 */
    backdrop-filter: blur(20px) saturate(160%); /* 磨砂玻璃核心属性 */
    -webkit-backdrop-filter: blur(20px) saturate(160%); 
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.04) !important; /* 更柔和的高级阴影 */
}

/* ==========================================
   3. 你原本的其他样式（完美保留）
   ========================================== */
.card, .table, .form-control, .form-select, .nav-tabs .nav-link { font-family: 'Segoe UI', Arial, sans-serif; }
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
.nav-tabs { border-bottom: 1px solid rgba(0,0,0,0.05); }
.nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 700; padding: 10px 20px; }
.nav-tabs .nav-link.active { color: var(--brand-orange); border-bottom: 3px solid var(--brand-orange); background: none; }
.voucher-box { padding: 15px; border-radius: 15px; height: 100%; transition: 0.3s; }
.voucher-active { border: 2px dashed var(--brand-orange); background: rgba(255, 255, 255, 0.5); }
.voucher-claimed { border: 2px dashed #ced4da; background: rgba(248, 249, 250, 0.5); }
.voucher-title { font-weight: 800; color: #333; margin-bottom: 2px; }
.fw-800 { font-weight: 800; }
.text-orange { color: var(--brand-orange); }

/* 让输入框在轻量磨砂背景下更显眼 */
.form-control, .form-select {
    background-color: rgba(255, 255, 255, 0.6) !important;
    border: 1px solid rgba(0,0,0,0.05) !important;
}
.form-control:focus, .form-select:focus {
    background-color: #fff !important;
    border-color: var(--brand-orange) !important;
    box-shadow: 0 0 0 0.25rem rgba(255, 107, 0, 0.15);
}
</style>

<div class="container py-5">
    <div class="mb-5 border-bottom pb-3 d-flex justify-content-between align-items-end">
        <div>
            <h1 class="fw-800">STRYDEX <span class="text-orange">Store.</span></h1>
            <p class="text-muted">Personal locker for performance and SS footwear.</p>
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
                <div class="mb-3"><span class="badge rounded-pill bg-light text-orange border">SS MEMBER</span></div>
                
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

                        // 精确匹配你的数据库大写字段（Order_Id, User_Id, Order_Date, Order_Status, Order_Amount）
                        $query_str = "SELECT o.*, p.Pro_Image, p.Pro_Name 
                                      FROM `order` o 
                                      LEFT JOIN order_detail od ON o.Order_Id = od.Order_Id 
                                      LEFT JOIN product p ON od.Pro_Id = p.Pro_Id 
                                      WHERE o.User_Id = '$user_id' AND DATE(o.Order_Date) <= '$today'";

                        if ($f_id != '') {
                            $clean_id = str_replace('ORD#', '', $f_id);
                            $query_str .= " AND o.Order_Id LIKE '%$clean_id%'";
                        }
                        if ($f_date != '') {
                            if ($f_date > $today) { $f_date = $today; }
                            $query_str .= " AND DATE(o.Order_Date) = '$f_date'";
                        }
                        if ($f_status != '' && $f_status != 'All Status') {
                            if ($f_status == 'Shipped') {
                                $query_str .= " AND o.Order_Status IN ('Shipping','Shipped')";
                            } elseif ($f_status == 'Delivered') {
                                $query_str .= " AND o.Order_Status IN ('Complete','Delivered')";
                            } else {
                                $query_str .= " AND o.Order_Status = '$f_status'";
                            }
                        }

                        $query_str .= " GROUP BY o.Order_Id ORDER BY o.Order_Id DESC";
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
                                        <option value="Pending" <?php if($f_status == 'Pending') echo 'selected'; ?>>Pending</option>
                                        <option value="Processing" <?php if($f_status == 'Processing') echo 'selected'; ?>>Processing</option>
                                        <option value="Shipped" <?php if($f_status == 'Shipped') echo 'selected'; ?>>Shipped</option>
                                        <option value="Delivered" <?php if($f_status == 'Delivered') echo 'selected'; ?>>Delivered</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-dark btn-sm w-100 rounded-3 shadow-sm py-2 fw-bold text-uppercase" style="background: #FF6B00; border: none;">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive bg-white rounded-4 shadow-sm p-3">
                            <table class="table align-middle table-hover mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th class="border-0 pb-3 order-id-column" style="display: none;">ORDER ID</th>
                                        <th class="border-0 pb-3">TRANS. DATE</th>
                                        <th class="border-0 pb-3">TRANS. TIME</th>
                                        <th class="border-0 pb-3">ORDER AMOUNT</th>
                                        <th class="border-0 pb-3">STATUS</th>
                                        <th class="border-0 pb-3 text-end">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold">
                                    <?php if($purchased_data && $purchased_data->num_rows > 0): ?>
                                        <?php while($row = $purchased_data->fetch_assoc()): 
                                            $status = normalizeOrderStatus($row['Order_Status'] ?? 'Pending');
                                            $badge_color = 'bg-secondary-subtle text-secondary';
                                            if ($status == 'Processing') {
                                                $badge_color = "bg-warning-subtle text-warning";
                                            } elseif ($status == 'Shipped') {
                                                $badge_color = "bg-info-subtle text-info";
                                            } elseif ($status == 'Delivered') {
                                                $badge_color = "bg-success-subtle text-success";
                                            }
                                            $product_image = "../images/brands/placeholder.png"; 
                                            
                                            if (!empty($row['Pro_Image'])) {
                                                $path_parts = pathinfo($row['Pro_Image']);
                                                $filename = $path_parts['filename'];
                                                
                                                // 自动在文件夹里模糊寻找图片名称
                                                $found_images = glob("../uploads/{$filename}*.*");
                                                if (!empty($found_images)) {
                                                    $product_image = $found_images[0];
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td class="py-3 order-id-column" style="display: none;">
                                                    <span class="text-dark">ORD#<?php echo sprintf("%06d", $row['Order_Id']); ?></span>
                                                </td>
                                                <td><?php echo date("Y-m-d", strtotime($row['Order_Date'])); ?></td>
                                                <td class="trans-time" data-order-time="<?php echo htmlspecialchars($row['Order_Date']); ?>">--</td>
                                                <td>RM <?php echo number_format($row['Order_Amount'] ?? 0, 2); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?php echo $badge_color; ?> px-3 py-2">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="order_view.php?order_id=<?php echo (int)$row['Order_Id']; ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">View Details</a>
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
                <div class="col-12 text-center py-3 text-muted">No available promo vouchers at the moment.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // 隐藏/显示 Order ID 列的核心逻辑
    function toggleOrderIdColumn() {
        const checkbox = document.getElementById('showOrderId');
        const columns = document.querySelectorAll('.order-id-column');
        const display = checkbox.checked ? 'table-cell' : 'none';
        columns.forEach(col => {
            col.style.display = display;
        });
    }

    // 点击 Details 按钮加载异步订单明细弹窗
    function showItemPopup(orderId) {
        let paddedOrderId = String(orderId).padStart(6, '0');
        
        Swal.fire({
            title: 'Order Items (ID: #ORD-' + paddedOrderId + ')',
            customClass: { title: 'text-start w-100 fs-5 mt-2 ms-2 fw-bold text-dark' },
            html: '<div id="popup-loading" class="py-4"><div class="spinner-border text-warning"></div><p class="text-muted small mt-2">Loading items...</p></div>',
            showConfirmButton: false,
            showCloseButton: true,
            width: '600px',
            focusCancel: true,
            background: '#f8f9fa',
            didOpen: () => {
                // 直接向自身页面发起 AJAX API 安全请求，完美防跨模块越权
                const url = 'user_dashboard.php?fetch_items_api=1&order_id=' + orderId;
                
                fetch(url)
                    .then(response => response.text())
                    .then(htmlData => {
                        Swal.update({ html: htmlData });
                    })
                    .catch(() => {
                        Swal.update({ html: '<div class="py-4 text-danger text-center">Failed to load items.</div>' });
                    });
            }
        });
    }

    // 动态时间显示
    function updateClock() {
        const clockEl = document.getElementById('live-clock');
        const dateEl = document.getElementById('live-date');
        if(!clockEl || !dateEl) return;
        
        const now = new Date();
        clockEl.innerText = now.toLocaleTimeString('en-US', { hour12: true });
        dateEl.innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    function updateTransactionTimes() {
        document.querySelectorAll('.trans-time').forEach(el => {
            const orderTime = el.getAttribute('data-order-time');
            if (!orderTime) return;

            const normalizedOrderTime = orderTime.trim().replace(' ', 'T');
            const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalizedOrderTime);
            const date = new Date(hasTimezone ? normalizedOrderTime : `${normalizedOrderTime}Z`);

            if (!Number.isNaN(date.getTime())) {
                el.textContent = date.toLocaleTimeString('en-US', {
                    timeZone: 'Asia/Kuala_Lumpur',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                return;
            }

            const match = orderTime.match(/(\d{1,2}):(\d{2})(?::\d{2})?(?:\s*(AM|PM))?/i);
            if (!match) return;

            let hour = parseInt(match[1], 10);
            const minute = match[2];
            const suffix = match[3];

            if (suffix) {
                const normalizedSuffix = suffix.toUpperCase();
                if (normalizedSuffix === 'PM' && hour < 12) hour += 12;
                if (normalizedSuffix === 'AM' && hour === 12) hour = 0;
            }

            const period = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            el.textContent = `${displayHour}:${minute} ${period}`;
        });
    }

    setInterval(updateClock, 1000);
    setInterval(updateTransactionTimes, 1000);
    updateClock();
    updateTransactionTimes();

    // 密码强度的前端实时验证交互
    const newPassInput = document.getElementById('newPassInput');
    if (newPassInput) {
        newPassInput.addEventListener('input', function() {
            const val = this.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const bar = document.getElementById('passwordStrengthBar');
            const label = document.getElementById('passwordStrengthLabel');
            
            document.getElementById('weakIndicator').style.color = '#ccc';
            document.getElementById('mediumIndicator').style.color = '#ccc';
            document.getElementById('strongIndicator').style.color = '#ccc';

            if (val.length === 0) {
                bar.style.width = '0%';
                label.innerText = 'Enter password';
            } else if (score <= 1) {
                bar.className = 'progress-bar bg-danger rounded-pill';
                bar.style.width = '33%';
                label.innerText = 'Weak';
                document.getElementById('weakIndicator').style.color = '#dc3545';
            } else if (score <= 3) {
                bar.className = 'progress-bar bg-warning rounded-pill';
                bar.style.width = '66%';
                label.innerText = 'Medium';
                document.getElementById('mediumIndicator').style.color = '#ffc107';
            } else {
                bar.className = 'progress-bar bg-success rounded-pill';
                bar.style.width = '100%';
                label.innerText = 'Strong';
                document.getElementById('strongIndicator').style.color = '#198754';
            }
        });
    }

    // OTP 身份认证逻辑前端 AJAX 的处理绑定
    document.addEventListener('DOMContentLoaded', function() {
        const sendOtpBtn = document.getElementById('sendSecurityOTP');
        const verifyOtpBtn = document.getElementById('verifySecurityOTP');
        
        if (sendOtpBtn) {
            sendOtpBtn.addEventListener('click', function() {
                sendOtpBtn.disabled = true;
                sendOtpBtn.innerText = 'Sending...';
                
                const formData = new FormData();
                formData.append('action', 'send_security_otp');

                fetch('user_dashboard.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('securityOtpSection').classList.remove('d-none');
                        document.getElementById('securityOtpMessage').innerText = data.message;
                        document.getElementById('securityOtpMessage').className = 'mt-2 small text-success';
                    } else {
                        alert(data.message);
                    }
                })
                .finally(() => {
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.innerText = 'Send OTP';
                });
            });
        }

        if (verifyOtpBtn) {
            verifyOtpBtn.addEventListener('click', function() {
                const otpVal = document.getElementById('security_otp').value;
                const formData = new FormData();
                formData.append('action', 'verify_security_otp');
                formData.append('otp', otpVal);

                fetch('user_dashboard.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('securityOtpSection').classList.add('d-none');
                        sendOtpBtn.classList.add('d-none');
                        document.getElementById('verifiedBadge').classList.remove('d-none');
                        
                        // 移除禁用，允许修改密码
                        document.getElementById('currentPassInput').removeAttribute('disabled');
                        document.getElementById('newPassInput').removeAttribute('disabled');
                        document.getElementById('confirmPassInput').removeAttribute('disabled');
                        document.getElementById('updatePasswordBtn').removeAttribute('disabled');
                    } else {
                        document.getElementById('securityOtpMessage').innerText = data.message;
                        document.getElementById('securityOtpMessage').className = 'mt-2 small text-danger';
                    }
                });
            });
        }
    });

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
