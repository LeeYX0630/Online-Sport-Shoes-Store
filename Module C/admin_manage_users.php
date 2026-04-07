<?php
// for admin to manage users 
session_start();
require_once '../includes/db_connection.php';

// Security Check: Must be Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch Users with Search Functionality
$search = "";
$sql = "SELECT * FROM users WHERE role = 'Customer' ORDER BY user_id DESC"; 

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $sql = "SELECT * FROM users WHERE role = 'Customer' AND (full_name LIKE '%$search%' OR email LIKE '%$search%') ORDER BY user_id DESC";
}

$result = $conn->query($sql);

$page_title = "User Management";
include '../includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark">User Management</h2>
            <p class="text-muted">Manage customer accounts (Block/Unblock)</p>
        </div>
        
        <form action="" method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control" placeholder="Search Name or Email..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i></button>
            <?php if(!empty($search)): ?>
                <a href="admin_manage_users.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>User</th>
                            <th>Status</th> <th>Contact</th>
                            <th>Joined Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-muted">#<?php echo $row['user_id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <?php if(!empty($row['profile_image']) && $row['profile_image'] !== 'default.png'): ?>
                                                    <img src="../Module A/uploads/<?php echo $row['profile_image']; ?>" class="rounded-circle" style="width:40px; height:40px; object-fit:cover;">
                                                <?php else: ?>
                                                    <span class="fw-bold text-secondary"><?php echo strtoupper(substr($row['full_name'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php if ($row['status'] === 'Blocked'): ?>
                                            <span class="badge bg-danger rounded-pill px-3">Blocked</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill px-3">Active</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td class="text-muted small">
                                    <?php 
                                    echo !empty($row['created_at']) ? date("d M Y", strtotime($row['created_at'])) : "Unknown"; 
                                    ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <?php if ($row['status'] === 'Active'): ?>
                                            <a href="admin_toggle_status.php?id=<?php echo $row['user_id']; ?>&current_status=Active" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('⚠️ Confirm Block:\n\nUser will not be able to login.\nAre you sure?');"
                                               title="Block User">
                                                <i class="bi bi-slash-circle"></i> Block
                                            </a>
                                        <?php else: ?>
                                            <a href="admin_toggle_status.php?id=<?php echo $row['user_id']; ?>&current_status=Blocked" 
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('✅ Confirm Unblock:\n\nRestore access for this user?');"
                                               title="Unblock User">
                                                <i class="bi bi-check-circle"></i> Unblock
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Total Customers: <?php echo $result->num_rows; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>