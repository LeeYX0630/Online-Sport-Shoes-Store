<?php

include '../includes/db_connection.php';
include '../includes/header.php';

$swalCode = ""; 

function uploadImage($file) {
    $target_dir = "../uploads/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $filename = time() . "_" . basename($file["name"]);
    $target_file = $target_dir . $filename;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($fileType, $allowed)) {
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $filename;
        }
    }
    return false;
}

// 1. Delete Logic
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $delSql = "DELETE FROM rooms WHERE room_id='$id'";
    if ($conn->query($delSql) === TRUE) {
        $swalCode = "Swal.fire({
            title: 'Deleted!',
            text: 'The room has been deleted.',
            icon: 'success',
            confirmButtonColor: '#28a745'
        }).then(() => { window.location.href = 'admin_manage_rooms.php'; });";
    } else {
        $swalCode = "Swal.fire({ title: 'Error', text: '" . $conn->error . "', icon: 'error' });";
    }
}

// 2. Add / Edit Logic
if (isset($_POST['save_room'])) {
    
    $id = $_POST['room_id'] ?? '';
    $name = trim($conn->real_escape_string($_POST['room_name'])); 
    $desc = $conn->real_escape_string($_POST['description']);
    $fac = $conn->real_escape_string($_POST['facilities']);
    
    $min = floatval($_POST['min_price']);
    $max = floatval($_POST['max_price']);
    
    if ($min < 0 || $max < 0) {
        $swalCode = "Swal.fire({ title: 'Invalid Price', text: 'Prices cannot be negative!', icon: 'error', confirmButtonColor: '#d33' });";
    } elseif ($min > $max) {
        $swalCode = "Swal.fire({ title: 'Logic Error', text: 'Min Price cannot be greater than Max Price!', icon: 'error', confirmButtonColor: '#d33' });";
    } else {
        $checkSql = (!empty($id)) 
            ? "SELECT room_id FROM rooms WHERE room_name = '$name' AND room_id != '$id'"
            : "SELECT room_id FROM rooms WHERE room_name = '$name'";
        
        $checkRes = $conn->query($checkSql);
        
        if ($checkRes->num_rows > 0) {
            $swalCode = "Swal.fire({ title: 'Duplicate Name', text: 'This Homestay Name already exists.', icon: 'warning', confirmButtonColor: '#333' });";
        } else {
            $img_sql_part = "";
            if (!empty($_FILES['room_image']['name'])) {
                $uploaded = uploadImage($_FILES['room_image']);
                if ($uploaded) {
                    $img_sql_part = ", room_image='$uploaded'";
                }
            }

            if (!empty($id)) {
                $sql = "UPDATE rooms SET room_name='$name', description='$desc', facilities='$fac', min_price='$min', max_price='$max' $img_sql_part WHERE room_id='$id'";
                if ($conn->query($sql)) {
                    $swalCode = "Swal.fire({ title: 'Edit Complete', text: 'Success!', icon: 'success', confirmButtonColor: '#28a745' }).then(() => { window.location.href = 'admin_manage_rooms.php'; });";
                } else {
                    $swalCode = "Swal.fire({ title: 'Error', text: '" . $conn->error . "', icon: 'error' });";
                }
            } else {
                $img_name = (!empty($_FILES['room_image']['name'])) ? uploadImage($_FILES['room_image']) : '';
                $sql = "INSERT INTO rooms (room_name, description, facilities, room_image, min_price, max_price) VALUES ('$name', '$desc', '$fac', '$img_name', '$min', '$max')";
                if ($conn->query($sql)) {
                    $swalCode = "Swal.fire({ title: 'Add Complete', text: 'Success!', icon: 'success', confirmButtonColor: '#28a745' }).then(() => { window.location.href = 'admin_manage_rooms.php'; });";
                } else {
                    $swalCode = "Swal.fire({ title: 'Error', text: '" . $conn->error . "', icon: 'error' });";
                }
            }
        }
    }
}

// 3. Filter Logic
$where_clauses = [];
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $s = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "room_name LIKE '%$s%'";
}
if (isset($_GET['filter_min']) && $_GET['filter_min'] !== '') {
    $min = floatval($_GET['filter_min']);
    $where_clauses[] = "min_price >= $min"; 
}
if (isset($_GET['filter_max']) && $_GET['filter_max'] !== '') {
    $max = floatval($_GET['filter_max']);
    $where_clauses[] = "max_price <= $max";
}
if (isset($_GET['filter_desc']) && $_GET['filter_desc'] !== '') {
    $d = $conn->real_escape_string($_GET['filter_desc']);
    $where_clauses[] = "(description LIKE '%$d%' OR facilities LIKE '%$d%')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Rooms</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        div:where(.swal2-container) { z-index: 20000 !important; }

        .btn-back { display: inline-flex; align-items: center; margin-top:10px; margin-bottom: 20px; color: #555; text-decoration: none; font-weight: bold; font-size: 14px; background-color: #e9ecef; padding: 8px 20px; border-radius: 4px; transition: all 0.3s ease; }
        .btn-back:hover { background-color: #dde2e6; color: #333; transform: translateX(-3px); }
        h2 { text-align: center; color: #333; margin-bottom: 20px; margin-top:10px; }
        
        .filter-bar { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #555; white-space: nowrap; }
        .filter-group input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; height: 38px; box-sizing: border-box; width: 200px; margin: 0; }
        .btn-filter { height: 38px; padding: 0 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-clear { height: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; box-sizing: border-box; }

        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .list-title { font-size: 20px; font-weight: bold; color: #333; margin: 0; }
        .btn-add-new { height: 40px; background: #28a745; color: white; padding: 0 20px; border-radius: 4px; font-weight: bold; border: 1px solid #28a745; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; box-sizing: border-box; }

        .admin-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-bottom: 30px; }
        .room-card { background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e0e0e0; display: flex; flex-direction: column; transition: transform 0.3s ease; }
        .room-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .card-image { width: 100%; height: 200px; background-color: #eee; overflow: hidden; }
        .card-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .room-card:hover .card-image img { transform: scale(1.05); }
        .no-image-text { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #e9ecef; color: #6c757d; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .card-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 18px; font-weight: bold; color: #333; margin: 0 0 10px 0; }
        .room-price { font-size: 18px; color: #28a745; font-weight: bold; margin-bottom: 15px; }
        .room-price span { font-size: 14px; color: #999; font-weight: normal; }
        .card-info { font-size: 13px; color: #666; margin-bottom: 15px; line-height: 1.5; }
        .card-info strong { color: #333; }
        .card-actions { margin-top: auto; border-top: 1px solid #eee; padding-top: 15px; display: flex; gap: 10px; }
        .btn-card { flex: 1; text-align: center; padding: 10px 0; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 13px; cursor: pointer; border: none; color: white; display: inline-block;}
        .btn-edit { background-color: #28a745; }
        .btn-del { background-color: #dc3545; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 25px; width: 500px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); animation: slideDown 0.3s ease-out; position: relative; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; border: none; background: none; cursor: pointer; }
        .close-btn:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea, .form-group input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .form-row { display: flex; gap: 15px; }
        .form-row .col { flex: 1; }
    </style>

    <script>
        function openModal(mode, data = {}) {
            document.getElementById('modalTitle').innerText = mode === 'edit' ? 'Edit Room Details' : 'Add New Room';
            document.getElementById('roomId').value = data.id || '';
            document.getElementById('roomName').value = data.name || '';
            document.getElementById('roomDesc').value = data.desc || '';
            document.getElementById('roomFac').value = data.fac || '';
            document.getElementById('minPrice').value = data.min || '';
            document.getElementById('maxPrice').value = data.max || '';
            
            // Reset error
            var errorMsg = document.getElementById('priceError');
            if(errorMsg) errorMsg.style.display = 'none';

            var imgHint = document.getElementById('imgHint');
            imgHint.innerText = mode === 'edit' ? "Leave empty to keep current image." : "";
            document.getElementById('roomModal').style.display = 'flex';
        }
        function closeModal() { document.getElementById('roomModal').style.display = 'none'; }
        window.onclick = function(event) { if (event.target == document.getElementById('roomModal')) closeModal(); }
        
        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#333',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'admin_manage_rooms.php?delete=' + id;
            })
        }

        // Add/Edit Check
        function checkPriceAndSubmit() {
            var form = document.getElementById('roomForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            var min = parseFloat(document.getElementById('minPrice').value) || 0;
            var max = parseFloat(document.getElementById('maxPrice').value) || 0;
            
            var errorMsg = document.getElementById('priceError');

            if (min > max) {
                errorMsg.style.display = 'block';
                return; 
            } else {
                errorMsg.style.display = 'none';
            }

            if (min < 80 || max > 1000) {
                Swal.fire({
                    title: 'Check Price',
                    html: "Price range is <b>RM " + min + " - RM " + max + "</b>.<br>This seems unusual (Standard: 80 - 1000).<br>Are you sure?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745', 
                    cancelButtonColor: '#d33',    
                    confirmButtonText: 'Yes, Save it',
                    cancelButtonText: 'No, let me edit'
                }).then((result) => {
                    if (result.isConfirmed) { submitForm(); }
                });
            } else {
                submitForm(); 
            }
        }

        function submitForm() {
            var form = document.getElementById('roomForm');
            var hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'save_room';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            form.submit();
        }

        //  Filter Validation Check 
        function validateFilter() {
            var minInput = document.getElementById('filterMin').value;
            var maxInput = document.getElementById('filterMax').value;
            
            var min = parseFloat(minInput);
            var max = parseFloat(maxInput);

            // 1. Check Negative Values
            if ((minInput !== "" && min < 0) || (maxInput !== "" && max < 0)) {
                Swal.fire({
                    title: 'Invalid Price',
                    text: 'Filter prices cannot be negative!',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
                return;
            }

            // 2. Check Min > Max
            if (minInput !== "" && maxInput !== "" && min > max) {
                Swal.fire({
                    title: 'Invalid Filter',
                    text: 'Min Price cannot be greater than Max Price!',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
                return;
            } else {
                document.getElementById('filterForm').submit();
            }
        }
    </script>
</head>
<body>

<div class="container">
    <a href="../Module C/admin_dashboard.php" class="btn-back">&larr; Back to Dashboard</a>
    <h2>Manage Rooms (Homestays)</h2>

    <form class="filter-bar" method="get" id="filterForm">
        <div class="filter-group">
            <label>Search Name</label>
            <input type="text" name="search" placeholder="e.g. Sunset Villa" value="<?php echo $_GET['search'] ?? ''; ?>">
        </div>
        <div class="filter-group">
            <label>Min Price (RM)</label>
            <input type="number" name="filter_min" id="filterMin" placeholder="100" value="<?php echo $_GET['filter_min'] ?? ''; ?>">
        </div>
        <div class="filter-group">
            <label>Max Price (RM)</label>
            <input type="number" name="filter_max" id="filterMax" placeholder="500" value="<?php echo $_GET['filter_max'] ?? ''; ?>">
        </div>
        <div class="filter-group">
            <label>Description/Facilities</label>
            <input type="text" name="filter_desc" placeholder="e.g. Pool" value="<?php echo $_GET['filter_desc'] ?? ''; ?>">
        </div>
        <div class="filter-group">
            <label style="visibility: hidden;">Action</label>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-filter" onclick="validateFilter()">APPLY FILTER</button>
                <?php if(!empty($_GET)) echo '<a href="admin_manage_rooms.php" class="btn-clear">CLEAR</a>'; ?>
            </div>
        </div>
    </form>

    <div class="action-bar">
        <h3 class="list-title">Room List</h3>
        <button type="button" class="btn-add-new" onclick="openModal('add')">+ Add New Room</button>
    </div>

    <div class="admin-grid">
        <?php
        $sql = "SELECT * FROM rooms $where_sql ORDER BY room_name ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $hasImage = !empty($row['room_image']);
                $img_src = $hasImage ? "../uploads/" . $row['room_image'] : "";
                $jsData = json_encode([
                    'id' => $row['room_id'],
                    'name' => $row['room_name'],
                    'desc' => $row['description'],
                    'fac' => $row['facilities'],
                    'min' => $row['min_price'], 
                    'max' => $row['max_price']
                ]);
                
                $price_display = "";
                if ($row['min_price'] == 0 && $row['max_price'] == 0) { $price_display = "Price Pending"; } 
                elseif ($row['min_price'] == $row['max_price']) { $price_display = "RM " . number_format($row['min_price'], 2); } 
                else { $price_display = "RM " . number_format($row['min_price'],0) . " - " . number_format($row['max_price'],0); }
                ?>
                <div class="room-card">
                    <div class="card-image">
                        <?php if ($hasImage): ?>
                            <img src="<?php echo $img_src; ?>" alt="Img">
                        <?php else: ?>
                            <div class="no-image-text">Image Not Available</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo $row['room_name']; ?></h3>
                        <div class="room-price"><?php echo $price_display; ?> <span>/ night</span></div>
                        <div class="card-info">
                            <strong>Facilities:</strong> <?php echo substr($row['facilities'], 0, 40) . '...'; ?><br>
                            <strong>Desc:</strong> <?php echo substr($row['description'], 0, 60) . '...'; ?>
                        </div>
                        <div class="card-actions">
                            <button class="btn-card btn-edit" onclick='openModal("edit", <?php echo $jsData; ?>)'>EDIT</button>
                            <button class="btn-card btn-del" onclick="confirmDelete(<?php echo $row['room_id']; ?>)">DELETE</button>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p style='grid-column:1/-1; text-align:center;'>No rooms found.</p>";
        }
        ?>
    </div>
</div>

<div id="roomModal" class="modal-overlay">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <h3 id="modalTitle">Add New Room</h3>
        
        <form action="" method="post" enctype="multipart/form-data" id="roomForm">
            <input type="hidden" name="room_id" id="roomId">

            <div class="form-group">
                <label>Room Name</label>
                <input type="text" name="room_name" id="roomName" required placeholder="e.g. Sunset Villa">
            </div>

            <div class="form-group">
                <label>Price Range (RM)</label>
                <div class="form-row">
                    <div class="col">
                        <input type="number" step="0.01" name="min_price" id="minPrice" required placeholder="Min Price">
                    </div>
                    <div class="col">
                        <input type="number" step="0.01" name="max_price" id="maxPrice" required placeholder="Max Price">
                        
                        <small id="priceError" style="color: #dc3545; display: none; font-weight: bold; margin-top: 5px; font-size: 12px;">
                            Cannot be smaller than Min Price!
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="roomDesc" rows="3" placeholder="Describe the place..."></textarea>
            </div>

            <div class="form-group">
                <label>Facilities</label>
                <textarea name="facilities" id="roomFac" rows="2" placeholder="e.g. WiFi, Pool, Parking"></textarea>
            </div>

            <div class="form-group">
                <label>Room Image</label>
                <input type="file" name="room_image">
                <small id="imgHint" style="color:#666; font-size:12px;"></small>
            </div>

            <button type="button" class="btn-save" onclick="checkPriceAndSubmit()">SAVE ROOM</button>
        </form>
    </div>
</div>

<?php if (!empty($swalCode)) { echo "<script>$swalCode</script>"; } ?>
<?php include '../includes/footer.php'; ?>

</body>
</html>