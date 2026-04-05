<?php
include '../includes/db_connection.php';
include '../includes/header.php';

// Check URL
if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
    echo "<script>alert('No Room Selected'); window.location.href='room_catalogue.php';</script>";
    exit;
}

$room_id = intval($_GET['room_id']); 

// Get Homestay Data
$sql_room = "SELECT * FROM rooms WHERE room_id = '$room_id'";
$res_room = $conn->query($sql_room);

if ($res_room->num_rows == 0) {
    echo "<div style='padding:50px; text-align:center;'>Homestay not found.</div>";
    include '../includes/footer.php';
    exit;
}

$room = $res_room->fetch_assoc();
$room_img = !empty($room['room_image']) ? "../uploads/" . $room['room_image'] : "../assets/images/placeholder.jpg";

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $room['room_name']; ?> - Details</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 0; }
        
        .detail-container { 
            max-width: 1200px; 
            margin: 30px auto; 
            padding: 0 30px; 
        }
        
        .btn-back { 
            display: inline-flex; align-items: center; margin-bottom: 20px; 
            color: #555; text-decoration: none; font-weight: bold; font-size: 14px; 
            background-color: #e9ecef; padding: 8px 20px; border-radius: 4px; 
            transition: all 0.3s ease;
        }
        .btn-back:hover { background-color: #dde2e6; color: #333; transform: translateX(-3px); }

        .homestay-header {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
            display: flex;
            flex-direction: column; 
        }
        
        @media (min-width: 992px) {
            .homestay-header { 
                flex-direction: row; 
                align-items: stretch; 
            }
        }

        .header-image {
            flex: 1; 
            background-color: #eee;
            overflow: hidden;
            min-height: 300px;
        }
        .header-image img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }

        .header-info {
            flex: 1; 
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .main-title { 
            font-size: 32px;
            font-weight: 800; 
            margin: 0 0 10px 0; 
            color: #222; 
            line-height: 1.2;
        }
        
        .price-range { 
            font-size: 24px; 
            color: #28a745; 
            font-weight: bold; 
            margin-bottom: 20px; 
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .price-range small { font-size: 14px; color: #888; font-weight: normal; margin-left: 5px; }
        
        .info-block { margin-bottom: 20px; }
        .info-label { 
            font-weight: 800; display: block; margin-bottom: 8px; 
            color: #444; text-transform: uppercase; font-size: 12px; 
            letter-spacing: 1px; 
        }
        .info-text { 
            font-size: 15px; 
            line-height: 1.6; 
            color: #555; 
        }

        .section-title { 
            font-size: 24px; font-weight: bold; margin-bottom: 20px; 
            border-left: 5px solid #333; padding-left: 15px; color: #333; 
        }

        .category-list { display: flex; flex-direction: column; gap: 20px; }

        .category-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: #28a745;
        }

        .cat-info { flex-grow: 1; padding-right: 20px; }
        .cat-name { font-size: 20px; font-weight: bold; margin: 0 0 8px 0; color: #333; }
        .cat-desc { font-size: 14px; color: #666; margin-bottom: 10px; line-height: 1.4; }
        
        .cat-badges { display: flex; gap: 10px; }
        .badge { background: #f4f4f4; color: #555; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; display: flex; align-items: center; }

        .cat-action { text-align: right; min-width: 160px; display: flex; flex-direction: column; align-items: flex-end; }
        .cat-price { font-size: 22px; font-weight: bold; color: #28a745; display: block; margin-bottom: 10px; }
        .cat-price span { font-size: 13px; color: #999; font-weight: normal; }
        
        .btn-book {
            background-color: #333;
            color: white;
            padding: 10px 25px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: background 0.3s;
            text-align: center;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn-book:hover { background-color: #28a745; }

        @media (max-width: 991px) {
            .header-image { min-height: 250px; } 
            .category-card { flex-direction: column; align-items: flex-start; }
            .cat-action { text-align: left; margin-top: 15px; width: 100%; align-items: flex-start; }
            .btn-book { display: block; width: 100%; }
        }
        /* --- Progress Bar CSS --- */
        .progress-wrapper { max-width: 600px; margin: 0 auto 30px auto; }
        .progressbar { counter-reset: step; padding: 0; display: flex; justify-content: space-between; list-style: none; position: relative; }
        .progressbar li { width: 33.33%; position: relative; text-align: center; font-size: 13px; color: #ccc; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .progressbar li:before { content: counter(step); counter-increment: step; width: 30px; height: 30px; line-height: 28px; border: 2px solid #e0e0e0; background: #fff; display: block; text-align: center; margin: 0 auto 10px auto; border-radius: 50%; color: #ccc; font-weight: bold; z-index: 2; position: relative; }
        .progressbar li:after { content: ''; position: absolute; width: 100%; height: 3px; background: #e0e0e0; top: 15px; left: -50%; z-index: 0; }
        .progressbar li:first-child:after { content: none; }
        
        /* Active State  */
        .progressbar li.active { color: #333; }
        .progressbar li.active:before { border-color: #28a745; background: #fff; color: #28a745; box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1); }
        
        /* Completed State  */
        .progressbar li.completed { color: #28a745; }
        .progressbar li.completed:before { content: '✔'; border-color: #28a745; background: #28a745; color: #fff; }
        .progressbar li.completed + li:after { background: #28a745; } /* 绿线连接 */
    </style>
</head>
<body>

<div class="detail-container">
    <div class="progress-wrapper">
        <ul class="progressbar">
            <li class="active">View Details</li>
            <li>Select Dates</li>
            <li>Payment</li>
        </ul>
    </div>

    <a href="room_catalogue.php" class="btn-back">&larr; Back to Catalogue</a>

    <div class="homestay-header">
        <div class="header-image">
            <img src="<?php echo $room_img; ?>" alt="<?php echo $room['room_name']; ?>">
        </div>
        
        <div class="header-info">
            <h1 class="main-title"><?php echo $room['room_name']; ?></h1>
            
            <div class="price-range">
                <?php 
                if($room['min_price'] > 0) {
                    if ($room['min_price'] != $room['max_price']) {
                        echo "RM " . number_format($room['min_price'], 0) . " - RM " . number_format($room['max_price'], 0);
                    } else {
                        echo "RM " . number_format($room['min_price'], 0);
                    }
                    echo " <small>/ night</small>";
                } else {
                    echo "Check prices below";
                }
                ?>
            </div>

            <div class="info-block">
                <span class="info-label">Description</span>
                <div class="info-text"><?php echo nl2br($room['description']); ?></div>
            </div>

            <div class="info-block">
                <span class="info-label">Facilities</span>
                <div class="info-text"><?php echo $room['facilities'] ? $room['facilities'] : 'Standard Amenities'; ?></div>
            </div>
        </div>
    </div>

    <h3 class="section-title">Select Your Room Type</h3>
    
    <div class="category-list">
        <?php
        $sql_cats = "SELECT * FROM categories WHERE room_id = '$room_id' ORDER BY price_per_night ASC";
        $res_cats = $conn->query($sql_cats);

        if ($res_cats->num_rows > 0) {
            while ($cat = $res_cats->fetch_assoc()) {
                ?>
                <div class="category-card">
                    <div class="cat-info">
                        <h4 class="cat-name"><?php echo $cat['category_name']; ?></h4>
                        <div class="cat-desc">
                            <?php echo $cat['description'] ? $cat['description'] : 'Enjoy a comfortable stay in this room category.'; ?>
                        </div>
                        <div class="cat-badges">
                            <div class="badge">Max Pax: <?php echo $cat['max_pax']; ?> Adults</div>
                        </div>
                    </div>
                    
                    <div class="cat-action">
                        <span class="cat-price">
                            RM <?php echo number_format($cat['price_per_night'], 2); ?>
                            <span>/ night</span>
                        </span>
                        
                        <?php 
                        // Check if user is admin
                        if ($isAdmin) {
                            
                            ?>
                            <button type="button" onclick="showAdminWarning()" class="btn-book">
                                BOOK NOW
                            </button>
                            <?php
                        } else {
                        
                            ?>
                            <a href="../Module C/check_availability.php?category_id=<?php echo $cat['category_id']; ?>" class="btn-book">
                                BOOK NOW
                            </a>
                            <?php
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p style='color:#777; font-style:italic; padding:30px; background:#fff; border-radius:8px; text-align:center;'>No room categories available for this homestay yet.</p>";
        }
        ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>

<script>
    function showAdminWarning() {
        Swal.fire({
            title: "Admin can't be booking", 
            icon: "warning",                 
            confirmButtonColor: "#28a745",  
            confirmButtonText: "OK",         
            width: '400px'                   
        });
    }
</script>

</body>
</html>