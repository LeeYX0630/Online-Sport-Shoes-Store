<?php
// Module B: 核心交易组 - 订单结算中心 (Checkout & Payment)
ob_start();
session_start();
require_once '../includes/db_connection.php';

// 1. 登录与购物车安全检查
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Module A/login.php");
    exit();
}
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: catalogue.php");
    exit();
}

$uid = $_SESSION['user_id'];
$error = "";
$success_msg = "";

if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'checkout.php') === false && empty($_POST)) {
    unset($_SESSION['user_chose_promo']);
    unset($_SESSION['applied_promo_code']);
    unset($_SESSION['applied_discount']);
    unset($_SESSION['applied_user_promo_id']);
    unset($_SESSION['auto_promo_notified']);
    $_SESSION['promo_mode'] = 'AUTO';
}

// =========================================================================
// 【核心修改】：通过默认地址外键外连带出用户的首选配送信息，彻底剪除 user 冗余字段读取
// =========================================================================
$user_sql = "SELECT u.*, ua.Address_Text, ua.Postcode, ua.State, ua.City 
             FROM `user` u 
             LEFT JOIN `user_address` ua ON u.Default_Address_Id = ua.Address_Id 
             WHERE u.User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();
$current_balance = floatval($user_info['User_Balance']);
$has_pin = !empty($user_info['User_PIN']);

// 提取用户基础资料
$user_phone    = $user_info['User_Phone'];
$user_address  = $user_info['Address_Text'] ?? ''; 
$user_state    = $user_info['State'] ?? '';        
$user_postcode = $user_info['Postcode'] ?? '';   
$user_city     = $user_info['City'] ?? '';       

// ── 🌟 核心修复 1：精准判定是否为“全新进入结算页” ──
$is_fresh_entry = false;

// 1. 通过来源页面判断：只要是普通的 GET 请求，且不是 checkout 自己刷新或支付网关跳回
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (strpos($referer, 'checkout.php') === false && 
        strpos($referer, 'card_auth.php') === false && 
        strpos($referer, 'wallet_auth.php') === false && 
        strpos($referer, 'bank_portal.php') === false) {
        $is_fresh_entry = true;
    }
}

// 2. 通过购物车内容判断：如果用户增减了商品，也算全新计算
$current_cart_hash = md5(serialize($_SESSION['cart']));
if (!isset($_SESSION['last_cart_hash']) || $_SESSION['last_cart_hash'] !== $current_cart_hash) {
    $is_fresh_entry = true;
    $_SESSION['last_cart_hash'] = $current_cart_hash;
}

// 3. 执行强力洗脑：只要是新进来的，忘掉之前用户的所有手动设置，强行切回全自动模式！
if ($is_fresh_entry) {
    $_SESSION['promo_mode'] = 'AUTO';
    unset($_SESSION['auto_promo_notified']); // 拔掉防弹窗标记，让 SweetAlert 满血复活
    unset($_SESSION['applied_promo_code']);
    unset($_SESSION['applied_discount']);
    unset($_SESSION['applied_user_promo_id']);
}

// 2. 抓取该用户的所有保存地址
$address_book = [];
$addr_sql = "SELECT * FROM user_address WHERE User_Id = '$uid' AND Is_Deleted = 0 ORDER BY Is_Default DESC, Address_Id ASC";
$addr_res = $conn->query($addr_sql);
if ($addr_res && $addr_res->num_rows > 0) {
    while ($row = $addr_res->fetch_assoc()) {
        $address_book[] = $row;
    }
}

$name_parts = explode(' ', $user_info['User_Name'], 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : "";

$subtotal = 0;
$checkout_items = [];
foreach ($_SESSION['cart'] as $cart_key => $item) {
    $pid = $item['pro_id'];
    $sql_p = "SELECT Pro_Name, Pro_Price, Pro_Image FROM product WHERE Pro_Id = '$pid'";
    $res_p = $conn->query($sql_p);
    
    if ($res_p && $res_p->num_rows > 0) {
        $p_data = $res_p->fetch_assoc();
        $p_data['qty'] = $item['qty'];
        $p_data['size'] = $item['size'];
        $p_data['color'] = !empty($item['custom_preview']) ? 'Custom Design' : ($item['color'] ?? 'Default');
        
        // --- 【核心修复：采用与 cart.php 一致的智能搜索逻辑】 ---
        if (!empty($item['custom_preview'])) {
            // 1. 如果是 3D 定制作品，直接使用 Base64 快照
            $p_data['display_image'] = $item['custom_preview'];
        } else {
            // 2. 普通商品或默认款：根据基本名称搜索文件夹
            $base_img = $p_data['Pro_Image']; 
            $path_parts = pathinfo($base_img);
            $base_name = preg_replace('/_\d+$/', '', $path_parts['filename']); // 去掉末尾数字
            
            // 在 uploads 文件夹中寻找所有匹配的文件
            $found_files = glob("../uploads/{$base_name}*.*");
            
            if (!empty($found_files)) {
                // 默认取搜索到的第一张
                $final_img = $found_files[0]; 
                
                // 如果用户选了特定颜色，尝试匹配颜色关键字
                if ($p_data['color'] !== 'Default' && $p_data['color'] !== 'Custom Design') {
                    $color_slug = strtolower(str_replace(' ', '_', $p_data['color']));
                    foreach ($found_files as $file) {
                        if (strpos(strtolower($file), $color_slug) !== false) {
                            $final_img = $file;
                            break;
                        }
                    }
                }
                $p_data['display_image'] = $final_img;
            } else {
                $p_data['display_image'] = "../images/placeholder.png"; // 没搜到则显示占位图
            }
        }
        if (isset($item['price'])) {
            $p_data['Pro_Price'] = $item['price'];
        }
            
        $p_data['item_total'] = $p_data['Pro_Price'] * $item['qty'];
        $subtotal += $p_data['item_total'];
        $checkout_items[] = $p_data;
    }
}

function getOptimalPromo($conn, $user_id, $subtotal) {
    $best_promo = null;
    $best_discount = 0;
    $best_discount_info = '';
    
    // 查询该用户拥有且未使用的优惠券
    $promo_query = $conn->query("
        SELECT p.*, up.User_Promo_Id 
        FROM user_promo up
        JOIN promo p ON up.Promo_Id = p.Promo_Id
        WHERE up.User_Id = '$user_id' 
        AND up.Is_Used = 'No' 
        AND p.Promo_Status = 'Active' 
        AND p.Expired_Date >= CURDATE()
        ORDER BY p.Promo_Value DESC
    ");
    
    if ($promo_query && $promo_query->num_rows > 0) {
        while ($promo = $promo_query->fetch_assoc()) {
            // 计算该 promo 的折扣额度
            $discount_amount = 0;
            if ($promo['Promo_Type'] === 'Percentage') {
                $discount_amount = $subtotal * (floatval($promo['Promo_Value']) / 100);
                $discount_display = intval($promo['Promo_Value']) . '% OFF';
            } else {
                $discount_amount = floatval($promo['Promo_Value']);
                $discount_display = 'RM ' . number_format($discount_amount, 2) . ' OFF';
            }
            
            // 比较折扣，选择最大的
            if ($discount_amount > $best_discount) {
                $best_discount = $discount_amount;
                $best_promo = $promo;
                $best_discount_info = $discount_display;
            }
        }
    }
    
    return [
        'promo' => $best_promo,
        'discount_amount' => $best_discount,
        'discount_info' => $best_discount_info
    ];
}

function getUserAvailablePromos($conn, $user_id, $subtotal) {
    $available_promos = [];
    $promo_query = $conn->query("
        SELECT p.*, up.User_Promo_Id 
        FROM user_promo up
        JOIN promo p ON up.Promo_Id = p.Promo_Id
        WHERE up.User_Id = '$user_id' 
        AND up.Is_Used = 'No' 
        AND p.Promo_Status = 'Active' 
        AND p.Expired_Date >= CURDATE()
    ");

    if ($promo_query && $promo_query->num_rows > 0) {
        while ($row = $promo_query->fetch_assoc()) {
            $available_promos[] = [
                'promo_code' => $row['Promo_Code'],
                'promo_name' => $row['Promo_Name'],
                'discount_display' => ($row['Promo_Type'] === 'Percentage') ? intval($row['Promo_Value'])."% OFF" : "RM ".$row['Promo_Value']." OFF",
                'discount_amount' => ($row['Promo_Type'] === 'Percentage') ? ($subtotal * ($row['Promo_Value']/100)) : floatval($row['Promo_Value']),
                'expired_date' => $row['Expired_Date']
            ];
        }
    }
    return $available_promos;
}

// --- 购物车指纹检测 ---
$current_cart_hash = md5(serialize($_SESSION['cart']));
if (!isset($_SESSION['last_cart_hash']) || $_SESSION['last_cart_hash'] !== $current_cart_hash) {
    $_SESSION['promo_mode'] = 'AUTO';
    $_SESSION['last_cart_hash'] = $current_cart_hash;
    unset($_SESSION['auto_promo_notified']); 
}

// 4. 处理优惠码逻辑
$discount = 0;
$applied_code = "";
$auto_applied = false;
$available_vouchers = getUserAvailablePromos($conn, $uid, $subtotal);

if (!isset($_SESSION['promo_mode'])) {
    $_SESSION['promo_mode'] = 'AUTO';
}

$is_manual_action = isset($_POST['apply_coupon']);
$has_input_code = (isset($_POST['coupon_code']) && trim($_POST['coupon_code']) !== '');

if ($is_manual_action || $has_input_code) {
    $code = $conn->real_escape_string(trim($_POST['coupon_code']));
    
    if ($code !== "") {
        $sql_c = "SELECT p.*, up.User_Promo_Id FROM user_promo up 
                  JOIN promo p ON up.Promo_Id = p.Promo_Id 
                  WHERE p.Promo_Code = '$code' AND up.User_Id = '$uid' AND up.Is_Used = 'No' 
                  AND p.Expired_Date >= CURDATE() AND p.Promo_Status = 'Active'";
        $res_c = $conn->query($sql_c);
        
        if ($res_c && $res_c->num_rows > 0) {
            $promo = $res_c->fetch_assoc();
            $applied_code = $code;
            if ($promo['Promo_Type'] === 'Percentage') {
                $discount = $subtotal * (floatval($promo['Promo_Value']) / 100);
                $success_msg = "Applied " . intval($promo['Promo_Value']) . "% OFF";
            } else {
                $discount = floatval($promo['Promo_Value']);
                $success_msg = "Applied RM " . number_format($discount, 2) . " OFF";
            }
            $_SESSION['promo_mode'] = 'MANUAL';
            $_SESSION['applied_promo_code'] = $applied_code;
            $_SESSION['applied_discount'] = $discount;
            $_SESSION['applied_user_promo_id'] = $promo['User_Promo_Id'];
        } else {
            $error = "Invalid or expired code, or you don't own this coupon.";
            $_SESSION['promo_mode'] = 'MANUAL';
            $applied_code = $code; 
            unset($_SESSION['applied_promo_code'], $_SESSION['applied_discount'], $_SESSION['applied_user_promo_id']);
        }
    } else {
        $_SESSION['promo_mode'] = 'NONE'; 
        unset($_SESSION['applied_promo_code'], $_SESSION['applied_discount'], $_SESSION['applied_user_promo_id']);
        $applied_code = "";
        $discount = 0;
    }
} else {
    if ($_SESSION['promo_mode'] === 'NONE') {
        $applied_code = "";
        $discount = 0;
        $auto_applied = false;
    } elseif ($_SESSION['promo_mode'] === 'MANUAL' && isset($_SESSION['applied_promo_code'])) {
        $applied_code = $_SESSION['applied_promo_code'];
        $discount = $_SESSION['applied_discount'];
        $auto_applied = false;
    } else {
        $optimal = getOptimalPromo($conn, $uid, $subtotal);
        if ($optimal['promo'] !== null) {
            $best_promo = $optimal['promo'];
            $discount = $optimal['discount_amount'];
            $applied_code = $best_promo['Promo_Code'];
            $auto_applied = true;
            
            $_SESSION['applied_promo_code'] = $applied_code;
            $_SESSION['applied_discount'] = $discount;
            $_SESSION['applied_user_promo_id'] = $best_promo['User_Promo_Id'];
            $_SESSION['promo_mode'] = 'AUTO';
            
            $success_msg = "✨ Auto-Applied Best Deal: " . htmlspecialchars($best_promo['Promo_Name']);
        } else {
            $applied_code = "";
            $discount = 0;
            $_SESSION['promo_mode'] = 'AUTO';
        }
    }
}

$shipping = ($subtotal >= 250) ? 0 : 15.00;
$grand_total = max(0, ($subtotal + $shipping) - $discount);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    if (isset($_POST['fpx_success']) && isset($_SESSION['fpx_temp_data'])) {
        $savedPost = $_SESSION['fpx_temp_data'];
        unset($_SESSION['fpx_temp_data']);
        $_POST = array_merge($savedPost, $_POST);
    }

    if (isset($_POST['wallet_resume']) && isset($_SESSION['wallet_temp_data'])) {
        $savedPost = $_SESSION['wallet_temp_data'];
        unset($_SESSION['wallet_temp_data']);
        $_POST = array_merge($savedPost, $_POST);
        $grand_total = floatval($savedPost['grand_total'] ?? $grand_total);
        $discount = floatval($savedPost['discount'] ?? $discount);
        $shipping = floatval($savedPost['shipping'] ?? $shipping);
        $subtotal = floatval($savedPost['subtotal'] ?? $subtotal);
    }

    $f_name = trim($_POST['first_name']);
    $raw_postcode = $_POST['postcode'] ?? ''; 
    $postcode = ($raw_postcode === 'other') ? trim($_POST['custom_postcode'] ?? '') : trim($raw_postcode);
    $phone = trim($_POST['phone']);

    // 后端验证
    if (!preg_match("/^[a-zA-Z\s]+$/", $f_name)) {
        $error = "Names should only contain letters.";
    } elseif (strlen($phone) > 12 || !preg_match("/^0[1-9][0-9]{7,9}$/", $phone)) {
        $error = "Invalid Malaysia phone number format (max 12 digits).";
    } elseif (!preg_match("/^[0-9]{5}$/", $postcode)) {
        $error = "Postcode must be 5 digits.";
    } elseif ($_POST['pay_type'] === 'fpx' && (empty($_POST['fpx_bank']) || $_POST['fpx_bank'] === '')) {
        $error = "Please select a bank for FPX payment.";
    } elseif ($_POST['pay_type'] === 'wallet' && !isset($_POST['wallet_resume'])) {
        $_SESSION['wallet_temp_data'] = $_POST;
        $_SESSION['wallet_temp_data']['grand_total'] = $grand_total;
        $_SESSION['wallet_temp_data']['shipping'] = $shipping;
        $_SESSION['wallet_temp_data']['discount'] = $discount;
        $_SESSION['wallet_temp_data']['subtotal'] = $subtotal;
        header("Location: wallet_auth.php");
        exit();
    } elseif ($_POST['pay_type'] === 'wallet' && isset($_POST['wallet_resume'])) {
        $input_pin = $_POST['wallet_pin'] ?? '';
        
        if (!preg_match('/^[0-9]{6}$/', $input_pin)) {
            $error = "Security Error: PIN must be exactly 6 numeric digits. No letters or symbols allowed.";
        } else {
            $check_db = $conn->query("SELECT User_PIN, User_Balance FROM `user` WHERE User_Id = '$uid'");
            $latest = $check_db->fetch_assoc();
            
            if (!$latest || empty($latest['User_PIN'])) {
                $error = "Wallet not activated. Please set a PIN in your dashboard.";
            } 
            elseif (!password_verify($input_pin, $latest['User_PIN'])) {
                $error = "Incorrect Wallet PIN. Transaction denied.";
            } 
            elseif (floatval($latest['User_Balance']) < $grand_total) {
                $error = "Insufficient wallet balance.";
            }
        }
    } elseif ($_POST['pay_type'] === 'card') {
        $_SESSION['card_temp_data'] = $_POST;
        $_SESSION['card_temp_data']['grand_total'] = $grand_total;
        $_SESSION['card_temp_data']['shipping'] = $shipping;
        $_SESSION['card_temp_data']['discount'] = $discount;
        $_SESSION['card_temp_data']['subtotal'] = $subtotal;
        
        $real_promo_id = "NULL";
        $real_user_promo_id = "NULL";
        if (!empty($applied_code)) {
            $p_res = $conn->query("SELECT p.Promo_Id, up.User_Promo_Id 
                                   FROM user_promo up 
                                   JOIN promo p ON up.Promo_Id = p.Promo_Id 
                                   WHERE p.Promo_Code = '$applied_code' 
                                   AND up.User_Id = '$uid' 
                                   AND up.Is_Used = 'No' 
                                   LIMIT 1");
            if ($p_res && $p_row = $p_res->fetch_assoc()) {
                $real_promo_id = intval($p_row['Promo_Id']);
                $real_user_promo_id = intval($p_row['User_Promo_Id']);
            }
        }
        $_SESSION['final_applied_promo_id'] = $real_promo_id;
        $_SESSION['final_applied_user_promo_id'] = $real_user_promo_id;
        
        header("Location: card_auth.php");
        exit();
    }
    
    if (empty($error)) {
        $email = $conn->real_escape_string($_POST['contact_email']);
        $addr  = $conn->real_escape_string($_POST['address']);
        $apt   = $conn->real_escape_string($_POST['apartment'] ?? '');
        
        $city     = $conn->real_escape_string(trim($_POST['city']));
        $state    = $conn->real_escape_string(trim($_POST['state']));
        $postcode = $conn->real_escape_string(trim($_POST['postcode']));
        $pay_type = $_POST['pay_type'];

        if (isset($_POST['save_address']) && $_POST['save_address'] == '1') {
            $combined_addr = $addr . ($apt ? ", " . $apt : "");
            $is_first = (count($address_book) === 0) ? 1 : 0; 
            
            // 完美匹配你的结构化数据列
            $stmt_save_addr = $conn->prepare("INSERT INTO user_address (User_Id, Address_Text, Postcode, State, City, Is_Default, Is_Deleted) VALUES (?, ?, ?, ?, ?, ?, 0)");
            if ($stmt_save_addr) {
                $stmt_save_addr->bind_param("issssi", $uid, $combined_addr, $postcode, $state, $city, $is_first);
                $stmt_save_addr->execute();
                $new_addr_id = $stmt_save_addr->insert_id;
                $stmt_save_addr->close();
                
                if ($is_first) {
                    $conn->query("UPDATE `user` SET Default_Address_Id = '$new_addr_id' WHERE User_Id = '$uid'");
                }
            }
        }
        
        if ($pay_type === 'fpx' && !isset($_POST['fpx_success'])) {
            $_SESSION['fpx_temp_data'] = $_POST; 
            $selected_bank = $_POST['fpx_bank'];
            header("Location: bank_portal.php?bank=$selected_bank&amt=$grand_total");
            exit();
        }

        if ($pay_type !== 'fpx' || isset($_POST['fpx_success'])) {
            $final_addr = "$f_name $last_name, $addr" . ($apt ? " ($apt)" : "") . ", $postcode, $city, $state";
        }
        $order_date = date('Y-m-d H:i:s');
        
        $conn->begin_transaction();
        try {
            $promo_id_to_save = "NULL";
            if (isset($_SESSION['applied_promo_code'])) {
                $code_temp = $_SESSION['applied_promo_code'];
                $p_res = $conn->query("SELECT Promo_Id FROM promo WHERE Promo_Code = '$code_temp'");
                if ($p_row = $p_res->fetch_assoc()) {
                    $promo_id_to_save = $p_row['Promo_Id'];
                }
            }

            function generateTrackingNum($conn) {
                do {
                    $track_num = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    $check = $conn->query("SELECT Order_Id FROM `ORDER` WHERE Order_Tracking_Num = '$track_num'");
                } while ($check->num_rows > 0);
                return $track_num;
            }
            $tracking_no = generateTrackingNum($conn);

            $pay_method_display = "Unknown";
            if ($_POST['pay_type'] === 'wallet') {
                $pay_method_display = "Store Wallet";
            } elseif ($_POST['pay_type'] === 'card') {
                $pay_method_display = "Credit / Debit Card";
            } elseif ($_POST['pay_type'] === 'fpx') {
                $pay_method_display = "FPX Online Banking (" . ($_POST['fpx_bank'] ?? 'Standard') . ")";
            }

            // 1. 插入主订单
            $sql_order = "INSERT INTO `ORDER` (User_Id, Order_Amount, Order_Shipping_Addr, Order_Date, Order_Tracking_Num, Promo_Id, Payment_Status, Payment_Method, Order_Status) 
                        VALUES ('$uid', '$grand_total', '$final_addr', '$order_date', '$tracking_no', $promo_id_to_save, 'Paid', '$pay_method_display', 'Pending')";
            $conn->query($sql_order);
            $order_id = $conn->insert_id;

            // 2. 直接遍历购物车，插入订单详情并更新库存
            foreach ($_SESSION['cart'] as $item) {
                $p_id = $item['pro_id'];
                $qty = $item['qty'];
                $size = $conn->real_escape_string($item['size']);
                $color = !empty($item['custom_preview']) ? 'Custom Design' : ($item['color'] ?? 'Default');
                $color = $conn->real_escape_string($color);
                
                $v_res = $conn->query("SELECT Pro_Price FROM product WHERE Pro_Id = '$p_id'");
                $p_info = $v_res->fetch_assoc();
                $unit_price = isset($item['price']) ? floatval($item['price']) : floatval($p_info['Pro_Price']);
                $subtotal_item = $unit_price * $qty;
                $item_custom_preview = $conn->real_escape_string($item['custom_preview'] ?? '');

                $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal, Pro_Size, Pro_Colour, Custom_Preview) 
                              VALUES ('$order_id', '$p_id', '$qty', '$subtotal_item', '$size', '$color', '$item_custom_preview')");
                
                $db_color_key = ($p_id == 16 || $p_id == 17) ? 'Default' : $color;
                $conn->query("UPDATE PRODUCT_STOCK SET Quantity = Quantity - $qty 
                              WHERE Pro_Id = '$p_id' AND Pro_Size = '$size' AND Pro_Colour = '$db_color_key'");
            }

            // ── 🌟【新增库存检查逻辑】
            try {
                $stock_check_sql = "
                    SELECT ps.Quantity AS Current_Stock, p.Pro_Name, b.Admin_Id
                    FROM PRODUCT_STOCK ps
                    JOIN product p ON ps.Pro_Id = p.Pro_Id
                    JOIN brand b ON p.Brand_Id = b.Brand_Id
                    WHERE ps.Pro_Id = '$p_id' AND ps.Pro_Size = '$size' AND ps.Pro_Colour = '$db_color_key'
                ";
                
                $stock_res = $conn->query($stock_check_sql);
                if ($stock_res && $stock_row = $stock_res->fetch_assoc()) {
                    $current_stock = intval($stock_row['Current_Stock']);
                    $pro_name = $stock_row['Pro_Name'];
                    $target_admin_id = $stock_row['Admin_Id'];

                    if ($current_stock <= 5) {
                        $notif_type = 'low_stock';
                        $notif_title = "Low Stock Warning";
                        $notif_msg = "Warning: Product '{$pro_name}' (Size: {$size}, Color: {$db_color_key}) is running low. Only {$current_stock} left!";
                        $notif_link = "admin_manage_products.php?open_stock_id=" . $p_id;

                        $dup_check = $conn->query("SELECT 1 FROM notification WHERE Notif_Type = 'low_stock' AND Related_Id = '$p_id' AND Notif_Created_At > DATE_SUB(NOW(), INTERVAL 1 DAY)");
                        
                        if ($dup_check && $dup_check->num_rows == 0) {
                            if (!empty($target_admin_id)) {
                                $stmt_brand = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt_brand->bind_param("ssssii", $notif_type, $notif_title, $notif_msg, $notif_link, $target_admin_id, $p_id);
                                $stmt_brand->execute();
                                $stmt_brand->close();
                            }

                            $stmt_global = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, NULL, ?)");
                            $stmt_global->bind_param("ssssi", $notif_type, $notif_title, $notif_msg, $notif_link, $p_id);
                            $stmt_global->execute();
                            $stmt_global->close();
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Low Stock Notification Error: " . $e->getMessage());
            }
            
            if (isset($_SESSION['applied_user_promo_id']) && !empty($_SESSION['applied_user_promo_id'])) {
                $user_promo_id = intval($_SESSION['applied_user_promo_id']);
                $conn->query("UPDATE user_promo SET Is_Used = 'Yes' WHERE User_Promo_Id = '$user_promo_id'");
            }
            
            $conn->commit();

            // ── 🌟【新增核心逻辑】新订单触发通知
            try {
                $notif_type = 'new_order';
                $notif_title = "New Order Placed";
                $order_tracking_num = $tracking_no;
                $tres = $conn->query("SELECT Order_Tracking_Num FROM `ORDER` WHERE Order_Id = '$order_id' LIMIT 1");
                if ($tres && $trow = $tres->fetch_assoc()) {
                    $order_tracking_num = $trow['Order_Tracking_Num'];
                }
                $notif_msg = "A new order #ODR{$order_tracking_num} has been successfully placed by Customer.";
                $notif_link = "admin_manage_orders.php";

                $brand_admin_sql = "
                    SELECT DISTINCT b.Admin_Id 
                    FROM order_detail od
                    JOIN product p ON od.Pro_Id = p.Pro_Id
                    JOIN brand b ON p.Brand_Id = b.Brand_Id
                    WHERE od.Order_Id = '$order_id' AND b.Admin_Id IS NOT NULL
                ";
                $brand_admin_res = $conn->query($brand_admin_sql);

                if ($brand_admin_res && $brand_admin_res->num_rows > 0) {
                    $stmt_notif = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, ?, ?)");
                    while ($brand_admin_row = $brand_admin_res->fetch_assoc()) {
                        $target_admin_id = intval($brand_admin_row['Admin_Id']);
                        $stmt_notif->bind_param("ssssii", $notif_type, $notif_title, $notif_msg, $notif_link, $target_admin_id, $order_id);
                        $stmt_notif->execute();
                    }
                    $stmt_notif->close();
                }

                $stmt_global = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, NULL, ?)");
                $stmt_global->bind_param("ssssi", $notif_type, $notif_title, $notif_msg, $notif_link, $order_id);
                $stmt_global->execute();
                $stmt_global->close();

            } catch (Exception $e) {
                error_log("Notification Error: " . $e->getMessage());
            }

            require_once 'send_receipt_handler.php'; 
            sendOrderReceiptEmail($order_id, $conn);
        
            unset($_SESSION['cart']);
            header("Location: payment_success.php?order_id=" . $order_id);
            exit();
        } catch (Exception $e) { 
            $conn->rollback(); 
            $error = "Order Failed: " . $e->getMessage(); 
        }
    }
}

include '../includes/header.php';
?>

<?php if($error || $success_msg): ?>
<div style="max-width: 1100px; margin: 20px auto 0; padding: 0 20px;">
    <?php if($error): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #f5c6cb; margin-bottom: 20px;">
        <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if($success_msg): ?>
    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #c3e6cb; margin-bottom: 20px;">
        <strong>✓ Success:</strong> <?php echo htmlspecialchars($success_msg); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
    body { background-color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; }
    .checkout-container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
    .checkout-grid { display: grid; grid-template-columns: 1fr 400px; gap: 60px; }
    @media (max-width: 992px) { .checkout-grid { grid-template-columns: 1fr; } }
    
    .section-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; color: #000; }
    .input-field { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 5px; font-size: 0.95rem; margin-bottom: 12px; transition: border 0.2s; background-color: #fff; box-sizing: border-box; height: 48px; line-height: 1.4;  }
    .input-field:focus { border: 2px solid #000; outline: none; }
    .row-cols-2 { display: flex; gap: 15px; align-items: baseline; }
    .row-cols-2 > div { flex: 1; }
    
    .payment-option { border: 1px solid #d9d9d9; border-radius: 5px; padding: 15px; margin-bottom: 10px; cursor: pointer; display: flex; align-items: center; gap: 15px; background: #fff; }
    .payment-option.active { border: 2px solid #17735b; background: #f4f9f8; }

    .save-postcode { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .save-postcode input { margin-top: 0; }
    .save-postcode label { margin: 0; }

    .sidebar { background: #fafafa; border-left: 1px solid #e6e6e6; padding: 20px; border-radius: 10px; }
    .cart-item { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; position: relative; }
    .item-img-wrapper { position: relative; width: 64px; height: 64px; border: 1px solid #e6e6e6; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; }
    .item-img-wrapper img { width: 90%; mix-blend-mode: multiply; }
    .qty-badge { position: absolute; top: -10px; right: -10px; background: #666; color: #fff; font-size: 12px; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    
    .btn-apply { height: 46px; align-self: center; border-radius: 12px; font-weight: 700; transition: 0.3s; }
    .btn-pay-now { width: 100%; background: #17735b; color: #fff; border: none; padding: 18px; border-radius: 5px; font-weight: 600; font-size: 1.1rem; cursor: pointer; margin-top: 25px; transition: background 0.3s; }
</style>

<div class="checkout-container">
    <div class="checkout-grid">
        <div class="main-form">
            <form id="orderForm" method="POST" action="">
                <input type="hidden" name="place_order" value="1">
                <div class="mb-5">
                    <h5 class="section-title">Contact</h5>
                    <input type="email" name="contact_email" class="input-field" placeholder="Email" value="<?php echo htmlspecialchars($user_info['User_Email']); ?>" required>
                </div>

                <div class="mb-5">
                    <h5 class="section-title">Delivery</h5>
                    
                    <?php if (!empty($address_book)): ?>
                    <div style="margin-bottom: 20px;">
                        <select id="savedAddressSelector" class="input-field" onchange="fillSavedAddress(this)" style="background-color: #f4f9f8; font-weight: 600; border-color: #17735b; color: #17735b;">
                            <option value="new">➕ Enter a completely new address</option>
                            <?php foreach($address_book as $idx => $addr): ?>
                                <option value="<?php echo $idx; ?>" <?php echo ($idx === 0) ? 'selected' : ''; ?>>
                                    📍 <?php echo htmlspecialchars($addr['Address_Text'] . ', ' . $addr['Postcode'] . ' ' . $addr['State']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <script>const userAddressBook = <?php echo json_encode($address_book); ?>;</script>
                    <?php else: ?>
                    <script>const userAddressBook = [];</script>
                    <?php endif; ?>

                    <div id="deliveryFormFields">
                        <div>
                            <input type="text" name="first_name" class="input-field" placeholder="First name" value="<?php echo $first_name; ?>" required oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')">
                        </div>
                        
                        <input type="text" name="address" class="input-field form-trigger-new" placeholder="Address" required>
                        
                        <div class="row-cols-2">
    <div>
        <input type="text" name="state" id="stateInput" class="input-field" placeholder="State" required>
    </div>
    <div>
        <input type="text" name="city" id="cityInput" class="input-field" placeholder="City" required>
    </div>
</div>
<div class="row-cols-2">
    <div>
        <input type="text" name="postcode" id="postcodeInput" class="input-field" placeholder="Postcode (5 digits)" maxlength="5" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
    </div>
    <div>
        <input type="text" name="phone" id="phone_field" class="input-field" placeholder="Phone (e.g. 0123456789)" value="<?php echo htmlspecialchars($user_phone); ?>" required>
    </div>
</div>
                        
                        <div class="save-address" id="saveAddressDiv" style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                            <input type="checkbox" name="save_address" id="save_address" value="1" class="form-check-input">
                            <label for="save_address" class="form-check-label small text-muted">Save this address to my address book for future orders</label>
                        </div>
                    </div>
                </div>
                

                <div class="mb-5">
                    <h5 class="section-title">Payment</h5>
                    <div class="payment-option <?php echo ($current_balance < $grand_total || !$has_pin) ? 'disabled' : ''; ?>" 
                         onclick="selectPay(this)">
                        
                        <input type="radio" name="pay_type" value="wallet" 
                            <?php echo ($current_balance >= $grand_total && $has_pin) ? '' : 'disabled'; ?>>
                        
                        <div class="flex-grow-1">
                            <div class="fw-bold"><i class="bi bi-wallet2 me-2"></i>Store Wallet</div>
                            <div class="small text-muted">
                                <?php if (!$has_pin): ?>
                                    <span class="text-danger">PIN not set. Activate in Dashboard.</span>
                                <?php else: ?>
                                    Balance: <strong>RM <?php echo number_format($current_balance, 2); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($has_pin && $current_balance < $grand_total): ?>
                            <span class="badge-insufficient">Insufficient Wallet</span>
                        <?php endif; ?>
                    </div>

                    <div class="payment-option active" onclick="selectPay(this)">
                        <input type="radio" name="pay_type" value="card" checked>
                        <div><div class="fw-bold">Credit / Debit Card</div><div class="small text-muted">Visa, Mastercard</div></div>
                    </div>
                    
                    <div class="payment-option" onclick="selectPay(this)">
                        <input type="radio" name="pay_type" value="fpx">
                        <div><div class="fw-bold">FPX</div><div class="small text-muted">Online Banking</div></div>
                    </div>
                    <div id="fpxBankDiv" style="display:none; margin-left: 15px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <div class="mb-3">
                        <label for="fpxBank" class="small fw-bold">Select Your Bank</label>
                        <select name="fpx_bank" id="fpxBank" class="input-field" required>
                            <option value="" disabled selected>Choose Bank</option>
                            <option value="MAYBANK">Maybank (MB)</option>
                            <option value="CIMB">CIMB (CIMB)</option>
                            <option value="PUBLIC">Public Bank (PB)</option>
                            <option value="RHB">RHB Bank (RHB)</option>
                            <option value="AMBANK">AmBank (AB)</option>
                            <option value="AFFIN">AFFIN Bank (AF)</option>
                            <option value="ALLIANCE">Alliance Bank (AB)</option>
                            <option value="BOOST">Boost (MY)</option>
                            <option value="UOB">UOB (UOB)</option>
                            <option value="OCBC">OCBC Bank (OCBC)</option>
                            <option value="HSBC">HSBC (HB)</option>
                            <option value="SCB">Standard Chartered (SCB)</option>
                            <option value="DBS">DBS (DB)</option>
                            <option value="BIM">Bank Islam (BI)</option>
                            <option value="IMM">Islamic Bank Mal (IM)</option>
                            <option value="BANK_MUAMALAT">Bank Muamalat (MB)</option>
                        </select>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="place_order" value="1">
                <button type="button" class="btn-pay-now" onclick="startPaymentProcess()">Pay now</button>
            </form>
        </div>

        <div class="sidebar-wrapper">
            <div class="sidebar">
                <h5 class="section-title" style="border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px;">
                    Order Summary
                </h5>
                <div class="mb-4">
                    <?php foreach($checkout_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-img-wrapper">
                                <img src="<?php echo $item['display_image']; ?>" onerror="this.src='../images/placeholder.png'">
                                <span class="qty-badge"><?php echo $item['qty']; ?></span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold small"><?php echo $item['Pro_Name']; ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;"><?php echo $item['size']; ?> / <?php echo $item['color']; ?></div>
                            </div>
                            <div class="fw-bold small">RM <?php echo number_format($item['item_total'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($auto_applied && $applied_code): ?>
                    <div style="background: #f0f9f7; border-left: 4px solid #17735b; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                        <div style="color: #17735b; font-weight: 600; font-size: 0.9rem; margin-bottom: 4px;">✨ Auto-Applied Best Deal</div>
                        <div style="color: #333; font-size: 0.85rem;">
                            <strong><?php echo htmlspecialchars($best_promo['Promo_Name'] ?? ''); ?></strong><br>
                            Code: <code style="background: white; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($applied_code); ?></code>
                            &nbsp;|&nbsp;
                            Saving: <span style="color: #17735b; font-weight: 600;">RM <?php echo number_format($discount, 2); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($available_vouchers)): ?>
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 8px; font-weight: 600;">
                            💳 Your Available Vouchers:
                        </div>
                        <div style="display: grid; gap: 8px;">
                            <?php foreach ($available_vouchers as $voucher): ?>
                                <div class="voucher-card" 
                                     style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px; cursor: pointer; transition: all 0.2s; background: white;"
                                     onmouseover="this.style.borderColor='#17735b'; this.style.boxShadow='0 2px 8px rgba(23, 115, 91, 0.1)'"
                                     onmouseout="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'"
                                     onclick="applyVoucher('<?php echo htmlspecialchars($voucher['promo_code']); ?>')">
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.85rem; color: #333;">
                                                <?php echo htmlspecialchars($voucher['promo_name']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #999; margin-top: 2px;">
                                                Expires: <?php echo date('d M Y', strtotime($voucher['expired_date'])); ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 700; color: #17735b; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($voucher['discount_display']); ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: #17735b;">
                                                Save RM <?php echo number_format($voucher['discount_amount'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #666; font-family: monospace; background: #f5f5f5; padding: 4px 6px; border-radius: 3px; display: inline-block;">
                                        <?php echo htmlspecialchars($voucher['promo_code']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="voucher-card" 
                                 style="border: 1px dashed #ccc; border-radius: 6px; padding: 10px; cursor: pointer; text-align: center; background: #fafafa;"
                                 onmouseover="this.style.borderColor='#999'; this.style.backgroundColor='#f0f0f0'"
                                 onmouseout="this.style.borderColor='#ccc'; this.style.backgroundColor='#fafafa'"
                                 onclick="applyVoucher('')">
                                <div style="font-weight: 600; font-size: 0.85rem; color: #555;">
                                    🚫 Don't use any vouchers this time
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="mb-4">
                    <div style="margin-bottom: 8px; font-size: 0.85rem; color: #666;">
                        <?php if ($auto_applied && $applied_code): ?>
                            Have a different code? Enter it below:
                        <?php else: ?>
                            Have a promo code? Enter it below:
                        <?php endif; ?>
                    </div>
                    <div class="input-group">
                        <input type="text" name="coupon_code" class="form-control" placeholder="Discount code" value="">
                        <button type="submit" name="apply_coupon" class="btn btn-dark btn-apply">Apply</button>
                    </div>
                </form>

                <div class="total-line d-flex justify-content-between h4 fw-bold">
                    <span>Total</span>
                    <span>RM <?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 处理 voucher 点击应用（保持原样）
function applyVoucher(promoCode) {
    const couponInput = document.querySelector('input[name="coupon_code"]');
    if (couponInput) {
        couponInput.value = promoCode;
    }
    const applyBtn = document.querySelector('button[name="apply_coupon"]');
    if (applyBtn) {
        applyBtn.click();
    }
}

// =========================================================================
// 【精准修复】：剔除旧 custom 节点残留，防止脚本崩溃卡死 City 的 fillSavedAddress
// =========================================================================
function fillSavedAddress(selectEl) {
    const val = selectEl.value;
    const addressInput = document.querySelector('input[name="address"]');
    const stateInput = document.getElementById('stateInput');
    const cityInput = document.getElementById('cityInput');
    const postcodeInput = document.getElementById('postcodeInput');
    const saveCheckboxDiv = document.getElementById('saveAddressDiv');
    const saveCheckbox = document.getElementById('save_address');

    if (val === 'new') {
        setFormLock(false);
        addressInput.value = '';
        stateInput.value = '';
        cityInput.value = '';
        postcodeInput.value = '';
        if (saveCheckboxDiv) saveCheckboxDiv.style.display = 'flex';
        if (saveCheckbox) saveCheckbox.checked = false;
    } else {
        const addrData = userAddressBook[val];
        
        const firstNameInput = document.querySelector('input[name="first_name"]');
        if (firstNameInput) firstNameInput.value = "<?php echo $first_name; ?>";
        
        if (addressInput) addressInput.value = addrData.Address_Text || '';
        
        // ── 💡 核心修复：如果地址簿里没有定义 City，则使用当前用户的全局默认 City 兜底 ──
        if (stateInput) stateInput.value = addrData.State || addrData.state || "<?php echo $user_state; ?>";
        if (cityInput) cityInput.value = addrData.City || addrData.city || "<?php echo $user_city; ?>";
        if (postcodeInput) postcodeInput.value = addrData.Postcode || addrData.postcode || "<?php echo $user_postcode; ?>";
        
        if (saveCheckboxDiv) saveCheckboxDiv.style.display = 'none';
        if (saveCheckbox) saveCheckbox.checked = false;
        
        setFormLock(true); 
    }
}

// =========================================================================
// 统一干净的 DOMContentLoaded 监听器
// =========================================================================
document.addEventListener('DOMContentLoaded', function() {
    const addrSelector = document.getElementById('savedAddressSelector');
    
    // 情况 A：如果用户持有保存的地址簿，直接直刷装载数据
    if (addrSelector && addrSelector.value !== 'new') {
        fillSavedAddress(addrSelector);
    } 
    // 情况 B：无地址簿老用户读取主外键散落数据兜底
    else {
        const dbState = "<?php echo $user_state; ?>";
        const dbCity = "<?php echo $user_city; ?>"; 
        const dbPostcode = "<?php echo $user_postcode; ?>";
        const dbAddress = "<?php echo $conn->real_escape_string($user_address); ?>";

        if (dbAddress || dbState || dbCity || dbPostcode) {
            const addressInput = document.querySelector('input[name="address"]');
            if (addressInput) addressInput.value = dbAddress;
            
            const stateInput = document.getElementById('stateInput');
            if (stateInput) stateInput.value = dbState;
            
            const cityInput = document.getElementById('cityInput');
            if (cityInput) cityInput.value = dbCity;
            
            const postcodeInput = document.getElementById('postcodeInput');
            if (postcodeInput) postcodeInput.value = dbPostcode;
        }
    }

    // 初始化支付视图切换
    const checkedPayRadio = document.querySelector('input[name="pay_type"]:checked');
    if (checkedPayRadio) {
        const activeOption = checkedPayRadio.closest('.payment-option');
        if (activeOption) selectPay(activeOption);
    }

    // 显示错误/成功消息
    <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Payment Failed', text: '<?php echo $error; ?>', confirmButtonColor: '#17735b' });
    <?php endif; ?>

    <?php if ($success_msg): ?>
        Swal.fire({ icon: 'success', title: 'Success', text: <?php echo json_encode($success_msg); ?>, timer: 2500, showConfirmButton: false });
    <?php endif; ?>
});

function selectPay(el) {
    if (!el || el.classList.contains('disabled')) return;

    document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    
    const radio = el.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    const payType = radio ? radio.value : '';
    
    const walletDiv = document.getElementById('walletPinField');
    const fpxDiv = document.getElementById('fpxBankDiv');
    const cardDiv = document.getElementById('cardFieldsDiv');
    
    if (walletDiv) walletDiv.style.display = (payType === 'wallet') ? 'block' : 'none';
    if (fpxDiv) fpxDiv.style.display = (payType === 'fpx') ? 'block' : 'none';
    if (cardDiv) cardDiv.style.display = (payType === 'card') ? 'block' : 'none';

    document.querySelectorAll('#fpxBank, #wallet_pin_input').forEach(input => {
        input.removeAttribute('required');
    });

    if (payType === 'wallet') {
        const pinInp = document.getElementById('wallet_pin_input');
        if (pinInp) pinInp.setAttribute('required', 'true');
    } else if (payType === 'fpx') {
        const bankSel = document.getElementById('fpxBank');
        if (bankSel) bankSel.setAttribute('required', 'true');
    }
}

async function startPaymentProcess() {
    const payType = document.querySelector('input[name="pay_type"]:checked').value;
    
    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const address = document.querySelector('input[name="address"]').value.trim();
    const phone = document.querySelector('input[name="phone"]').value.trim();
    const state = document.getElementById('stateInput').value.trim();
    const city = document.getElementById('cityInput').value.trim();
    const postcode = document.getElementById('postcodeInput').value.trim();
    
    if (!firstName || !address || !phone || !state || !city || !postcode) {
        Swal.fire('Information Required', 'Please complete your delivery details first.', 'warning');
        return;
    }

    if (payType === 'wallet') {
        submitCheckoutForm();
    }
    else if (payType === 'card') {
        submitCheckoutForm();
    }
    else if (payType === 'fpx') {
        const fpxBank = document.querySelector('select[name="fpx_bank"]').value;
        if (!fpxBank) {
            Swal.fire('Bank Required', 'Please select a bank for FPX payment.', 'warning');
            return;
        }
        submitCheckoutForm();
    }
}

function submitCheckoutForm() {
    Swal.fire({
        title: 'Processing Payment...',
        html: 'Please do not refresh the page.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    document.getElementById('orderForm').submit();
}

function setFormLock(isLocked) {
    const container = document.getElementById('deliveryFormFields');
    if (!container) return;
    const inputs = container.querySelectorAll('.input-field');
    
    inputs.forEach(el => {
        if (isLocked) {
            if (el.tagName === 'INPUT') el.readOnly = true;
            el.style.pointerEvents = 'none';
            el.style.backgroundColor = '#f8fafc'; 
            el.style.borderColor = '#e2e8f0';
            el.style.color = '#64748b';
        } else {
            if (el.tagName === 'INPUT') el.readOnly = false;
            el.style.pointerEvents = 'auto';
            el.style.backgroundColor = '#fff';
            el.style.borderColor = '#d9d9d9';
            el.style.color = '#333';
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>