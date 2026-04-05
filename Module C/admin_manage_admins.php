<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo "<script>alert('Access Denied. Super Admin privileges required.'); window.location.href='admin_dashboard.php';</script>";
    exit();
}

$msg = "";

if (isset($_POST['toggle_status'])) {
    $id_to_edit = intval($_POST['admin_id']);
    $current_status = $_POST['current_status'];
    
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';

    if ($id_to_edit == $_SESSION['admin_id']) {
        $msg = "<div class='alert alert-danger'>You cannot deactivate your own account!</div>";
    } else {
        $check_sql = "SELECT role FROM admins WHERE admin_id = $id_to_edit";
        $check_res = $conn->query($check_sql);
        $target_role = $check_res->fetch_assoc()['role'];

        if ($target_role === 'superadmin') {
             $msg = "<div class='alert alert-danger'>Cannot deactivate another Super Admin.</div>";
        } else {
            $sql = "UPDATE admins SET status = '$new_status' WHERE admin_id = $id_to_edit";
            if ($conn->query($sql)) {
                $action_word = ($new_status === 'active') ? "Activated" : "Deactivated";
                $color = ($new_status === 'active') ? "success" : "warning";
                $msg = "<div class='alert alert-$color'>Admin account has been $action_word.</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
            }
        }
    }
}

$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $s = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE username LIKE '%$s%' OR full_name LIKE '%$s%' OR email LIKE '%$s%'";
}

$sql = "SELECT * FROM admins $where_clause ORDER BY role DESC, admin_id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: white; border-right: 1px solid #dee2e6; }
        .nav-link { color: #333; }
        .card { border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .admin-inactive { opacity: 0.6; background-color: #f9f9f9; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-light pt-3">
            <h5 class="px-3 mb-3 text-muted">System</h5>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">← Dashboard</a></li>
                <li class="nav-item"><a class="nav-link fw-bold active" href="#">Manage Admins</a></li>
                <li class="nav-item"><a class="nav-link text-success" href="add_admin.php">+ Add New Admin</a></li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h1 class="h2">Manage Administrators</h1>
                <a href="add_admin.php" class="btn btn-success">
                    <i class="bi bi-person-plus-fill me-2"></i>Add Admin
                </a>
            </div>

            <?php echo $msg; ?>

            <div class="card mb-4">
                <div class="card-body bg-white rounded">
                    <form method="GET" class="row g-2">
                        <div class="col-md-10">
                            <input type="text" name="search" class="form-control" placeholder="Search by Name, Username or Email..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Admin Details</th>
                                    <th>Status</th>
                                    <th>Role</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr class="<?php echo ($row['status'] == 'inactive') ? 'admin-inactive' : ''; ?>">
                                            <td class="ps-4 text-muted">#<?php echo $row['admin_id']; ?></td>
                                            <td>
                                                <strong><?php echo $row['full_name']; ?></strong><br>
                                                <small class="text-muted">@<?php echo $row['username']; ?></small>
                                                <div class="small text-muted mt-1"><i class="bi bi-envelope"></i> <?php echo $row['email']; ?></div>
                                            </td>
                                            <td>
                                                <?php if($row['status'] == 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($row['role'] == 'superadmin'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-shield-lock-fill me-1"></i> SUPER</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark">Admin</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if ($row['admin_id'] != $_SESSION['admin_id'] && $row['role'] != 'superadmin'): ?>
                                                    
                                                    <form method="POST" style="display:inline-block;">
                                                        <input type="hidden" name="admin_id" value="<?php echo $row['admin_id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $row['status']; ?>">
                                                        <input type="hidden" name="toggle_status" value="1">
                                                        
                                                        <?php if($row['status'] == 'active'): ?>
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deactivate <?php echo $row['username']; ?>? They will not be able to login.');">
                                                                <i class="bi bi-slash-circle"></i> Deactivate
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Re-activate <?php echo $row['username']; ?>?');">
                                                                <i class="bi bi-check-circle"></i> Activate
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>

                                                <?php elseif ($row['admin_id'] == $_SESSION['admin_id']): ?>
                                                    <span class="text-muted small fst-italic">You</span>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-lock"></i> Locked</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-4">No admins found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

</body>
</html>