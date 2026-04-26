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

$msg = "";

// --- 2. 逻辑处理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current_admin_level != 1) {
        $msg = "<script>window.onload = () => { Swal.fire('Denied', 'Only Super Admin can manage promos.', 'error'); }</script>";
    } else {
        // Add Promo
        if (isset($_POST['add_promo'])) {
            $name = $conn->real_escape_string($_POST['promo_name']); // 新增字段
            $code = strtoupper(trim($_POST['promo_code'])); 
            $value = floatval($_POST['promo_value']);
            $expiry = $_POST['Expired_Date'];
            $status = $_POST['promo_status'];

            $check = $conn->query("SELECT * FROM promo WHERE Promo_Code = '$code'");
            if ($check->num_rows > 0) {
                $msg = "<script>window.onload = () => { Swal.fire('Error', 'Promo code already exists!', 'error'); }</script>";
            } else {
                // SQL 插入包含 Promo_Name
                $sql = "INSERT INTO promo (Promo_Name, Promo_Code, Promo_Value, Expired_Date, Promo_Status) 
                        VALUES ('$name', '$code', $value, '$expiry', '$status')";
                if ($conn->query($sql)) {
                    $msg = "<script>window.onload = () => { Swal.fire('Success', 'Promo created successfully!', 'success'); }</script>";
                }
            }
        }

        // Delete Promo
        if (isset($_POST['delete_promo'])) {
            $p_id = intval($_POST['promo_id']);
            $conn->query("DELETE FROM promo WHERE Promo_Id = $p_id");
            $msg = "<script>window.onload = () => { Swal.fire('Deleted!', 'The promo has been removed.', 'success'); }</script>";
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

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="promo_id" id="delete_promo_id">
    <input type="hidden" name="delete_promo" value="1">
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
                                <input type="date" name="Expired_Date" class="form-control" required <?php echo ($current_admin_level != 1) ? 'disabled' : ''; ?>>
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
                                                            <button type="button" class="btn btn-link text-danger p-0" onclick="confirmDelete(<?php echo $row['Promo_Id']; ?>, '<?php echo $row['Promo_Code']; ?>')">
                                                                <i class="bi bi-trash-fill fs-5"></i>
                                                            </button>
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
function confirmDelete(id, code) {
    Swal.fire({
        title: 'Are you sure?',
        text: "Delete promo code: " + code,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF8C00',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_promo_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    })
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>