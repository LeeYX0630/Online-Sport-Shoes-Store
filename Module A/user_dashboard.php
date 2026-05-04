<?php
// Module A/user_dashboard.php
date_default_timezone_set("Asia/Kuala_Lumpur");

session_start();
require_once '../includes/db_connection.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// ===============================
// HANDLE PROFILE UPDATE
// ===============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        // Check duplicate email to prevent "Duplicate Entry" crash
        $check_email = $conn->query("SELECT User_Id FROM `user` WHERE User_Email='$new_email' AND User_Id != '$user_id'");
        
        if ($check_email && $check_email->num_rows > 0) {
            $msg = "Email already used by another account!";
            $msg_type = "danger";
        } else {
            // Update user details
            $conn->query("UPDATE `user` SET User_Name='$new_name', User_Phone='$clean_phone', User_Email='$new_email' WHERE User_Id='$user_id'");
            $_SESSION['user_name'] = $new_name;

            // ==========================================
            // HANDLE PROFILE IMAGE UPLOAD
            // ==========================================
            if (!empty($_FILES['profile_image']['name'])) {
                // Point directly to the parent folder's uploads directory
                $upload_dir = __DIR__ . "/../uploads/";

                // Create the folder if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Append timestamp to prevent duplicate file names
                $filename = time() . "_" . basename($_FILES['profile_image']['name']);
                $target = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                    $conn->query("UPDATE `user` SET User_Image='$filename' WHERE User_Id='$user_id'");
                } else {
                    $msg = "Error: Failed to move uploaded file. Check folder permissions.";
                    $msg_type = "danger";
                }
            }
            
            $msg = "Profile updated successfully!";
            $msg_type = "success";
        }
    }
}

// ===============================
// FETCH DATA
// ===============================
$user_res = $conn->query("SELECT * FROM `user` WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();

// Use backticks for `order` table as it is a reserved SQL keyword
$purchases = $conn->query("SELECT * FROM `order` WHERE User_Id = '$user_id' ORDER BY Order_Id DESC");

// Load active promo codes from database
$available_promos = $conn->query("SELECT * FROM promo WHERE Promo_Status = 'Active' AND Expired_Date >= CURDATE() ORDER BY Promo_Id DESC");

// FIXED: Added "../" so HTML looks in the project root's uploads folder
$profile_pic = !empty($user['User_Image']) ? "../uploads/".$user['User_Image'] : "../uploads/default.png";

include '../includes/header.php';
?>

<style>
:root { --brand-orange: #FF6B00; }
body { background-color: #F8F9FA; font-family: 'Plus Jakarta Sans', sans-serif; }

/* Dashboard Cards */
.card { border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #fff; }

/* Profile Section */
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

/* Tabs Styling */
.nav-tabs { border-bottom: 1px solid #eee; }
.nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 700; padding: 10px 20px; }
.nav-tabs .nav-link.active { color: var(--brand-orange); border-bottom: 3px solid var(--brand-orange); background: none; }

/* Voucher Styling */
.voucher-box { padding: 15px; border-radius: 15px; height: 100%; transition: 0.3s; }
.voucher-active { border: 2px dashed var(--brand-orange); background: #fff; }
.voucher-claimed { border: 2px dashed #ced4da; background: #f8f9fa; }
.voucher-title { font-weight: 800; color: #333; margin-bottom: 2px; }

/* Utility */
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
                        <!-- 添加此重置入口 -->
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
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr class="text-muted">
                                        <th class="border-0">ID</th>
                                        <th class="border-0">Date</th>
                                        <th class="border-0">Total</th>
                                        <th class="border-0">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($purchases && $purchases->num_rows > 0): ?>
                                        <?php while($row = $purchases->fetch_assoc()): ?>
                                            <tr>
                                                <td class="fw-bold">#<?php echo $row['Order_Id']; ?></td>
                                                <td><?php echo date("d M Y", strtotime($row['Order_Date'])); ?></td>
                                                <td class="fw-bold text-orange">RM <?php echo number_format($row['Total_Amount'] ?? 0, 2); ?></td>
                                                <td><span class="badge rounded-pill bg-light text-success border">Completed</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No purchases found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
                                <div class="voucher-title text-muted">No Active Promo</div>
                                <p class="small text-muted mb-0">目前没有有效优惠券，请稍后再来查看。</p>
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
            // 发送 AJAX 到后端保存 PIN 码
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
    // 步骤 1: 发送 OTP
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
            // 步骤 2: 输入 OTP 和 新 PIN
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
</script>

<?php include '../includes/footer.php'; ?>