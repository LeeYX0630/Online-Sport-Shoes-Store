<?php
// Module B: 核心交易组 - 产品目录页面 (Catalogue)
include '../includes/db_connection.php'; 
include '../includes/header.php';

$search_query = "";
$where_clause = "";

// 搜索功能：适配运动鞋名称、描述或品牌名称 [cite: 51, 52]
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE Pro_Name LIKE '%$search_query%' 
                     OR Pro_Description LIKE '%$search_query%' 
                     OR Brand_Id IN (SELECT Brand_Id FROM BRAND WHERE Brand_Name LIKE '%$search_query%')";
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
            color: #212529; /* 使用项目规范的主要文字颜色  */
        }

        .catalogue-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .page-header h2 { font-size: 32px; color: #333; margin-bottom: 10px; }
        .page-header p { color: #666; font-size: 16px; }

        .room-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .room-card {
            background-color: #ffffff; /* 全局背景白色  */
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
            color: #FF6B00; /* 使用项目强调色  */
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

        .room-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin: 0 0 10px 0;
        }

        .room-price {
            font-size: 18px;
            color: #28A745; /* 成功反馈颜色  */
            font-weight: bold;
            margin-bottom: 15px;
        }
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
            background-color: #333333; /* 导航栏/稳重背景色  */
            color: #fff;
            text-align: center;
            padding: 12px 0;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            box-sizing: border-box; 
            transition: 0.3s;
        }
        .btn-view:hover { background-color: #FF6B00; } /* 悬停变为强调色  */
        
        .no-results { grid-column: 1 / -1; text-align: center; padding: 50px; color: #999; }
    </style>
</head>
<body>

<div class="catalogue-container">
    <div class="page-header">
        <h2>Premium Sports Collection</h2>
        <p>Step into performance with our latest athletic footwear.</p>
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
                
                // 鞋子图片路径 [cite: 54]
                $img_name = $row['Pro_Image'];
                $img_src = !empty($img_name) ? "../uploads/" . $img_name : "../assets/images/placeholder.jpg";
                
                // 价格处理 [cite: 53]
                $price = $row['Pro_Price'];
                $price_display = "RM " . number_format($price, 2);

                // 描述与品牌详情 [cite: 51, 52]
                $desc = !empty($row['Pro_Description']) ? substr($row['Pro_Description'], 0, 60) . '...' : 'Premium quality sports shoes.';
                $brand = $row['Brand_Name'];
                $stock = $row['Pro_Stock_Quantity']; // 库存数量 [cite: 55]
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
            echo "<div class='no-results'>No sport shoes found in the store.</div>";
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>