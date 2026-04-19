<?php
// admin_manage_products.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_role = $_SESSION['role'];
$admin_brand_id = $_SESSION['admin_brand'] ?? 0;
$username = $_SESSION['username'] ?? 'Admin';

$swalCode = ""; 

// 1. 删除逻辑
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $checkSql = ($admin_role == 1) ? "" : " AND Brand_Id = '$admin_brand_id'";
    $delSql = "DELETE FROM product WHERE Pro_Id = '$id' $checkSql";
    if ($conn->query($delSql) === TRUE) {
        $swalCode = "Swal.fire({ title: 'Deleted!', text: 'Product removed successfully.', icon: 'success', confirmButtonColor: '#FF6B00' }).then(() => { window.location.href = 'admin_manage_products.php'; });";
    }
}

// 2. 获取筛选参数
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['cat'] ?? '';
$brand_filter = $_GET['brand'] ?? '';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 1000;

// 3. 构建 SQL 查询
$queryCondition = ($admin_role == 1) ? "1=1" : "p.Brand_Id = '$admin_brand_id'";

if (!empty($search)) {
    $queryCondition .= " AND (p.Pro_Name LIKE '%$search%' OR p.Pro_Id LIKE '%$search%')";
}
if (!empty($cat_filter)) {
    $queryCondition .= " AND p.Cat_Id = '$cat_filter'";
}
if (!empty($brand_filter)) {
    $queryCondition .= " AND p.Brand_Id = '$brand_filter'";
}
$queryCondition .= " AND p.Pro_Price BETWEEN $min_price AND $max_price";

$sql = "SELECT p.*, b.Brand_Name, c.Cat_Name 
        FROM product p
        LEFT JOIN brand b ON p.Brand_Id = b.Brand_Id 
        LEFT JOIN category c ON p.Cat_Id = c.Cat_Id 
        WHERE $queryCondition 
        ORDER BY p.Pro_Id DESC";
$result = $conn->query($sql);

$categories = $conn->query("SELECT * FROM category");
$brands = $conn->query("SELECT * FROM brand");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Online Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --sidebar-width: 260px; --primary-orange: #FF6B00; --hover-orange: #e66000; }
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .main-wrapper { margin-left: var(--sidebar-width); min-height: 100vh; width: calc(100% - var(--sidebar-width)); }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; border-bottom: 1px solid #edf2f7; position: sticky; top: 0; z-index: 100; }
        .user-profile-circle { width: 40px; height: 40px; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 600; }

        .product-content-area { padding: 30px 40px; }
        
        /* Filter Card容器 */
        .filter-card {
            background: white; padding: 20px; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 25px;
        }
        
        .filter-row { display: flex; align-items: center; gap: 20px; flex-wrap: nowrap; }
        
        /* 搜索框 */
        .search-wrapper { flex: 1.5; position: relative; }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-wrapper input { 
            width: 100%; padding: 10px 10px 10px 40px; border-radius: 12px; 
            border: 1px solid #e2e8f0; background: #f8fafc; transition: 0.3s;
        }
        .search-wrapper input:focus { border-color: var(--primary-orange); background: white; outline: none; box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1); }

        /* 下拉选择框样式 */
        .select-custom { 
            flex: 0.8; position: relative; display: flex; align-items: center;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 10px; 
        }
        .select-custom i { color: #64748b; margin-right: 5px; font-size: 14px; }
        .select-custom select { border: none; background: transparent; padding: 10px 5px; width: 100%; font-size: 14px; outline: none; cursor: pointer; }

        /* 💡 橙色价格滑块样式 */
        .price-range-wrapper { flex: 1.2; padding: 0 10px; }
        .price-label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .price-label span { color: var(--primary-orange); }
        
        .range-slider { position: relative; height: 5px; background: #e2e8f0; border-radius: 5px; margin-top: 15px; }
        .slider-track { position: absolute; height: 100%; background: var(--primary-orange); border-radius: 5px; }
        .range-slider input {
            position: absolute; width: 100%; height: 5px; top: -5px; background: none; 
            pointer-events: none; -webkit-appearance: none; appearance: none;
        }
        input[type="range"]::-webkit-slider-thumb {
            height: 16px; width: 16px; border-radius: 50%; background: #fff;
            border: 2px solid var(--primary-orange); pointer-events: auto; -webkit-appearance: none; cursor: pointer;
        }

        /* 按钮 */
        .btn-add { 
            background: var(--primary-orange); color: white; padding: 11px 18px; 
            border-radius: 12px; font-weight: 600; text-decoration: none; 
            display: flex; align-items: center; gap: 8px; transition: 0.3s; white-space: nowrap;
        }
        .btn-add:hover { background: var(--hover-orange); color: white; }

        .btn-filter-submit {
            background: #f1f5f9; border: none; padding: 10px 15px; border-radius: 12px; color: #475569; font-weight: 600; transition: 0.3s;
        }
        .btn-filter-submit:hover { background: var(--primary-orange); color: white; }

        /* 表格样式 */
        .table-container { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; }
        .brand-badge { background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>

    <?php include_once '../includes/admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="top-bar">
            <div>
                <h2 class="m-0 fs-5 fw-bold">Manage Products</h2>
                <p class="m-0 text-muted small">Welcome back, <?php echo htmlspecialchars($username); ?></p>
            </div>
            <div class="user-profile-circle"><?php echo strtoupper(substr($username, 0, 2)); ?></div>
        </header>

        <div class="product-content-area">
            <h4 class="fw-bold text-dark mb-3">Product List</h4>

            <div class="filter-card">
                <form method="GET" id="filterForm" class="filter-row">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" placeholder="Search name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="select-custom">
                        <i class="bi bi-tag"></i>
                        <select name="cat" onchange="this.form.submit()">
                            <option value="">Categories</option>
                            <?php while($c = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $c['Cat_Id']; ?>" <?php if($cat_filter == $c['Cat_Id']) echo 'selected'; ?>>
                                    <?php echo $c['Cat_Name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if($admin_role == 1): ?>
                    <div class="select-custom">
                        <i class="bi bi-award"></i>
                        <select name="brand" onchange="this.form.submit()">
                            <option value="">All Brands</option>
                            <?php while($b = $brands->fetch_assoc()): ?>
                                <option value="<?php echo $b['Brand_Id']; ?>" <?php if($brand_filter == $b['Brand_Id']) echo 'selected'; ?>>
                                    <?php echo $b['Brand_Name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="price-range-wrapper">
                        <div class="price-label">
                            Price: <span>RM <span id="minDisp"><?php echo $min_price; ?></span> - RM <span id="maxDisp"><?php echo $max_price; ?></span></span>
                        </div>
                        <div class="range-slider">
                            <div class="slider-track" id="track"></div>
                            <input type="range" name="min_price" id="minRange" min="0" max="1000" value="<?php echo $min_price; ?>" step="10">
                            <input type="range" name="max_price" id="maxRange" min="0" max="1000" value="<?php echo $max_price; ?>" step="10">
                        </div>
                    </div>

                    <button type="submit" class="btn-filter-submit">Apply</button>

                    <a href="add_product.php" class="btn-add">
                        <i class="bi bi-plus-lg"></i> Add Product
                    </a>
                </form>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>IMAGE</th>
                                <th>PRODUCT INFO</th>
                                <?php if($admin_role == 1): ?> <th>BRAND</th> <?php endif; ?>
                                <th>CATEGORY</th>
                                <th>PRICE</th>
                                <th>STOCK</th>
                                <th class="text-end">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><img src="../upload/<?php echo $row['Pro_Image']; ?>" class="product-img"></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $row['Pro_Name']; ?></div>
                                        <small class="text-muted">#<?php echo $row['Pro_Id']; ?></small>
                                    </td>
                                    <?php if($admin_role == 1): ?>
                                        <td><span class="brand-badge"><?php echo $row['Brand_Name']; ?></span></td>
                                    <?php endif; ?>
                                    <td><?php echo $row['Cat_Name']; ?></td>
                                    <td class="fw-bold">RM <?php echo number_format($row['Pro_Price'], 2); ?></td>
                                    <td><?php echo $row['Pro_Stock_Quantity']; ?></td>
                                    <td class="text-end">
                                        <a href="edit_product.php?id=<?php echo $row['Pro_Id']; ?>" class="text-primary me-3 fs-5"><i class="bi bi-pencil-square"></i></a>
                                        <a href="javascript:void(0);" class="text-danger fs-5" onclick="confirmDelete(<?php echo $row['Pro_Id']; ?>)"><i class="bi bi-trash3"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted">No products found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // 滑块逻辑
    const minRange = document.getElementById('minRange');
    const maxRange = document.getElementById('maxRange');
    const minDisp = document.getElementById('minDisp');
    const maxDisp = document.getElementById('maxDisp');
    const track = document.getElementById('track');

    function updateSlider() {
        let minVal = parseInt(minRange.value);
        let maxVal = parseInt(maxRange.value);

        if (maxVal - minVal < 50) {
            if (event?.target?.id === 'minRange') minRange.value = maxVal - 50;
            else maxRange.value = minVal + 50;
        }

        minDisp.innerText = minRange.value;
        maxDisp.innerText = maxRange.value;

        const p1 = (minRange.value / minRange.max) * 100;
        const p2 = (maxRange.value / maxRange.max) * 100;
        track.style.left = p1 + "%";
        track.style.right = (100 - p2) + "%";
    }

    minRange.addEventListener('input', updateSlider);
    maxRange.addEventListener('input', updateSlider);
    window.onload = updateSlider;

    function confirmDelete(id) {
        Swal.fire({
            title: 'Delete Product?',
            text: "This cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF6B00',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = 'admin_manage_products.php?delete=' + id;
        });
    }
    <?php if ($swalCode) echo $swalCode; ?>
    </script>
</body>
</html>