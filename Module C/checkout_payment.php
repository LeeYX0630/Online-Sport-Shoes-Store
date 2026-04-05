<?php
// module_c/checkout_payment.php
session_start();
require_once '../includes/db_connection.php';

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : '';

if ($room_id == 0 || $category_id == 0 || empty($check_in) || empty($check_out)) {
    header("Location: ../Module B/room_catalogue.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../Module A/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];


$sql_room = "SELECT room_name FROM rooms WHERE room_id = '$room_id'";
$res_room = $conn->query($sql_room);
if ($res_room->num_rows == 0) die("Room not found.");
$room_data = $res_room->fetch_assoc();
$room_name_base = $room_data['room_name'];

$sql_cat = "SELECT category_name, price_per_night FROM categories WHERE category_id = '$category_id'";
$res_cat = $conn->query($sql_cat);
if ($res_cat->num_rows == 0) die("Room category not found.");
$cat_data = $res_cat->fetch_assoc();

$price_per_night = floatval($cat_data['price_per_night']); 
$category_type = $cat_data['category_name'];

$display_room_name = "$room_name_base ($category_type)";

$one_year_limit = new DateTime();
$one_year_limit->modify('+1 year');


$date1 = new DateTime($check_in);
$date2 = new DateTime($check_out);
$interval = $date1->diff($date2);
$days = $interval->days == 0 ? 1 : $interval->days;

$original_total = $price_per_night * $days;


$discount_amount = 0;
$final_total = $original_total;
$coupon_msg = "";
$applied_coupon_code = ""; 


$my_coupons = []; 
$best_coupon_code = "";
$max_potential_discount = 0;

$sql_get_coupons = "SELECT c.code, c.discount_value, c.discount_type, c.min_spend
                    FROM user_coupons uc 
                    JOIN coupons c ON uc.coupon_id = c.coupon_id 
                    WHERE uc.user_id = '$user_id' 
                    AND uc.status = 'active' 
                    AND c.expiry_date >= CURDATE()";
$res_coupons = $conn->query($sql_get_coupons);

if ($res_coupons->num_rows > 0) {
    while($c = $res_coupons->fetch_assoc()) {
        $potential_save = 0;
        if ($original_total >= $c['min_spend']) {
            if ($c['discount_type'] == 'percent') {
                $potential_save = $original_total * ($c['discount_value'] / 100);
            } else {
                $potential_save = $c['discount_value'];
            }
            if ($potential_save > $original_total) $potential_save = $original_total;
        }
        $c['calculated_save'] = $potential_save;
        $my_coupons[] = $c;

        if ($potential_save > $max_potential_discount) {
            $max_potential_discount = $potential_save;
            $best_coupon_code = $c['code'];
        }
    }
}


if (($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) || (isset($_GET['auto_best']) && $_GET['auto_best'] == 1)) {
    
    if (isset($_GET['auto_best']) && $_GET['auto_best'] == 1 && !empty($best_coupon_code)) {
        $code_input = $best_coupon_code;
    } else {
        $code_input = trim($_POST['coupon_code']);
    }

    if (!empty($code_input)) {
        $found_coupon = null;
        foreach ($my_coupons as $mc) {
            if ($mc['code'] === $code_input) {
                $found_coupon = $mc;
                break;
            }
        }

        if ($found_coupon) {
            if ($original_total >= $found_coupon['min_spend']) {
                $applied_coupon_code = $found_coupon['code'];
                $discount_amount = $found_coupon['calculated_save']; 
                $final_total = $original_total - $discount_amount;
                
                if (isset($_GET['auto_best'])) {
                    $coupon_msg = "<div class='alert success mt-2'>⚡ Best deal applied automatically! Saved RM " . number_format($discount_amount, 2) . "</div>";
                } else {
                    $coupon_msg = "<div class='alert success mt-2'>Voucher applied successfully.</div>";
                }
            } else {
                $coupon_msg = "<div class='alert error mt-2'>Min spend RM " . $found_coupon['min_spend'] . " required.</div>";
            }
        } else {
            $coupon_msg = "<div class='alert error mt-2'>Invalid voucher code.</div>";
        }
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {
    $final_code = trim($_POST['applied_code_hidden']);
    $final_pay_amount = $original_total;
    $coupon_id_to_update = 0;

    if (!empty($final_code)) {
        $sql_c = "SELECT uc.uc_id, c.* FROM user_coupons uc JOIN coupons c ON uc.coupon_id = c.coupon_id 
                  WHERE uc.user_id = '$user_id' AND c.code = '$final_code' AND uc.status = 'active'";
        $res_c = $conn->query($sql_c);
        
        if ($res_c->num_rows > 0) {
            $coupon = $res_c->fetch_assoc();
            if ($original_total >= $coupon['min_spend']) {
                $coupon_id_to_update = $coupon['uc_id'];
                $disc = ($coupon['discount_type'] == 'percent') ? $original_total * ($coupon['discount_value'] / 100) : $coupon['discount_value'];
                if ($disc > $original_total) $disc = $original_total;
                $final_pay_amount = $original_total - $disc;
            }
        }
    }


    $sql_insert = "INSERT INTO bookings (user_id, room_id, check_in_date, check_out_date, total_price, booking_status, payment_status) 
                   VALUES ('$user_id', '$room_id', '$check_in', '$check_out', '$final_pay_amount', 'confirmed', 'paid')";

    if ($conn->query($sql_insert) === TRUE) {
        $new_booking_id = $conn->insert_id;

        if ($coupon_id_to_update > 0) {
            $conn->query("UPDATE user_coupons SET status = 'used' WHERE uc_id = '$coupon_id_to_update'");
        }
        
        $paid_amount = number_format($final_pay_amount, 2);
        
        echo "
        <!DOCTYPE html>
        <html>
        <head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head>
        <body>
        <script>
            Swal.fire({
                title: 'Payment Successful!',
                text: 'Thank you! Amount Paid: RM $paid_amount',
                icon: 'success',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'View Receipt'
            }).then((result) => {
                window.location.href = 'payment_success.php?booking_id=$new_booking_id';
            });
        </script>
        </body>
        </html>";
        exit();
    } else {
        $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
    }
} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout & Payment</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; }
        .checkout-container { max-width: 900px; margin: 50px auto; display: flex; gap: 30px; }
        .order-summary, .payment-form { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .order-summary { flex: 1; border-right: 5px solid #f0ad4e; }
        .payment-form { flex: 1.5; }
        .price-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .total-row { font-size: 1.4em; font-weight: bold; color: #333; border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px; }
        .discount-text { color: #28a745; }
        .alert { padding: 10px; border-radius: 5px; font-size: 0.9em; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .btn-smart { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        
        /* Progress Bar CSS */
        .progressbar { counter-reset: step; padding: 0; display: flex; justify-content: space-between; list-style: none; position: relative; }
        .progressbar li { width: 33.33%; position: relative; text-align: center; font-size: 13px; color: #ccc; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .progressbar li:before { content: counter(step); counter-increment: step; width: 30px; height: 30px; line-height: 28px; border: 2px solid #e0e0e0; background: #fff; display: block; text-align: center; margin: 0 auto 10px auto; border-radius: 50%; color: #ccc; font-weight: bold; z-index: 2; position: relative; }
        .progressbar li:after { content: ''; position: absolute; width: 100%; height: 3px; background: #e0e0e0; top: 15px; left: -50%; z-index: 0; }
        .progressbar li:first-child:after { content: none; }
        .progressbar li.active { color: #333; }
        .progressbar li.active:before { border-color: #28a745; background: #fff; color: #28a745; box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1); }
        .progressbar li.completed { color: #28a745; }
        .progressbar li.completed:before { content: '✔'; border-color: #28a745; background: #28a745; color: #fff; }
        .progressbar li.completed + li:after { background: #28a745; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3">
    <div class="container">
        <span class="navbar-brand mb-0 h1 fw-bold">Checkout</span>
        <a href="../index.php" class="text-white text-decoration-none">Cancel</a>
    </div>
</nav>

<div style="max-width: 600px; margin: 30px auto;">
    <ul class="progressbar">
        <li class="completed">View Details</li>
        <li class="completed">Select Dates</li>
        <li class="active">Payment</li>
    </ul>
</div>

<div class="container checkout-container">
    
    <div class="order-summary">
        <h4 class="fw-bold mb-4">Summary</h4>
        <div class="mb-3">
            <small class="text-muted d-block">Room Type</small>
            <strong><?php echo htmlspecialchars($display_room_name); ?></strong>
        </div>
        <div class="mb-3">
            <small class="text-muted d-block">Dates</small>
            <span><?php echo $check_in; ?> <i class="bi bi-arrow-right"></i> <?php echo $check_out; ?></span>
            <br><span class="badge bg-secondary"><?php echo $days; ?> Nights</span>
        </div>
        
        <hr>

        <div class="price-row text-muted" style="font-size: 0.9em;">
            <span>Price per night</span>
            <span>RM <?php echo number_format($price_per_night, 2); ?></span>
        </div>
        <div class="price-row text-muted" style="font-size: 0.9em;">
            <span>Duration</span>
            <span>x <?php echo $days; ?> nights</span>
        </div>
        
        <div class="price-row mt-2 fw-bold">
            <span>Subtotal</span>
            <span>RM <?php echo number_format($original_total, 2); ?></span>
        </div>

        <?php if ($discount_amount > 0): ?>
        <div class="price-row discount-text">
            <span>Voucher Applied</span>
            <span>- RM <?php echo number_format($discount_amount, 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="price-row total-row">
            <span>Total to Pay</span>
            <span class="text-primary">RM <?php echo number_format($final_total, 2); ?></span>
        </div>
    </div>

    <div class="payment-form">
        
        <h5 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-ticket-perforated me-2"></i>My Vouchers</span>
            
            <?php if (!empty($best_coupon_code) && $applied_coupon_code !== $best_coupon_code && $max_potential_discount > 0): ?>
                <a href="?room_id=<?php echo $room_id; ?>&category_id=<?php echo $category_id; ?>&check_in=<?php echo $check_in; ?>&check_out=<?php echo $check_out; ?>&auto_best=1" 
                   class="btn btn-sm btn-warning fw-bold btn-smart shadow-sm">
                   <i class="bi bi-lightning-charge-fill"></i> Auto-Apply Best (-RM<?php echo intval($max_potential_discount); ?>)
                </a>
            <?php endif; ?>
        </h5>
        
        <form method="POST" action=""> 
            <div class="input-group mb-3">
                <select name="coupon_code" class="form-select" <?php echo ($discount_amount > 0) ? 'disabled' : ''; ?>>
                    <option value="">-- Select a Voucher --</option>
                    
                    <?php if (!empty($my_coupons)): ?>
                        <?php foreach($my_coupons as $c): ?>
                            <?php 
                                $desc = ($c['discount_type'] == 'percent') ? intval($c['discount_value'])."% OFF" : "RM ".intval($c['discount_value'])." OFF";
                                $isSelected = ($applied_coupon_code === $c['code']) ? 'selected' : '';
                                $bestLabel = ($c['code'] === $best_coupon_code) ? " 🔥 Best Deal!" : "";
                            ?>
                            <option value="<?php echo $c['code']; ?>" <?php echo $isSelected; ?>>
                                <?php echo $c['code']; ?> - <?php echo $desc; ?><?php echo $bestLabel; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No vouchers available</option>
                    <?php endif; ?>

                </select>

                <button class="btn btn-dark fw-bold" type="submit" name="apply_coupon" <?php echo ($discount_amount > 0) ? 'disabled' : ''; ?>>
                    Apply
                </button>
                
                <?php if ($discount_amount > 0): ?>
                     <a href="?room_id=<?php echo $room_id; ?>&category_id=<?php echo $category_id; ?>&check_in=<?php echo $check_in; ?>&check_out=<?php echo $check_out; ?>" class="btn btn-outline-secondary">Remove</a>
                <?php endif; ?>
            </div>
            
            <?php echo $coupon_msg; ?>
        </form>

        <hr class="my-4">

        <h5 class="fw-bold mb-3">Payment Details</h5>
        <?php if(isset($msg)) echo $msg; ?>
        
        <form method="POST" onsubmit="return confirm('Proceed payment of RM <?php echo number_format($final_total, 2); ?>?');">
            <input type="hidden" name="applied_code_hidden" value="<?php echo htmlspecialchars($applied_coupon_code); ?>">
            <input type="hidden" name="confirm_payment" value="1">

            <div class="mb-3">
                <label class="form-label small fw-bold">Cardholder Name</label>
                <input type="text" id="card_holder" name="card_holder" class="form-control" placeholder="John Doe" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold">Card Number</label>
                <input type="text" id="cardNumber" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" required>
            </div>
            
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label small fw-bold">Expiry</label>
                    <input type="text" id="cardExpiry" class="form-control" placeholder="MM/YY" maxlength="5" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label small fw-bold">CVV</label>
                    <input type="text" id="cardCvv" class="form-control" placeholder="123" maxlength="3" required>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 py-3 fw-bold mt-2 shadow-sm">
                Pay RM <?php echo number_format($final_total, 2); ?>
            </button>
        </form>
    </div>
</div>

<script>
    document.getElementById('cardNumber').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.substring(0, 16); 
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    });

    document.getElementById('cardExpiry').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.substring(0, 4);
        if (value.length >= 2) {
            let month = parseInt(value.substring(0, 2));
            if (month > 12 || month === 0) value = value.substring(0, 1) + value.substring(2);
        }
        if (value.length > 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
    });
    
    document.getElementById('cardExpiry').addEventListener('blur', function (e) {
        let value = e.target.value;
        if (value.length === 5 && value.includes('/')) {
            let parts = value.split('/');
            let month = parseInt(parts[0]);
            let year = parseInt(parts[1]);
            const now = new Date();
            const curY = now.getFullYear() % 100;
            const curM = now.getMonth() + 1;
            if (year < curY || (year === curY && month < curM)) {
                alert('Card has expired.'); e.target.value = '';
            }
        }
    });

    document.getElementById('cardCvv').addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
    });

    document.getElementById('card_holder').addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '');
    });
</script>

</body>
</html>