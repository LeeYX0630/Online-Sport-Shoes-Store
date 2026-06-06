<?php
// admin_notifications.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id   = $_SESSION['admin_id'];
$admin_role = $_SESSION['role'];
$username   = $_SESSION['username'] ?? 'Admin';

// ── Header avatar (same as admin_manage_products.php) ──────────────────────
$admin_image = 'default_admin.png';
if ($admin_id) {
    $img_res = $conn->query("SELECT Admin_Image FROM admin WHERE Admin_Id = $admin_id");
    if ($img_res && $img_row = $img_res->fetch_assoc()) {
        $admin_image = !empty($img_row['Admin_Image'])
            ? $img_row['Admin_Image']
            : ($_SESSION['admin_image'] ?? 'default_admin.png');
    }
} else {
    $admin_image = $_SESSION['admin_image'] ?? 'default_admin.png';
}

// ── Notification visibility condition ──────────────────────────────────────
if ($admin_role == 1 || $admin_role == 2) {
    $notif_condition = "(n.Admin_Id IS NULL OR n.Admin_Id = $admin_id)";
} else {
    $notif_condition = "n.Admin_Id = $admin_id";
}

// ── AJAX: Mark single as read ──────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $nid = intval($_POST['notif_id']);
    // 使用 INSERT INTO ... ON DUPLICATE KEY UPDATE 确保不会重复插入
    $stmt = $conn->prepare("INSERT INTO admin_notification_status (Admin_Id, Notif_Id, Is_Read) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE Is_Read = 1");
    $stmt->bind_param("ii", $admin_id, $nid);
    $stmt->execute();
    echo json_encode(['status' => 'ok']);
    exit();
}

// ── AJAX: Mark all as read ─────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    // 找出当前管理员能看到、且还没读的所有通知 ID
    $sql = "SELECT n.Notif_Id FROM notification n 
            LEFT JOIN admin_notification_status s ON n.Notif_Id = s.Notif_Id AND s.Admin_Id = $admin_id
            WHERE $notif_condition AND (s.Is_Read IS NULL OR s.Is_Read = 0) AND (s.Is_Deleted IS NULL OR s.Is_Deleted = 0)";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nid = $row['Notif_Id'];
            $conn->query("INSERT INTO admin_notification_status (Admin_Id, Notif_Id, Is_Read) VALUES ($admin_id, $nid, 1) ON DUPLICATE KEY UPDATE Is_Read = 1");
        }
    }
    echo json_encode(['status' => 'ok']);
    exit();
}

// ── AJAX: Delete single ────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_one') {
    $nid = intval($_POST['notif_id']);
    $stmt = $conn->prepare("INSERT INTO admin_notification_status (Admin_Id, Notif_Id, Is_Deleted) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE Is_Deleted = 1");
    $stmt->bind_param("ii", $admin_id, $nid);
    $stmt->execute();
    echo json_encode(['status' => 'ok']);
    exit();
}

// ── AJAX: Clear all ────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    // 把当前管理员能看到的所有通知，在状态表中全部标记为已删除
    $sql = "SELECT n.Notif_Id FROM notification n WHERE $notif_condition";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nid = $row['Notif_Id'];
            $conn->query("INSERT INTO admin_notification_status (Admin_Id, Notif_Id, Is_Deleted) VALUES ($admin_id, $nid, 1) ON DUPLICATE KEY UPDATE Is_Deleted = 1");
        }
    }
    echo json_encode(['status' => 'ok']);
    exit();
}

// ── Fetch notifications (使用 LEFT JOIN 关联状态表) ────────────────────────
$notifications = [];
// 通过 LEFT JOIN 拿到当前登录管理员对每条通知的独立已读/删除状态
$query_sql = "
    SELECT n.*, 
           IFNULL(s.Is_Read, 0) AS Notif_Is_Read, 
           IFNULL(s.Is_Deleted, 0) AS Notif_Is_Deleted
    FROM notification n
    LEFT JOIN admin_notification_status s ON n.Notif_Id = s.Notif_Id AND s.Admin_Id = $admin_id
    WHERE $notif_condition AND (s.Is_Deleted IS NULL OR s.Is_Deleted = 0)
    ORDER BY n.Notif_Created_At DESC
";

$res = $conn->query($query_sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
}

$unread_count = count(array_filter($notifications, fn($n) => $n['Notif_Is_Read'] == 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* ── Global (matches admin_manage_products.php) ── */
        :root { --sidebar-width: 260px; --primary-orange: #FF8C00; --hover-orange: #e66000; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', 'Inter', sans-serif; margin: 0; }
        .main-wrapper { flex-grow: 1; margin-left: var(--sidebar-width); padding: 25px; min-height: 100vh; }

        /* ── Header (unchanged from products page) ── */
        .admin-header { background: white; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .admin-profile-img { width: 42px; height: 42px; border-radius: 50%; border: 2px solid var(--primary-orange); object-fit: cover; }

        /* ── Notification card ── */
        .notif-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }

        /* Top bar */
        .notif-topbar { padding: 18px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .notif-title-row { display: flex; align-items: center; gap: 10px; }
        .notif-title-row h5 { font-size: 16px; font-weight: 700; color: #1a1a1a; margin: 0; }
        .unread-badge { background: var(--primary-orange); color: #fff; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 20px; }
        .topbar-actions { display: flex; gap: 8px; }
        .btn-ghost { background: transparent; border: 1.5px solid #e0e0e0; color: #666; font-size: 12px; padding: 7px 14px; border-radius: 8px; cursor: pointer; font-family: inherit; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-ghost:hover { border-color: var(--primary-orange); color: var(--primary-orange); }
        .btn-ghost-danger:hover { border-color: #e53e3e; color: #e53e3e; }

        /* Filter tabs */
        .filter-tabs { padding: 14px 24px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 6px; flex-wrap: wrap; }
        .tab { font-size: 12px; font-weight: 600; padding: 6px 16px; border-radius: 20px; border: 1.5px solid #e0e0e0; color: #888; background: transparent; cursor: pointer; font-family: inherit; transition: all 0.2s; }
        .tab.active { background: var(--primary-orange); border-color: var(--primary-orange); color: #fff; }
        .tab:hover:not(.active) { border-color: var(--primary-orange); color: var(--primary-orange); }

        /* List */
        .notif-list { padding: 12px 16px; display: flex; flex-direction: column; gap: 6px; }

        .notif-item { display: flex; align-items: flex-start; gap: 14px; padding: 14px 16px; border-radius: 12px; border: 1px solid #f0f0f0; cursor: pointer; transition: all 0.2s; position: relative; }
        .notif-item:hover { background: #fffbf5; border-color: rgba(255,140,0,0.3); }
        .notif-item.unread { border-left: 3px solid var(--primary-orange); background: #fffcf7; }
        .notif-item.read { opacity: 0.65; }

        .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
        .dot.active { background: var(--primary-orange); }
        .dot.inactive { background: transparent; }

        .notif-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .icon-new_order      { background: #fff4e6; color: #FF8C00; }
        .icon-status_change  { background: #e8f5e9; color: #2e7d32; }
        .icon-low_stock      { background: #fde8e8; color: #c0392b; }

        .notif-body { flex: 1; min-width: 0; }
        .notif-row1 { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3px; }
        .notif-name { font-size: 14px; font-weight: 700; color: #1a1a1a; }
        .notif-time { font-size: 11px; color: #bbb; white-space: nowrap; margin-left: 8px; }
        .notif-msg { font-size: 12px; color: #777; line-height: 1.5; margin-bottom: 6px; }

        .notif-tag { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag-new_order     { background: #fff4e6; color: #e65c00; }
        .tag-status_change { background: #e8f5e9; color: #2e7d32; }
        .tag-low_stock     { background: #fde8e8; color: #c0392b; }

        .notif-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .btn-icon { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e0e0e0; background: #fff; color: #aaa; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all 0.2s; }
        .btn-icon:hover { border-color: var(--primary-orange); color: var(--primary-orange); }
        .btn-icon.del:hover { border-color: #e53e3e; color: #e53e3e; }

        /* Empty state */
        .empty-state { padding: 60px 24px; text-align: center; color: #ccc; }
        .empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }
        .empty-state p { font-size: 14px; }

        @media (max-width: 991px) { .main-wrapper { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

<?php include_once '../includes/admin_sidebar.php'; ?>

<div class="main-wrapper">

    <!-- ── Header (same structure as admin_manage_products.php) ── -->
    <header class="admin-header d-flex justify-content-between align-items-center">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <a href="admin_dashboard.php" class="text-decoration-none" style="color: var(--primary-orange);">Home</a>
                    </li>
                    <li class="breadcrumb-item active" style="color: #6c757d;">Notifications</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">Notifications</h4>
        </div>
        <div class="d-flex align-items-center">
            <div class="text-end me-3 text-dark">
                <div class="fw-bold"><?php echo htmlspecialchars($username); ?></div>
                <small class="text-muted"><?php
                    if ($admin_role == 1)      echo 'Super Admin';
                    elseif ($admin_role == 2)  echo 'Admin';
                    else                       echo 'Brand Manager';
                ?></small>
            </div>
            <img src="../uploads/admin/<?php echo $admin_image; ?>?t=<?php echo time(); ?>" class="admin-profile-img">
        </div>
    </header>

    <!-- ── Notification Card ── -->
    <div class="notif-card">

        <!-- Top bar -->
        <div class="notif-topbar">
            <div class="notif-title-row">
                <h5>All Notifications</h5>
                <?php if ($unread_count > 0): ?>
                    <span class="unread-badge" id="unreadBadge"><?php echo $unread_count; ?> unread</span>
                <?php else: ?>
                    <span class="unread-badge" id="unreadBadge" style="display:none;">0 unread</span>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <button class="btn-ghost" onclick="markAllRead()">
                    <i class="bi bi-check2-all"></i> Mark all as read
                </button>
                <button class="btn-ghost btn-ghost-danger" onclick="clearAll()">
                    <i class="bi bi-trash3"></i> Clear all
                </button>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <button class="tab active" onclick="filterTab(this,'all')">All</button>
            <button class="tab" onclick="filterTab(this,'unread')">Unread</button>
            <button class="tab" onclick="filterTab(this,'new_order')">New Order</button>
            <button class="tab" onclick="filterTab(this,'status_change')">Status Change</button>
            <button class="tab" onclick="filterTab(this,'low_stock')">Low Stock</button>
        </div>

        <!-- Notification list -->
        <div class="notif-list" id="notifList">
        <?php if (empty($notifications)): ?>
            <div class="empty-state" id="emptyState">
                <i class="bi bi-bell-slash"></i>
                <p>No notifications yet.</p>
            </div>
        <?php else: ?>
            <?php
            // Icon map
            $icon_map = [
                'new_order'     => 'bi-bag-check-fill',
                'status_change' => 'bi-arrow-repeat',
                'low_stock'     => 'bi-exclamation-triangle-fill',
            ];
            // Label map
            $label_map = [
                'new_order'     => 'New Order',
                'status_change' => 'Status Change',
                'low_stock'     => 'Low Stock',
            ];

            foreach ($notifications as $n):
                $is_unread  = $n['Notif_Is_Read'] == 0;
                $type       = $n['Notif_Type'];
                $icon_class = $icon_map[$type] ?? 'bi-bell';
                $label      = $label_map[$type] ?? $type;

                // Relative time
                $created  = strtotime($n['Notif_Created_At']);
                $diff     = time() - $created;
                if ($diff < 60)           $time_label = 'Just now';
                elseif ($diff < 3600)     $time_label = floor($diff/60) . ' min ago';
                elseif ($diff < 86400)    $time_label = floor($diff/3600) . ' hr ago';
                elseif ($diff < 172800)   $time_label = 'Yesterday';
                else                      $time_label = date('d M Y', $created);
            ?>
            <div class="notif-item <?php echo $is_unread ? 'unread' : 'read'; ?>"
                 data-id="<?php echo $n['Notif_Id']; ?>"
                 data-type="<?php echo $type; ?>"
                 data-read="<?php echo $n['Notif_Is_Read']; ?>"
                 onclick="handleClick(this, '<?php echo addslashes($n['Notif_Link'] ?? ''); ?>')">

                <div class="dot <?php echo $is_unread ? 'active' : 'inactive'; ?>"></div>

                <div class="notif-icon icon-<?php echo $type; ?>">
                    <i class="bi <?php echo $icon_class; ?>"></i>
                </div>

                <div class="notif-body">
                    <div class="notif-row1">
                        <span class="notif-name"><?php echo htmlspecialchars($n['Notif_Title']); ?></span>
                        <span class="notif-time"><?php echo $time_label; ?></span>
                    </div>
                    <div class="notif-msg"><?php echo htmlspecialchars($n['Notif_Message']); ?></div>
                    <span class="notif-tag tag-<?php echo $type; ?>"><?php echo $label; ?></span>
                </div>

                <div class="notif-actions" onclick="event.stopPropagation()">
                    <?php if ($is_unread): ?>
                    <div class="btn-icon" title="Mark as read" onclick="markOneRead(this, <?php echo $n['Notif_Id']; ?>)">
                        <i class="bi bi-check2"></i>
                    </div>
                    <?php endif; ?>
                    <div class="btn-icon del" title="Delete" onclick="deleteOne(this, <?php echo $n['Notif_Id']; ?>)">
                        <i class="bi bi-x"></i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div><!-- /.notif-list -->

    </div><!-- /.notif-card -->
</div><!-- /.main-wrapper -->

<script>
// ── Filter tabs ─────────────────────────────────────────────────────────────
function filterTab(el, type) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.notif-item').forEach(item => {
        if (type === 'all')         item.style.display = '';
        else if (type === 'unread') item.style.display = item.dataset.read === '0' ? '' : 'none';
        else                        item.style.display = item.dataset.type === type ? '' : 'none';
    });
}

// ── Click → mark read then navigate ─────────────────────────────────────────
function handleClick(el, link) {
    const id = el.dataset.id;
    if (el.dataset.read === '0') {
        ajaxPost({ action: 'mark_read', notif_id: id }, () => {
            el.classList.remove('unread');
            el.classList.add('read');
            el.dataset.read = '1';
            el.querySelector('.dot').className = 'dot inactive';
            const readBtn = el.querySelector('.btn-icon:not(.del)');
            if (readBtn) readBtn.remove();
            updateBadge();
        });
    }
    if (link) setTimeout(() => window.location.href = link, 200);
}

// ── Mark one as read ─────────────────────────────────────────────────────────
function markOneRead(btn, id) {
    const item = btn.closest('.notif-item');
    ajaxPost({ action: 'mark_read', notif_id: id }, () => {
        item.classList.remove('unread');
        item.classList.add('read');
        item.dataset.read = '1';
        item.querySelector('.dot').className = 'dot inactive';
        btn.remove();
        updateBadge();
    });
}

// ── Mark all as read ─────────────────────────────────────────────────────────
function markAllRead() {
    ajaxPost({ action: 'mark_all_read' }, () => {
        document.querySelectorAll('.notif-item.unread').forEach(item => {
            item.classList.remove('unread');
            item.classList.add('read');
            item.dataset.read = '1';
            item.querySelector('.dot').className = 'dot inactive';
            const readBtn = item.querySelector('.btn-icon:not(.del)');
            if (readBtn) readBtn.remove();
        });
        updateBadge();
    });
}

// ── Delete one ───────────────────────────────────────────────────────────────
function deleteOne(btn, id) {
    const item = btn.closest('.notif-item');
    ajaxPost({ action: 'delete_one', notif_id: id }, () => {
        item.style.transition = 'opacity 0.3s';
        item.style.opacity = '0';
        setTimeout(() => { item.remove(); updateBadge(); checkEmpty(); }, 300);
    });
}

// ── Clear all ────────────────────────────────────────────────────────────────
function clearAll() {
    Swal.fire({
        title: 'Clear all notifications?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF8C00',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Yes, clear all'
    }).then(result => {
        if (!result.isConfirmed) return;
        ajaxPost({ action: 'clear_all' }, () => {
            document.querySelectorAll('.notif-item').forEach(item => {
                item.style.transition = 'opacity 0.3s';
                item.style.opacity = '0';
                setTimeout(() => item.remove(), 300);
            });
            setTimeout(() => { updateBadge(); checkEmpty(); }, 350);
        });
    });
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function updateBadge() {
    const count = document.querySelectorAll('.notif-item.unread').length;
    const badge = document.getElementById('unreadBadge');
    badge.textContent = count + ' unread';
    badge.style.display = count > 0 ? '' : 'none';
}

function checkEmpty() {
    const list = document.getElementById('notifList');
    if (!list.querySelector('.notif-item')) {
        list.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-bell-slash"></i>
                <p>No notifications yet.</p>
            </div>`;
    }
}

function ajaxPost(data, callback) {
    const fd = new FormData();
    for (const key in data) fd.append(key, data[key]);
    fetch('admin_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => { if (res.status === 'ok') callback(); });
}
</script>
</body>
</html>