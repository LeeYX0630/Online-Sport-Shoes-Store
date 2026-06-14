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

// 💡 升级版：增加了 Postcode 和 State 字段，完美对接前端的输入框
$conn->query("
    CREATE TABLE IF NOT EXISTS user_address (
        Address_Id INT AUTO_INCREMENT PRIMARY KEY,
        User_Id INT NOT NULL,
        Address_Text TEXT NOT NULL,
        Postcode VARCHAR(10) NOT NULL,
        State VARCHAR(50) NOT NULL,
        Is_Default TINYINT(1) NOT NULL DEFAULT 0,
        Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_address_user (User_Id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$postcode_col = $conn->query("SHOW COLUMNS FROM user_address LIKE 'Postcode'");
if ($postcode_col && $postcode_col->num_rows === 0) {
    $conn->query("ALTER TABLE user_address ADD COLUMN Postcode VARCHAR(20) NOT NULL DEFAULT '' AFTER Address_Text");
}
$state_col = $conn->query("SHOW COLUMNS FROM user_address LIKE 'State'");
if ($state_col && $state_col->num_rows === 0) {
    $conn->query("ALTER TABLE user_address ADD COLUMN `State` VARCHAR(100) NOT NULL DEFAULT '' AFTER Postcode");
}

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

// Customer review the comments
// 查询所有买家留下的公共评价（带用户名）
$all_reviews_query = "
    SELECT r.*, u.`User_Name` 
    FROM `review_and_rating` r 
    JOIN `user` u ON r.`User_Id` = u.`User_Id` 
    ORDER BY r.`RR_Date` DESC
";
$all_reviews_result = $conn->query($all_reviews_query);

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
// ===================================================
    // ✨ 新增分支：处理来自 Reviews Feed 的讨论问答回复
    // ===================================================
    if (isset($_POST['action']) && $_POST['action'] === 'submit_review_reply') {
        $rr_id = isset($_POST['RR_Id']) ? intval($_POST['RR_Id']) : 0;
        $reply_content = trim($_POST['reply_content'] ?? '');
        
        // 过滤安全字符防止 SQL 注入与 XSS
        $reply_content = mysqli_real_escape_string($conn, htmlspecialchars($reply_content));
        
        // 严格拉取你当前登录会话中的 user_id 和 user_name
        $reply_user_id = intval($user_id);
        $reply_username = $_SESSION['user_name'] ?? 'Guest Buyer';
        
        // 智能判断角色身份 (如果你们系统以后有区分权限等级，可以在这里读取)
        $role = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin' : 'customer';

        if ($rr_id > 0 && !empty($reply_content)) {
            // 往我们刚刚创建的回复关系表中插入数据
            $reply_sql = "INSERT INTO `review_replies` (`RR_Id`, `User_Id`, `User_Name`, `Role`, `Reply_Content`) 
                          VALUES ($rr_id, $reply_user_id, '$reply_username', '$role', '$reply_content')";
            
            if ($conn->query($reply_sql)) {
                // 成功后，重定向刷新当前页面，回到 Reviews Feed 选项卡
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=Reply posted successfully&type=success");
                exit;
            } else {
                $msg = "Database Error: Unable to post your reply.";
                $msg_type = "danger";
            }
        } else {
            $msg = "Reply content cannot be empty!";
            $msg_type = "danger";
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
                $addresses = [];
                $postcodes = [];
                $states = [];
                $address_error = "";
                if (isset($_POST['addresses']) && is_array($_POST['addresses'])) {
                    foreach ($_POST['addresses'] as $idx => $address) {
                        $clean_address = trim(preg_replace('/\s+/', ' ', $address));
                        if ($clean_address !== '') {
                            $pc = isset($_POST['postcodes'][$idx]) ? trim($_POST['postcodes'][$idx]) : '';
                            $pc = preg_replace('/[^0-9]/', '', $pc);
                            $st = isset($_POST['states'][$idx]) ? trim($_POST['states'][$idx]) : '';

                            if (!preg_match('/^[0-9]{5}$/', $pc)) {
                                $address_error = "Every shipping address needs a 5-digit postcode.";
                            } elseif ($st === '') {
                                $address_error = "Please select a state for every shipping address.";
                            }

                            $addresses[] = substr($clean_address, 0, 500);
                            $postcodes[] = substr($pc, 0, 20);
                            $states[] = substr($st, 0, 100);
                        }
                    }
                }

                if (empty($addresses)) {
                    $msg = "Please add at least one shipping address.";
                    $msg_type = "danger";
                } elseif ($address_error !== "") {
                    $msg = $address_error;
                    $msg_type = "danger";
                } else {
                $default_index = isset($_POST['default_address_index']) ? (int)$_POST['default_address_index'] : 0;
                if ($default_index < 0 || $default_index >= count($addresses)) {
                    $default_index = 0;
                }

                $default_address = $addresses[$default_index] ?? '';

                $default_postcode = $postcodes[$default_index] ?? '';
                $default_state = $states[$default_index] ?? '';

                // Update user details
                $stmt = $conn->prepare("UPDATE `user` SET User_Name=?, User_Phone=?, User_Email=?, User_Address=?, User_Postcode=?, User_State=? WHERE User_Id=?");
                $stmt->bind_param("ssssssi", $new_name, $clean_phone, $new_email, $default_address, $default_postcode, $default_state, $user_id);
                $stmt->execute();
                $_SESSION['user_name'] = $new_name;

                $delete_stmt = $conn->prepare("DELETE FROM user_address WHERE User_Id=?");
                $delete_stmt->bind_param("i", $user_id);
                $delete_stmt->execute();

                if (!empty($addresses)) {
                    $insert_stmt = $conn->prepare("INSERT INTO user_address (User_Id, Address_Text, Postcode, `State`, Is_Default) VALUES (?, ?, ?, ?, ?)");
                    foreach ($addresses as $index => $address) {
                        $is_default = ($index === $default_index) ? 1 : 0;
                        $postcode = $postcodes[$index] ?? '';
                        $state = $states[$index] ?? '';
                        $insert_stmt->bind_param("isssi", $user_id, $address, $postcode, $state, $is_default);
                        $insert_stmt->execute();
                    }
                }

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

                // =======================================================
                // ADDED: MULTIPLE ADDRESSES SYNCHRONIZATION (SMART FIXED)
                // =======================================================
                if (isset($_POST['addresses'])) {
                    $submitted_addresses = $_POST['addresses'] ?? [];
                    $submitted_postcodes = $_POST['postcodes'] ?? [];
                    $submitted_states    = $_POST['states'] ?? [];
                    $default_index       = (int)($_POST['default_address_index'] ?? 0);

                    // 1. First, wipe out old address records for this user to fresh sync
                    $delete_stmt = $conn->prepare("DELETE FROM user_address WHERE User_Id = ?");
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();

                    // 2. Loop through each row submitted from the frontend HTML
                    foreach ($submitted_addresses as $i => $raw_addr) {
                        // FIX: 去除首尾空格，同时强力清除可能不小心残留在末尾的逗号，防止数据无限叠加
                        $clean_addr = rtrim(trim($raw_addr), ','); 
                        $clean_post = trim($submitted_postcodes[$i] ?? '');
                        $clean_stat = trim($submitted_states[$i] ?? '');
                        
                        // If all inputs in this row are completely empty, skip it
                        if (empty($clean_addr) && empty($clean_post) && empty($clean_stat)) {
                            continue;
                        }
                        
                        // FIX: 只有在邮编和州属都有值时，才用标准逗号拼接；否则只保留路名，防止拼接出多余的空白逗号
                        if (!empty($clean_post) && !empty($clean_stat)) {
                            $full_address_string = "$clean_addr, $clean_post, $clean_stat";
                        } else {
                            $full_address_string = $clean_addr;
                        }
                        
                        // Determine if this item is selected as Default by user
                        $is_default = ($i === $default_index) ? 1 : 0;
                        
                        // Insert new compiled string row into user_address database table
                        $insert_stmt = $conn->prepare("INSERT INTO user_address (User_Id, Address_Text, Is_Default) VALUES (?, ?, ?)");
                        $insert_stmt->bind_param("isi", $user_id, $full_address_string, $is_default);
                        $insert_stmt->execute();
                    }
                }
                // =======================================================
                
                $msg = "Profile updated successfully!";
                $msg_type = "success";
                }
            }
        }
    }
}
// ===============================
// FETCH DATA (SMART ACCURATE SPLITTING)
// ===============================
$user_res = $conn->query("SELECT * FROM user WHERE User_Id='$user_id'");
$user = $user_res->fetch_assoc();

$address_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_address WHERE User_Id=?");
$address_count_stmt->bind_param("i", $user_id);
$address_count_stmt->execute();
$address_count = $address_count_stmt->get_result()->fetch_assoc();

if ((int)($address_count['total'] ?? 0) === 0 && !empty($user['User_Address'])) {
    $legacy_address = trim($user['User_Address']);
    $is_default = 1;
    $insert_legacy_stmt = $conn->prepare("INSERT INTO user_address (User_Id, Address_Text, Is_Default) VALUES (?, ?, ?)");
    $insert_legacy_stmt->bind_param("isi", $user_id, $legacy_address, $is_default);
    $insert_legacy_stmt->execute();
}

$address_book = [];
$default_address_index = 0;
$address_stmt = $conn->prepare("SELECT Address_Text, Is_Default FROM user_address WHERE User_Id=? ORDER BY Is_Default DESC, Address_Id ASC");
$address_stmt->bind_param("i", $user_id);
$address_stmt->execute();
$address_result = $address_stmt->get_result();

// 标准马来西亚州属列表，用于比对验证
$valid_states = ['johor','kedah','kelantan','melaka','negeri sembillan','pahang','penang','perak','perlis','sabah','sarawak','selangor','terengganu','kuala lumpur','putrajaya','labuan'];

while ($address_row = $address_result->fetch_assoc()) {
    $is_default = (int)$address_row['Is_Default'] === 1;
    if ($is_default) {
        $default_address_index = count($address_book);
    }

    $full_text = trim($address_row['Address_Text']);
    $parts = explode(',', $full_text);
    $parts = array_map('trim', $parts); // 清除每个部分的空格

    $street_text = $full_text;
    $postcode = '';
    $state = '';

    // 智能校验：只有当最后一部分是有效州属，且倒数第二部分是5位数字时，才进行切分
    if (count($parts) >= 3) {
        $possible_state = strtolower(end($parts));
        $possible_postcode = $parts[count($parts) - 2];

        if (in_array($possible_state, $valid_states) && preg_match('/^\d{5}$/', $possible_postcode)) {
            $state = array_pop($parts);
            $postcode = array_pop($parts);
            $street_text = implode(', ', $parts);
        }
    }

    $address_book[] = [
        'full_text'  => $full_text,
        'text'       => $street_text,
        'postcode'   => $postcode,
        'state'      => $state,
        'is_default' => $is_default
    ];
}

if (empty($address_book)) {
    $address_book[] = ['full_text' => '', 'text' => '', 'postcode' => '', 'state' => '', 'is_default' => true];
}
$selected_address_text = $address_book[$default_address_index]['full_text'] ?? '';
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
.address-book-shell { border: 1px solid rgba(255, 107, 0, 0.18); background: rgba(255,255,255,0.72); overflow: hidden; }
.address-summary-card { background: #fff; border-left: 5px solid var(--brand-orange); }
.address-icon-badge { width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; background: #fff3e8; color: var(--brand-orange); flex: 0 0 auto; }
.address-summary-lines { white-space: pre-line; line-height: 1.45; overflow-wrap: anywhere; }
.address-toggle-btn { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; }
.address-row { background: #fff; border: 1px solid rgba(15, 23, 42, 0.09) !important; border-radius: 14px !important; transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
.address-row:hover { border-color: rgba(255, 107, 0, 0.45) !important; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06); transform: translateY(-1px); }
.address-row.selected { border-color: var(--brand-orange) !important; box-shadow: 0 0 0 0.18rem rgba(255, 107, 0, 0.14); }
.address-row.selected .id-addr-badge { background: var(--brand-orange) !important; color: #fff !important; }
.address-row .form-control, .address-row .form-select { background-color: #fff !important; }
.address-row textarea { min-height: 82px; resize: vertical; }
.address-book-actions .btn { min-width: 38px; }
.address-empty-hint { border: 1px dashed rgba(255, 107, 0, 0.35); background: #fff8f2; color: #7a4a1a; }
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
                    <button class="nav-link active" id="identity-tab" data-bs-toggle="tab" data-bs-target="#identity">
                    <i class="bi bi-person-gear me-2"></i>Identity Settings
                    </button>
            </li>
                <li class="nav-item">
                    <button class="nav-link" id="purchased-tab" data-bs-toggle="tab" data-bs-target="#purchased">
                    <i class="bi bi-bag-check-fill me-2"></i>Purchased Products
                    </button>
            </li>
                <li class="nav-item">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security">
                    <i class="bi bi-shield-lock-fill me-2"></i>Security
                    </button>
            </li>
                 <li class="nav-item">
                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#public-reviews" type="button"><i class="bi bi-people-fill me-1"></i> Reviews Feed
                </button>
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
            
           <div class="mb-4 address-book-shell rounded-4 border bg-white shadow-sm overflow-hidden" style="border-color: #eaeaea !important;">
    <div class="address-summary-card p-3 bg-light bg-opacity-25 border-bottom">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-geo-alt text-muted fs-5"></i>
                <div id="selectedAddressPreview" class="fw-semibold text-dark small">
                    <?php echo !empty($selected_address_text) ? htmlspecialchars($selected_address_text) : 'No address selected.'; ?>
                </div>
            </div>
            <span class="badge rounded-pill text-bg-light border text-muted small" id="addressCountBadge" style="font-size: 0.75rem; font-weight: 500;"><?php echo count($address_book); ?> Saved</span>
        </div>
    </div>

    <input type="hidden" name="default_address_index" id="defaultAddressIndex" value="<?php echo htmlspecialchars($default_address_index); ?>">

    <div class="p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.5px; font-size: 0.75rem;">Shipping Addresses</div>
            <button type="button" class="btn btn-sm rounded-pill fw-bold px-3" id="addNewAddressBtn" style="border: 1px solid #ff6700; color: #ff6700; background: transparent; font-size: 0.8rem;">
                <i class="bi bi-plus-lg me-1"></i> Add New
            </button>
        </div>

        <div id="addressBook" class="position-relative">
            <?php foreach ($address_book as $index => $address): ?>
                <?php 
                $is_current_selected = ($index == $default_address_index); 
                $addr_text = $address['text'] ?? '';
                $addr_postcode = $address['postcode'] ?? '';
                $addr_state = $address['state'] ?? '';
                ?>
                
                <div class="address-row py-3 border-bottom <?php echo $is_current_selected ? 'is-selected-active' : ''; ?>" data-index="<?php echo $index; ?>" style="transition: all 0.2s ease;">
                    
                    <div class="address-readonly-text d-flex align-items-center justify-content-between gap-3" style="cursor: pointer;" onclick="handleRowSelect(this, event)">
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <div class="custom-radio-dot rounded-circle border d-flex align-items-center justify-content-center" style="width: 16px; height: 16px; min-width: 16px; border-color: <?php echo $is_current_selected ? '#ff6700' : '#ccc'; ?>;">
                                <div class="dot-inner rounded-circle <?php echo $is_current_selected ? '' : 'd-none'; ?>" style="width: 8px; height: 8px; background-color: #ff6700;"></div>
                            </div>
                            
                            <?php if ($is_current_selected): ?>
                                <span class="badge id-addr-badge px-2 py-1 text-white" style="background-color: #ff6700; font-size: 0.7rem; font-weight: 500; border-radius: 4px;">Default</span>
                            <?php endif; ?>

                            <span class="readonly-combined-string text-dark small <?php echo $is_current_selected ? 'fw-bold' : 'text-muted'; ?>">
                                <?php echo !empty($addr_text) ? htmlspecialchars($addr_text) . ', ' . htmlspecialchars($addr_postcode) . ', ' . htmlspecialchars($addr_state) : '(Empty Address)'; ?>
                            </span>
                        </div>

                        <div class="action-icons-group d-flex gap-2">
                            <span class="text-muted p-1 hover-orange" title="Edit" onclick="editSingleAddress(this, event)" style="cursor: pointer;"><i class="bi bi-pencil"></i></span>
                            <span class="text-muted p-1 hover-danger" title="Delete" onclick="removeAddressBox(this, event)" style="cursor: pointer;"><i class="bi bi-trash3"></i></span>
                        </div>
                    </div>

                    <div class="address-input-fields d-none mt-3 p-3 rounded-3 bg-light bg-opacity-50 border">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">Street Address</label>
                                <textarea name="addresses[]" class="form-control address-text-field bg-white" rows="2" placeholder="House number, building, street name" oninput="updateSelectedAddressPreview()" style="font-size: 0.85rem; border-color: #e0e0e0;"><?php echo htmlspecialchars($addr_text); ?></textarea>
                            </div>
                            <div class="col-md-5">
                                <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">Postcode</label>
                                <input type="text" name="postcodes[]" class="form-control address-postcode-field bg-white" maxlength="5" value="<?php echo htmlspecialchars($addr_postcode); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, ''); updateSelectedAddressPreview()" style="font-size: 0.85rem; border-color: #e0e0e0;">
                            </div>
                            <div class="col-md-7">
                                <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">State</label>
                                <select name="states[]" class="form-select address-state-field bg-white" onchange="updateSelectedAddressPreview()" style="font-size: 0.85rem; border-color: #e0e0e0;">
                                    <option value="">Select State</option>
                                    <?php 
                                    $states_list = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Putrajaya','Labuan'];
                                    foreach ($states_list as $st) {
                                        // FIXED: Retaining the database state configuration correctly
                                        $selected = (trim(strtolower($addr_state)) === trim(strtolower($st))) ? 'selected' : '';
                                        echo "<option value=\"".htmlspecialchars($st)."\" $selected>$st</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-12 text-end mt-2 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-light border px-3 fw-bold rounded-2 text-muted" onclick="discardSingleAddress(this)" style="font-size: 0.8rem;">
                                    Cancel
                                </button>
                                <button type="button" class="btn btn-sm text-white px-3 fw-bold rounded-2" onclick="confirmSingleAddress(this)" style="background-color: #ff6700; font-size: 0.8rem;">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div> <div class="mb-4">
                <label class="small fw-bold text-muted d-block mb-2">Change Avatar</label>
                <div class="d-flex align-items-center gap-3">
                    <label for="avatarInput" class="btn btn-orange px-4 py-2 rounded-3 fw-bold text-white shadow-sm mb-0" style="cursor: pointer;">
                        <i class="bi bi-camera me-2"></i>Upload Photo
                    </label>
                    <input type="file" id="avatarInput" name="profile_image" accept="image/*" class="d-none" onchange="showSelectedFileName(this)">
                    
                    <span id="avatarFileName" class="small text-muted fw-semibold"></span>
                </div>
            </div>

            <div class="mb-4">
                                <label class="small fw-bold text-muted">Change Avatar</label>
                                <input type="file" name="profile_image" class="form-control bg-light border-0">
                            </div>
                            <button type="submit" class="btn btn-orange px-5 py-2">Save Profile Changes</button>
                        </form>
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
                                            
                                            // 提取并转换数据库里的时间
                                            $db_order_date = $row['Order_Date'];
                                            $formatted_date = date("Y-m-d", strtotime($db_order_date));
                                            $formatted_time = date("h:i A", strtotime($db_order_date)); // 转换为类似 03:30 PM 的格式
                                        ?>
                                            <tr>
                                                <td class="py-3 order-id-column" style="display: none;">
                                                    <span class="text-dark">ORD#<?php echo sprintf("%06d", $row['Order_Id']); ?></span>
                                                </td>
                                                <td><?php echo $formatted_date; ?></td>
                                                <td class="trans-time" data-order-time="<?php echo htmlspecialchars($db_order_date); ?>">
                                                    <?php echo $formatted_time; ?>
                                                </td>
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

                    <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        // 自动格式化未成功通过 PHP 转换的时区差异（可选，能让时间完全跟着用户本地手机/电脑系统走）
                        document.querySelectorAll('.trans-time').forEach(function(el) {
                            let rawDateStr = el.getAttribute('data-order-time');
                            if (rawDateStr) {
                                // 替换空格为 'T' 以兼容所有浏览器的 Date 解析
                                let utcDate = new Date(rawDateStr.replace(' ', 'T'));
                                if (!isNaN(utcDate.getTime())) {
                                    let options = { hour: '2-digit', minute: '2-digit', hour12: true };
                                    el.textContent = utcDate.toLocaleTimeString([], options);
                                }
                            }
                        });
                    });

                    // 确保你的 toggleOrderIdColumn 函数依然存在
                    function toggleOrderIdColumn() {
                        let show = document.getElementById('showOrderId').checked;
                        document.querySelectorAll('.order-id-column').forEach(el => {
                            el.style.display = show ? '' : 'none';
                        });
                    }
                    </script>
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

<div class="tab-pane fade" id="public-reviews">
    <div class="community-reviews-card p-4 p-md-5 mb-4" style="background: rgba(255, 255, 255, 0.72); backdrop-filter: blur(25px) saturate(160%); -webkit-backdrop-filter: blur(25px) saturate(160%); border: 1px solid rgba(255, 255, 255, 0.7); box-shadow: 0 24px 50px rgba(15, 23, 42, 0.04); border-radius: 20px;">
        
        <div class="community-reviews-title pb-2 mb-4 border-bottom border-secondary-subtle" style="font-size: 1.25rem; font-weight: 800; color: #0f172a;">
            <i class="bi bi-chat-square-heart-fill me-2" style="color: #ff6600;"></i> Customer Reviews & Testimonials
        </div>
        
        <?php if ($all_reviews_result && $all_reviews_result->num_rows > 0): ?>
            <?php while ($rev = $all_reviews_result->fetch_assoc()): ?>
                <div class="review-item py-3 border-bottom rgba-border" style="border-bottom: 1px solid rgba(15,23,42,0.06) !important;">
                    
                    <div class="review-user-info d-flex justify-content-between align-items-center mb-1" style="font-size: 0.9rem;">
                        <span class="review-user-name fw-bold" style="color: #0f172a;"><?php echo htmlspecialchars($rev['User_Name']); ?></span>
                        <span class="review-date text-muted" style="font-size: 0.8rem;"><?php echo date('d M Y, h:i A', strtotime($rev['RR_Date'])); ?></span>
                    </div>
                    
                    <div class="review-stars-static mb-2" style="color: #ffb700; font-size: 0.9rem;">
                        <?php 
                        $stars_count = intval($rev['Rat_Star']);
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $stars_count) {
                                echo '<i class="bi bi-star-fill"></i> ';
                            } else {
                                echo '<i class="bi bi-star" style="color: #cbd5e1;"></i> ';
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="review-comment-text" style="font-size: 0.95rem; color: #334155; line-height: 1.5;">
                        <?php echo !empty($rev['Rev_Content']) ? nl2br(htmlspecialchars($rev['Rev_Content'])) : '<em class="text-muted" style="font-style: italic;">No verbal comment left.</em>'; ?>
                    </div>

                    <?php if (!empty($rev['Rev_Image'])): ?>
                        <div class="mt-2">
                            <img src="../<?php echo htmlspecialchars($rev['Rev_Image']); ?>" class="review-uploaded-img border" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border-color: #e2e8f0; cursor: pointer;" alt="Review Photo" onclick="window.open(this.src)">
                        </div>
                    <?php endif; ?>
                    
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-chat-left-dots fs-2 d-block mb-2"></i>
                No reviews published yet. Be the first to leave one!
            </div>
        <?php endif; ?>
        
    </div>
</div>

</div> </div> </div> </div> <div class="clearfix"></div>

                </form>
            </div>
                           
                    
<div class="container my-4">
    <div class="card p-4 shadow-sm border-0" style="border-radius: 16px; background: #ffffff;">
        <h5 class="fw-800 mb-4" style="color: #0f172a;"><i class="bi bi-tag-fill text-warning me-2"></i>Available Promo Codes</h5>
        <div class="row">
            <?php if ($available_promos && $available_promos->num_rows > 0): ?>
                <?php while ($promo = $available_promos->fetch_assoc()): ?>
                    <div class="col-md-6 mb-3">
                        <div class="voucher-box voucher-active" style="border: 1px dashed #ff6600; border-radius: 12px; padding: 15px; background: #fffcf9;">
                            <div class="voucher-title fw-bold text-uppercase" style="color: #ff6600; font-size: 1.1rem;"><?php echo htmlspecialchars($promo['Promo_Code']); ?></div>
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
    function toggleOrderIdColumn() {
        const checkbox = document.getElementById('showOrderId');
        const columns = document.querySelectorAll('.order-id-column');
        const display = checkbox.checked ? 'table-cell' : 'none';
        columns.forEach(col => { col.style.display = display; });
    }

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
                const url = 'user_dashboard.php?fetch_items_api=1&order_id=' + orderId;
                fetch(url)
                    .then(response => response.text())
                    .then(htmlData => { Swal.update({ html: htmlData }); })
                    .catch(() => { Swal.update({ html: '<div class="py-4 text-danger text-center">Failed to load items.</div>' }); });
            }
        });
    }

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
                    hour: 'numeric', minute: '2-digit', hour12: true
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
                bar.style.width = '0%'; label.innerText = 'Enter password';
            } else if (score <= 1) {
                bar.className = 'progress-bar bg-danger rounded-pill'; bar.style.width = '33%'; label.innerText = 'Weak';
                document.getElementById('weakIndicator').style.color = '#dc3545';
            } else if (score <= 3) {
                bar.className = 'progress-bar bg-warning rounded-pill'; bar.style.width = '66%'; label.innerText = 'Medium';
                document.getElementById('mediumIndicator').style.color = '#ffc107';
            } else {
                bar.className = 'progress-bar bg-success rounded-pill'; bar.style.width = '100%'; label.innerText = 'Strong';
                document.getElementById('strongIndicator').style.color = '#198754';
            }
        });
    }

   

    async function forgotWalletPIN() {
        Swal.fire({
            title: 'Reset Wallet PIN', text: "We will send an OTP to your registered email.", icon: 'info',
            showCancelButton: true, confirmButtonText: 'Send OTP', confirmButtonColor: '#FF6B00', showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('../Module B/wallet_pin_reset_handler.php', {
                    method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=request_otp'
                }).then(res => res.json());
            }
        }).then((result) => { if (result.isConfirmed && result.value.success) { handleOTPInput(); } });
    }

    function handleOTPInput() {
        Swal.fire({
            title: 'Verify OTP',
            html: `<input type="text" id="otp_code" class="swal2-input" placeholder="6-digit OTP" maxlength="6">
                   <input type="password" id="reset_pin" class="swal2-input" placeholder="Enter New 6-digit PIN" maxlength="6">`,
            confirmButtonText: 'Reset PIN', confirmButtonColor: '#17735b',
            preConfirm: () => {
                const otp = document.getElementById('otp_code').value;
                const pin = document.getElementById('reset_pin').value;
                if (!/^\d{6}$/.test(otp)) return Swal.showValidationMessage('Invalid OTP format');
                if (!/^\d{6}$/.test(pin)) return Swal.showValidationMessage('PIN must be 6 digits');
                let formData = new URLSearchParams();
                formData.append('action', 'verify_and_reset'); formData.append('otp', otp); formData.append('new_pin', pin);
                return fetch('../Module B/wallet_pin_reset_handler.php', {
                    method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData.toString()
                }).then(res => res.json());
            }
        }).then((result) => {
            if (result.value && result.value.success) {
                Swal.fire('Success!', 'Your Wallet PIN has been updated.', 'success').then(() => location.reload());
            } else if (result.value) { Swal.fire('Failed', result.value.message, 'error'); }
        });
    }

   function refreshAddressLabels() {
    const defaultIndexInput = document.getElementById('defaultAddressIndex');
    const currentDefault = (defaultIndexInput && defaultIndexInput.value !== '') ? Number(defaultIndexInput.value) : 0;
    const rows = document.querySelectorAll('#addressBook .address-row');

    rows.forEach((row, index) => {
        row.setAttribute('data-index', index);
        row.dataset.index = index;

        const isSelected = (index === currentDefault);
        row.classList.toggle('is-selected-active', isSelected);

        const radioDot = row.querySelector('.custom-radio-dot');
        const innerDot = row.querySelector('.dot-inner');
        if (radioDot) radioDot.style.borderColor = isSelected ? '#ff6700' : '#ccc';
        if (innerDot) innerDot.classList.toggle('d-none', !isSelected);

        let labelContainer = row.querySelector('.d-flex.align-items-center');
        let existingBadge = row.querySelector('.id-addr-badge');
        
        if (isSelected) {
            if (!existingBadge && labelContainer) {
                const badge = document.createElement('span');
                badge.className = 'badge id-addr-badge px-2 py-1 text-white me-1';
                badge.style = 'background-color: #ff6700; font-size: 0.7rem; font-weight: 500; border-radius: 4px;';
                badge.textContent = 'Default';
                radioDot.insertAdjacentElement('afterend', badge);
            }
            row.querySelector('.readonly-combined-string')?.classList.add('fw-bold');
        } else {
            if (existingBadge) existingBadge.remove();
            row.querySelector('.readonly-combined-string')?.classList.remove('fw-bold');
        }
    });

    const countBadge = document.getElementById('addressCountBadge');
    if (countBadge) {
        countBadge.textContent = `${rows.length} Saved`;
    }
}

function updateSelectedAddressPreview() {
    const defaultIndexInput = document.getElementById('defaultAddressIndex');
    const rows = document.querySelectorAll('#addressBook .address-row');
    const selectedIndex = defaultIndexInput ? Number(defaultIndexInput.value) : 0;
    const preview = document.getElementById('selectedAddressPreview');
    if (!preview) return;

    const selectedRow = rows[selectedIndex];
    if (selectedRow) {
        const addr = selectedRow.querySelector('.address-text-field')?.value.trim() || '';
        const post = selectedRow.querySelector('.address-postcode-field')?.value.trim() || '';
        const state = selectedRow.querySelector('.address-state-field')?.value || '';
        
        let combined = [addr, post, state].filter(Boolean).join(', ');
        preview.textContent = combined ? combined : 'No address selected.';
    } else {
        preview.textContent = 'No address selected.';
    }
}

function handleRowSelect(element, event) {
    const row = element.closest('.address-row');
    if (!row) return;

    const rows = document.querySelectorAll('#addressBook .address-row');
    const selectedIndex = Array.from(rows).indexOf(row);
    const defaultIndexInput = document.getElementById('defaultAddressIndex');
    
    if (defaultIndexInput) defaultIndexInput.value = selectedIndex;

    refreshAddressLabels();
    updateSelectedAddressPreview();
}

function confirmSingleAddress(button) {
    const row = button.closest('.address-row');
    if (!row) return;

    const addr = (row.querySelector('.address-text-field')?.value || '').trim().replace(/,+$/, '');
    const post = (row.querySelector('.address-postcode-field')?.value || '').trim();
    const state = row.querySelector('.address-state-field')?.value || '';

    // 强校验：必须输入完整的5位邮编和选择州属
    if (!addr || post.length !== 5 || !state) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Address',
            text: 'Please enter a valid street address, 5-digit postcode, and select a state.',
            confirmButtonColor: '#ff6700'
        });
        return;
    }

    const textBlock = row.querySelector('.address-readonly-text');
    const inputBlock = row.querySelector('.address-input-fields');
    const textSpan = row.querySelector('.readonly-combined-string');

    // 严谨的前端同步文本展示
    if (textSpan) {
        textSpan.innerText = `${addr}, ${post}, ${state}`;
    }

    if (inputBlock) inputBlock.classList.add('d-none');
    if (textBlock) textBlock.classList.remove('d-none');
    row.classList.remove('is-new-unsaved');
    
    updateSelectedAddressPreview();
}

function discardSingleAddress(button) {
    const row = button.closest('.address-row');
    if (!row) return;

    const textBlock = row.querySelector('.address-readonly-text');
    const inputBlock = row.querySelector('.address-input-fields');
    
    const isNewUnsaved = row.classList.contains('is-new-unsaved');
    const addr = row.querySelector('.address-text-field')?.value.trim() || '';

    if (isNewUnsaved && !addr) {
        removeAddressBox(button, null);
    } else {
        if (inputBlock) inputBlock.classList.add('d-none');
        if (textBlock) textBlock.classList.remove('d-none');
    }
    
    updateSelectedAddressPreview();
}

function editSingleAddress(element, event) {
    if (event) event.stopPropagation();
    
    const row = element.closest('.address-row');
    if (!row) return;

    const textBlock = row.querySelector('.address-readonly-text');
    const inputBlock = row.querySelector('.address-input-fields');
    
    if (textBlock) textBlock.classList.add('d-none');
    if (inputBlock) inputBlock.classList.remove('d-none');
}

function addAddressBox() {
    const addressBook = document.getElementById('addressBook');
    if (!addressBook) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'address-row py-3 border-bottom is-new-unsaved';
    
    wrapper.innerHTML = `
        <div class="address-readonly-text d-none d-flex align-items-center justify-content-between gap-3" style="cursor: pointer;" onclick="handleRowSelect(this, event)">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <div class="custom-radio-dot rounded-circle border d-flex align-items-center justify-content-center" style="width: 16px; height: 16px; min-width: 16px;">
                    <div class="dot-inner rounded-circle d-none" style="width: 8px; height: 8px; background-color: #ff6700;"></div>
                </div>
                <span class="readonly-combined-string text-dark small"></span>
            </div>
            <div class="action-icons-group d-flex gap-2">
                <span class="text-muted p-1 hover-orange" title="Edit" onclick="editSingleAddress(this, event)" style="cursor: pointer;"><i class="bi bi-pencil"></i></span>
                <span class="text-muted p-1 hover-danger" title="Delete" onclick="removeAddressBox(this, event)" style="cursor: pointer;"><i class="bi bi-trash3"></i></span>
            </div>
        </div>

        <div class="address-input-fields p-3 rounded-3 bg-light bg-opacity-50 border">
            <div class="row g-3">
                <div class="col-12">
                    <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">Street Address</label>
                    <textarea name="addresses[]" class="form-control address-text-field bg-white" rows="2" placeholder="House number, building, street name" oninput="updateSelectedAddressPreview()" style="font-size: 0.85rem; border-color: #e0e0e0;"></textarea>
                </div>
                <div class="col-md-5">
                    <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">Postcode</label>
                    <input type="text" name="postcodes[]" class="form-control address-postcode-field bg-white" maxlength="5" placeholder="Zip code" oninput="this.value = this.value.replace(/[^0-9]/g, ''); updateSelectedAddressPreview()" style="font-size: 0.85rem; border-color: #e0e0e0;">
                </div>
                <div class="col-md-7">
                    <label class="small fw-bold text-muted mb-1" style="font-size: 0.75rem;">State</label>
                    <select name="states[]" class="form-select address-state-field bg-white" onchange="updateSelectedAddressPreview()" style="font-size: 0.85rem; border-color: #e0e0e0;">
                        <option value="">Select State</option>
                        <option value="Johor">Johor</option><option value="Kedah">Kedah</option><option value="Kelantan">Kelantan</option>
                        <option value="Melaka">Melaka</option><option value="Negeri Sembilan">Negeri Sembilan</option><option value="Pahang">Pahang</option>
                        <option value="Penang">Penang</option><option value="Perak">Perak</option><option value="Perlis">Perlis</option>
                        <option value="Sabah">Sabah</option><option value="Sarawak">Sarawak</option><option value="Selangor">Selangor</option>
                        <option value="Terengganu">Terengganu</option><option value="Kuala Lumpur">Kuala Lumpur</option><option value="Putrajaya">Putrajaya</option>
                        <option value="Labuan">Labuan</option>
                    </select>
                </div>
                <div class="col-12 text-end mt-2 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-light border px-3 fw-bold rounded-2 text-muted" onclick="discardSingleAddress(this)" style="font-size: 0.8rem;">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-sm text-white px-3 fw-bold rounded-2" onclick="confirmSingleAddress(this)" style="background-color: #ff6700; font-size: 0.8rem;">
                        Save
                    </button>
                </div>
            </div>
        </div>
    `;
    
    addressBook.appendChild(wrapper);
    refreshAddressLabels();
    updateSelectedAddressPreview();
}

function removeAddressBox(button, event) {
    if (event) event.stopPropagation();

    const rows = document.querySelectorAll('#addressBook .address-row');
    const rowToRemove = button.closest('.address-row');
    if (!rowToRemove) return;

    const defaultIndexInput = document.getElementById('defaultAddressIndex');
    const currentDefault = defaultIndexInput ? Number(defaultIndexInput.value) : 0;
    const removeIndex = Array.from(rows).indexOf(rowToRemove);

    if (rows.length <= 1) {
        if(rowToRemove.querySelector('.address-text-field')) rowToRemove.querySelector('.address-text-field').value = '';
        if(rowToRemove.querySelector('.address-postcode-field')) rowToRemove.querySelector('.address-postcode-field').value = '';
        if(rowToRemove.querySelector('.address-state-field')) rowToRemove.querySelector('.address-state-field').value = '';
        
        rowToRemove.querySelector('.address-readonly-text')?.classList.add('d-none');
        rowToRemove.querySelector('.address-input-fields')?.classList.remove('d-none');
        
        updateSelectedAddressPreview();
        return;
    }

    rowToRemove.remove();
    const updatedRows = document.querySelectorAll('#addressBook .address-row');
    if (!defaultIndexInput) return;

    if (removeIndex === currentDefault || currentDefault >= updatedRows.length) {
        const nextDefault = Math.max(0, Math.min(removeIndex, updatedRows.length - 1));
        if (updatedRows[nextDefault]) {
            defaultIndexInput.value = nextDefault;
        }
    } else {
        defaultIndexInput.value = currentDefault > removeIndex ? currentDefault - 1 : currentDefault;
    }
    
    refreshAddressLabels();
    updateSelectedAddressPreview();
}

document.addEventListener('DOMContentLoaded', function () {
    const addNewAddressBtn = document.getElementById('addNewAddressBtn');
    if (addNewAddressBtn) {
        addNewAddressBtn.addEventListener('click', function () {
            addAddressBox();
        });
    }

    document.querySelectorAll('#addressBook .address-row').forEach(row => {
        const addr = row.querySelector('.address-text-field')?.value.trim() || '';
        const post = row.querySelector('.address-postcode-field')?.value.trim() || '';
        const state = row.querySelector('.address-state-field')?.value || '';
        
        if (addr && post && state) {
            const textBlock = row.querySelector('.address-readonly-text');
            const inputBlock = row.querySelector('.address-input-fields');
            if (inputBlock) inputBlock.classList.add('d-none');
            if (textBlock) textBlock.classList.remove('d-none');
        }
    });

    refreshAddressLabels();
    updateSelectedAddressPreview();
});
// OTP 身份认证逻辑前端 AJAX 的处理绑定
document.addEventListener('DOMContentLoaded', function() {
    const sendOtpBtn = document.getElementById('sendSecurityOTP');
    const verifyOtpBtn = document.getElementById('verifySecurityOTP');
    
    // ==========================================
    // 1. 发送登录密码修改的安全验证码 (已修复传参格式)
    // ==========================================
    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', function() {
            sendOtpBtn.disabled = true;
            sendOtpBtn.innerText = 'Sending...';
            
            // 💡 改用 URLSearchParams，确保后端 XAMPP $_POST['action'] 100% 能完美接收
            const params = new URLSearchParams();
            params.append('action', 'send_security_otp');

            fetch('user_dashboard.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString() 
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('securityOtpSection').classList.remove('d-none');
                    document.getElementById('securityOtpMessage').innerText = data.message;
                    document.getElementById('securityOtpMessage').className = 'mt-2 small text-success';
                } else {
                    Swal.fire('Error', data.message || 'Failed to send OTP.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error or system issue.', 'error');
            })
            .finally(() => {
                sendOtpBtn.disabled = false;
                sendOtpBtn.innerText = 'Send OTP';
            });
        });
    }

    // ==========================================
    // 2. 校验登录密码修改的验证码 (已修复传参格式)
    // ==========================================
    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener('click', function() {
            const otpVal = document.getElementById('security_otp').value.trim();
            if (!/^\d{6}$/.test(otpVal)) {
                Swal.fire('Invalid Format', 'Please enter a valid 6-digit code.', 'warning');
                return;
            }

            const params = new URLSearchParams();
            params.append('action', 'verify_security_otp');
            params.append('otp', otpVal);

            fetch('user_dashboard.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString() 
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('securityOtpSection').classList.add('d-none');
                    sendOtpBtn.classList.add('d-none');
                    document.getElementById('verifiedBadge').classList.remove('d-none');
                    
                    // 核心解锁：移除禁用，允许修改密码
                    document.getElementById('currentPassInput').removeAttribute('disabled');
                    document.getElementById('newPassInput').removeAttribute('disabled');
                    document.getElementById('confirmPassInput').removeAttribute('disabled');
                    document.getElementById('updatePasswordBtn').removeAttribute('disabled');
                    
                    Swal.fire('Identity Verified!', data.message, 'success');
                } else {
                    document.getElementById('securityOtpMessage').innerText = data.message;
                    document.getElementById('securityOtpMessage').className = 'mt-2 small text-danger';
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Network error during validation.', 'error');
            });
        });
    }
});

// ==========================================
// 3. 电子钱包激活与 PIN 设置逻辑 (保持原逻辑畅通)
// ==========================================
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
                if (data.success) { 
                    Swal.fire('Activated!', 'Your wallet is now ready.', 'success').then(() => location.reload()); 
                } else {
                    Swal.fire('Failed', data.message || 'Failed to set PIN.', 'error');
                }
            });
        }
    });
}

// ==========================================
// 4. 电子钱包 PIN 码重置申请
// ==========================================
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
            })
            .then(res => {
                if (!res.ok) throw new Error('Network response error');
                return res.json();
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            handleOTPInput();
        } else if (result.isConfirmed && result.value) {
            Swal.fire('Failed', result.value.message || 'Could not send OTP.', 'error');
        }
    });
}

// ==========================================
// 5. 电子钱包 PIN 码验证并重置提交
// ==========================================
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
            const otp = document.getElementById('otp_code').value.trim();
            const pin = document.getElementById('reset_pin').value.trim();
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
</div> 
<?php include '../includes/footer.php'; ?>