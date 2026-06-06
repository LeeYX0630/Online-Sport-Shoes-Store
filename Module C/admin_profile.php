<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['username'] ?? 'Admin';

$sql = "SELECT * FROM admin WHERE Admin_Id = $admin_id";
$result = $conn->query($sql);
$admin = $result->fetch_assoc();

$admin_image = !empty($admin['Admin_Image']) ? $admin['Admin_Image'] : ($_SESSION['admin_image'] ?? 'default_admin.png');

$message = "";
if (isset($_POST['update_profile'])) {
    $new_name  = $conn->real_escape_string($_POST['admin_name']);
    $new_email = $conn->real_escape_string($_POST['admin_email']);
    $image_name = $admin['Admin_Image'];

    if (!empty($_FILES['admin_pic']['name'])) {
        $upload_dir = "../uploads/admin/";
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $image_name = str_replace(' ', '', $new_name) . ".jpg";
        $target = $upload_dir . $image_name;
        if (move_uploaded_file($_FILES['admin_pic']['tmp_name'], $target)) {
            $_SESSION['admin_image'] = $image_name;
        }
    }

    $update_sql = "UPDATE admin SET Admin_Name='$new_name', Admin_Email='$new_email', Admin_Image='$image_name' WHERE Admin_Id=$admin_id";
    if ($conn->query($update_sql)) {
        $_SESSION['username']    = $new_name;
        $_SESSION['admin_image'] = $image_name;
        header("Location: admin_profile.php?updated=1");
        exit();
    }
}

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = "Profile updated successfully!";
}

// Stats counts — Level 3 只看自己品牌的订单和商品，Level 1/2 显示 ALL
$admin_level = $admin['Admin_Level'];

if ($admin_level == 3) {
    // Orders managed：只统计含有该 Admin 旗下品牌商品的订单
    $orders_res = $conn->query("
        SELECT COUNT(DISTINCT o.Order_Id) as c
        FROM `order` o
        WHERE EXISTS (
            SELECT 1 FROM order_detail od
            JOIN product p ON od.Pro_Id = p.Pro_Id
            JOIN brand b ON p.Brand_Id = b.Brand_Id
            WHERE od.Order_Id = o.Order_Id
            AND b.Admin_Id = $admin_id
        )
    ");
    $orders_count = $orders_res ? $orders_res->fetch_assoc()['c'] : 0;

    // Products managed：只统计该 Admin 旗下品牌的商品
    $products_res = $conn->query("
        SELECT COUNT(*) as c
        FROM product p
        JOIN brand b ON p.Brand_Id = b.Brand_Id
        WHERE b.Admin_Id = $admin_id
    ");
    $products_count = $products_res ? $products_res->fetch_assoc()['c'] : 0;
} else {
    $orders_count   = null; // 显示 ALL
    $products_count = null; // 显示 ALL
}

// Avatar initials fallback
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($admin['Admin_Name'])))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile | Online Sports Shoes Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root { --orange-primary: #FF8C00; --sidebar-width: 260px; }
        body { background-color: #f4f5f7; margin: 0; font-family: 'Segoe UI', 'Inter', sans-serif; }
        .wrapper { display: flex; }
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; }

        /* ── Header (unchanged) ── */
        .admin-header {
            background: white; padding: 15px 30px;
            border-radius: 15px; margin-bottom: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .admin-profile-img {
            width: 42px; height: 42px; border-radius: 50%;
            border: 2px solid var(--orange-primary); object-fit: cover;
        }

        /* ── Profile card ── */
        .profile-card { background: #fff; border-radius: 20px; overflow: hidden; border: 1px solid #ebebeb; }

        /* Banner */
        .profile-banner {
            background: linear-gradient(135deg, #FF8C00 0%, #FF5E00 60%, #e04800 100%);
            padding: 32px 32px 64px; position: relative; overflow: hidden;
        }
        .profile-banner::before {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .profile-banner::after {
            content: ''; position: absolute; bottom: -20px; left: 30%;
            width: 120px; height: 120px; border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .banner-title { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.7); letter-spacing: 0.5px; margin-bottom: 4px; }
        .banner-sub   { font-size: 22px; font-weight: 600; color: #fff; }

        /* Avatar overlap */
        .overlap-row {
            padding: 0 32px; margin-top: -44px; margin-bottom: 24px;
            display: flex; align-items: flex-end; justify-content: space-between;
        }
        .avatar-wrap { position: relative; }
        .avatar-box {
            width: 88px; height: 88px; border-radius: 18px;
            background: linear-gradient(135deg, #FF8C00, #FF5E00);
            border: 4px solid #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 600; color: #fff;
            box-shadow: 0 4px 16px rgba(255,100,0,0.25);
            object-fit: cover; overflow: hidden;
        }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
        .cam-btn {
            position: absolute; bottom: -4px; right: -4px;
            width: 28px; height: 28px; border-radius: 50%;
            background: #FF8C00; border: 2px solid #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .cam-btn i { font-size: 12px; }
        .super-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fff4e6; border: 1px solid #ffe0b2;
            color: #e65c00; font-size: 11px; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* Identity */
        .identity-block { padding: 0 32px 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 24px; }
        .identity-name  { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 2px; }
        .identity-email { font-size: 13px; color: #999; }

        /* Stats */
        .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; padding: 0 32px 24px; }
        .stat-card { background: #fff8f0; border: 1px solid #ffe5c2; border-radius: 14px; padding: 14px 12px; text-align: center; }
        .stat-val  { font-size: 20px; font-weight: 600; color: #FF8C00; margin-bottom: 3px; }
        .stat-lbl  { font-size: 11px; color: #bbb; letter-spacing: 0.3px; }

        /* Alert */
        .success-alert {
            display: flex; align-items: center; gap: 8px;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            color: #166534; font-size: 13px; padding: 10px 16px;
            border-radius: 12px; margin: 0 32px 24px;
        }

        /* Form */
        .form-wrap        { padding: 0 32px; }
        .section-label    { font-size: 11px; font-weight: 600; color: #bbb; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 16px; }
        .form-grid        { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid label  { display: block; font-size: 12px; font-weight: 600; color: #888; margin-bottom: 7px; }
        .form-grid input[type="text"],
        .form-grid input[type="email"] {
            width: 100%; padding: 10px 14px;
            background: #fafafa; border: 1px solid #e8e8e8;
            border-radius: 10px; color: #1a1a1a; font-size: 14px;
            outline: none; transition: border-color 0.2s, background 0.2s;
            font-family: inherit;
        }
        .form-grid input:focus { border-color: #FF8C00; background: #fff; box-shadow: 0 0 0 3px rgba(255,140,0,0.1); }
        .form-grid input[readonly] { color: #aaa; background: #f5f5f5; cursor: default; }
        .brand-pill {
            display: inline-flex; align-items: center; gap: 7px;
            background: #fff4e6; border: 1px solid #ffe0b2;
            color: #e65c00; padding: 10px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; width: 100%;
        }

        /* Footer */
        .card-footer-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 32px 28px; margin-top: 24px;
            border-top: 1px solid #f0f0f0;
        }
        .footer-hint { font-size: 12px; color: #bbb; display: flex; align-items: center; gap: 5px; }
        .footer-hint i { font-size: 14px; color: #FF8C00; }
        .footer-hint b { color: #888; font-weight: 500; }

        .btn-save {
            display: inline-flex; align-items: center; gap: 8px;
            background: transparent; border: 1.5px solid #FF8C00;
            color: #FF8C00; padding: 10px 28px; border-radius: 10px;
            font-size: 14px; font-weight: 500; cursor: pointer;
            font-family: inherit; transition: background 0.2s, color 0.2s, transform 0.1s;
        }
        .btn-save:hover  { background: #fff4e6; color: #e65c00; }
        .btn-save:active { transform: scale(0.97); }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">

        <!-- ── Header (unchanged) ── -->
        <header class="admin-header d-flex justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--orange-primary);">Home</a>
                        </li>
                        <li class="breadcrumb-item active">Account Settings</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Admin Profile</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3">
                    <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
                    <small class="text-muted"><?php echo ($admin['Admin_Level'] == 1) ? 'Super Admin' : 'Manager'; ?></small>
                </div>
                <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
            </div>
        </header>

        <!-- ── Profile Card ── -->
        <div class="profile-card">

            <!-- Banner -->
            <div class="profile-banner">
                <div class="banner-title">Admin Profile</div>
                <div class="banner-sub">Account Settings</div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data">

                <!-- Avatar overlap -->
                <div class="overlap-row">
                    <div class="avatar-wrap">
                        <div class="avatar-box" id="avatarBox">
                            <?php if (!empty($admin['Admin_Image']) && $admin['Admin_Image'] !== 'default_admin.png'): ?>
                                <img src="../uploads/admin/<?php echo $admin['Admin_Image']; ?>?t=<?php echo time(); ?>" id="preview">
                            <?php else: ?>
                                <span id="initials-text"><?php echo htmlspecialchars($initials); ?></span>
                                <img id="preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <label for="admin_pic" class="cam-btn">
                            <i class="bi bi-camera-fill"></i>
                        </label>
                        <input type="file" name="admin_pic" id="admin_pic" hidden accept="image/*" onchange="previewImg(this)">
                    </div>
                    <div class="super-badge">
                        <i class="bi bi-shield-check"></i>
                        <?php echo ($admin['Admin_Level'] == 1) ? 'Super Admin' : 'Manager'; ?>
                    </div>
                </div>

                <!-- Identity -->
                <div class="identity-block">
                    <div class="identity-name"><?php echo htmlspecialchars($admin['Admin_Name']); ?></div>
                    <div class="identity-email"><?php echo htmlspecialchars($admin['Admin_Email']); ?></div>
                </div>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-val"><?php echo $orders_count !== null ? $orders_count : 'ALL'; ?></div>
                        <div class="stat-lbl">Orders managed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val"><?php echo !empty($admin['Admin_Brand']) ? htmlspecialchars($admin['Admin_Brand']) : 'ALL'; ?></div>
                        <div class="stat-lbl">Brand access</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val"><?php echo $products_count !== null ? $products_count : 'ALL'; ?></div>
                        <div class="stat-lbl">Products managed</div>
                    </div>
                </div>

                <!-- Success alert -->
                <?php if ($message): ?>
                <div class="success-alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="form-wrap">
                    <div class="section-label">Edit Information</div>
                    <div class="form-grid">
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="admin_name" value="<?php echo htmlspecialchars($admin['Admin_Name']); ?>" required>
                        </div>
                        <div>
                            <label>Email Address</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($admin['Admin_Email']); ?>" required>
                        </div>
                        <div>
                            <label>Assigned Brand</label>
                            <div class="brand-pill">
                                <i class="bi bi-tag-fill"></i>
                                <?php echo !empty($admin['Admin_Brand']) ? htmlspecialchars($admin['Admin_Brand']) : 'ALL ACCESS'; ?>
                            </div>
                        </div>
                        <div>
                            <label>Account Level</label>
                            <input type="text" value="Level <?php echo $admin['Admin_Level']; ?> — <?php echo ($admin['Admin_Level'] == 1) ? 'Super Admin' : 'Manager'; ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer-bar">
                    <span class="footer-hint">
                        <i class="bi bi-info-circle"></i>
                        Image saved as <b><?php echo str_replace(' ', '', $admin['Admin_Name']); ?>.jpg</b>
                    </span>
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="bi bi-floppy-fill"></i>
                        Save Profile
                    </button>
                </div>

            </form>
        </div><!-- /.profile-card -->

    </div><!-- /.main-content -->
</div><!-- /.wrapper -->

<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('preview');
            var initials = document.getElementById('initials-text');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (initials) initials.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>