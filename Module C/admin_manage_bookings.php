<?php
// Module C/admin_manage_bookings.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Module A/admin_login.php");
    exit();
}

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $booking_id = intval($_POST['booking_id']);

    if ($action === 'cancel') {
        $sql = "UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = $booking_id";
        if ($conn->query($sql)) $msg = "<div class='alert alert-warning'>Booking #$booking_id cancelled.</div>";
    } elseif ($action === 'delete') {
        $sql = "DELETE FROM bookings WHERE booking_id = $booking_id";
        if ($conn->query($sql)) $msg = "<div class='alert alert-danger'>Booking #$booking_id deleted permanently.</div>";
    }
}

$where_clauses = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(b.booking_id LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

if (isset($_GET['room_id']) && !empty($_GET['room_id'])) {
    $rid = intval($_GET['room_id']);
    $where_clauses[] = "b.room_id = '$rid'";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $st = $conn->real_escape_string($_GET['status']);
    $where_clauses[] = "b.booking_status = '$st'";
}

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $dt = $conn->real_escape_string($_GET['date']);
    $where_clauses[] = "b.check_in_date = '$dt'";
}

$sql = "SELECT b.*, r.room_name, u.full_name 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.room_id 
        LEFT JOIN users u ON b.user_id = u.user_id";

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY b.booking_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #343a40; }
        .sidebar a { color: rgba(255,255,255,.75); text-decoration: none; padding: 10px 20px; display: block; }
        .sidebar a:hover { color: #fff; background: rgba(255,255,255,.1); }
        .sidebar a.active { color: #fff; background: #0d6efd; }
        
        .table-card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .action-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; padding: 0; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar py-3">
            <h4 class="text-white px-3 mb-4">Admin Panel</h4>
            <ul class="nav flex-column">
                <li class="nav-item"><a href="admin_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a href="admin_manage_bookings.php" class="active">Manage Bookings</a></li>
                <li class="nav-item"><a href="../index.php" target="_blank">View Site <i class="bi bi-box-arrow-up-right ms-1"></i></a></li>
            </ul>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 py-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Booking Management</h2>
                <a href="admin_generate_report.php" target="_blank" class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>Export Report
                </a>
            </div>

            <?php echo $msg; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body bg-light rounded">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search ID or Customer Name" value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="room_id" class="form-select">
                                <option value="">All Rooms</option>
                                <?php
                                $r_res = $conn->query("SELECT room_id, room_name FROM rooms");
                                while($r = $r_res->fetch_assoc()){
                                    $selected = (isset($_GET['room_id']) && $_GET['room_id'] == $r['room_id']) ? 'selected' : '';
                                    echo "<option value='".$r['room_id']."' $selected>".$r['room_name']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="confirmed" <?php if(isset($_GET['status']) && $_GET['status']=='confirmed') echo 'selected'; ?>>Confirmed</option>
                                <option value="cancelled" <?php if(isset($_GET['status']) && $_GET['status']=='cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date" class="form-control" value="<?php echo $_GET['date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Customer</th>
                                <th>Room</th>
                                <th>Check-In / Out</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">#<?php echo $row['booking_id']; ?></td>
                                        <td>
                                            <?php echo !empty($row['full_name']) ? $row['full_name'] : '<span class="text-muted">Unknown</span>'; ?>
                                        </td>
                                        <td><?php echo $row['room_name']; ?></td>
                                        <td>
                                            <div class="small"><?php echo $row['check_in_date']; ?></div>
                                            <div class="small text-muted"><?php echo $row['check_out_date']; ?></div>
                                        </td>
                                        <td class="fw-bold text-success">RM <?php echo number_format($row['total_price'], 2); ?></td>
                                        <td>
                                            <?php 
                                                $badge = ($row['booking_status'] == 'confirmed') ? 'bg-success' : 'bg-danger';
                                                echo "<span class='badge $badge'>" . ucfirst($row['booking_status']) . "</span>";
                                            ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="generate_receipt.php?booking_id=<?php echo $row['booking_id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark action-btn me-1" title="Print Receipt">
                                                <i class="bi bi-receipt"></i>
                                            </a>

                                            <?php if($row['booking_status'] != 'cancelled'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $row['booking_id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning action-btn me-1" title="Cancel Booking">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete permanently?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['booking_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted">No bookings found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

</body>
</html>