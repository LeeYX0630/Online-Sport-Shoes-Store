<?php
// Module B: 核心交易组 - 产品目录页面 (Catalogue)
include '../includes/db_connection.php'; 
include '../includes/header.php';

$where_clauses = [];

// 1. 搜索功能：适配运动鞋名称、描述或品牌名称
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(product.Pro_Name LIKE '%$search_query%' 
                         OR product.Pro_Description LIKE '%$search_query%' 
                         OR brand.Brand_Name LIKE '%$search_query%')";
}

// 2. 品牌过滤功能：当用户点击品牌墙时触发
if (isset($_GET['brand_id']) && !empty($_GET['brand_id'])) {
    $filter_brand_id = intval($_GET['brand_id']);
    $where_clauses[] = "product.Brand_Id = '$filter_brand_id'";
}

$where_clause = "";
if (count($where_clauses) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_clauses);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shoe Catalogue | Online Sport Shoes Store</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0; padding: 0;
            color: #212529; 
        }

        .catalogue-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* --- 新增：JD Sports 风格品牌墙样式 --- */
        .brand-section {
            margin-bottom: 50px;
        }
        .brand-section h3 {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
        }
        .brand-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .brand-card {
            text-decoration: none;
            color: #333;
            display: block;
        }
        .brand-image-wrapper {
            width: 100%;
            height: 250px;
            background-color: #333; /* 默认深色背景衬托 Logo */
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        .brand-image-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .brand-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.8; /* 稍微变暗以凸显品牌感 */
            transition: opacity 0.3s ease;
        }
        .brand-image-wrapper:hover img {
            opacity: 1;
        }
        .brand-name {
            margin-top: 15px;
            font-weight: bold;
            font-size: 16px;
            text-transform: capitalize;
        }

        /* 响应式调整 */
        @media (max-width: 992px) { .brand-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .brand-grid { grid-template-columns: 1fr; } }
        /* --------------------------------- */

        .page-header {
            text-align: center;
            margin-bottom: 40px;
            margin-top: 20px;
        }
        .page-header h2 { font-size: 32px; color: #333; margin-bottom: 10px; }
        .page-header p { color: #666; font-size: 16px; }

        .room-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        @media (max-width: 992px) { .room-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .room-grid { grid-template-columns: 1fr; } }

        .room-card {
            background-color: #ffffff; 
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0px 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e0e0;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0px 8px 25px rgba(0,0,0,0.1);
        }

        .card-image {
            width: 100%;
            height: 220px;
            background-color: #eeeeee;
            overflow: hidden;
        }
        .card-image img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.5s ease;
        }
        .room-card:hover .card-image img {
            transform: scale(1.05);
        }

        .card-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .category-badge {
            background-color: #f8f9fa;
            color: #FF6B00; 
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 10px;
            width: fit-content;
            border: 1px solid #FF6B00;
        }

        .room-title { font-size: 20px; font-weight: bold; color: #333; margin: 0 0 10px 0; }
        .room-price { font-size: 18px; color: #28A745; font-weight: bold; margin-bottom: 15px; }
        .room-price span { font-size: 14px; color: #999; font-weight: normal; }

        .room-details {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }
        .room-details li { margin-bottom: 8px; line-height: 1.4; }

        .card-footer { margin-top: auto; }
        .btn-view {
            display: block;
            width: 100%;
            background-color: #333333; 
            color: #fff;
            text-align: center;
            padding: 12px 0;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            box-sizing: border-box; 
            transition: 0.3s;
        }
        .btn-view:hover { background-color: #FF6B00; } 
        
        .no-results { grid-column: 1 / -1; text-align: center; padding: 50px; color: #999; }
        
        .clear-filter {
            display: inline-block;
            margin-top: 10px;
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }
        .clear-filter:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="catalogue-container">

    <div class="brand-section">
        <h3>All Brands</h3>
        <div class="brand-grid">
            <?php
            // 获取所有可用的品牌
            $brand_sql = "SELECT * FROM brand ORDER BY Brand_Name ASC";
            $brand_res = $conn->query($brand_sql);
            
            if ($brand_res && $brand_res->num_rows > 0) {
                while($b = $brand_res->fetch_assoc()) {
                    // 如果你数据库里的 Brand_Logo 是具体的鞋子氛围图，效果会完美贴合 JD Sports 风格
                    $logo = !empty($b['Brand_Logo']) ? "../uploads/" . $b['Brand_Logo'] : "../assets/images/placeholder.jpg";
                    
                    // 点击卡片，传入 brand_id 过滤下方产品
                    echo '
                    <a href="catalogue.php?brand_id='.$b['Brand_Id'].'" class="brand-card">
                        <div class="brand-image-wrapper">
                            <img src="'.$logo.'" alt="'.$b['Brand_Name'].'" onerror="this.src=\'../assets/images/placeholder.jpg\'">
                        </div>
                        <div class="brand-name">'.$b['Brand_Name'].'</div>
                    </a>';
                }
            }
            ?>
        </div>
    </div>
    
    <hr style="border-top: 1px solid #e0e0e0; margin-bottom: 40px;">

    <div class="page-header">
        <h2>Premium Sports Collection</h2>
        <p>Step into performance with our latest athletic footwear.</p>
        <?php 
        // 如果用户进行了筛选，显示清除筛选的按钮
        if(!empty($_GET['search']) || !empty($_GET['brand_id'])) {
            echo '<a href="catalogue.php" class="clear-filter"><i class="bi bi-x-circle"></i> Clear Filters</a>';
        }
        ?>
    </div>

    <div class="room-grid">
        <?php
        $sql = "SELECT product.*, brand.Brand_Name 
                FROM product 
                JOIN brand ON product.Brand_Id = brand.Brand_Id
                $where_clause 
                ORDER BY Pro_Name ASC";
        
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                
                $img_name = $row['Pro_Image'];
                $img_src = !empty($img_name) ? "../uploads/" . $img_name : "../assets/images/placeholder.jpg";
                
                $price = $row['Pro_Price'];
                $price_display = "RM " . number_format($price, 2);

                $desc = !empty($row['Pro_Description']) ? substr($row['Pro_Description'], 0, 60) . '...' : 'Premium quality sports shoes.';
                $brand = $row['Brand_Name'];
                $stock = $row['Pro_Stock_Quantity']; 
                ?>
                
                <div class="room-card">
                    <div class="card-image">
                        <img src="<?php echo $img_src; ?>" alt="<?php echo $row['Pro_Name']; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                    </div>
                    
                    <div class="card-content">
                        <div class="category-badge"><?php echo $brand; ?></div>

                        <h3 class="room-title"><?php echo $row['Pro_Name']; ?></h3>

                        <div class="room-price">
                            <?php echo $price_display; ?>
                        </div>

                        <ul class="room-details">
                            <li><strong>Description:</strong> <?php echo $desc; ?></li>
                            <li><strong>Available Sizes:</strong> <?php echo $row['Pro_Size']; ?></li>
                            <li><strong>Stock:</strong> <?php echo $stock; ?> pairs left</li>
                        </ul>

                        <div class="card-footer">
                            <a href="product_details.php?pro_id=<?php echo $row['Pro_Id']; ?>" class="btn-view">
                                VIEW PRODUCT
                            </a>
                        </div>
                    </div>
                </div>

                <?php
            }
        } else {
            echo "<div class='no-results'>No sport shoes found matching your criteria.</div>";
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>