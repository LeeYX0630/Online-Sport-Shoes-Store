<?php
session_start();

require_once 'includes/db_connection.php';

$sql = "SELECT * FROM product ORDER BY Pro_Id DESC LIMIT 6";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Online Sport Shoes Store System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
    .hero-section {
        position: relative;
        height: 80vh; 
        min-height: 500px;
        color: #F5F5F5;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }
    
    .video-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -2;
        object-fit: cover;
        opacity: 0;
        transition: opacity 1.5s ease-in-out;
    }

    .video-active { opacity: 1; }

    .hero-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4); 
        z-index: -1;
    }
    
    .hero-section h1 {
        position: relative;
        color: #F5F5F5;
        font-size: 3.5em;
        margin-bottom: 20px;
        text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
    }

    .hero-section p {
        position: relative;
        font-size: 1.5em;
        margin-bottom: 40px;
        text-shadow: 1px 1px 5px rgba(0,0,0,0.5);
    }

    .cta-button {
        position: relative;
        background-color: #f0ad4e;
        color: white;
        padding: 15px 40px;
        text-decoration: none;
        font-size: 1.2em;
        border-radius: 5px;
        font-weight: bold;
        transition: 0.3s;
    }

    .cta-button:hover {
        background-color: #ec971f;
        transform: scale(1.05);
    }

    .featured-container {
        max-width: 1200px;
        margin: 50px auto;
        padding: 0 20px;
        position: relative;
    }

    .section-title {
        text-align: center;
        color: #333333;
        margin-bottom: 40px;
    }
    
    .section-subtitle {
        text-align: center;
        color: #666;
        margin-top: -30px;
        margin-bottom: 40px;
        font-size: 0.9em;
    }

    .room-grid {
        display: flex;
        overflow-x: auto;
        gap: 25px;
        padding-bottom: 25px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        cursor: grab;
        
        user-select: none;
        -webkit-user-select: none;
        
        scrollbar-width: thin;
        scrollbar-color: #ccc #f1f1f1;
    }
    
    .room-grid:active {
        cursor: grabbing;
    }

    .room-grid::-webkit-scrollbar {
        height: 14px; 
    }

    .room-grid::-webkit-scrollbar-track {
        background: #f5f5f5; 
        border-radius: 10px;
        margin: 0 20px;
        border: 1px solid #e0e0e0;
    }


    .room-grid::-webkit-scrollbar-thumb {
        background-color: #bbb;
        border-radius: 10px;
        border: 3px solid #f5f5f5;
        min-width: 50px;
    }

    .room-grid::-webkit-scrollbar-thumb:hover {
        background-color: #f0ad4e;
        cursor: pointer;
    }

    .room-card {
        background: white;
        border: 1px solid #ddd;
        min-width: 320px;
        max-width: 320px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        transition: transform 0.3s, box-shadow 0.3s;
        border-radius: 8px;
        overflow: hidden;
        flex: 0 0 auto;
    }

    .room-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .room-img {
        width: 100%;
        height: 220px;
        object-fit: cover;
        background-color: #eee;
    }

    .room-info {
        padding: 20px;
    }
    
    .room-info h3 {
        margin-top: 0;
        font-size: 1.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .price {
        color: #28a745;
        font-weight: bold;
        font-size: 1.2em;
        margin: 10px 0;
    }
    
    .btn-details {
        display: block;
        width: 100%;
        text-align: center;
        background: #333;
        color: white;
        padding: 10px 0;
        text-decoration: none;
        border-radius: 4px;
        margin-top: 15px;
        font-weight: bold;
    }
    .btn-details:hover { background: #000; }

    .badge-new {
        position: absolute;
        top: 15px;
        right: 15px;
        background: #dc3545;
        color: white;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: bold;
        border-radius: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    @media (max-width: 768px) {
        .room-card {
            min-width: 280px;
        }
    }
    </style>
</head>
<body>

<?php
$page_title = "Home";
$is_home_root = true; 

include 'includes/header.php';
?>

<div class="hero-section">
    <div class="hero-overlay"></div>

    <video class="video-background video-active" id="video1" muted playsinline>
        <source src="images/Sport Shoe Video 1.mp4" type="video/mp4">
    </video>
    <video class="video-background" id="video2" muted playsinline>
        <source src="images/Sport Shoe Video 2.mp4" type="video/mp4">
    </video>
    <video class="video-background" id="video3" muted playsinline>
        <source src="images/Sport Shoe Video 3.mp4" type="video/mp4">
    </video>

    <h1>Step Into Your Style</h1>
    <p>Discover the best performance sport shoes at unbeatable prices.</p>
    <a href="Module B/catalogue.php" class="cta-button">Shop Now</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const videos = [
            document.getElementById('video1'),
            document.getElementById('video2'),
            document.getElementById('video3')
        ];
        let currentVideoIndex = 0;
        videos.forEach(v => v.load());
        videos[0].play();

        function playNextVideo() {
            let nextIndex = (currentVideoIndex + 1) % videos.length;
            videos[nextIndex].currentTime = 0;
            videos[nextIndex].play();
            videos[currentVideoIndex].classList.remove('video-active');
            videos[nextIndex].classList.add('video-active');
            currentVideoIndex = nextIndex;
        }
        videos.forEach((video) => {
            video.addEventListener('ended', playNextVideo);
        });

        const slider = document.querySelector('.room-grid');
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.classList.add('active');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.classList.remove('active');
        });
        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.classList.remove('active');
        });
        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2; 
            slider.scrollLeft = scrollLeft - walk;
        });
    });
</script>

<div class="featured-container">
    <h2 class="section-title">Recommended Collections</h2>

<div class="room-grid">
        <?php
        if ($result && $result->num_rows > 0) {
            $count = 0;
            while($row = $result->fetch_assoc()) {
                $count++;
                // 适配 PRODUCT 表字段 [cite: 54, 51, 53, 49]
                $img_path = !empty($row['Pro_Image']) ? "uploads/" . $row['Pro_Image'] : "images/placeholder.jpg";
                $original_price = $row['Pro_Price'];
                
                /**
                 * 价格逻辑：
                 * 检查是否有折扣字段（例如 Pro_Sale_Price）。
                 * 如果没有，这里暂时模拟逻辑或直接显示原价。
                 */
                $has_discount = false; // 如果以后数据库有折扣字段，可以改为 !empty($row['Pro_Sale_Price'])
                $sale_price = 0;       // 这里放对应的折扣字段
                
                if ($has_discount) {
                    $price_html = '<span class="old-price">RM ' . number_format($original_price, 2) . '</span>';
                    $price_html .= '<span class="price">RM ' . number_format($sale_price, 2) . '</span>';
                } else {
                    $price_html = '<span class="price">RM ' . number_format($original_price, 2) . '</span>';
                }
                
                $newBadge = ($count <= 2) ? '<span class="badge-new">NEW</span>' : '';

                echo '
                <div class="room-card" style="position:relative;">
                    '.$newBadge.'
                    <img src="'.$img_path.'" alt="'.$row['Pro_Name'].'" class="room-img">
                    <div class="room-info">
                        <h3>'.$row['Pro_Name'].'</h3>
                        <div class="price-box">'.$price_html.'</div>
                        <p style="color:#666; font-size:0.9em;">' . (isset($row['Pro_Description']) ? substr($row['Pro_Description'], 0, 80) : "No description") . '...</p>
                        <a href="Module B/product_details.php?pro_id='.$row['Pro_Id'].'" class="btn-details">View Details</a>
                    </div>
                </div>
                ';
            }
        } else {
            echo '<p style="text-align:center; width:100%;">No featured products available at the moment.</p>';
        }
        ?>
    </div>
</div>

<?php 
include_once 'includes/footer.php'; 
?>

</body>
</html>