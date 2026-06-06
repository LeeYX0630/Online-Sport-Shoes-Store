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

// 获取用户详细资料 (包含 User_PIN)
$user_sql = "SELECT * FROM `USER` WHERE User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();
$current_balance = floatval($user_info['User_Balance']);
$has_pin = !empty($user_info['User_PIN']);

$user_sql = "SELECT * FROM `USER` WHERE User_Id = '$uid'";
$user_res = $conn->query($user_sql);
$user_info = $user_res->fetch_assoc();
$current_balance = floatval($user_info['User_Balance']);

// 提取用户基础资料
$user_phone = $user_info['User_Phone'];
$user_address = $user_info['User_Address'];
$user_state = $user_info['User_State'];
$user_postcode = $user_info['User_Postcode'];
// 假设城市信息也存在，若不存在则留空
$user_city = isset($user_info['User_City']) ? $user_info['User_City'] : "";

$name_parts = explode(' ', $user_info['User_Name'], 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : "";

$subtotal = 0;
$checkout_items = [];
foreach ($_SESSION['cart'] as $cart_key => $item) {
    $pid = $item['pro_id'];
    // 这里的 SQL 保持不变
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
    
    // 【核心修复】：只查询该用户拥有且未使用的优惠券
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
            
            // 判断该 promo 是否对该用户可用
            // 规则1: 新用户 promo (包含 "Welcome" 或 "New User" 的)
            $is_new_user_promo = (
                strpos(strtoupper($promo['Promo_Name']), 'WELCOME') !== false ||
                strpos(strtoupper($promo['Promo_Name']), 'NEW USER') !== false
            );
            
            // 规则2: 生日 promo (包含 "Birthday" 的，并且用户 ID 在代码中)
            $is_birthday_promo = (
                strpos(strtoupper($promo['Promo_Name']), 'BIRTHDAY') !== false &&
                strpos($promo['Promo_Code'], (string)$user_id) !== false
            );
            
            // 规则3: 全局 promo (既不是新用户也不是生日的)
            $is_global_promo = !$is_new_user_promo && !$is_birthday_promo;
            
            // 如果是新用户 promo，检查用户是否有订单（有订单就不是新用户）
            if ($is_new_user_promo) {
                $order_check = $conn->query("SELECT 1 FROM `order` WHERE User_Id = '$user_id' LIMIT 1");
                if ($order_check && $order_check->num_rows > 0) {
                    continue; // 跳过这个新用户 promo
                }
            }
            
            // 如果是生日 promo，检查用户生日是否在当月
            if ($is_birthday_promo) {
                $birthday_check = $conn->query("
                    SELECT 1 FROM `user` 
                    WHERE User_Id = '$user_id' 
                    AND MONTH(User_DateOfBirth) = MONTH(CURDATE())
                    LIMIT 1
                ");
                if (!$birthday_check || $birthday_check->num_rows === 0) {
                    continue; // 跳过这个生日 promo
                }
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

    while ($promo = $promo_query && $row = $promo_query->fetch_assoc()) {
        $available_promos[] = [
            'promo_code' => $row['Promo_Code'],
            'promo_name' => $row['Promo_Name'],
            'discount_display' => ($row['Promo_Type'] === 'Percentage') ? intval($row['Promo_Value'])."% OFF" : "RM ".$row['Promo_Value']." OFF",
            'discount_amount' => ($row['Promo_Type'] === 'Percentage') ? ($subtotal * ($row['Promo_Value']/100)) : $row['Promo_Value'],
            'expired_date' => $row['Expired_Date']
        ];
    }
    return $available_promos;
}


// 4. 处理优惠码逻辑 - 如果用户没有手动输入，则自动应用最优优惠
// 4. 处理优惠码逻辑 - 修复手动选择被覆盖的漏洞
$discount = 0;
$applied_code = "";
$auto_applied = false;
$available_vouchers = getUserAvailablePromos($conn, $uid, $subtotal);

// 【核心修复】：检查当前请求中是否有优惠码输入，或者 Session 中是否已记录用户的手动选择
$is_manual_action = isset($_POST['apply_coupon']);
$has_input_code = (isset($_POST['coupon_code']) && trim($_POST['coupon_code']) !== '');

if ($is_manual_action || $has_input_code) {
    // A. 用户正在手动操作（输入了代码并点击 Apply 或直接点击支付）
    $code = $conn->real_escape_string(trim($_POST['coupon_code']));
    
    if ($code !== "") {
        // 【核心修复】：从 user_promo 表验证用户是否真的拥有该优惠券
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
            // 锁定手动选择状态，禁止自动优化干扰，并记录 User_Promo_Id
            $_SESSION['user_chose_promo'] = true;
            $_SESSION['applied_promo_code'] = $applied_code;
            $_SESSION['applied_discount'] = $discount;
            $_SESSION['applied_user_promo_id'] = $promo['User_Promo_Id'];  // 记录用于核销
        } else {
            $error = "Invalid or expired code, or you don't own this coupon.";
            // 即使无效，也维持手动状态，防止系统自动跳回 Best Deal
            $_SESSION['user_chose_promo'] = true;
            $applied_code = $code; 
        }
    } else {
        // 用户清空了输入框，视为【明确不使用优惠券】
        $_SESSION['user_chose_promo'] = true; // 锁定手动状态，阻止系统自动寻找 Best Deal
        unset($_SESSION['applied_promo_code'], $_SESSION['applied_discount'], $_SESSION['applied_user_promo_id']);
        $applied_code = "";
        $discount = 0;
    }
} else {
    // B. 无手动操作时：检查 Session 记录[cite: 39]
    if (isset($_SESSION['user_chose_promo']) && $_SESSION['user_chose_promo']) {
        $applied_code = $_SESSION['applied_promo_code'] ?? '';
        $discount = $_SESSION['applied_discount'] ?? 0;
        $auto_applied = false;
    } else {
        // 只有在完全没有手动干预的情况下，才执行“自动寻找最大折扣”[cite: 39]
        $optimal = getOptimalPromo($conn, $uid, $subtotal);
        if ($optimal['promo'] !== null) {
            $best_promo = $optimal['promo'];
            $discount = $optimal['discount_amount'];
            $applied_code = $best_promo['Promo_Code'];
            $auto_applied = true;
            $success_msg = "✨ Auto-Applied Best Deal: " . htmlspecialchars($best_promo['Promo_Name']) . " - " . $optimal['discount_info'];
        }
    }
}

$shipping = ($subtotal >= 250) ? 0 : 15.00;
$grand_total = max(0, ($subtotal + $shipping) - $discount);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    // 如果是从银行授权页返回，先恢复之前暂存的订单数据再进行验证
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
    
    // 【修复 1】：安全获取邮编，防止产生 Undefined array key 警告
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
        
        // --- 新增：PIN 校验规则 (禁止字母和符号) ---
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
        
        // 解析当前生效的优惠券对应的真实主键
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
        // 将正确洗净的 ID 存入独立的 Session 变量
        $_SESSION['final_applied_promo_id'] = $real_promo_id;
        $_SESSION['final_applied_user_promo_id'] = $real_user_promo_id;
        
        header("Location: card_auth.php");
        exit();
    }
    
    
    if (empty($error)) {
        $email = $conn->real_escape_string($_POST['contact_email']);
        $addr = $conn->real_escape_string($_POST['address']);
        $apt = $conn->real_escape_string($_POST['apartment']);
        $city = $conn->real_escape_string($_POST['city']);
        $state = $conn->real_escape_string($_POST['state']);
        $pay_type = $_POST['pay_type'];
        
        if ($pay_type === 'fpx' && !isset($_POST['fpx_success'])) {
            $_SESSION['fpx_temp_data'] = $_POST; // 暂存所有寄送资料
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
                
                // 获取单价计算小计
                $v_res = $conn->query("SELECT Pro_Price FROM product WHERE Pro_Id = '$p_id'");
                $p_info = $v_res->fetch_assoc();
                $unit_price = isset($item['price']) ? floatval($item['price']) : floatval($p_info['Pro_Price']);
                $subtotal_item = $unit_price * $qty;
                $item_custom_preview = $conn->real_escape_string($item['custom_preview'] ?? '');

                // 核心：直接关联 Order_Id，Sub_Order_Id 设为 0
                $conn->query("INSERT INTO ORDER_DETAIL (Order_Id, Pro_Id, Order_Qty, Order_Subtotal, Pro_Size, Pro_Colour, Custom_Preview) 
                              VALUES ('$order_id', '$p_id', '$qty', '$subtotal_item', '$size', '$color', '$item_custom_preview')");
                
                // 更新库存
                $db_color_key = ($p_id == 16 || $p_id == 17) ? 'Default' : $color;
                $conn->query("UPDATE PRODUCT_STOCK SET Quantity = Quantity - $qty 
                              WHERE Pro_Id = '$p_id' AND Pro_Size = '$size' AND Pro_Colour = '$db_color_key'");
            }

            // ── 🌟【新增库存检查逻辑】放在更新库存的下一行 ───────────────────
                try {
                    // 1. 查出当前变动商品扣减后的【当前尺码与颜色】的最新剩余库存，以及它属于哪个品牌管理员
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

                        // 2. 判断该尺码颜色的剩余库存是否少于或等于 5
                        if ($current_stock <= 5) {
                            $notif_type = 'low_stock';
                            $notif_title = "Low Stock Warning";
                            // 文案提示更贴心：带上具体的尺码和颜色
                            $notif_msg = "Warning: Product '{$pro_name}' (Size: {$size}, Color: {$db_color_key}) is running low. Only {$current_stock} left!";
                            $notif_link = "admin_manage_products.php?open_stock_id=" . $p_id;

                            // 3. 24小时防刷机制：避免同一个商品频繁刷一模一样的通知
                            $dup_check = $conn->query("SELECT 1 FROM notification WHERE Notif_Type = 'low_stock' AND Related_Id = '$p_id' AND Notif_Created_At > DATE_SUB(NOW(), INTERVAL 1 DAY)");
                            
                            if ($dup_check && $dup_check->num_rows == 0) {
                                
                                // 精准发给对应的 Level 3 品牌管理员
                                if (!empty($target_admin_id)) {
                                    $stmt_brand = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt_brand->bind_param("ssssii", $notif_type, $notif_title, $notif_msg, $notif_link, $target_admin_id, $p_id);
                                    $stmt_brand->execute();
                                    $stmt_brand->close();
                                }

                                // 广播发给 Level 1 & 2 的系统总管理员
                                $stmt_global = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, NULL, ?)");
                                $stmt_global->bind_param("ssssi", $notif_type, $notif_title, $notif_msg, $notif_link, $p_id);
                                $stmt_global->execute();
                                $stmt_global->close();
                            }
                        }
                    }
                } catch (Exception $e) {
                    // 记录错误日志，确保不会因为通知代码报错而卡死或终止顾客的 Checkout 下单流程
                    error_log("Low Stock Notification Error: " . $e->getMessage());
                }
            
            // 核销优惠券
            if (isset($_SESSION['applied_user_promo_id']) && !empty($_SESSION['applied_user_promo_id'])) {
                $user_promo_id = intval($_SESSION['applied_user_promo_id']);
                $conn->query("UPDATE user_promo SET Is_Used = 'Yes' WHERE User_Promo_Id = '$user_promo_id'");
            }
            
            $conn->commit();

            // ── 🌟【新增核心逻辑】顾客结算成功，自动向管理员触发 New Order 通知 ──
            try {
                $notif_type = 'new_order';
                $notif_title = "New Order Placed";
                // 从数据库读取刚插入订单的追踪号，确保使用数据库中的值（fallback 到生成的 $tracking_no）
                $order_tracking_num = $tracking_no;
                $tres = $conn->query("SELECT Order_Tracking_Num FROM `ORDER` WHERE Order_Id = '$order_id' LIMIT 1");
                if ($tres && $trow = $tres->fetch_assoc()) {
                    $order_tracking_num = $trow['Order_Tracking_Num'];
                }
                $notif_msg = "A new order #ODR{$order_tracking_num} has been successfully placed by Customer.";
                $notif_link = "admin_manage_orders.php";

                // 1. 找出这个新订单里面包含了哪些 Level 3 品牌管理员管理的产品
                $brand_admin_sql = "
                    SELECT DISTINCT b.Admin_Id 
                    FROM order_detail od
                    JOIN product p ON od.Pro_Id = p.Pro_Id
                    JOIN brand b ON p.Brand_Id = b.Brand_Id
                    WHERE od.Order_Id = '$order_id' AND b.Admin_Id IS NOT NULL
                ";
                $brand_admin_res = $conn->query($brand_admin_sql);

                // 2. 循环给这些涉及到的 Level 3 品牌管理员发送精准通知
                if ($brand_admin_res && $brand_admin_res->num_rows > 0) {
                    $stmt_notif = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, ?, ?)");
                    while ($brand_admin_row = $brand_admin_res->fetch_assoc()) {
                        $target_admin_id = intval($brand_admin_row['Admin_Id']);
                        $stmt_notif->bind_param("ssssii", $notif_type, $notif_title, $notif_msg, $notif_link, $target_admin_id, $order_id);
                        $stmt_notif->execute();
                    }
                    $stmt_notif->close();
                }

                // 3. 同时发一条全局公共广播通知（Admin_Id = NULL），让 Level 1 和 Level 2 的总管理员也能收到
                $stmt_global = $conn->prepare("INSERT INTO notification (Notif_Type, Notif_Title, Notif_Message, Notif_Link, Admin_Id, Related_Id) VALUES (?, ?, ?, ?, NULL, ?)");
                $stmt_global->bind_param("ssssi", $notif_type, $notif_title, $notif_msg, $notif_link, $order_id);
                $stmt_global->execute();
                $stmt_global->close();

            } catch (Exception $e) {
                // 即使通知写入失败，也不要影响客户购物车结算成功的流程
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

<!-- 在页面顶部显示错误或成功消息 -->
<?php if($error || $success_msg): ?>
<div style="max-width: 1100px; margin: 20px auto 0; padding: 0 20px;">
    <?php if($error): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #f5c6cb; margin-bottom: 20px;">
        <strong>❌ 出错：</strong> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if($success_msg): ?>
    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #c3e6cb; margin-bottom: 20px;">
        <strong>✓ 成功：</strong> <?php echo htmlspecialchars($success_msg); ?>
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
                    
                    <div>
                        <div><input type="text" name="first_name" class="input-field" placeholder="First name" value="<?php echo $first_name; ?>" required oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '')"></div>
                    </div>
                    
                    <input type="text" name="address" class="input-field" placeholder="Address" 
                        value="<?php echo htmlspecialchars($user_address); ?>" required>
                    <input type="text" name="apartment" class="input-field" placeholder="Apartment, suite, unit etc. (optional)">
                    
                    <div class="row-cols-2">
                        <div>
                            <select name="state" id="stateSelect" class="input-field" required onchange="updateCities()">
                                <option value="" disabled selected>Select State</option>
                            </select>
                        </div>
                        <div>
                            <select name="city" id="citySelect" class="input-field" required onchange="updatePostcodes()">
                                <option value="" disabled selected>Select City</option>
                            </select>
                        </div>
                    </div>
                    <div class="row-cols-2">
                        <div>
                            <select name="postcode" id="postcodeSelect" class="input-field" required onchange="toggleCustomPostcode()">
                                <option value="" disabled selected>Postcode</option>
                            </select>
                        </div>
                        <div>
                            <input type="text" name="phone" id="phone_field" class="input-field" placeholder="Phone (e.g. 0123456789)" value="<?php echo htmlspecialchars($user_phone); ?>" required oninput="...">
                        </div>
                    </div>
                    <div id="customPostcodeDiv" style="display:none;">
                        <input type="text" name="custom_postcode" id="customPostcode" class="input-field" placeholder="Enter your postcode (5 digits)" maxlength="5" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    
                    <div class="save-postcode">
                        <input type="checkbox" name="save_postcode" id="save_postcode" class="form-check-input" <?php echo (isset($_COOKIE['saved_postcode']) && $_COOKIE['saved_postcode'] === $user_info['User_Postcode']) ? 'checked' : ''; ?>>
                        <label for="save_postcode" class="form-check-label small text-muted">Save this postcode for future orders</label>
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

// 处理 voucher 点击应用
function applyVoucher(promoCode) {
    // 填入优惠码
    const couponInput = document.querySelector('input[name="coupon_code"]');
    if (couponInput) {
        couponInput.value = promoCode;
    }
    
    // 【修复】：不要使用 form.submit()，而是模拟点击 Apply 按钮
    // 这样才能确保后端接收到 $_POST['apply_coupon']，从而触发清除逻辑
    const applyBtn = document.querySelector('button[name="apply_coupon"]');
    if (applyBtn) {
        applyBtn.click();
    }
}

const locationData = {
    "Johor": {
        "Johor Bahru": ["80000", "81100", "81200", "81300"],
        "Batu Pahat": ["83000", "83010", "83040"],
        "Kluang": ["86000", "86100"],
        "Muar": ["84000", "84150"],
        "Segamat": ["85000", "85100"],
        "Pontian": ["82000", "82100"],
        "Kota Tinggi": ["81900"],
        "Mersing": ["86800"],
        "Tangkak": ["84900"],
        "Kulai": ["81000"]
    },
    "Kedah": {
        "Alor Setar": ["05000", "05100", "05200"],
        "Sungai Petani": ["08000", "08100"],
        "Kulim": ["09000", "09100"],
        "Langkawi": ["07000"],
        "Kubang Pasu": ["06000", "06100"],
        "Baling": ["09100"],
        "Sik": ["08200"],
        "Yan": ["06900"],
        "Pendang": ["06700"]
    },
    "Kelantan": {
        "Kota Bharu": ["15000", "15100", "16100"],
        "Pasir Mas": ["17000"],
        "Tumpat": ["16200"],
        "Tanah Merah": ["17500"],
        "Gua Musang": ["18300"],
        "Machang": ["18500"],
        "Kuala Krai": ["18000"]
    },
    "Melaka": {
        "Melaka City": ["75000", "75100", "75200", "75300", "75400"],
        "Alor Gajah": ["78000", "78100", "78300"],
        "Jasin": ["77000", "77100", "77300"]
    },
    "Negeri Sembilan": {
        "Seremban": ["70000", "70100", "70200", "70300", "70450"],
        "Port Dickson": ["71000", "71010"],
        "Nilai": ["71800"],
        "Jempol": ["72100"],
        "Tampin": ["73000"],
        "Kuala Pilah": ["72000"]
    },
    "Pahang": {
        "Kuantan": ["25000", "25100", "25200"],
        "Temerloh": ["28000"],
        "Bentong": ["28700"],
        "Mekan": ["26600"],
        "Raub": ["27600"],
        "Jerantut": ["27000"],
        "Cameron Highlands": ["39000", "39100"]
    },
    "Perak": {
        "Ipoh": ["30000", "30100", "31400"],
        "Taiping": ["34000", "34010"],
        "Teluk Intan": ["36000"],
        "Manjung/Siawan": ["32000", "32200"],
        "Kuala Kangsar": ["33000"],
        "Tapah": ["35000"],
        "Batu Gajah": ["31000"]
    },
    "Perlis": {
        "Kangar": ["01000"],
        "Arau": ["02600"],
        "Kuala Perlis": ["02000"],
        "Padang Besar": ["02100"]
    },
    "Pulau Pinang": {
        "Georgetown": ["10000", "10100", "10200", "10300", "10450"],
        "Bayan Lepas": ["11900", "11920", "11950"],
        "Butterworth": ["12000", "12100", "13400"],
        "Bukit Mertajam": ["14000", "14020"],
        "Kepala Batas": ["13200"],
        "Nibong Tebal": ["14300"]
    },
    "Sabah": {
        "Kota Kinabalu": ["88000", "88100", "88200", "88300"],
        "Sandakan": ["90000"],
        "Tawau": ["91000"],
        "Lahad Datu": ["91100"],
        "Penampang": ["89500"],
        "Keningau": ["89000"],
        "Putatan": ["88200"]
    },
    "Sarawak": {
        "Kuching": ["93000", "93100", "93200", "93300"],
        "Miri": ["98000", "98100"],
        "Sibu": ["96000"],
        "Bintulu": ["97000"],
        "Samarahan": ["94300"],
        "Sri Aman": ["95000"],
        "Limbang": ["98700"]
    },
    "Selangor": {
        "Shah Alam": ["40000", "40100", "40150", "40170", "40460"],
        "Petaling Jaya": ["46000", "46100", "46200", "47300", "47301", "47400"],
        "Klang": ["41000", "41050", "41100", "41200", "42100"],
        "Subang Jaya": ["47500", "47600", "47610"],
        "Puchong": ["47100", "47110", "47160"],
        "Cyberjaya": ["63000", "63100", "63200"],
        "Kajang": ["43000"],
        "Rawang": ["48000"],
        "Semenyih": ["43500"],
        "Sepang": ["43900"]
    },
    "Terengganu": {
        "Kuala Terengganu": ["20000", "20100", "21000"],
        "Kemaman": ["24000"],
        "Dungun": ["23000"],
        "Besut": ["22200"],
        "Marang": ["21600"],
        "Hulu Terengganu": ["21700"]
    },
    "Kuala Lumpur": {
        "KL City": ["50000", "50100", "50250", "50450", "50480"],
        "Cheras": ["56000", "56100"],
        "Kepong": ["52100", "52200"],
        "Wangsa Maju": ["53300"],
        "Setapak": ["53000", "53100"],
        "Bangsar": ["59000", "59100"],
        "Old Klang Road": ["58000", "58200"],
        "Sentul": ["51000", "51100"]
    },
    "Putrajaya": {
        "Putrajaya": ["62000", "62007", "62100", "62250"]
    },
    "Labuan": {
        "Labuan": ["87000", "87008", "87010"]
    }
};

// 初始化 State 下拉框
function initStates() {
    const stateSelect = document.getElementById('stateSelect');
    Object.keys(locationData).sort().forEach(state => {
        let option = document.createElement('option');
        option.value = state;
        option.text = state;
        stateSelect.add(option);
    });
}

function updateCities() {
    const state = document.getElementById('stateSelect').value;
    const citySelect = document.getElementById('citySelect');
    const postcodeSelect = document.getElementById('postcodeSelect');
    
    citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
    postcodeSelect.innerHTML = '<option value="" disabled selected>Postcode</option>';
    
    if (locationData[state]) {
        Object.keys(locationData[state]).sort().forEach(city => {
            let option = document.createElement('option');
            option.value = city;
            option.text = city;
            citySelect.add(option);
        });
    }
}

function updatePostcodes() {
    const state = document.getElementById('stateSelect').value;
    const city = document.getElementById('citySelect').value;
    const postcodeSelect = document.getElementById('postcodeSelect');
    
    postcodeSelect.innerHTML = '<option value="" disabled selected>Postcode</option>';
    
    if (locationData[state] && locationData[state][city]) {
        locationData[state][city].forEach(postcode => {
            let option = document.createElement('option');
            option.value = postcode;
            option.text = postcode;
            postcodeSelect.add(option);
        });
    }
    // Add "Other" option
    let otherOption = document.createElement('option');
    otherOption.value = 'other';
    otherOption.text = 'Other';
    postcodeSelect.add(otherOption);
}

// 核心修复：支付方式切换逻辑
// 核心修复：切换支付方式逻辑
function selectPay(el) {
    if (!el || el.classList.contains('disabled')) return;

    // 1. 切换视觉 active 状态
    document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    
    // 2. 勾选隐藏的单选框
    const radio = el.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    const payType = radio ? radio.value : '';
    
    // 3. 切换输入框显示/隐藏
    const walletDiv = document.getElementById('walletPinField');
    const fpxDiv = document.getElementById('fpxBankDiv');
    const cardDiv = document.getElementById('cardFieldsDiv');
    
    if (walletDiv) walletDiv.style.display = (payType === 'wallet') ? 'block' : 'none';
    if (fpxDiv) fpxDiv.style.display = (payType === 'fpx') ? 'block' : 'none';
    if (cardDiv) cardDiv.style.display = (payType === 'card') ? 'block' : 'none';

    // 4. 清除/设置必填项，防止逻辑冲突
    document.querySelectorAll('.fpx-auth-input, #fpxBank, .card-input, #wallet_pin_input').forEach(input => {
        input.removeAttribute('required');
    });

    if (payType === 'wallet') {
        document.getElementById('wallet_pin_input').setAttribute('required', 'true');
    } else if (payType === 'fpx') {
        document.getElementById('fpxBank').setAttribute('required', 'true');
        document.querySelectorAll('.fpx-auth-input').forEach(i => i.setAttribute('required', 'true'));
    }
}

async function startPaymentProcess() {
    const payType = document.querySelector('input[name="pay_type"]:checked').value;
    
    // 1. 基础验证：姓名、地址、电话
    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const address = document.querySelector('input[name="address"]').value.trim();
    const phone = document.querySelector('input[name="phone"]').value.trim();
    
    if (!firstName || !address || !phone) {
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
    } else {
        submitCheckoutForm();
    }
    submitCheckoutForm();
}

// 统一提交函数
function submitCheckoutForm() {
    Swal.fire({
        title: 'Processing Payment...',
        html: 'Please do not refresh the page.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    document.getElementById('orderForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    initStates(); // 初始化所有州属

    // 获取数据库传来的资料
    const dbState = "<?php echo $user_state; ?>";
    const dbPostcode = "<?php echo $user_postcode; ?>";
    const dbCity = "<?php echo $user_city; ?>"; // 如果数据库有存城市名

    // 1. 自动选择州属
    if (dbState) {
        const stateSelect = document.getElementById('stateSelect');
        stateSelect.value = dbState;
        updateCities(); // 触发城市下拉框更新

        // 2. 自动选择城市 (如果有匹配项)
        if (dbCity) {
            const citySelect = document.getElementById('citySelect');
            citySelect.value = dbCity;
            updatePostcodes(); // 触发邮编下拉框更新

            // 3. 自动选择邮编
            if (dbPostcode) {
                const postcodeSelect = document.getElementById('postcodeSelect');
                // 检查邮编是否存在于下拉列表中
                let found = Array.from(postcodeSelect.options).some(opt => opt.value === dbPostcode);
                if (found) {
                    postcodeSelect.value = dbPostcode;
                } else {
                    // 如果列表里没有，选择 'other' 并填入自定义框
                    postcodeSelect.value = 'other';
                    toggleCustomPostcode();
                    document.getElementById('customPostcode').value = dbPostcode;
                }
            }
        }
    }

    // 初始化支付方式
    const activeOption = document.querySelector('.payment-option.active');
    if (activeOption) {
        selectPay(activeOption);
    }
});

function formatExpiry(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length >= 2) {
        input.value = val.slice(0, 2) + '/' + val.slice(2, 4);
    } else {
        input.value = val;
    }
}

function toggleCustomPostcode() {
    const postcodeSelect = document.getElementById('postcodeSelect');
    const customDiv = document.getElementById('customPostcodeDiv');
    const customInput = document.getElementById('customPostcode');
    if (postcodeSelect.value === 'other') {
        customDiv.style.display = 'block';
        customInput.required = true;
        customInput.focus();
    } else {
        customDiv.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}


function validateCheckoutForm() {
    const payType = document.querySelector('input[name="pay_type"]:checked').value;
    
    // 基本信息验证
    if (!document.querySelector('input[name="first_name"]').value) {
        Swal.fire('Incomplete', 'Please enter your first name.', 'warning');
        return false;
    }
    if (!document.querySelector('input[name="address"]').value) {
        Swal.fire('Incomplete', 'Please enter your address.', 'warning');
        return false;
    }
    if (!document.querySelector('select[name="state"]').value) {
        Swal.fire('Incomplete', 'Please select a state.', 'warning');
        return false;
    }
    if (!document.querySelector('select[name="city"]').value) {
        Swal.fire('Incomplete', 'Please select a city.', 'warning');
        return false;
    }
    if (!document.querySelector('select[name="postcode"]').value) {
        Swal.fire('Incomplete', 'Please select a postcode.', 'warning');
        return false;
    }
    if (document.querySelector('select[name="postcode"]').value === 'other' && !document.getElementById('customPostcode').value) {
        Swal.fire('Incomplete', 'Please enter the postcode.', 'warning');
        return false;
    }
    const phone = document.querySelector('input[name="phone"]').value;
    if (!phone || phone.length < 9 || phone.length > 12) {
        Swal.fire('Invalid Input', 'Please enter a valid phone number (9-12 digits).', 'warning');
        return false;
    }
    
    // 支付方式特定验证
    if (payType === 'card') {
        const cardNo = document.querySelector('input[name="card_no"]').value;
        const cardName = document.querySelector('input[name="cardholder_name"]').value;
        const expiry = document.querySelector('input[name="expiry"]').value;
        const cvv = document.querySelector('input[name="cvv"]').value;
        
        if (!cardNo || cardNo.length !== 16) {
            Swal.fire('Invalid Input', 'Please enter a valid 16-digit card number.', 'warning');
            return false;
        }
        if (!cardName) {
            Swal.fire('Incomplete', 'Please enter the cardholder name.', 'warning');
            return false;
        }
        if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
            Swal.fire('Invalid Input', 'Please enter a valid expiry date (MM/YY format).', 'warning');
            return false;
        }
        if (!cvv || cvv.length !== 3) {
            Swal.fire('Invalid Input', 'Please enter a valid 3-digit CVV.', 'warning');
            return false;
        }
    } else if (payType === 'fpx') {
        const fpxBank = document.querySelector('select[name="fpx_bank"]').value;
        if (!fpxBank) {
            Swal.fire('Incomplete', 'Please select a bank.', 'warning');
            return false;
        }
    }
    
    
    return true;
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function() {
    initStates(); // 初始化所有州属
    
    // 初始化支付方式的显示/隐藏状态
    const payType = document.querySelector('input[name="pay_type"]:checked').value;
    const walletDiv = document.getElementById('walletPinField');
    const fpxDiv = document.getElementById('fpxBankDiv');
    const cardDiv = document.getElementById('cardFieldsDiv');
    
    if (payType === 'wallet') {
        if (walletDiv) walletDiv.style.display = 'block';
        const pinField = document.getElementById('wallet_pin_input');
        if (pinField) pinField.required = true;
    } else if (payType === 'card') {
        if (cardDiv) cardDiv.style.display = 'block';
        // 设置卡支付字段为必填
        const cardFields = ['card_no', 'cardholder_name', 'expiry', 'cvv'];
        cardFields.forEach(fieldId => {
            const field = document.querySelector(`[name="${fieldId}"]`);
            if (field) field.required = true;
        });
    } else if (payType === 'fpx') {
        if (fpxDiv) fpxDiv.style.display = 'block';
        const fpxBank = document.getElementById('fpxBank');
        if (fpxBank) fpxBank.required = true;
    }

    // 统一显示来自后端的错误信息
    <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Payment Failed',
            text: '<?php echo $error; ?>',
            confirmButtonColor: '#17735b'
        });
    <?php endif; ?>

    // 统一显示来自后端的成功信息
    <?php if ($success_msg): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?php echo $success_msg; ?>',
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>
});

</script>

<?php include '../includes/footer.php'; ?>