<?php
// get_user_info.php
require_once '../includes/db_connection.php';

if (!isset($_GET['id'])) {
    echo "<div class='p-4 text-danger'>User ID missing.</div>";
    exit();
}

$id = intval($_GET['id']);

// 获取用户信息
$user_res = $conn->query("SELECT * FROM user WHERE User_Id = $id");
$user = $user_res->fetch_assoc();

// 获取购买历史
$order_res = $conn->query("SELECT * FROM `order` WHERE User_Id = $id ORDER BY Order_Date DESC");

if (!$user) {
    echo "<div class='p-4 text-danger'>User not found.</div>";
    exit();
}
?>

<div class="modal-header border-0 shadow-sm">
    <h5 class="modal-title fw-bold" style="color: #FF8C00;">
        <i class="bi bi-person-vcard me-2"></i>Customer Intelligence
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body p-4">
    <div class="row g-4">
        <div class="col-md-5 border-end">
            <div class="mb-4">
                <label class="text-muted small fw-bold text-uppercase mb-2 d-block">Account Profile</label>
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center fw-bold me-3" style="width: 55px; height: 55px; color: #FF8C00; border: 2px solid #fff2e6; font-size: 1.2rem;">
                        <?php echo strtoupper(substr($user['User_Name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['User_Name']); ?></h6>
                        <small class="text-muted">Customer #ID-<?php echo $user['User_Id']; ?></small>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p class="mb-2 text-dark small"><i class="bi bi-envelope text-orange me-2"></i><?php echo htmlspecialchars($user['User_Email']); ?></p>
                    <p class="mb-2 text-dark small"><i class="bi bi-telephone text-orange me-2"></i><?php echo $user['User_Phone']; ?></p>
                    <p class="mb-0 text-dark small">
                        <i class="bi bi-shield-check text-orange me-2"></i>Status: 
                        <span class="badge <?php echo ($user['User_Status'] == 'Active') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> rounded-pill">
                            <?php echo $user['User_Status']; ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="p-3 rounded-4 bg-light border">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block">Total Orders</small>
                        <span class="fw-bold fs-5 text-orange"><?php echo $order_res->num_rows; ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Date of Birth</small>
                        <span class="fw-bold" style="font-size: 0.9rem;">
                            <?php 
                                // 检查 User_DateOfBirth 字段是否有值
                                echo (!empty($user['User_DateOfBirth']) && $user['User_DateOfBirth'] != '0000-00-00') 
                                     ? date('Y-m-d', strtotime($user['User_DateOfBirth'])) 
                                     : 'Not Set'; 
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <label class="text-muted small fw-bold text-uppercase mb-3 d-block">Purchase History</label>
            
            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-hover align-middle">
                    <thead class="sticky-top bg-white">
                        <tr class="small" style="color: #FF8C00; border-bottom: 2px solid #fff2e6;">
                            <th>Order ID</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($order_res->num_rows > 0): ?>
                            <?php while ($o = $order_res->fetch_assoc()): ?>
                            <tr style="font-size: 0.85rem;">
                                <td class="fw-bold">#ORD-<?php echo $o['Order_Id']; ?></td>
                                <td class="text-muted"><?php echo date('M d, Y', strtotime($o['Order_Date'])); ?></td>
                                <td class="text-end fw-bold">RM <?php echo number_format($o['Order_Amount'], 2); ?></td>
                                <td class="text-center">
                                    <?php 
                                        $s = $o['Order_Status'];
                                        $badge = ($s == 'Completed') ? 'bg-success' : (($s == 'Cancelled') ? 'bg-danger' : 'bg-warning text-dark');
                                    ?>
                                    <span class="badge <?php echo $badge; ?>" style="font-size: 0.7rem;"><?php echo $s; ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted small">
                                    <i class="bi bi-bag-x fs-2 d-block mb-2 opacity-25"></i>
                                    No transaction records.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer border-0">
    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
</div>

<style>
    .text-orange { color: #FF8C00; }
</style>