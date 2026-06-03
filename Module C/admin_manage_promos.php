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
$admin_image = 'default_admin.png';
$admin_id = $_SESSION['admin_id'] ?? null;
if ($admin_id) {
    $img_res = $conn->query("SELECT Admin_Image FROM admin WHERE Admin_Id = $admin_id");
    if ($img_res && $img_row = $img_res->fetch_assoc()) {
        $admin_image = !empty($img_row['Admin_Image']) ? $img_row['Admin_Image'] : ($_SESSION['admin_image'] ?? 'default_admin.png');
    } else {
        $admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';
    }
} else {
    $admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';
}

$today_date = date('Y-m-d');
$conn->query("UPDATE promo SET Promo_Status = 'Inactive' WHERE Expired_Date < '$today_date' AND Promo_Status = 'Active'");
$msg = "";

// 获取当前 Level 2/3 管理员所负责的 Brand_Id (如果有的话)
$managed_brand_ids = [];
if ($current_admin_level == 2 || $current_admin_level == 3) {
    $brand_q = $conn->query("SELECT Brand_Id FROM brand WHERE Admin_Id = $admin_id");
    if($brand_q) {
        while($b = $brand_q->fetch_assoc()) {
            $managed_brand_ids[] = $b['Brand_Id'];
        }
    }
}

// -------------------------------------------------------------------------
// 后端 POST 请求处理中心
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 创建优惠券 (Add Promo)
    if (isset($_POST['add_promo'])) {
        if ($current_admin_level != 1) {
            $msg = "<script>window.onload = () => { Swal.fire('Denied', 'Only Super Admin can manage promos.', 'error'); }</script>";
        } else {
            $name = $conn->real_escape_string($_POST['promo_name']);
            $code = strtoupper(trim($_POST['promo_code'])); 
            $value = floatval($_POST['promo_value']);
            $expiry = $_POST['Expired_Date'];
            $status = $_POST['promo_status'];
            $brand_id_insert = ($_POST['brand_id'] !== 'NULL') ? intval($_POST['brand_id']) : "NULL";
            
            $today = date('Y-m-d');

            if ($expiry < $today) {
                $msg = "<script>window.onload = () => { Swal.fire('Invalid Date', 'Expired date cannot be in the past!', 'error'); }</script>";
            } else {
                $check = $conn->query("SELECT * FROM promo WHERE Promo_Code = '$code'");
                if ($check->num_rows > 0) {
                    $msg = "<script>window.onload = () => { Swal.fire('Error', 'Promo code already exists!', 'error'); }</script>";
                } else {
                    $sql = "INSERT INTO promo (Brand_Id, Promo_Name, Promo_Code, Promo_Value, Expired_Date, Promo_Status) 
                            VALUES ($brand_id_insert, '$name', '$code', $value, '$expiry', '$status')";
                    if ($conn->query($sql)) {
                        $msg = "<script>window.onload = () => { Swal.fire('Success', 'Promo created successfully!', 'success'); }</script>";
                    }
                }
            }
        }
    }

    // 2. 启禁用状态切换 (Toggle Promo Status)
    if (isset($_POST['toggle_status_promo'])) {
        if ($current_admin_level != 1) {
            $msg = "<script>window.onload = () => { Swal.fire('Denied', 'Only Super Admin can toggle status.', 'error'); }</script>";
        } else {
            $p_id = intval($_POST['promo_id']);
            $new_status = $_POST['new_status'];
            $stmt = $conn->prepare("UPDATE promo SET Promo_Status = ? WHERE Promo_Id = ?");
            $stmt->bind_param("si", $new_status, $p_id);
            if ($stmt->execute()) {
                $action_text = ($new_status === 'Inactive') ? 'Deactivated' : 'Activated';
                $msg = "<script>window.onload = () => { Swal.fire('$action_text!', 'Promo status has been updated.', 'success'); }</script>";
            }
            $stmt->close();
        }
    }

    // 3. 分发优惠券给指定目标 (Distribute Promo)
    if (isset($_POST['assign_promo_to_user'])) {
        $dist_type = $_POST['distribution_type']; 
        $target_promo_id = intval($_POST['target_promo_id']);
        
        // 权限越权校验
        $has_permission = false;
        $promo_check = $conn->query("SELECT Brand_Id FROM promo WHERE Promo_Id = $target_promo_id")->fetch_assoc();
        
        if ($current_admin_level == 1) {
            $has_permission = true;
        } else {
            if ($promo_check && in_array($promo_check['Brand_Id'], $managed_brand_ids)) {
                $has_permission = true;
            }
        }

        if (!$has_permission) {
            $msg = "<script>window.onload = () => { Swal.fire('Denied', 'You do not have permission to distribute this brand\'s promo.', 'error'); }</script>";
        } else {
            $target_users = [];
            
            if ($dist_type === 'single') {
                $target_users[] = intval($_POST['target_user_id']);
            } elseif ($dist_type === 'all') {
                $res = $conn->query("SELECT User_Id FROM user WHERE User_Status = 'Active'");
                while($u = $res->fetch_assoc()) $target_users[] = $u['User_Id'];
            } elseif ($dist_type === 'group') {
                $gid = intval($_POST['target_group_id']);
                $res = $conn->query("SELECT User_Id FROM user_group_members WHERE Group_Id = $gid");
                while($u = $res->fetch_assoc()) $target_users[] = $u['User_Id'];
            }

            if(empty($target_users)) {
                $msg = "<script>window.onload = () => { Swal.fire('Notice', 'No users found in the selected target.', 'info'); }</script>";
            } else {
                $success_count = 0;
                $email_list = [];

                foreach ($target_users as $uid) {
                    $check_exists = $conn->query("SELECT * FROM user_promo WHERE User_Id = $uid AND Promo_Id = $target_promo_id AND Is_Used = 'No'");
                    if ($check_exists->num_rows == 0) {
                        if($conn->query("INSERT INTO user_promo (User_Id, Promo_Id, Is_Used) VALUES ($uid, $target_promo_id, 'No')")) {
                            $success_count++;
                            $umail = $conn->query("SELECT User_Email FROM user WHERE User_Id = $uid")->fetch_assoc();
                            if ($umail) $email_list[] = $umail['User_Email'];
                        }
                    }
                }

                if ($success_count > 0) {
                    require_once '../includes/PHPMailer/Exception.php';
                    require_once '../includes/PHPMailer/PHPMailer.php';
                    require_once '../includes/PHPMailer/SMTP.php';
                    require_once '../includes/mail_config.php';
                    
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
                        
                        foreach($email_list as $em) {
                            $mail->addBCC($em);
                        }

                        $mail->isHTML(true);
                        $mail->Subject = "A New Reward for You: " . $promo_data['Promo_Name'];
                        $mail->Body    = "
                            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                                <h2 style='color: #FF8C00;'>Great News!</h2>
                                <p>We have added a special discount to your account.</p>
                                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;'>
                                    <p style='margin: 0; font-size: 14px; color: #666;'>Your Exclusive Code:</p>
                                    <h1 style='margin: 10px 0; color: #333;'>{$promo_data['Promo_Code']}</h1>
                                    <p style='margin: 0; font-size: 18px; color: #FF8C00; font-weight: bold;'>Value: $discount_text</p>
                                </div>
                                <p style='margin-top: 20px;'>Login to your Dashboard to view all your rewards.</p>
                                <hr>
                                <p style='font-size: 12px; color: #999;'>Thank you for shopping with SS Sports Shoes Store.</p>
                            </div>";

                        $mail->send();
                        $msg = "<script>window.onload = () => { Swal.fire('Success!', 'Promo assigned to $success_count user(s) and email sent.', 'success'); }</script>";
                    } catch (Exception $e) {
                        $msg = "<script>window.onload = () => { Swal.fire('Assigned with Warning', 'Promo assigned, but email failed.', 'warning'); }</script>";
                    }
                } else {
                    $msg = "<script>window.onload = () => { Swal.fire('Notice', 'All selected targets already have this active promo.', 'info'); }</script>";
                }
            }
        }
    }

    // 4. 【新增功能】创建群组 (Create Group)
    if (isset($_POST['create_group'])) {
        if ($current_admin_level != 1) {
            $msg = "<script>window.onload = () => { Swal.fire('Denied', 'Only Super Admin can create groups.', 'error'); }</script>";
        } else {
            $gname = $conn->real_escape_string(trim($_POST['group_name']));
            if (!empty($gname)) {
                $check_g = $conn->query("SELECT * FROM user_groups WHERE Group_Name = '$gname'");
                if ($check_g->num_rows > 0) {
                    $msg = "<script>window.onload = () => { Swal.fire('Error', 'Group name already exists!', 'error'); }</script>";
                } else {
                    if ($conn->query("INSERT INTO user_groups (Group_Name) VALUES ('$gname')")) {
                        $msg = "<script>window.onload = () => { Swal.fire('Success', 'Group created successfully!', 'success'); }</script>";
                    }
                }
            }
        }
    }

    // 5. 【优化版】将用户批量移入群组 (Bulk Add Users to Group)
    if (isset($_POST['add_user_to_group'])) {
        if ($current_admin_level != 1) {
            $msg = "<script>window.onload = () => { Swal.fire('Denied', 'Only Super Admin can manage members.', 'error'); }</script>";
        } else {
            $gid = intval($_POST['assoc_group_id']);
            $uids = $_POST['assoc_user_ids'] ?? []; // 接收的是一个包含多个ID的数组
            
            if (empty($uids)) {
                $msg = "<script>window.onload = () => { Swal.fire('Notice', 'Please select at least one user.', 'info'); }</script>";
            } else {
                $values = [];
                foreach ($uids as $uid) {
                    $values[] = "($gid, " . intval($uid) . ")";
                }
                
                // 使用 INSERT IGNORE 拼接成一条大 SQL 执行，自动忽略已存在于该群组的记录，性能极高
                $sql_bulk = "INSERT IGNORE INTO user_group_members (Group_Id, User_Id) VALUES " . implode(',', $values);
                
                if ($conn->query($sql_bulk)) {
                    $affected = $conn->affected_rows;
                    $msg = "<script>window.onload = () => { Swal.fire('Success', 'Successfully processed " . count($uids) . " users into the group!', 'success'); }</script>";
                } else {
                    $msg = "<script>window.onload = () => { Swal.fire('Error', 'Failed to add users.', 'error'); }</script>";
                }
            }
        }
    }
}

// -------------------------------------------------------------------------
// 获取最新渲染数据（必须置于 POST 处理之后，以确保群组更新后实时刷新下拉）
// -------------------------------------------------------------------------
$all_brands = $conn->query("SELECT Brand_Id, Brand_Name FROM brand ORDER BY Brand_Name ASC");
$all_users = $conn->query("SELECT User_Id, User_Name, User_Email FROM user WHERE User_Status = 'Active' ORDER BY User_Name ASC");
$all_groups = $conn->query("SHOW TABLES LIKE 'user_groups'")->num_rows > 0 ? $conn->query("SELECT * FROM user_groups ORDER BY Group_Name ASC") : false;

if ($current_admin_level == 1) {
    $active_promos = $conn->query("SELECT Promo_Id, Promo_Code, Promo_Name FROM promo WHERE Promo_Status = 'Active' ORDER BY Promo_Name ASC");
} else {
    if (count($managed_brand_ids) > 0) {
        $brand_ids_str = implode(',', $managed_brand_ids);
        $active_promos = $conn->query("SELECT Promo_Id, Promo_Code, Promo_Name FROM promo WHERE Promo_Status = 'Active' AND Brand_Id IN ($brand_ids_str) ORDER BY Promo_Name ASC");
    } else {
        $active_promos = false; 
    }
}

$promos = $conn->query("SELECT p.*, b.Brand_Name FROM promo p LEFT JOIN brand b ON p.Brand_Id = b.Brand_Id ORDER BY p.Promo_Id DESC");
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
                    <small class="text-muted"><?php echo ($current_admin_level == 1) ? 'Super Admin' : 'Brand Manager'; ?></small>
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
                            <?php if ($current_admin_level == 1): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">LINK TO BRAND</label>
                                <select name="brand_id" class="form-select">
                                    <option value="NULL">Global (All Brands)</option>
                                    <?php while($b = $all_brands->fetch_assoc()): ?>
                                        <option value="<?php echo $b['Brand_Id']; ?>"><?php echo htmlspecialchars($b['Brand_Name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <?php endif; ?>
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
                                <input type="date" name="Expired_Date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">STATUS</label>
                                <select name="promo_status" class="form-select" <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <button type="submit" name="add_promo" class="btn btn-orange w-100 py-2 fw-bold shadow-sm <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>">
                                <i class="bi <?php echo ($current_admin_level != 1) ? 'bi-lock-fill' : 'bi-plus-circle'; ?> me-2"></i> Add Promo Code
                            </button>
                        </form>
                    </div>

                    <div class="card p-4 mt-4">
                        <h5 class="fw-bold mb-4" style="color: var(--orange-primary);"><i class="bi bi-person-plus me-2"></i>Distribute to User</h5>
                        
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">DISTRIBUTION TYPE</label>
                                <select name="distribution_type" id="dist_type" class="form-select" onchange="toggleDistributionType()" required>
                                    <option value="single">Single User</option>
                                    <option value="all">All Active Users</option>
                                    <option value="group">User Group</option>
                                </select>
                            </div>

                            <div class="mb-3" id="div_single_user">
                                <label class="form-label small fw-bold">SELECT CUSTOMER</label>
                                <select name="target_user_id" id="select_single_user" class="form-select" required>
                                    <option value="" disabled selected>-- Select User --</option>
                                    <?php 
                                    $all_users->data_seek(0);
                                    while($u = $all_users->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $u['User_Id']; ?>"><?php echo htmlspecialchars($u['User_Name']); ?> (<?php echo $u['User_Email']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="div_group_user" style="display: none;">
                                <label class="form-label small fw-bold">SELECT GROUP</label>
                                <select name="target_group_id" id="select_group_user" class="form-select">
                                    <option value="" disabled selected>-- Select Group --</option>
                                    <?php if($all_groups && $all_groups->num_rows > 0): ?>
                                        <?php 
                                        $all_groups->data_seek(0);
                                        while($g = $all_groups->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $g['Group_Id']; ?>"><?php echo htmlspecialchars($g['Group_Name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No groups created yet</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">SELECT PROMO CODE</label>
                                <select name="target_promo_id" class="form-select" required>
                                    <option value="" disabled selected>-- Select Promo --</option>
                                    <?php if($active_promos && $active_promos->num_rows > 0): ?>
                                        <?php 
                                        $active_promos->data_seek(0);
                                        while($p = $active_promos->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $p['Promo_Id']; ?>">[<?php echo $p['Promo_Code']; ?>] <?php echo htmlspecialchars($p['Promo_Name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No active promos available</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <button type="submit" name="assign_promo_to_user" class="btn btn-orange w-100 py-2 fw-bold shadow-sm" <?php if(!$active_promos || $active_promos->num_rows == 0) echo 'disabled'; ?>>
                                <i class="bi bi-send-fill me-2"></i> Distribute Promo
                            </button>
                        </form>
                    </div>

                    <?php if ($current_admin_level == 1): ?>
                    <div class="card p-4 mt-4" style="border: 1px solid #ffd099; background-color: #fffdfa;">
                        <h5 class="fw-bold mb-3" style="color: var(--orange-primary);"><i class="bi bi-tags-fill me-2"></i>Group Manager Center</h5>
                        <p class="text-muted small mb-3">Create clusters and bind customers into custom target segments.</p>
                        
                        <form action="" method="POST" class="border-bottom pb-3 mb-3">
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-secondary">1. CREATE NEW GROUP</label>
                                <input type="text" name="group_name" class="form-control form-control-sm" placeholder="e.g. Nike Fans / VIP Cluster" required>
                            </div>
                            <button type="submit" name="create_group" class="btn btn-sm btn-orange w-100 fw-bold">
                                <i class="bi bi-plus-square me-1"></i> Create Group
                            </button>
                        </form>

                        <!-- B表单: 批量把用户加进群组内 -->
                        <form action="" method="POST">
                            <label class="form-label small fw-bold text-secondary">2. BATCH ASSIGN CUSTOMERS TO GROUP</label>
                            
                            <!-- 选择目标群组 -->
                            <div class="mb-2">
                                <select name="assoc_group_id" class="form-select form-select-sm" required>
                                    <option value="" disabled selected>-- Target Group --</option>
                                    <?php 
                                    if ($all_groups && $all_groups->num_rows > 0) {
                                        $all_groups->data_seek(0);
                                        while($g = $all_groups->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $g['Group_Id']; ?>"><?php echo htmlspecialchars($g['Group_Name']); ?></option>
                                    <?php 
                                        endwhile; 
                                    } 
                                    ?>
                                </select>
                            </div>

                            <!-- 实时搜索输入框 -->
                            <div class="mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="userSearchInput" class="form-control" placeholder="Type name or email to filter..." onkeyup="filterGroupUsers()">
                                </div>
                            </div>

                            <!-- 可滚动的多选复选框区域 -->
                            <div class="mb-3" id="userCheckboxList" style="max-height: 220px; overflow-y: auto; border: 1px solid #ced4da; padding: 10px; background: #fff; border-radius: 6px;">
                                <?php 
                                $all_users->data_seek(0);
                                while($u = $all_users->fetch_assoc()): 
                                ?>
                                    <div class="form-check user-item mb-1">
                                        <input class="form-check-input" type="checkbox" name="assoc_user_ids[]" value="<?php echo $u['User_Id']; ?>" id="chk_u_<?php echo $u['User_Id']; ?>">
                                        <label class="form-check-label small text-dark d-block cursor-pointer" for="chk_u_<?php echo $u['User_Id']; ?>">
                                            <strong><?php echo htmlspecialchars($u['User_Name']); ?></strong> 
                                            <br><span class="text-muted text-xs"><?php echo $u['User_Email']; ?></span>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <button type="submit" name="add_user_to_group" class="btn btn-sm btn-outline-dark w-100 fw-bold">
                                <i class="bi bi-person-plus-fill me-1"></i> Link Selected Users to Group
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

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
                                            <th>Brand</th>
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
                                                    <td>
                                                        <span class="badge bg-info text-dark"><?php echo $row['Brand_Name'] ?? 'Global'; ?></span>
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
                                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmStatusChange(<?php echo $row['Promo_Id']; ?>, '<?php echo $row['Promo_Code']; ?>', 'Inactive')">
                                                                    <i class="bi bi-pause-circle-fill"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-sm btn-outline-success" onclick="confirmStatusChange(<?php echo $row['Promo_Id']; ?>, '<?php echo $row['Promo_Code']; ?>', 'Active', '<?php echo $row['Expired_Date']; ?>')">
                                                                    <i class="bi bi-play-circle-fill"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <i class="bi bi-lock-fill text-muted"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center py-5 text-muted">No promos found.</td></tr>
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
function toggleDistributionType() {
    var type = document.getElementById('dist_type').value;
    var singleDiv = document.getElementById('div_single_user');
    var groupDiv = document.getElementById('div_group_user');
    var singleSelect = document.getElementById('select_single_user');
    var groupSelect = document.getElementById('select_group_user');

    if (type === 'single') {
        singleDiv.style.display = 'block';
        groupDiv.style.display = 'none';
        singleSelect.required = true;
        groupSelect.required = false;
    } else if (type === 'group') {
        singleDiv.style.display = 'none';
        groupDiv.style.display = 'block';
        singleSelect.required = false;
        groupSelect.required = true;
    } else if (type === 'all') {
        singleDiv.style.display = 'none';
        groupDiv.style.display = 'none';
        singleSelect.required = false;
        groupSelect.required = false;
    }
}

function confirmStatusChange(id, code, targetStatus, expiredDate = null) {
    let title = targetStatus === 'Inactive' ? 'Deactivate Promo?' : 'Activate Promo?';
    let text = targetStatus === 'Inactive' ? `This will prevent users from using ${code}.` : `This will allow users to use ${code} again.`;
    let icon = targetStatus === 'Inactive' ? 'warning' : 'info';
    let confirmBtnColor = targetStatus === 'Inactive' ? '#d33' : '#17735b';

    if (targetStatus === 'Active' && expiredDate) {
        const today = new Date().toISOString().split('T')[0];
        if (expiredDate < today) {
            title = '⚠️ Re-activate Expired Code?';
            text = `Warning: The promo code "${code}" expired on ${expiredDate}. Proceeding will override expiry check.`;
            icon = 'warning';
            confirmBtnColor = '#FF8C00'; 
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
function filterGroupUsers() {
    var input = document.getElementById('userSearchInput').value.toLowerCase();
    var items = document.querySelectorAll('#userCheckboxList .user-item');
    
    items.forEach(function(item) {
        var text = item.innerText.toLowerCase();
        if (text.indexOf(input) > -1) {
            item.setAttribute('style', 'display: block !important');
        } else {
            item.setAttribute('style', 'display: none !important');
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>