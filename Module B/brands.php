<?php
include '../includes/db_connection.php'; 
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Brands | Online Sport Shoes Store</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0; padding: 0;
            color: #212529; 
        }

        .brands-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            min-height: 60vh;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
            margin-top: 20px;
        }
        .page-header h2 { font-size: 32px; color: #333; margin-bottom: 10px; }
        .page-header p { color: #666; font-size: 16px; }

        .brand-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }
        
        .brand-card {
            text-decoration: none;
            color: #333;
            display: block;
        }
        .brand-image-wrapper {
            width: 100%;
            height: 250px;
            background-color: #333333;
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
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }
        .brand-image-wrapper:hover img {
            opacity: 1;
        }
        .brand-name {
            margin-top: 15px;
            font-weight: bold;
            font-size: 18px;
            text-transform: capitalize;
            text-align: center;
        }

        /* 响应式调整 */
        @media (max-width: 992px) { .brand-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .brand-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="brands-container">
    <div class="page-header">
        <h2>Shop By Brand</h2>
        <p>Choose your favorite brand to explore their latest collections.</p>
    </div>

    <div class="brand-grid">
        <?php
        $brand_sql = "SELECT * FROM brand ORDER BY Brand_Name ASC";
        $brand_res = $conn->query($brand_sql);
        
        if ($brand_res && $brand_res->num_rows > 0) {
            while($b = $brand_res->fetch_assoc()) {
                if ($logo = !empty($b['Brand_Logo'])) {
                    $logo = "../images/brands/" . $b['Brand_Logo'];
                } else {
                    $logo = "../images/placeholder.png";
                }

            // 点击后跳转到 catalogue.php 并带上 brand_id
            echo '
            <a href="catalogue.php?brand_id='.$b['Brand_Id'].'" class="brand-card">
                <div class="brand-image-wrapper">
                    <img src="'.$logo.'" alt="'.$b['Brand_Name'].'">
                </div>
                <div class="brand-name">'.$b['Brand_Name'].'</div>
            </a>';
            }
        } else {
            echo "<p style='grid-column: 1/-1; text-align: center;'>No brands available at the moment.</p>";
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>