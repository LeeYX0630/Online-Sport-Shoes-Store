<?php
// admin/admin_dashboard.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['username'] = 'superadmin';
    $_SESSION['role'] = 'superadmin';     
}

$verify_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_super'])) {
    $sa_username = $_POST['sa_username'];
    $sa_password = $_POST['sa_password'];

    $sql = "SELECT * FROM admins WHERE username = ? AND role = 'superadmin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sa_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($sa_password, $row['password'])) {
            $_SESSION['role'] = 'superadmin';
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['username'] = $row['username'];
            header("Location: add_admin.php");
            exit();
        } else {
            $verify_msg = "Wrong password!";
        }
    } else {
        $verify_msg = "Super Admin not found!";
    }
}

$res_count = $conn->query("SELECT COUNT(*) as total FROM bookings");
$total_bookings = $res_count->fetch_assoc()['total'];

$res_revenue = $conn->query("SELECT SUM(total_price) as revenue FROM bookings WHERE booking_status = 'confirmed'");
$total_revenue = $res_revenue->fetch_assoc()['revenue'] ?? 0;

$today = date('Y-m-d');
$res_upcoming = $conn->query("SELECT COUNT(*) as upcoming FROM bookings WHERE booking_status = 'confirmed' AND check_in_date >= '$today'");
$upcoming_bookings = $res_upcoming->fetch_assoc()['upcoming'];

$sql_recent = "SELECT b.*, r.room_name FROM bookings b JOIN rooms r ON b.room_id = r.room_id ORDER BY b.booking_id DESC LIMIT 5";
$recent_orders = $conn->query($sql_recent);

$days_data = [
    'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 
    'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0
];

$days_data = [
    'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 
    'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0
];

$sql_chart = "SELECT DAYNAME(check_in_date) as day_name, COUNT(*) as cnt 
              FROM bookings 
              WHERE booking_status = 'confirmed'
              GROUP BY day_name";
$res_chart = $conn->query($sql_chart);

if ($res_chart) {
    while ($row = $res_chart->fetch_assoc()) {
        $day = trim($row['day_name']);
        if (isset($days_data[$day])) {
            $days_data[$day] = (int)$row['cnt'];
        }
    }
}

$max_val = max(array_values($days_data)) + 1;

$chartConfig = [
    'type' => 'bar',
    'data' => [
        'labels' => array_keys($days_data),
        'datasets' => [[
            'label' => 'Bookings',
            'data' => array_values($days_data),
            'backgroundColor' => 'rgba(13, 110, 253, 0.6)',
            'borderColor' => 'rgba(13, 110, 253, 1)',
            'borderWidth' => 1,
            'borderRadius' => 5
        ]]
    ],
    'options' => [
        'plugins' => [
            'legend' => ['display' => false],
            'datalabels' => [
                'anchor' => 'end',
                'align' => 'top',
                'color' => '#666',
                'font' => ['weight' => 'bold']
            ]
        ],
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'suggestedMax' => $max_val,
                'grid' => ['color' => 'rgba(0,0,0,0.05)']
            ],
            'x' => [
                'grid' => ['display' => false]
            ]
        ]
    ]
];

$chartUrl = "https://quickchart.io/chart?c=" . rawurlencode(json_encode($chartConfig));
?>


<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Homestay System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
      .sidebar { min-height: 100vh; box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1); }
      
      .stat-card { transition: transform 0.2s; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
      .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 12px rgba(0,0,0,0.2); }
      
      .modal-backdrop { z-index: 1040; }
      .modal { z-index: 1050; }
    </style>
  </head>
  <body>

    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
      <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#">Homestay Admin 🏨</a>
      <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="navbar-nav">
        <div class="nav-item text-nowrap">
          <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="../Module%20A/logout.php?redirect=home" onclick="return confirm('Are you sure you want to sign out from Admin Panel?');">Sign out
    </a>
</div>
        </div>
      </div>
    </header>

    <div class="container-fluid">
      <div class="row">
        
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse"> 
          <div class="position-sticky pt-3">
            
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-2 mb-1 text-muted text-uppercase">
              <span>Core</span>
            </h6>
            <ul class="nav flex-column mb-3">
              <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                  <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link text-dark" href="admin_manage_bookings.php">
                  <i class="bi bi-calendar-check me-2"></i> Manage Bookings
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link text-dark" href="../Module A/admin_reset_user.php">
                  <i class="bi bi-people me-2"></i> Manage Users
                </a>
              </li>
            </ul>

            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-3 mb-1 text-muted text-uppercase">
              <span>Inventory</span>
            </h6>
            <ul class="nav flex-column mb-3">
              <li class="nav-item">
                <a class="nav-link text-dark" href="../Module B/admin_manage_rooms.php">
                  <i class="bi bi-house-door me-2"></i> Manage Rooms
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link text-dark" href="../Module B/admin_manage_categories.php">
                  <i class="bi bi-tags me-2"></i> Room Categories
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link text-dark" href="admin_manage_vouchers.php">
                  <i class="bi bi-ticket-perforated me-2"></i> Manage Vouchers
                </a>
              </li>
            </ul>

            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-3 mb-1 text-muted text-uppercase">
              <span>System</span>
            </h6>
            <ul class="nav flex-column mb-2">
              
              <?php if ($_SESSION['role'] === 'superadmin'): ?>
                  <li class="nav-item">
                    <a class="nav-link text-success" href="admin_manage_admins.php">
                        <i class="bi bi-person-gear me-2"></i> Manage Admins
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link text-success" href="add_admin.php">
                        <i class="bi bi-person-plus-fill me-2"></i> Add New Admin
                    </a>
                  </li>
              <?php else: ?>
                  <li class="nav-item">
                    <a class="nav-link text-secondary" href="#" data-bs-toggle="modal" data-bs-target="#superAdminModal">
                        <i class="bi bi-lock-fill me-2"></i> Manage Admins <small>(Locked)</small>
                    </a>
                  </li>
              <?php endif; ?>

            </ul>

            <hr class="my-3">

            <div class="p-3">
                <div class="d-flex align-items-center link-dark text-decoration-none">
                    <i class="bi bi-person-circle fs-4 me-2"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong><br>
                        <small class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></small>
                    </div>
                </div>
            </div>
          </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          
          <?php if (!empty($verify_msg)): ?>
            <div class="alert alert-danger mt-3 alert-dismissible fade show" role="alert">
                <?php echo $verify_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

            

          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Dashboard Overview</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
               <a href="admin_generate_report.php" target="_blank" class="btn btn-sm btn-danger me-2">
                   <i class="bi bi-file-earmark-pdf-fill me-1"></i> Export Report
               </a>

               <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary">
                   <i class="bi bi-box-arrow-up-right me-1"></i> View Live Site
               </a>
            </div>
          </div>

          <div class="row g-4 mb-4"> 
            
            <div class="col-12 col-md-6 col-lg-4"> 
                <div class="card bg-primary text-white stat-card h-100"> 
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-uppercase mb-0 opacity-75">Total Bookings</h6>
                            <h2 class="display-4 fw-bold my-2"><?php echo $total_bookings; ?></h2>
                            <p class="card-text small">Lifetime orders</p>
                        </div>
                        <i class="bi bi-cart3" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4"> 
                <div class="card bg-success text-white stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-0 opacity-75">Total Revenue</h6>
                                <h2 class="display-4 fw-bold my-2">RM <?php echo number_format($total_revenue, 0); ?></h2>
                                <p class="card-text small">Confirmed payments</p>
                            </div>
                            <i class="bi bi-currency-dollar" style="font-size: 3rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4"> 
                <div class="card bg-warning text-dark stat-card h-100"> 
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-0 opacity-75">Upcoming Stays</h6>
                                <h2 class="display-4 fw-bold my-2"><?php echo $upcoming_bookings; ?></h2>
                                <p class="card-text small">Check-ins pending</p>
                            </div>
                            <i class="bi bi-calendar-check" style="font-size: 3rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title fw-bold text-secondary">
                                <i class="bi bi-bar-chart-fill me-2"></i>Popular Check-in Days
                            </h5>
                            <span class="badge bg-light text-dark border">Real-time Data</span>
                        </div>
                        
                        <img src="<?php echo $chartUrl; ?>" class="img-fluid rounded" style="max-height: 300px; width: 100%; object-fit: contain;" alt="Chart">
                        
                        <div class="text-center mt-3 text-muted small">
                            Based on confirmed check-in dates.
                        </div>
                    </div>
                </div>
            </div>

          </div>

          <h3 class="mt-4">Recent Orders</h3>
          <div class="table-responsive bg-white shadow-sm rounded p-3">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">ID</th>
                  <th scope="col">Room</th>
                  <th scope="col">Dates</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($recent_orders->num_rows > 0): ?>
                    <?php while($row = $recent_orders->fetch_assoc()): ?>
                    <tr>
                      <td>#<?php echo $row['booking_id']; ?></td>
                      <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                      <td>
                          <small class="d-block text-muted">In: <?php echo $row['check_in_date']; ?></small>
                          <small class="d-block text-muted">Out: <?php echo $row['check_out_date']; ?></small>
                      </td>
                      <td>RM <?php echo number_format($row['total_price'], 2); ?></td>
                      <td>
                        <?php 
                            $badge = ($row['booking_status'] == 'confirmed') ? 'bg-success' : 'bg-secondary';
                            if ($row['booking_status'] == 'cancelled') $badge = 'bg-danger';
                            echo "<span class='badge $badge'>" . ucfirst($row['booking_status']) . "</span>";
                        ?>
                      </td>
                      <td>
                          <a href="admin_manage_bookings.php?booking_id=<?php echo $row['booking_id']; ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                      </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-3">No orders found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </main>
      </div>
    </div>

    <div class="modal fade" id="superAdminModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-dark text-white">
            <h5 class="modal-title" id="modalLabel">🔐 Super Admin Login Required</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>You need Super Admin privileges to add a new administrator. Please verify your credentials.</p>
            <form method="POST">
                <input type="hidden" name="verify_super" value="1">
                <div class="mb-3">
                    <label class="form-label">Super Admin Username</label>
                    <input type="text" name="sa_username" class="form-control" required placeholder="superadmin">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="sa_password" class="form-control" required placeholder="********">
                </div>
                <button type="submit" class="btn btn-primary w-100">Verify & Login</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>