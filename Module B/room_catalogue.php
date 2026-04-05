<?php

include '../includes/db_connection.php'; 
include '../includes/header.php';

$search_query = "";
$where_clause = "";

// Search functionality
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $conn->real_escape_string($_GET['search']);
    $where_clause = "WHERE room_name LIKE '%$search_query%' OR description LIKE '%$search_query%'";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Catalogue</title>
    <style>
 
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0; padding: 0;
            color: #333333;
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
            color: #666;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 10px;
            width: fit-content;
            border: 1px solid #ddd;
        }

        .room-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin: 0 0 10px 0;
        }

        .room-price {
            font-size: 18px;
            color: #28a745;
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
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 12px 0;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            box-sizing: border-box; 
        }
        .btn-view:hover { background-color: #000; }
        
        .no-results { grid-column: 1 / -1; text-align: center; padding: 50px; color: #999; }
    </style>
</head>
<body>

<div class="catalogue-container">
    <div class="page-header">
        <h2>Our Homestays</h2>
        <p>Choose from our variety of homestays.</p>
    </div>

    <div class="room-grid">
        <?php
        // 3. Fetch rooms from database with search filter
        $sql = "SELECT rooms.*, 
                       (SELECT COUNT(*) FROM categories WHERE categories.room_id = rooms.room_id) as cat_count
                FROM rooms 
                $where_clause 
                ORDER BY room_name ASC";
        
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                
                // room image
                $img_name = $row['room_image'];
                if (!empty($img_name)) {

                    $img_src = "../uploads/" . $img_name;
                } else {

                    $img_src = "../assets/images/placeholder.jpg"; 
                }
                
                // show price arrangement(max/min)
                $min = $row['min_price'];
                $max = $row['max_price'];
                $price_display = "";
                
                if ($min == 0 && $max == 0) {
                    $price_display = "Check Details";
                } elseif ($min == $max) {
                    $price_display = "RM " . number_format($min, 2);
                } else {
                    $price_display = "RM " . number_format($min, 2) . " - " . number_format($max, 2);
                }

                // show description and facilities
                $desc = !empty($row['description']) ? substr($row['description'], 0, 60) . '...' : 'Enjoy a comfortable stay.';
                $facilities = !empty($row['facilities']) ? $row['facilities'] : 'Standard Amenities';

                // 4. category count
                $count = $row['cat_count'];
                ?>
                
                <div class="room-card">
                    <div class="card-image">
                        <img src="<?php echo $img_src; ?>" alt="<?php echo $row['room_name']; ?>" onerror="this.src='../assets/images/placeholder.jpg'">
                    </div>
                    
                    <div class="card-content">
                        <div class="category-badge"><?php echo $count; ?> Room Types Available</div>

                        <h3 class="room-title"><?php echo $row['room_name']; ?></h3>

                        <div class="room-price">
                            <?php echo $price_display; ?>
                            <span>/ night</span>
                        </div>

                        <ul class="room-details">
                            <li><strong>Description:</strong> <?php echo $desc; ?></li>
                            <li><strong>Facilities:</strong> <?php echo substr($facilities, 0, 50) . '...'; ?></li>
                        </ul>

                        <div class="card-footer">
                            <a href="room_details.php?room_id=<?php echo $row['room_id']; ?>" class="btn-view">
                                VIEW DETAILS
                            </a>
                        </div>
                    </div>
                </div>

                <?php
            }
        } else {
            echo "<div class='no-results'>No homestays found in the database.</div>";
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>