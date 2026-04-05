<?php
<?php
// superadmin_verify.php - Page for normal admin to verify as superadmin before adding new admin
session_start();
require_once '../includes/db_connection.php';

// Check if user is logged in as admin (either normal or super)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: admin_login.php");
    exit();
}

// If already superadmin, redirect directly to add_admin.php
if ($_SESSION['role'] === 'superadmin') {
    header("Location: add_admin.php");
    exit();
}

$verify_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_super'])) {
    $sa_username = trim($_POST['sa_username']);
    $sa_password = trim($_POST['sa_password']);

    // Validate superadmin credentials
    $sql = "SELECT * FROM admins WHERE username = ? AND role = 'superadmin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sa_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($sa_password, $row['password'])) {
            // Verification successful: upgrade session to superadmin
            $_SESSION['role'] = 'superadmin';
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['username'] = $row['username'];
            header("Location: add_admin.php");
            exit();
        } else {
            $verify_msg = "Invalid password for Super Admin.";
        }
    } else {
        $verify_msg = "Super Admin account not found.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .verify-container { max-width: 400px; margin: 100px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="verify-container">
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill text-warning" style="font-size: 3rem;"></i>
        <h2 class="mt-3">Super Admin Verification</h2>
        <p class="text-muted">To add a new admin, please verify your Super Admin credentials.</p>
    </div>

    <?php if (!empty($verify_msg)): ?>
        <div class="alert alert-danger text-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $verify_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="verify_super" value="1">
        <div class="mb-3">
            <label class="form-label fw-bold">Super Admin Username</label>
            <input type="text" name="sa_username" class="form-control" required placeholder="Enter superadmin username">
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold">Password</label>
            <input type="password" name="sa_password" class="form-control" required placeholder="Enter password">
        </div>
        <button type="submit" class="btn btn-warning w-100 fw-bold">Verify & Proceed</button>
    </form>

    <div class="text-center mt-3">
        <a href="admin_dashboard.php" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>