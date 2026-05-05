<?php
// admin_manage_promos.php
session_start();
require_once '../includes/db_connection.php';

// 1. 安全检查
if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$current_admin_level = $_SESSION['role']; 
$admin_name = $_SESSION['username'] ?? 'Admin';
$admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';
$today_date = date('Y-m-d');
$conn->query("UPDATE promo SET Promo_Status = 'Inactive' WHERE Expired_Date < '$today_date' AND Promo_Status = 'Active'");

$msg = "";

// --- 2. 逻辑处理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current_admin_level != 1) {
        $msg = "<script>window.onload = () => { Swal.fire('Denied', 'Only Super Admin can manage promos.', 'error'); }</script>";
    } else {
        // Add Promo
        // --- 【后端关键修复】：防止选择过去日期 ---
    if (isset($_POST['add_promo'])) {
        $name = $conn->real_escape_string($_POST['promo_name']);
        $code = strtoupper(trim($_POST['promo_code'])); 
        $value = floatval($_POST['promo_value']);
        $expiry = $_POST['Expired_Date'];
        $status = $_POST['promo_status'];
        
        // 获取今天的日期
        $today = date('Y-m-d');

        if ($expiry < $today) {
            // 如果选择的日期早于今天，直接拦截并报错
            $msg = "<script>window.onload = () => { Swal.fire('Invalid Date', 'Expired date cannot be in the past!', 'error'); }</script>";
        } else {
            // 原有的重复检查逻辑
            $check = $conn->query("SELECT * FROM promo WHERE Promo_Code = '$code'");
            if ($check->num_rows > 0) {
                $msg = "<script>window.onload = () => { Swal.fire('Error', 'Promo code already exists!', 'error'); }</script>";
            } else {
                $sql = "INSERT INTO promo (Promo_Name, Promo_Code, Promo_Value, Expired_Date, Promo_Status) 
                        VALUES ('$name', '$code', $value, '$expiry', '$status')";
                if ($conn->query($sql)) {
                    $msg = "<script>window.onload = () => { Swal.fire('Success', 'Promo created successfully!', 'success'); }</script>";
                }
            }
        }
    }

        // Delete Promo
        if (isset($_POST['toggle_status_promo'])) {
            $p_id = intval($_POST['promo_id']);
            $new_status = $_POST['new_status'];
            
            // 将 DELETE 改为 UPDATE 状态
            $stmt = $conn->prepare("UPDATE promo SET Promo_Status = ? WHERE Promo_Id = ?");
            $stmt->bind_param("si", $new_status, $p_id);
            
            if ($stmt->execute()) {
                $action_text = ($new_status === 'Inactive') ? 'Deactivated' : 'Activated';
                $msg = "<script>window.onload = () => { Swal.fire('$action_text!', 'Promo status has been updated.', 'success'); }</script>";
            }
            $stmt->close();
        }

        // Auto-Issue Birthday Promos
        if (isset($_POST['auto_birthday_promo'])) {
            $current_month = date('m'); // Get current month (01-12)
            $current_year = date('Y');
            
            // Get all users with birthday in current month
            $birthday_users = $conn->query("
                SELECT User_Id, User_Name, User_Email, User_DateOfBirth 
                FROM user 
                WHERE MONTH(User_DateOfBirth) = '$current_month' 
                AND User_Status = 'Active'
            ");
            
            $count_issued = 0;
            $count_already_exists = 0;
            
            if ($birthday_users && $birthday_users->num_rows > 0) {
                while ($user = $birthday_users->fetch_assoc()) {
                    $user_id = $user['User_Id'];
                    $user_name = $user['User_Name'];
                    $birth_day = date('d', strtotime($user['User_DateOfBirth']));
                    
                    // Create unique promo code: BDAY_userid_month_day (e.g., BDAY_5_0512)
                    $month_day = $current_month . $birth_day;
                    $promo_code = "BDAY{$user_id}{$month_day}";
                    
                    // Check if this birthday promo already exists for this user in this month
                    $check_existing = $conn->query("
                        SELECT Promo_Id FROM promo 
                        WHERE Promo_Code = '$promo_code' 
                        OR (Promo_Name LIKE '%Birthday%' AND Promo_Code LIKE '%{$user_id}%')
                        LIMIT 1
                    ");
                    
                    if ($check_existing && $check_existing->num_rows > 0) {
                        $count_already_exists++;
                    } else {
                        // Create new birthday promo: 15% off
                        $promo_name = "Birthday Special - {$user_name} 15% Off";
                        $promo_value = 15.00;
                        $promo_type = 'Percentage';
                        $promo_status = 'Active';
                        $promo_expiry = date('Y-m-d', strtotime('+30 days')); // Valid for 30 days
                        
                        $insert_stmt = $conn->prepare("
                            INSERT INTO promo 
                            (Promo_Name, Promo_Code, Promo_Value, Expired_Date, Promo_Status, Promo_Type) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        if ($insert_stmt) {
                            $insert_stmt->bind_param('sddsss', $promo_name, $promo_code, $promo_value, $promo_expiry, $promo_status, $promo_type);
                            if ($insert_stmt->execute()) {
                                $count_issued++;
                            }
                            $insert_stmt->close();
                        }
                    }
                }
                
                $msg = "<script>window.onload = () => { Swal.fire('Birthday Promos Generated!', 'Issued: {$count_issued} new promos | Already existing: {$count_already_exists}', 'success'); }</script>";
            } else {
                $msg = "<script>window.onload = () => { Swal.fire('No Birthday Users', 'No active users with birthdays this month.', 'info'); }</script>";
            }
        }

    if (isset($_POST['assign_promo_to_user'])) {
        $target_user_id = intval($_POST['target_user_id']);
        $target_promo_id = intval($_POST['target_promo_id']);

        // 1. 检查该用户是否已经拥有此优惠券且尚未使用
        $check_exists = $conn->query("SELECT * FROM user_promo WHERE User_Id = $target_user_id AND Promo_Id = $target_promo_id AND Is_Used = 'No'");
        
        if ($check_exists->num_rows > 0) {
            $msg = "<script>window.onload = () => { Swal.fire('Notice', 'This user already has this active promo.', 'info'); }</script>";
        } else {
            // 2. 插入 user_promo 表进行绑定
            $sql_assign = "INSERT INTO user_promo (User_Id, Promo_Id, Is_Used) VALUES ($target_user_id, $target_promo_id, 'No')";
            
            if ($conn->query($sql_assign)) {
                // --- 发送邮件通知流程 ---
                require_once '../includes/PHPMailer/Exception.php';
                require_once '../includes/PHPMailer/PHPMailer.php';
                require_once '../includes/PHPMailer/SMTP.php';
                require_once '../includes/mail_config.php'; // 确保此文件已配置
                
                // 获取用户及优惠券详情
                $user_data = $conn->query("SELECT User_Name, User_Email FROM user WHERE User_Id = $target_user_id")->fetch_assoc();
                $promo_data = $conn->query("SELECT Promo_Code, Promo_Name, Promo_Value, Promo_Type FROM promo WHERE Promo_Id = $target_promo_id")->fetch_assoc();
                
                $discount_text = ($promo_data['Promo_Type'] === 'Percentage') ? intval($promo_data['Promo_Value'])."% OFF" : "RM ".number_format($promo_data['Promo_Value'], 2)." OFF";

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_EMAIL; 
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom(SMTP_EMAIL, 'Sole 2 Soul Shoes Store');
                    $mail->addAddress($user_data['User_Email'], $user_data['User_Name']);
                    $mail->isHTML(true);
                    $mail->Subject = "A New Reward for You: " . $promo_data['Promo_Name'];
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                            <h2 style='color: #FF8C00;'>Congratulations, {$user_data['User_Name']}!</h2>
                            <p>We have added a special discount to your account.</p>
                            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;'>
                                <p style='margin: 0; font-size: 14px; color: #666;'>Your Exclusive Code:</p>
                                <h1 style='margin: 10px 0; color: #333;'>{$promo_data['Promo_Code']}</h1>
                                <p style='margin: 0; font-size: 18px; color: #FF8C00; font-weight: bold;'>Value: $discount_text</p>
                            </div>
                            <p style='margin-top: 20px;'>Login to your <a href='http://localhost/SS_Sports_Shoes/Module%20A/user_dashboard.php'>Dashboard</a> to view all your rewards.</p>
                            <hr>
                            <p style='font-size: 12px; color: #999;'>Thank you for shopping with SS Sports Shoes Store.</p>
                        </div>";

                    $mail->send();
                    $msg = "<script>window.onload = () => { Swal.fire('Success!', 'Promo assigned and notification email sent to {$user_data['User_Email']}.', 'success'); }</script>";
                } catch (Exception $e) {
                    $msg = "<script>window.onload = () => { Swal.fire('Assigned with Warning', 'Promo assigned, but email failed: {$mail->ErrorInfo}', 'warning'); }</script>";
                }
            }
        }
    }
}
}

$promos = $conn->query("SELECT * FROM promo ORDER BY Promo_Id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promo Management | Online Sports Shoes Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --orange-primary: #FF8C00; --sidebar-width: 260px; }
        body { background-color: #f8f9fa; margin: 0; font-family: 'Segoe UI', sans-serif; }
        .wrapper { display: flex; }
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; }
        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--orange-primary); object-fit: cover; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .table thead th { background-color: #fcfcfc; color: var(--orange-primary); text-transform: uppercase; font-size: 0.75rem; border-bottom: 2px solid #fff2e6; }
        .btn-orange { background-color: var(--orange-primary); color: white; border: none; }
        .btn-orange:hover { background-color: #e67e00; color: white; }
    </style>
</head>
<body>

<form id="statusToggleForm" method="POST" style="display:none;">
    <input type="hidden" name="promo_id" id="promo_id_field">
    <input type="hidden" name="new_status" id="new_status_field">
    <input type="hidden" name="toggle_status_promo" value="1">
</form>

<div class="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a></li>
                        <li class="breadcrumb-item active">Promotions</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Manage Promo Codes</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 text-dark">
                    <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
                    <small class="text-muted"><?php echo ($current_admin_level == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <div class="container-fluid p-0">
            <?php echo $msg; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-4" style="color: var(--orange-primary);">Create New Promo</h5>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">PROMO NAME</label>
                                <input type="text" name="promo_name" class="form-control" placeholder="e.g. Summer Sale 2026" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">PROMO CODE</label>
                                <input type="text" name="promo_code" class="form-control" placeholder="e.g. SUMMER26" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">DISCOUNT VALUE (RM)</label>
                                <input type="number" step="0.01" name="promo_value" class="form-control" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">EXPIRED DATE</label>
                                <input type="date" 
                                    name="Expired_Date" 
                                    class="form-control" 
                                    min="<?php echo date('Y-m-d'); ?>" 
                                    required 
                                    <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">STATUS</label>
                                <select name="promo_status" class="form-select" <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <button type="submit" name="add_promo" class="btn btn-orange w-100 py-2 fw-bold shadow-sm <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>" <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                                <i class="bi <?php echo ($current_admin_level != 1) ? 'bi-lock-fill' : 'bi-plus-circle'; ?> me-2"></i>
                                <?php echo ($current_admin_level != 1) ? 'Super Admin Only' : 'Add Promo Code'; ?>
                            </button>
                        </form>
                    </div>

                    <?php 
                        $all_users = $conn->query("SELECT User_Id, User_Name, User_Email FROM user WHERE User_Status = 'Active' ORDER BY User_Name ASC");
                        $active_promos = $conn->query("SELECT Promo_Id, Promo_Code, Promo_Name FROM promo WHERE Promo_Status = 'Active' ORDER BY Promo_Name ASC");
                    ?>

                    <div class="card p-4 mt-4">
                        <h5 class="fw-bold mb-4" style="color: var(--orange-primary);"><i class="bi bi-person-plus me-2"></i>Distribute to User</h5>
                        <p class="text-muted small mb-4">Directly assign a specific promo code to an individual customer.</p>
                        
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">SELECT CUSTOMER</label>
                                <select name="target_user_id" class="form-select" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                                    <option value="" disabled selected>-- Select User --</option>
                                    <?php while($u = $all_users->fetch_assoc()): ?>
                                        <option value="<?php echo $u['User_Id']; ?>"><?php echo htmlspecialchars($u['User_Name']); ?> (<?php echo $u['User_Email']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">SELECT PROMO CODE</label>
                                <select name="target_promo_id" class="form-select" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                                    <option value="" disabled selected>-- Select Promo --</option>
                                    <?php while($p = $active_promos->fetch_assoc()): ?>
                                        <option value="<?php echo $p['Promo_Id']; ?>">[<?php echo $p['Promo_Code']; ?>] <?php echo htmlspecialchars($p['Promo_Name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <button type="submit" name="assign_promo_to_user" class="btn btn-orange w-100 py-2 fw-bold shadow-sm <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>">
                                <i class="bi bi-send-fill me-2"></i> Distribute Promo
                            </button>
                        </form>
                    </div>

                    <div class="card p-4 mt-4">
                        <h5 class="fw-bold mb-4" style="color: var(--orange-primary);"><i class="bi bi-cake2 me-2"></i>Birthday Promo Manager</h5>
                        <p class="text-muted small mb-4">Auto-generate 15% off promo codes for users with birthdays this month.</p>
                        <form action="" method="POST">
                            <button type="submit" name="auto_birthday_promo" class="btn btn-orange w-100 py-2 fw-bold shadow-sm <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>" <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                                <i class="bi <?php echo ($current_admin_level != 1) ? 'bi-lock-fill' : 'bi-gift'; ?> me-2"></i>
                                <?php echo ($current_admin_level != 1) ? 'Super Admin Only' : 'Generate Birthday Promos'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Promo Code List</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Promo Info</th>
                                            <th>Value</th>
                                            <th>Expired Date</th>
                                            <th>Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($promos->num_rows > 0): ?>
                                            <?php while ($row = $promos->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="ps-4">
                                                        <div class="fw-bold text-dark"><?php echo $row['Promo_Code']; ?></div>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($row['Promo_Name']); ?></div>
                                                    </td>
                                                    <td class="text-success fw-bold">RM <?php echo number_format($row['Promo_Value'], 2); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($row['Expired_Date'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo ($row['Promo_Status'] == 'Active') ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo $row['Promo_Status']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($current_admin_level == 1): ?>
                                                            <?php if ($row['Promo_Status'] == 'Active'): ?>
                                                                <!-- 激活状态显示禁用按钮 -->
                                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                        onclick="confirmStatusChange(<?php echo $row['Promo_Id']; ?>, '<?php echo $row['Promo_Code']; ?>', 'Inactive')">
                                                                    <i class="bi bi-pause-circle-fill me-1"></i> Deactivate
                                                                </button>
                                                            <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                                    onclick="confirmStatusChange(<?php echo $row['Promo_Id']; ?>, '<?php echo $row['Promo_Code']; ?>', 'Active', '<?php echo $row['Expired_Date']; ?>')">
                                                                <i class="bi bi-play-circle-fill me-1"></i> Activate
                                                            </button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <i class="bi bi-lock-fill text-muted"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No promos found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmStatusChange(id, code, targetStatus, expiredDate = null) {
    let title = targetStatus === 'Inactive' ? 'Deactivate Promo?' : 'Activate Promo?';
    let text = targetStatus === 'Inactive' 
        ? `This will prevent users from using ${code}.` 
        : `This will allow users to use ${code} again.`;
    let icon = targetStatus === 'Inactive' ? 'warning' : 'info';
    let confirmBtnColor = targetStatus === 'Inactive' ? '#d33' : '#17735b';

    // --- 【新增逻辑】：检查激活操作是否涉及过期代码 ---
    if (targetStatus === 'Active' && expiredDate) {
        const today = new Date().toISOString().split('T')[0]; // 获取格式为 YYYY-MM-DD 的今天日期
        
        if (expiredDate < today) {
            title = '⚠️ Re-activate Expired Code?';
            text = `Warning: The promo code "${code}" expired on ${expiredDate}. Re-activating it will allow users to use it despite the expiry date. Do you wish to proceed?`;
            icon = 'warning';
            confirmBtnColor = '#FF8C00'; // 使用橙色表示警告
        }
    }

    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: targetStatus === 'Inactive' ? 'Yes, Deactivate' : 'Yes, Activate'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('promo_id_field').value = id;
            document.getElementById('new_status_field').value = targetStatus;
            document.getElementById('statusToggleForm').submit();
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>