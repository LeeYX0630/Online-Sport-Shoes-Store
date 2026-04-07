<?php
include '../includes/db_connection.php';
include '../includes/header.php';

$swalCode = "";

// =======================================================
// (Delete)
// =======================================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $get_rid = $conn->query("SELECT room_id FROM categories WHERE category_id='$id'");
    
    if ($get_rid->num_rows > 0) {
        $rid_row = $get_rid->fetch_assoc();
        $r_id = $rid_row['room_id'];

        $conn->query("DELETE FROM categories WHERE category_id='$id'");
        
        // Sync prices
        $sync_sql = "SELECT MIN(price_per_night) as min_p, MAX(price_per_night) as max_p FROM categories WHERE room_id='$r_id'";
        $sync_res = $conn->query($sync_sql);
        $sync_row = $sync_res->fetch_assoc();
        
        $new_min = $sync_row['min_p'] ?? 0;
        $new_max = $sync_row['max_p'] ?? 0;

        $conn->query("UPDATE rooms SET min_price='$new_min', max_price='$new_max' WHERE room_id='$r_id'");
    }

    $swalCode = "Swal.fire({
        title: 'Deleted!',
        text: 'The category has been deleted.',
        icon: 'success',
        confirmButtonColor: '#28a745'
    }).then(() => {
        window.location.href = 'admin_manage_categories.php';
    });";
}

// =======================================================
// (Save Data)
// =======================================================
if (isset($_POST['save_data'])) {
    $cat_id = $_POST['cat_id_hidden'] ?? '';
    $room_id_select = $_POST['homestay_select']; 
    
    $cat_name = $_POST['category_select'];
    if ($cat_name === 'other') {
        $cat_name = trim($conn->real_escape_string($_POST['category_new_input']));
    }

    $price = floatval($_POST['price_per_night']);
    $pax = intval($_POST['max_pax']);
    $desc = $conn->real_escape_string($_POST['description']);

    $target_room_id = $room_id_select;
    if (!empty($cat_id)) {
        $current_cat_query = $conn->query("SELECT room_id FROM categories WHERE category_id='$cat_id'");
        if ($current_cat_query->num_rows > 0) {
            $current_row = $current_cat_query->fetch_assoc();
            $target_room_id = $current_row['room_id'];
        }
    }

    // Get room price limits
    $limit_sql = "SELECT min_price, max_price FROM rooms WHERE room_id='$target_room_id'";
    $limit_res = $conn->query($limit_sql);
    $limit_row = $limit_res->fetch_assoc();
    
    $room_min = floatval($limit_row['min_price']);
    $room_max = floatval($limit_row['max_price']);

    // Validation
    if ($price < 0 || $pax < 0) {
        $swalCode = "Swal.fire({ title: 'Error', text: 'Price and Pax cannot be negative!', icon: 'error', confirmButtonColor: '#d33' });";
    } elseif (empty($cat_name)) {
        $swalCode = "Swal.fire({ title: 'Error', text: 'Category name cannot be empty!', icon: 'error', confirmButtonColor: '#d33' });";
    } elseif ($price < $room_min || $price > $room_max) {
        $errorMsg = "Price (RM $price) must be between RM $room_min and RM $room_max (as set in Room Details).";
        $swalCode = "Swal.fire({ title: 'Invalid Price', text: '$errorMsg', icon: 'error', confirmButtonColor: '#d33' });";
    } else {
        $operation_success = false;
        $successTitle = "";

        if (!empty($cat_id)) {
            // Edit
            $check = $conn->query("SELECT category_id FROM categories WHERE room_id='$target_room_id' AND category_name='$cat_name' AND category_id != '$cat_id'");
            if ($check->num_rows > 0) {
                $swalCode = "Swal.fire({ title: 'Duplicate Name', text: 'This name is already used in this Homestay.', icon: 'warning', confirmButtonColor: '#333' });";
            } else {
                $sql = "UPDATE categories SET 
                        category_name='$cat_name', 
                        price_per_night='$price', 
                        max_pax='$pax', 
                        description='$desc' 
                        WHERE category_id='$cat_id'";
                
                if ($conn->query($sql) === TRUE) {
                    $operation_success = true;
                    $successTitle = "Edit Complete";
                }
            }
        } else {
            // Add
            $check = $conn->query("SELECT category_id FROM categories WHERE room_id='$target_room_id' AND category_name='$cat_name'");
            if ($check->num_rows > 0) {
                $swalCode = "Swal.fire({ title: 'Duplicate', text: 'This Homestay already has a category named $cat_name.', icon: 'warning', confirmButtonColor: '#333' });";
            } else {
                $sql = "INSERT INTO categories (room_id, category_name, price_per_night, max_pax, description) 
                        VALUES ('$target_room_id', '$cat_name', '$price', '$pax', '$desc')";
                
                if ($conn->query($sql) === TRUE) {
                    $operation_success = true;
                    $successTitle = "Add Complete";
                }
            }
        }

        if ($operation_success) {
            $swalCode = "Swal.fire({ 
                title: '$successTitle', 
                text: 'Data saved successfully.', 
                icon: 'success', 
                confirmButtonColor: '#28a745' 
            }).then(() => {
                window.location.href = 'admin_manage_categories.php';
            });";
        }
    }
}

// =======================================================
//  (Filter & Search)
// =======================================================
$where_clauses = [];

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $s = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(rooms.room_name LIKE '%$s%' OR categories.category_name LIKE '%$s%')";
}
if (isset($_GET['filter_cat']) && !empty($_GET['filter_cat'])) {
    $f_cat = $conn->real_escape_string($_GET['filter_cat']);
    $where_clauses[] = "categories.category_name = '$f_cat'";
}
if (isset($_GET['filter_min']) && $_GET['filter_min'] !== '') {
    $min = floatval($_GET['filter_min']);
    $where_clauses[] = "categories.price_per_night >= $min";
}
if (isset($_GET['filter_max']) && $_GET['filter_max'] !== '') {
    $max = floatval($_GET['filter_max']);
    $where_clauses[] = "categories.price_per_night <= $max";
}
if (isset($_GET['filter_desc']) && $_GET['filter_desc'] !== '') {
    $f_desc = $conn->real_escape_string($_GET['filter_desc']);
    $where_clauses[] = "categories.description LIKE '%$f_desc%'";
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
    <title>Manage Homestays Categories</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .container { max-width: 1300px; margin: 40px auto; padding: 0 20px; }
        div:where(.swal2-container) { z-index: 20000 !important; }

        .btn-back { display: inline-flex; align-items: center; margin-bottom: 20px; margin-top: 10px; color: #555; text-decoration: none; font-weight: bold; font-size: 14px; background-color: #e9ecef; padding: 8px 20px; border-radius: 4px; transition: all 0.3s ease; }
        .btn-back:hover { background-color: #dde2e6; color: #333; transform: translateX(-3px); }

        h2 { text-align: center; color: #333; margin-bottom: 20px; }

        /* Filter Bar (Top) */
        .filter-bar { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #555; white-space: nowrap; line-height: 1.2; }
        .filter-group select, .filter-group input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; height: 38px; box-sizing: border-box; width: 200px; margin:0; }
        .filter-actions { display: flex; gap: 10px; }
        .btn-filter { height: 38px; padding: 0 20px; background-color: #28a745 !important; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; box-sizing: border-box; }
        .btn-clear { height: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0 20px; background-color: #dc3545 !important; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; box-sizing: border-box; border: none; }

        /* Action Bar (Middle) */
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .list-title { font-size: 20px; font-weight: bold; color: #333; margin: 0; }
        .search-add-group { display: flex; align-items: center; gap: 15px; height: 40px; }
        
        /* Styled to look like a form but works via JS */
        .search-box-wrapper { display: flex; height: 100%; align-items: stretch; }
        .search-box-wrapper input { height: 40px; padding: 0 12px; border: 1px solid #ccc; border-right: none; border-radius: 4px 0 0 4px; width: 250px; outline: none; box-sizing: border-box; margin:0; }
        .search-box-wrapper button { height: 40px; padding: 0 20px; background: #333; color: #fff; border: 1px solid #333; border-radius: 0 4px 4px 0; cursor: pointer; font-weight: bold; box-sizing: border-box; margin:0; line-height: normal; }
        
        .btn-add-new { height: 40px; background: #28a745; color: white; padding: 0 20px; border-radius: 4px; font-weight: bold; border: 1px solid #28a745; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; box-sizing: border-box; margin:0; text-decoration: none; font-size: 14px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        table th { background: #343a40; color: white; padding: 12px; text-align: left; }
        table td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; color: #333; font-size: 14px; }
        .homestay-name-cell { font-weight: bold; background-color: #f8f9fa; vertical-align: top; }
        .btn-edit, .btn-del { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 30px; border-radius: 3px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; margin-right: 5px; }
        .btn-edit { background: #28a745 !important; } 
        .btn-del { background: #dc3545 !important; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 25px; width: 500px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); animation: slideDown 0.3s ease-out; position: relative; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; border: none; background: none; cursor: pointer; }
        .close-btn:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        #newCategoryDiv { display: none; margin-top: 5px; }
        #newCategoryDiv input { border: 1px solid #28a745; background-color: #f0fff4; }
    </style>

    <script>
        function toggleCategorySelect() {
            var select = document.getElementById('catSelect');
            var inputDiv = document.getElementById('newCategoryDiv');
            var inputField = document.getElementById('categoryInput');
            if (select.value === 'other') {
                inputDiv.style.display = 'block';
                inputField.required = true;
            } else {
                inputDiv.style.display = 'none';
                inputField.required = false;
            }
        }

        function confirmDelete(catId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', 
                cancelButtonColor: '#333',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin_manage_categories.php?delete=' + catId;
                }
            })
        }

        function openModal(mode, data = {}) {
            var title = document.getElementById('modalTitle');
            var roomSelect = document.getElementById('homestaySelect');
            document.getElementById('catId').value = data.cat_id || '';
            document.getElementById('roomPrice').value = data.price || '';
            document.getElementById('catDesc').value = data.desc || ''; 
            document.getElementById('catPax').value = data.pax || ''; 
            
            var catSelect = document.getElementById('catSelect');
            var found = false;
            if (data.cat_name) {
                for (var i = 0; i < catSelect.options.length; i++) {
                    if (catSelect.options[i].text === data.cat_name) {
                        catSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if(!found) catSelect.value = ''; 
            } else { catSelect.value = ''; }
            document.getElementById('newCategoryDiv').style.display = 'none';
            roomSelect.value = data.room_id || '';

            if (mode === 'edit') {
                title.innerText = 'Edit Category Detail';
                roomSelect.disabled = true; 
                document.getElementById('homestayIdHidden').value = data.room_id;
            } else {
                title.innerText = 'Add New Category'; 
                roomSelect.disabled = false;
                document.getElementById('homestayIdHidden').value = '';
            }
            document.getElementById('categoryModal').style.display = 'flex';
        }
        function closeModal() { document.getElementById('categoryModal').style.display = 'none'; }
        window.onclick = function(event) { if (event.target == document.getElementById('categoryModal')) closeModal(); }

        // ★★★ VALIDATE FILTER (Handles both Search & Filter Click) ★★★
        function validateFilter() {
            var minInput = document.getElementById('filterMin').value;
            var maxInput = document.getElementById('filterMax').value;
            var min = parseFloat(minInput);
            var max = parseFloat(maxInput);

            // 1. Negative Check
            if ((minInput !== "" && min < 0) || (maxInput !== "" && max < 0)) {
                Swal.fire({ title: 'Invalid Price', text: 'Prices cannot be negative!', icon: 'error', confirmButtonColor: '#d33' });
                return;
            }
            // 2. Logic Check
            if (minInput !== "" && maxInput !== "" && min > max) {
                Swal.fire({ title: 'Invalid Filter', text: 'Min Price cannot be greater than Max Price!', icon: 'error', confirmButtonColor: '#d33' });
                return;
            }
            
            // 3. Submit the Form
            document.getElementById('filterForm').submit();
        }
        
        // Handle "Enter" key in Search Bar to trigger validation
        function handleEnter(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
                validateFilter();
            }
        }
    </script>
</head>
<body>

<div class="container">
    <a href="../Module C/admin_dashboard.php" class="btn-back">&larr; Back to Dashboard</a>
    <h2>Manage Homestays Categories</h2>

    <form class="filter-bar" method="get" id="filterForm">
        <div class="filter-group">
            <label>Filter Category</label>
            <select name="filter_cat">
                <option value="">All Categories</option>
                <?php
                $cats = $conn->query("SELECT DISTINCT category_name FROM categories ORDER BY category_name ASC");
                while($c = $cats->fetch_assoc()) {
                    $sel = (isset($_GET['filter_cat']) && $_GET['filter_cat'] == $c['category_name']) ? 'selected' : '';
                    echo "<option value='".$c['category_name']."' $sel>".$c['category_name']."</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Min Price (RM)</label>
            <input type="number" name="filter_min" id="filterMin" placeholder="100" value="<?php echo htmlspecialchars($_GET['filter_min'] ?? ''); ?>">
        </div>
        <div class="filter-group">
            <label>Max Price (RM)</label>
            <input type="number" name="filter_max" id="filterMax" placeholder="500" value="<?php echo htmlspecialchars($_GET['filter_max'] ?? ''); ?>">
        </div>

        <div class="filter-group">
            <label>Description Keyword</label>
            <input type="text" name="filter_desc" placeholder="e.g. WiFi" value="<?php echo htmlspecialchars($_GET['filter_desc'] ?? ''); ?>">
        </div>
        
        <div class="filter-group">
            <label style="visibility: hidden;">Action</label> 
            <div class="filter-actions">
                <button type="button" class="btn-filter" onclick="validateFilter()">APPLY FILTER</button>
                <?php if(!empty($_GET)) echo '<a href="admin_manage_categories.php" class="btn-clear">CLEAR</a>'; ?>
            </div>
        </div>
    </form>
    <div class="action-bar">
        <h3 class="list-title">Homestay List</h3>
        
        <div class="search-add-group">
            <div class="search-box-wrapper">
                <input type="text" name="search" placeholder="Search Homestay or Category..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       form="filterForm" onkeypress="handleEnter(event)">
                
                <button type="button" onclick="validateFilter()">SEARCH</button>
            </div>

            <button type="button" class="btn-add-new" onclick="openModal('add')">+ Add New Category</button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="25%">Homestay Name</th>
                <th width="20%">Category</th>
                <th width="15%">Price (RM)</th>
                <th width="5%">Pax</th>
                <th width="20%">Description</th>
                <th width="15%">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT categories.*, rooms.room_name 
                    FROM categories 
                    JOIN rooms ON categories.room_id = rooms.room_id 
                    $where_sql 
                    ORDER BY rooms.room_name ASC, categories.category_name ASC";
            
            $result = $conn->query($sql);
            $groupedData = [];
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $groupedData[$row['room_name']][] = $row;
                }
            }

            if (!empty($groupedData)) {
                foreach ($groupedData as $name => $rows) {
                    $count = count($rows);
                    $isFirst = true;
                    foreach ($rows as $row) {
                        echo "<tr>";
                        if ($isFirst) {
                            echo "<td rowspan='$count' class='homestay-name-cell'>" . $row['room_name'] . "</td>";
                            $isFirst = false;
                        }
                        echo "<td>" . $row['category_name'] . "</td>";
                        echo "<td>RM " . number_format($row['price_per_night'], 2) . "</td>";
                        echo "<td>" . $row['max_pax'] . "</td>";
                        echo "<td>" . substr($row['description'], 0, 30) . "...</td>";
                        echo "<td>";
                        
                        $descSafe = htmlspecialchars($row['description'], ENT_QUOTES);
                        $jsData = json_encode([
                            'cat_id'=>$row['category_id'],
                            'room_id'=>$row['room_id'], 
                            'room_name'=>$row['room_name'],
                            'cat_name'=>$row['category_name'],
                            'price'=>$row['price_per_night'],
                            'desc'=>$descSafe,
                            'pax'=>$row['max_pax']
                        ]);
                        
                        echo "<button class='btn-edit' onclick='openModal(\"edit\", $jsData)'>EDIT</button>";
                        echo "<button class='btn-del' onclick='confirmDelete(" . $row['category_id'] . ")'>DEL</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center; padding:20px;'>No results found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<div id="categoryModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Category</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form action="" method="post">
            <input type="hidden" name="cat_id_hidden" id="catId">
            <input type="hidden" name="homestay_select" id="homestayIdHidden">
            <div class="form-group">
                <label>Homestay Name</label>
                <select name="homestay_select" id="homestaySelect" onchange="document.getElementById('homestayIdHidden').value = this.value">
                    <option value="">-- Choose Existing Homestay --</option>
                    <?php
                    $r_res = $conn->query("SELECT * FROM rooms ORDER BY room_name ASC");
                    while($r = $r_res->fetch_assoc()) echo "<option value='".$r['room_id']."'>".$r['room_name']."</option>";
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_select" id="catSelect" onchange="toggleCategorySelect()" required>
                    <option value="">-- Choose Category --</option>
                    <?php
                    $c_res = $conn->query("SELECT DISTINCT category_name FROM categories ORDER BY category_name ASC");
                    while($c = $c_res->fetch_assoc()) echo "<option value='".$c['category_name']."'>".$c['category_name']."</option>";
                    ?>
                    <option value="other" style="color:blue; font-weight:bold;">+ Other (Create New Category)</option>
                </select>
                <div id="newCategoryDiv">
                    <input type="text" name="category_new_input" id="categoryInput" placeholder="Enter New Category Name">
                </div>
            </div>
            <div class="form-group">
                <label>Price Per Night (RM)</label>
                <input type="number" step="0.01" min="0" name="price_per_night" id="roomPrice" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Max Pax</label>
                <input type="number" min="1" name="max_pax" id="catPax" required placeholder="2">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="catDesc" rows="3" placeholder="Enter category description..."></textarea>
            </div>
            <button type="submit" name="save_data" class="btn-save">SAVE</button>
        </form>
    </div>
</div>

<?php if (!empty($swalCode)) { echo "<script>$swalCode</script>"; } ?>
<?php include '../includes/footer.php'; ?>

</body>
</html>