<?php
// Module C/admin_manage_vouchers.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Module A/admin_login.php");
    exit();
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code'])); 
    $discount_value = floatval($_POST['discount_value']);
    $discount_type = $_POST['discount_type'];
    $min_spend = floatval($_POST['min_spend']);
    $expiry_date = $_POST['expiry_date'];

    if ($discount_value <= 0) {
        $msg = "<div class='alert alert-danger'>Error: Discount value must be greater than 0.</div>";
    } elseif ($min_spend < 0) {
        $msg = "<div class='alert alert-danger'>Error: Minimum spend cannot be negative.</div>";
    } elseif ($discount_type == 'percent' && $discount_value > 100) {
        $msg = "<div class='alert alert-danger'>Error: Percentage discount cannot exceed 100%.</div>";
    } else {
        $check = $conn->query("SELECT * FROM coupons WHERE code = '$code'");
        if ($check->num_rows > 0) {
            $msg = "<div class='alert alert-danger'>Error: Coupon Code '$code' already exists!</div>";
        } else {
            $sql = "INSERT INTO coupons (code, discount_value, discount_type, min_spend, expiry_date) 
                    VALUES ('$code', '$discount_value', '$discount_type', '$min_spend', '$expiry_date')";
            
            if ($conn->query($sql)) {
                $msg = "<div class='alert alert-success'>Voucher '$code' created successfully! Don't forget to distribute it.</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
            }
        }
    }
}

if (isset($_POST['delete_coupon'])) {
    $id = intval($_POST['coupon_id']);
    $conn->query("DELETE FROM coupons WHERE coupon_id = $id");
    $msg = "<div class='alert alert-warning'>Voucher deleted.</div>";
}

if (isset($_POST['distribute_coupon'])) {
    $cid = intval($_POST['coupon_id']);
    
    $sql_dist = "INSERT INTO user_coupons (user_id, coupon_id, status)
                 SELECT user_id, '$cid', 'active' FROM users 
                 WHERE role = 'Customer'
                 AND user_id NOT IN (SELECT user_id FROM user_coupons WHERE coupon_id = '$cid')";
                 
    if ($conn->query($sql_dist)) {
        $count = $conn->affected_rows;
        $msg = "<div class='alert alert-success'>Success! Voucher sent to $count new users.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Error distributing: " . $conn->error . "</div>";
    }
}

$where_clauses = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "code LIKE '%$search%'";
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $type = $conn->real_escape_string($_GET['type']);
    $where_clauses[] = "discount_type = '$type'";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $_GET['status'];
    if ($status === 'active') {
        $where_clauses[] = "expiry_date >= CURDATE()";
    } elseif ($status === 'expired') {
        $where_clauses[] = "expiry_date < CURDATE()";
    }
}

$sql_query = "SELECT * FROM coupons";
if (count($where_clauses) > 0) {
    $sql_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_query .= " ORDER BY expiry_date DESC";

$coupons = $conn->query($sql_query);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Vouchers | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #dee2e6; }
        .nav-link { color: #333; }
        .nav-link.active { background-color: #e9ecef; font-weight: bold; }
        .card { border: none; shadow: 0 2px 5px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-light pt-3">
            <h5 class="px-3 mb-3 text-muted">Admin Panel</h5>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">← Back to Dashboard</a></li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Vouchers & Coupons</h1>
            </div>

            <?php echo $msg; ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-body bg-light rounded">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search Voucher Code..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <option value="fixed" <?php if(isset($_GET['type']) && $_GET['type']=='fixed') echo 'selected'; ?>>Fixed Amount (RM)</option>
                                <option value="percent" <?php if(isset($_GET['type']) && $_GET['type']=='percent') echo 'selected'; ?>>Percentage (%)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php if(isset($_GET['status']) && $_GET['status']=='active') echo 'selected'; ?>>Active Only</option>
                                <option value="expired" <?php if(isset($_GET['status']) && $_GET['status']=='expired') echo 'selected'; ?>>Expired Only</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-search me-1"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm rounded-4">
                        <div class="card-header bg-dark text-white fw-bold">
                            <i class="bi bi-plus-circle me-2"></i>Create New Voucher
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="add_coupon" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Voucher Code</label>
                                    <input type="text" name="code" class="form-control text-uppercase" placeholder="e.g. SAVE20" required>
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label small fw-bold">Discount Value</label>
                                        <input type="number" step="0.01" min="0.01" name="discount_value" class="form-control" placeholder="10" required>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label small fw-bold">Type</label>
                                        <select name="discount_type" class="form-select">
                                            <option value="fixed">RM (Fixed)</option>
                                            <option value="percent">% (Percent)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Min Spend (RM)</label>
                                    <input type="number" name="min_spend" class="form-control" value="0" min="0">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Expiry Date</label>
                                    <input type="date" name="expiry_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold">Create Voucher</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card shadow-sm rounded-4">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Code</th>
                                            <th>Discount</th>
                                            <th>Min Spend</th>
                                            <th>Expiry</th>
                                            <th class="text-end pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($coupons->num_rows > 0): ?>
                                            <?php while($c = $coupons->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-primary"><?php echo $c['code']; ?></td>
                                                    <td>
                                                        <?php echo ($c['discount_type'] == 'percent') ? intval($c['discount_value'])."%" : "RM ".number_format($c['discount_value'],0); ?> OFF
                                                    </td>
                                                    <td>RM <?php echo $c['min_spend']; ?></td>
                                                    <td>
                                                        <?php 
                                                            $is_expired = (strtotime($c['expiry_date']) < time());
                                                            echo $is_expired ? "<span class='badge bg-danger'>Expired</span>" : "<span class='text-dark'>".$c['expiry_date']."</span>"; 
                                                        ?>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <?php if (!$is_expired): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Send this voucher to ALL registered customers?');">
                                                            <input type="hidden" name="coupon_id" value="<?php echo $c['coupon_id']; ?>">
                                                            <button type="submit" name="distribute_coupon" class="btn btn-sm btn-success me-1" title="Send to All Users">
                                                                <i class="bi bi-gift-fill"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>

                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher permanently?');">
                                                            <input type="hidden" name="coupon_id" value="<?php echo $c['coupon_id']; ?>">
                                                            <button type="submit" name="delete_coupon" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-ticket-perforated display-4 d-block mb-2 text-secondary"></i>
                                                No vouchers found matching your criteria.
                                            </td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>