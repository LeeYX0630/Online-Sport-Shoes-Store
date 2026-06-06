<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'db_connection.php';

$is_root = file_exists('includes/db_connection.php');

if ($is_root) {
    $path_root  = "";
    $path_mod_a = "Module A/";
    $path_mod_b = "Module B/";
    $path_mod_c = "Module C/";
} else {
    $path_root  = "../";
    $path_mod_a = "../Module A/";
    $path_mod_b = "../Module B/";
    $path_mod_c = "../Module C/";
}

$current_page = basename($_SERVER['PHP_SELF']);
$auth_pages = ['login.php', 'register.php', 'admin_login.php', 'forgot_password.php'];
if (!isset($page_title)) $page_title = "Online Sport Shoes Store";

$nav_is_logged_in = false;
$nav_user_name = "";
$nav_profile_pic = $path_mod_a . "images/user_icon.png";


if (isset($_SESSION['user_id'])) {
    $nav_is_logged_in = true;
    $uid = $_SESSION['user_id'];
    
    // 使用真实的表名 USER 和 字段名 User_Name, User_Image
    $sql = "SELECT User_Name, User_Image FROM `USER` WHERE User_Id = '$uid'";
    $res = $conn->query($sql);
    
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $nav_user_name = $row['User_Name'];
        
        
        if (!empty($row['User_Image'])) {
            $nav_profile_pic = $path_mod_a . "uploads/" . $row['User_Image'];
        }
    }
} elseif (isset($_SESSION['admin_id'])) {
    $nav_is_logged_in = true;
    $nav_user_name = isset($_SESSION['username']) ? $_SESSION['username'] . " (Admin)" : "Administrator";
}

$header_cart_count = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $header_cart_count += isset($item['qty']) ? intval($item['qty']) : 0;
    }
}
?>
<!doctype html>
<html lang="en" class="h-100">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo $path_mod_a; ?>style.css"> 
    
    <style>
      body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }

      .bg-brand-dark { background-color: #333333; }
      .navbar-nav .nav-link { color: rgba(255,255,255,0.85); font-weight: 500; margin-right: 15px; }
      .navbar-nav .nav-link:hover { color: #fff; }

      .navbar-nav .nav-link.active { color: #fff; font-weight: 700; border-bottom: 2px solid #FF6B00; }
      
      .search-form { width: 100%; max-width: 400px; }
      .search-input { border-radius: 20px 0 0 20px; border: none; }
      .search-btn { border-radius: 0 20px 20px 0; background-color: #FF6B00; color: white; border: none; }
      .search-btn:hover { background-color: #e66000; color: white; }
    </style>
  </head>
  
  <body class="d-flex flex-column h-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-brand-dark py-3 shadow-sm">
      <div class="container-fluid px-4">
        
        <a class="navbar-brand d-flex align-items-center me-3" href="<?php echo $path_root; ?>index.php">
           <img src="<?php echo $path_mod_a; ?>../images/picture/STRYDEX_Logo.jpeg" alt="STRYDEX Logo" style="height: 40px; width: auto;" class="d-inline-block align-text-top me-2 rounded bg-white p-1">
           <span class="fw-bold text-warning d-none d-md-block">STRYDEX</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav me-3 mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo $path_root; ?>index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?php echo ($current_page == 'catalogue.php') ? 'active' : ''; ?>" href="<?php echo $path_mod_b; ?>catalogue.php">Catalogue</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?php echo ($current_page == 'about_us.php') ? 'active' : ''; ?>" href="<?php echo $path_mod_a; ?>about_us.php">About Us</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?php echo ($current_page == 'partner_with_us.php') ? 'active' : ''; ?>" href="<?php echo $path_mod_c; ?>partner_with_us.php">Partner With Us</a>
            </li>
          </ul>

          <form class="d-flex mx-auto search-form mb-3 mb-lg-0" action="<?php echo $path_mod_b; ?>catalogue.php" method="GET">
            <div class="input-group">
                <input class="form-control search-input" type="search" name="search" placeholder="Search shoes by brand or name..." aria-label="Search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button class="btn search-btn" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
          </form>

          <div class="d-flex align-items-center ms-lg-3">
            
            <?php if ($nav_is_logged_in): ?>
                
                <div class="d-flex align-items-center me-3 text-white">
                    <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($nav_user_name); ?>&background=random'" class="rounded-circle me-2 border border-2 border-white" style="width: 35px; height: 35px; object-fit: cover;">
                    <span class="d-none d-xl-block small"><?php echo htmlspecialchars($nav_user_name); ?></span>
                </div>

                <?php if (isset($_SESSION['admin_id'])): ?>
                    <a class="btn btn-warning btn-sm me-2 fw-bold" href="<?php echo $path_mod_c; ?>admin_dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                    </a>
                <?php else: ?>
                    
                    <a class="btn btn-outline-light btn-sm me-2 position-relative" href="<?php echo $path_mod_b; ?>cart.php" onclick="if(typeof viewShoppingCart === 'function') { viewShoppingCart(); return false; }" title="Shopping Cart">
                        <i class="bi bi-bag"></i>
                        <?php if ($header_cart_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; padding: 3px 5px;">
                                <?php echo $header_cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <a class="btn btn-outline-light btn-sm me-2" href="<?php echo $path_mod_a; ?>user_dashboard.php" title="My Profile">
                        <i class="bi bi-person-circle"></i>
                    </a>
                <?php endif; ?>
                
                <a class="btn btn-link text-white-50 text-decoration-none btn-sm" href="<?php echo $path_mod_a; ?>logout.php" onclick="return confirm('Confirm sign out?');">
                    <i class="bi bi-box-arrow-right fs-5"></i>
                </a>

            <?php else: ?>
                
                <?php if (!in_array($current_page, $auth_pages)): ?>
                    <a href="<?php echo $path_mod_a; ?>login.php" class="btn btn-outline-light btn-sm me-2 px-3">Login</a>
                    <a href="<?php echo $path_mod_a; ?>register.php" class="btn btn-warning btn-sm px-3 fw-bold text-dark">Sign up</a>
                <?php endif; ?>

            <?php endif; ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
          </div>
        </div>
      </div>
    </nav>