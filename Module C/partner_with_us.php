<?php
//====partner_with_us.php====
session_start();
require_once '../includes/db_connection.php';
require_once 'send_partner_otp.php'; // ⚠️ 请确保路径正确，如果跟 partner_with_us.php 同级直接这样写

$success  = false;
$errors   = [];
$old      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Sanitise helpers ───────────────────────────────────
    $str  = fn(string $k) => trim($_POST[$k] ?? '');
    $old  = [
        'brand'    => $str('brand'),
        'business_name' => $str('business_name'),
        'email'         => $str('email'),
        'phone'         => $str('phone'),
        'reg_number'    => $str('reg_number'),
        'bank_name'     => $str('bank_name'),
        'bank_acc_no'   => $str('bank_acc_no'),
        'address'       => $str('address'),
        'agreed_terms'  => isset($_POST['agreed_terms']),
    ];

    // 银行账号长度映射（用于服务器端与前端验证）
    $bank_lengths = [
        'Maybank (MB)' => 12,
        'CIMB (CIMB)' => 14,
        'Public Bank (PB)' => 10,
        'RHB Bank (RHB)' => 14,
        'AmBank (AB)' => 13,
        'AFFIN Bank (AF)' => 12,
        'Alliance Bank (AB)' => 15,
        'Boost (MY)' => 12,
        'UOB (UOB)' => 11,
        'OCBC Bank (OCBC)' => 10,
        'HSBC (HB)' => 12,
        'Standard Chartered (SCB)' => 12,
        'DBS (DB)' => 10,
        'Bank Islam (BI)' => 14,
        'Islamic Bank Mal (IM)' => 14,
        'Bank Muamalat (MB)' => 14,
    ];


    // ── Validation ─────────────────────────────────────────
    if ($old['business_name'] === '')
        $errors['business_name'] = 'Please enter your business name.';

    if ($old['brand'] === '')
        $errors['brand'] = 'Please enter your brand name.';

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Please enter a valid email address.';

    if ($old['phone'] === '')
        $errors['phone'] = 'Please enter your contact number.';

    if ($old['reg_number'] === '')
        $errors['reg_number'] = 'Please enter your registration number.';

    if (empty($_FILES['auth_doc']['name']))
        $errors['auth_doc'] = 'Please upload a verification document.';

    if ($old['bank_name'] === '')
        $errors['bank_name'] = 'Please select a bank.';

    if ($old['bank_acc_no'] === '' || !ctype_digit($old['bank_acc_no']))
        $errors['bank_acc_no'] = 'Please enter a valid account number (digits only).';

    // ── Bank account length validation based on selected bank ──
    if (!isset($errors['bank_acc_no']) && !empty($old['bank_name']) && isset($bank_lengths[$old['bank_name']])) {
        $expected = (int)$bank_lengths[$old['bank_name']];
        if (strlen($old['bank_acc_no']) !== $expected) {
            $errors['bank_acc_no'] = "Account number must be {$expected} digits for {$old['bank_name']}.";
        }
    }

    if (empty($_FILES['bank_statement']['name']))
        $errors['bank_statement'] = 'Please upload your bank statement.';

    if ($old['address'] === '')
        $errors['address'] = 'Please enter your warehouse address.';

    if (!$old['agreed_terms'])
        $errors['agreed_terms'] = 'You must agree to the Terms & Conditions.';

    // ── File-size / type check (optional server guard) ─────
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_bytes     = 2 * 1024 * 1024; // 2 MB

    foreach (['auth_doc', 'bank_statement'] as $fld) {
        if (!isset($errors[$fld]) && !empty($_FILES[$fld]['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES[$fld]['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed_types))
                $errors[$fld] = 'Only PDF, JPG, and PNG files are accepted.';
            elseif ($_FILES[$fld]['size'] > $max_bytes)
                $errors[$fld] = 'File must be smaller than 2 MB.';
        }
    }

    // ── Uniqueness checks (block submission on duplicates)
    // Only run checks for fields that passed basic validation and have no prior errors
    if (!isset($errors['brand']) && !empty($old['brand'])) {
        $sql = "SELECT COUNT(*) FROM vendors WHERE brand = ?";
        if ($stmtc = $conn->prepare($sql)) {
            $stmtc->bind_param('s', $old['brand']);
            $stmtc->execute();
            $stmtc->bind_result($cnt);
            $stmtc->fetch();
            if (!empty($cnt)) $errors['brand'] = 'Brand name already registered.';
            $stmtc->close();
        }
    }

    if (!isset($errors['email']) && !empty($old['email']) && filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $sql = "SELECT COUNT(*) FROM vendors WHERE email = ?";
        if ($stmte = $conn->prepare($sql)) {
            $stmte->bind_param('s', $old['email']);
            $stmte->execute();
            $stmte->bind_result($cnt2);
            $stmte->fetch();
            if (!empty($cnt2)) $errors['email'] = 'Email address is already in use.';
            $stmte->close();
        }
    }

    if (!isset($errors['reg_number']) && !empty($old['reg_number'])) {
        $sql = "SELECT COUNT(*) FROM vendors WHERE reg_number = ?";
        if ($stmtr = $conn->prepare($sql)) {
            $stmtr->bind_param('s', $old['reg_number']);
            $stmtr->execute();
            $stmtr->bind_result($cnt3);
            $stmtr->fetch();
            if (!empty($cnt3)) $errors['reg_number'] = 'Registration number already exists.';
            $stmtr->close();
        }
    }

    // ── If valid → process (save / email / DB insert here) ─
    if (empty($errors)) {
        // Move uploaded files and insert into DB (vendors)
        $upload_dir = '../uploads/vendors/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $auth_dest = null;
        $bank_dest = null;

        if (!empty($_FILES['auth_doc']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['auth_doc']['name'], PATHINFO_EXTENSION));
            $fname = 'auth_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($_FILES['auth_doc']['tmp_name'], $upload_dir . $fname)) {
                // Store only the filename in DB/session; files remain on disk in uploads/vendors/
                $auth_dest = $fname;
            }
        }

        if (!empty($_FILES['bank_statement']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['bank_statement']['name'], PATHINFO_EXTENSION));
            $fname = 'bank_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($_FILES['bank_statement']['tmp_name'], $upload_dir . $fname)) {
                // Store only the filename in DB/session; files remain on disk in uploads/vendors/
                $bank_dest = $fname;
            }
        }

        // 标记上传是否成功（在各自 move_uploaded_file 成功的分支内设置）
        $auth_upload_ok = !empty($auth_dest);
        $bank_upload_ok = !empty($bank_dest);

        // 如果两个文件都上传成功，先暂存 session 并发送 OTP 然后跳转到验证页
        if ($auth_upload_ok && $bank_upload_ok) {

            $_SESSION['partner_temp_data'] = [
                'brand'         => $old['brand'],
                'business_name' => $old['business_name'],
                'email'         => $old['email'],
                'phone'         => $old['phone'],
                'reg_number'    => $old['reg_number'],
                'bank_name'     => $old['bank_name'],
                'bank_acc_no'   => $old['bank_acc_no'],
                'address'       => $old['address'],
                'auth_doc_path' => $auth_dest,
                'bank_doc_path' => $bank_dest
            ];

            // 调用你封装的发 OTP 函数
            $is_sent = sendPartnerOTP($old['email']);

            if ($is_sent) {
                header("Location: verify_partner_otp.php"); // 确认文件名是否正确
                exit();
            } else {
                $errors['otp'] = 'Failed to send OTP to your email. Please check your email address and try again.';
                unset($_SESSION['partner_temp_data']);
                // 继续到页面渲染以显示错误（不会写入 DB）
            }
        }

        // 如果没有走 OTP 重定向，则继续走原来的 DB 插入流程
        // Insert into DB using mysqli connection ($conn from includes/db_connection.php)
        $status = 'pending';
        $created_at = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO vendors
            (brand, business_name, email, phone, reg_number, auth_doc_path, bank_name, bank_acc_no, bank_statement_path, warehouse_address, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssssssssss',
                $old['brand'],
                $old['business_name'],
                $old['email'],
                $old['phone'],
                $old['reg_number'],
                $auth_dest,
                $old['bank_name'],
                $old['bank_acc_no'],
                $bank_dest,
                $old['address'],
                $status,
                $created_at
            );
            if ($stmt->execute()) {
                $success = true;
                $old     = []; // clear form after success
            } else {
                $errors['db'] = 'Failed to save your application. Please try again.';
            }
            $stmt->close();
        } else {
            $errors['db'] = 'Database error (prepare failed).';
        }
    }
}

// Helper: render an error badge
function err(array $errors, string $field): string {
    if (!isset($errors[$field])) return '';
    $msg = htmlspecialchars($errors[$field]);
    return <<<HTML
        <div class="error-msg">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" style="flex-shrink:0">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            $msg
        </div>
    HTML;
}

// Helper: border color shortcut
function bc(array $errors, string $field): string {
    return isset($errors[$field]) ? '#DC3545' : '#e5e7eb';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Become Our Partner – Online Sports Shoes Store</title>
    <style>
        /* ── Reset & base ──────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Arial, "Segoe UI", sans-serif';
            background: #f3f4f6;
            color: #212529;
            line-height: 1.6;
            min-height: 100vh;
        }

                /* 模糊背景图 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../images/picture/。。1.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            filter: blur(10px);
            z-index: -1;
        }


        /* ── Navbar ────────────────────────────────────── */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #333333;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .75rem;
        }
        .navbar-inner svg { color: #fff; flex-shrink: 0; }
        .navbar-title {
            color: #fff;
            font-weight: 700;
            font-size: clamp(.95rem, 2.5vw, 1.1rem);
            letter-spacing: .05em;
        }

        /* ── Main wrapper ──────────────────────────────── */
        .main-wrap {
            max-width: 56rem;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }

        /* ── Card ──────────────────────────────────────── */
        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0,0,0,.12);
            overflow: hidden;
        }

        /* ── Card header ───────────────────────────────── */
        .card-header {
            padding: 2rem 2.5rem 1.5rem;
            border-bottom: 4px solid #FF6B00 !important;
        }
        .card-header h2 {
            font-size: clamp(1.4rem, 4vw, 1.75rem);
            font-weight: 700;
            color: #333333;
        }
        .card-header p {
            margin-top: .35rem;
            color: #666;
            font-size: .95rem;
        }

        /* ── Form body ─────────────────────────────────── */
        .form-body { padding: 2rem 2.5rem 2.5rem; }
        .form-section { margin-bottom: 2rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 1.5rem; }
        .form-group:last-child { margin-bottom: 0; }

        /* ── Labels ────────────────────────────────────── */
        label.field-label {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .875rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: .5rem;
        }
        label.field-label svg { color: #FF6B00; flex-shrink: 0; }
        .req { color: #DC3545; }

        /* ── Inputs / select / textarea ────────────────── */
        .form-control {
            width: 100%;
            padding: .75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: .5rem;
            font-size: .95rem;
            color: #212529;
            background: #fff;
            transition: border-color .2s;
            appearance: none;
            -webkit-appearance: none;
        }
        .form-control:focus {
            outline: none;
            border-color: #FF6B00;
        }
        .form-control.is-invalid { border-color: #DC3545; }
        textarea.form-control { resize: none; }

        /* ── File upload zone ──────────────────────────── */
        .file-zone {
            position: relative;
            border: 2px dashed #d1d5db;
            border-radius: .5rem;
            background: #f9fafb;
            padding: 2rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s;
        }
        .file-zone:hover, .file-zone:focus-within { border-color: #FF6B00; }
        .file-zone.is-invalid { border-color: #DC3545; }
        .file-zone input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-zone svg { color: #9ca3af; margin-bottom: .75rem; }
        .file-zone .file-label {
            font-size: .875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: .25rem;
        }
        .file-zone .file-hint { font-size: .75rem; color: #6b7280; }

        /* ── Section divider ───────────────────────────── */
        .section-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .section-divider .bar {
            width: 4px;
            height: 2rem;
            border-radius: 9999px;
            background: #FF6B00;
            flex-shrink: 0;
        }
        .section-divider h3 { font-size: 1.1rem; font-weight: 700; color: #333333; }
        .info-banner {
            background: #fff3e0;
            border-left: 4px solid #FF6B00;
            border-radius: .375rem;
            padding: .75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: .875rem;
            color: #666;
        }

        /* ── Error message ─────────────────────────────── */
        .error-msg {
            display: flex;
            align-items: center;
            gap: .35rem;
            color: #DC3545;
            font-size: .8rem;
            margin-top: .4rem;
        }

        /* ── Terms block ───────────────────────────────── */
        .terms-wrap { margin-top: 1.5rem; }
        .terms-check-row {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: .5rem;
        }
        .terms-check-row input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            margin-top: .15rem;
            cursor: pointer;
            accent-color: #FF6B00;
            flex-shrink: 0;
        }
        .terms-check-row label {
            font-size: .875rem;
            color: #374151;
            cursor: pointer;
        }
        .terms-toggle {
            background: none;
            border: none;
            color: #FF6B00;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
            font-size: .875rem;
            padding: 0;
        }
        .terms-toggle:hover { text-decoration: none; }

        /* ── Terms content panel ───────────────────────── */
        .terms-panel {
            display: none;
            border: 2px solid #e5e7eb;
            border-radius: .5rem;
            background: #fff;
            padding: 1.5rem;
            margin-top: .75rem;
            max-height: 16rem;
            overflow-y: auto;
        }
        .terms-panel.open { display: block; }
        .terms-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .terms-panel-header h4 {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: 1rem;
            font-weight: 700;
            color: #333333;
        }
        .terms-panel-header h4 svg { color: #FF6B00; }
        .terms-close {
            background: none;
            border: none;
            color: #6b7280;
            font-size: .8rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: underline;
        }
        .terms-close:hover { text-decoration: none; }
        .terms-content p { font-size: .875rem; color: #4b5563; margin-bottom: .75rem; }
        .terms-content p:last-child { margin-bottom: 0; }

        /* ── Submit button ─────────────────────────────── */
        .btn-submit {
            width: 100%;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: .5rem;
            background: #9ca3af;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: not-allowed;
            transition: background .25s, transform .1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-top: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        .btn-submit.active {
            background: #FF6B00;
            cursor: pointer;
        }
        .btn-submit.active:hover  { background: #e66000; transform: scale(1.015); }
        .btn-submit.active:active { transform: scale(.985); }

        /* ── Success banner ────────────────────────────── */
        .success-banner {
            background: #d1fae5;
            border: 2px solid #34d399;
            border-radius: .75rem;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .success-banner svg { color: #059669; flex-shrink: 0; margin-top: .1rem; }
        .success-banner h3 { font-weight: 700; color: #065f46; margin-bottom: .25rem; }
        .success-banner p  { font-size: .875rem; color: #047857; }

        /* ── Global error summary ──────────────────────── */
        .error-summary {
            background: #fee2e2;
            border: 2px solid #f87171;
            border-radius: .75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-size: .875rem;
            color: #991b1b;
        }
        .error-summary strong { display: block; margin-bottom: .4rem; }

        /* ── Footer note ───────────────────────────────── */
        .footer-note {
            text-align: center;
            margin-top: 2rem;
            font-size: .875rem;
            color: #6b7280;
        }

/* 🌟 新增：图片放大预览样式 (移植自 add_product.php) */
.img-zoom-modal {
    display: none; /* 默认隐藏 */
    position: fixed;
    z-index: 10000; /* 确保在最上层 */
    padding-top: 50px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.9); /* 黑色背景 */
    backdrop-filter: blur(5px); /* 可选：增加背景模糊，更高级 */
}

.img-zoom-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 85vh; /* 限制高度在屏幕内 */
    animation-name: zoomAnimation;
    animation-duration: 0.3s;
    border: 3px solid #fff;
    border-radius: 8px;
    object-fit: contain; /* 确保整张图片可见 */
}

@keyframes zoomAnimation {
    from {transform:scale(0)}
    to {transform:scale(1)}
}

.close-zoom-modal {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    transition: 0.3s;
    cursor: pointer;
}

.close-zoom-modal:hover,
.close-zoom-modal:focus {
    color: #bbb;
    text-decoration: none;
    cursor: pointer;
}

/* 移动端适配 */
@media only screen and (max-width: 700px){
    .img-zoom-content {
        width: 100%;
        max-height: 70vh;
    }
    .close-zoom-modal {
        right: 20px;
        top: 10px;
    }
}

    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- ═══════════════════════ Header ═══════════════════════ -->
<?php include '../includes/header.php'; ?>

<!-- ═══════════════════════ MAIN ═════════════════════════ -->
<main class="main-wrap">
    <div class="card">

        <!-- Card header -->
        <div class="card-header">
            <h2>Become Our Partner</h2>
            <p>Join our marketplace and grow your business</p>
        </div>

        <!-- Form body -->
        <div class="form-body">

            <?php
            // Server-side alerts will be shown via SweetAlert2 (JS rendered below)
            ?>

            <!-- ════════════════════════════════════════
                 FORM
            ════════════════════════════════════════ -->
            <form id="vendorForm"
                  method="POST"
                  action=""
                  enctype="multipart/form-data"
                  novalidate>

                <!-- ── Business name ── -->
                <div class="form-group">
                    <label class="field-label" for="business_name">
                        <!-- Building icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                        Business / Company Name <span class="req">*</span>
                    </label>
                    <input
                        class="form-control <?= isset($errors['business_name']) ? 'is-invalid' : '' ?>"
                        type="text" id="business_name" name="business_name"
                        value="<?= htmlspecialchars($old['business_name'] ?? '') ?>"
                        placeholder="e.g., Pro-Kicks Malaysia"
                    />
                    <?= err($errors, 'business_name') ?>
                </div>

                <!-- ── Brand name (new) ── -->
                <div class="form-group">
                    <label class="field-label" for="brand">
                        <!-- Tag icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M20.59 13.41L11 3 3 11l8.59 8.59a2 2 0 0 0 2.83 0l6.17-6.17a2 2 0 0 0 0-2.83z"/>
                            <circle cx="7.5" cy="7.5" r="1.5"/>
                        </svg>
                        Brand Name <span class="req">*</span>
                    </label>
                    <input
                        class="form-control <?= isset($errors['brand']) ? 'is-invalid' : '' ?>"
                        type="text" id="brand" name="brand"
                        value="<?= htmlspecialchars($old['brand'] ?? '') ?>"
                        placeholder="e.g., Yonex"
                    />
                    <?= err($errors, 'brand') ?>
                </div>

                <!-- ── Email & Phone ── -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="field-label" for="email">
                            <!-- Mail icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                <polyline points="2,4 12,13 22,4"/>
                            </svg>
                            Business Email <span class="req">*</span>
                        </label>
                        <input
                            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                            type="email" id="email" name="email"
                            value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                            placeholder="example@business.com"
                        />
                        <?= err($errors, 'email') ?>
                    </div>

                    <div class="form-group">
                        <label class="field-label" for="phone">
                            <!-- Phone icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.58 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.77a16 16 0 0 0 5.37 5.37l1.62-1.62a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21.73 14z"/>
                            </svg>
                            Contact Number <span class="req">*</span>
                        </label>
                        <input
                            class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                            type="tel" id="phone" name="phone"
                            value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                            inputmode="numeric"
                            placeholder="012-3456789"
                        />
                        <?= err($errors, 'phone') ?>
                    </div>
                </div>

                <!-- ── Registration number ── -->
                <div class="form-group">
                    <label class="field-label" for="reg_number">
                        <!-- FileText icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        Business Registration No. (SSM) <span class="req">*</span>
                    </label>
                    <input
                        class="form-control <?= isset($errors['reg_number']) ? 'is-invalid' : '' ?>"
                        type="text" id="reg_number" name="reg_number"
                        value="<?= htmlspecialchars($old['reg_number'] ?? '') ?>"
                        placeholder="e.g., 202401xxxxxx"
                        inputmode="numeric"
                        maxlength="12" 
                        minlength="12"
                    />
                    <?= err($errors, 'reg_number') ?>
                </div>

                <!-- ── Auth document ── -->
                <div class="form-group">
                    <label class="field-label">
                        <!-- Upload icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        Verification Document (SSM) <span class="req">*</span>
                    </label>
                    <div class="file-zone">
        <input type="file" name="auth_doc" id="auth_doc" accept=".pdf,.jpg,.png" required>
        
        <div class="file-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round" style="display:block;margin:0 auto .75rem">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        <p>Click or drag file to this area to upload</p>
            <p style="font-size: 0.8rem; color: #6b7280;">Max 2MB. JPG, PNG or PDF.</p>
        </div>

        <div class="file-preview" id="preview_auth_doc" style="display: none; margin-top: 10px;"></div>
    </div>
                    <?= err($errors, 'auth_doc') ?>
                </div>

                <!-- ══ Bank Details section ══════════════════ -->
                <div class="section-divider" style="margin-top:1.5rem;">
                    <div class="bar"></div>
                    <h3>Bank Account Details</h3>
                </div>
                <div class="info-banner">
                    <strong>Important:</strong> The bank account holder name must match your Business / Company Name exactly, otherwise the application will be rejected.
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="field-label" for="bank_name">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                            Bank Name <span class="req">*</span>
                        </label>
                        <select class="form-control <?= isset($errors['bank_name']) ? 'is-invalid' : '' ?>"
                                id="bank_name" name="bank_name">
                            <option value="">-- Select Bank --</option>
                            <?php
                            $banks = ['Maybank (MB)', 'CIMB (CIMB)', 'Public Bank (PB)', 'RHB Bank (RHB)', 'AmBank (AB)', 'AFFIN Bank (AF)', 'Alliance Bank (AB)', 'Boost (MY)', 'UOB (UOB)', 'OCBC Bank (OCBC)', 'HSBC (HB)', 'Standard Chartered (SCB)', 'DBS (DB)', 'Bank Islam (BI)', 'Islamic Bank Mal (IM)', 'Bank Muamalat (MB)'];
                            // 对应的显示名字
                            $bankLabels = $banks;
                            foreach ($banks as $i => $b):
                                $sel = (($old['bank_name'] ?? '') === $b) ? 'selected' : '';
                            ?>
                                <option value="<?= $b ?>" <?= $sel ?>><?= $bankLabels[$i] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= err($errors, 'bank_name') ?>
                    </div>

                    <div class="form-group">
                        <label class="field-label" for="bank_acc_no">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                <line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                            Account Number <span class="req">*</span>
                        </label>
                        <input
                            class="form-control <?= isset($errors['bank_acc_no']) ? 'is-invalid' : '' ?>"
                            type="text" id="bank_acc_no" name="bank_acc_no"
                            value="<?= htmlspecialchars($old['bank_acc_no'] ?? '') ?>"
                            placeholder="e.g., 5123xxxxxxxx"
                            inputmode="numeric"
                        />
                        <?= err($errors, 'bank_acc_no') ?>
                    </div>
                </div>


                <!-- ── Bank statement ── -->
                <div class="form-group">
                    <label class="field-label">
                        <!-- FileText icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        Bank Statement Header <span class="req">*</span>
                    </label>
                    <div class="file-zone">
        <input type="file" name="bank_statement" id="bank_statement" accept=".pdf,.jpg,.png" required>
        
        <div class="file-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round" style="display:block;margin:0 auto .75rem">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        <p>Click or drag file to this area to upload</p>
            <p style="font-size: 0.8rem; color: #6b7280;">Max 2MB. JPG, PNG or PDF.</p>
        </div>

        <div class="file-preview" id="preview_bank_statement" style="display: none; margin-top: 10px;"></div>
                    <?= err($errors, 'bank_statement') ?>
                </div>

                <!-- ── Warehouse address ── -->
                <div class="form-group">
                    <label class="field-label" for="address">
                        <!-- MapPin icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        Warehouse Address <span class="req">*</span>
                    </label>
                    <textarea
                        class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                        id="address" name="address" rows="4"
                        placeholder="Enter your complete warehouse / shipping address"
                    ><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
                    <?= err($errors, 'address') ?>
                </div>

                <!-- ── Terms & Conditions ── -->
                <div class="terms-wrap">
                    <div class="terms-check-row">
                        <input type="checkbox" id="agreed_terms" name="agreed_terms"
                               <?= (!empty($old['agreed_terms'])) ? 'checked' : '' ?>
                               onchange="syncSubmitBtn()" />
                        <label for="agreed_terms">
                            I have read and agree to the&nbsp;<button
                                type="button" class="terms-toggle"
                                onclick="toggleTerms()">Terms &amp; Conditions</button>&nbsp;for partner registration
                            <span class="req">*</span>
                        </label>
                    </div>
                    <?= err($errors, 'agreed_terms') ?>

                    <!-- Terms content panel -->
                    <div class="terms-panel" id="termsPanel">
                        <div class="terms-panel-header">
                            <h4>
                                <!-- FileCheck icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <polyline points="9 15 11 17 15 13"/>
                                </svg>
                                Terms &amp; Conditions
                            </h4>
                            <button type="button" class="terms-close" onclick="toggleTerms()">Close</button>
                        </div>
                        <div class="terms-content">
                            <p><strong>1. Partnership Agreement:</strong> By submitting this application, you agree to become an authorized partner of Online Sports Shoes Store and comply with all marketplace policies.</p>
                            <p><strong>2. Product Quality:</strong> All products listed must be authentic, in good condition, and accurately described. Counterfeit or misleading items are strictly prohibited.</p>
                            <p><strong>3. Commission Structure:</strong> The platform charges a commission of 15% on each successful transaction. Payment settlements occur bi-weekly to your registered bank account.</p>
                            <p><strong>4. Shipping &amp; Fulfillment:</strong> Partners are responsible for timely shipping within 2 business days of order confirmation and must provide tracking information.</p>
                            <p><strong>5. Returns &amp; Refunds:</strong> You must accept returns within 14 days for defective or misrepresented items and process refunds according to platform policies.</p>
                            <p><strong>6. Account Verification:</strong> All information provided must be accurate. False documentation will result in immediate application rejection and potential legal action.</p>
                            <p><strong>7. Termination:</strong> Either party may terminate this partnership with 30 days written notice. The platform reserves the right to suspend accounts violating these terms.</p>
                        </div>
                    </div>
                </div>

                <!-- ── Submit button ── -->
                <button type="submit" class="btn-submit" id="submitBtn">
                    <!-- CheckCircle2 icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Submit Application
                </button>

            </form><!-- /form -->
        </div><!-- /form-body -->
    </div><!-- /card -->

    <p class="footer-note">
        After submission, we will review your application within 2–3 business days and notify you via email.
    </p>
</main>

<div id="imageZoomModal" class="img-zoom-modal">
    <span class="close-zoom-modal">&times;</span>
    <img class="img-zoom-content" id="imgModalSrc">
</div>

<!-- ═══════════════════════ JAVASCRIPT ══════════════════ -->
<script>
    // ── Update file zone label when a file is chosen ──────
    function updateFileLabel(input, labelId) {
        const label = document.getElementById(labelId);
        if (input.files && input.files[0]) {
            label.textContent = input.files[0].name;
        } else {
            label.textContent = 'Click to upload or drag & drop';
        }
    }

    // ── Toggle Terms panel ────────────────────────────────
    function toggleTerms() {
        const panel = document.getElementById('termsPanel');
        panel.classList.toggle('open');
    }

    // ── Sync submit button state ──────────────────────────
    function syncSubmitBtn() {
        const checked = document.getElementById('agreed_terms').checked;
        const btn = document.getElementById('submitBtn');
        btn.disabled = !checked;
        btn.classList.toggle('active', checked);
    }



    function updateBankAccHint() {
        const sel = document.getElementById('bank_name');
        const hint = document.getElementById('bankAccHint');
        if (!sel || !hint) return;
        const expected = bankLengths[sel.value];
        hint.textContent = expected ? `Expected ${expected} digits` : '';
    }

    // Update hint when bank selection changes
    document.addEventListener('DOMContentLoaded', () => {
        const bn = document.getElementById('bank_name');
        if (bn) bn.addEventListener('change', updateBankAccHint);
    });

    // ── Client-side validation (progressive enhancement) ──
    document.getElementById('vendorForm').addEventListener('submit', function(e) {
        let valid = true;

        const checks = [
            { id: 'brand',    test: v => v.trim() !== '',   msg: 'Please enter your brand name.' },
            { id: 'business_name', test: v => v.trim() !== '',   msg: 'Please enter your business name.' },
            { id: 'email',         test: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), msg: 'Please enter a valid email address.' },
            { id: 'phone',         test: v => v.trim() !== '',   msg: 'Please enter your contact number.' },
            { id: 'reg_number',    test: v => v.trim() !== '',   msg: 'Please enter your registration number.' },
            { id: 'bank_name',     test: v => v !== '',          msg: 'Please select a bank.' },
            { id: 'bank_acc_no',   test: v => v.trim() !== '' && /^\d+$/.test(v.trim()), msg: 'Please enter a valid account number (digits only).' },
            { id: 'address',       test: v => v.trim() !== '',   msg: 'Please enter your warehouse address.' },
        ];

        // Clear previous inline errors
        document.querySelectorAll('.js-err').forEach(el => el.remove());
        document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));

        checks.forEach(({ id, test, msg }) => {
            const el = document.getElementById(id);
            if (!test(el.value)) {
                el.classList.add('is-invalid');
                const err = document.createElement('div');
                err.className = 'error-msg js-err';
                err.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msg}`;
                el.parentNode.insertBefore(err, el.nextSibling);
                valid = false;
            }
        });

        // File checks
        ['auth_doc', 'bank_statement'].forEach(id => {
            const el = document.getElementById(id);
            const zone = el.closest('.file-zone');
            if (!el.files || el.files.length === 0) {
                zone.classList.add('is-invalid');
                const msgs = { auth_doc: 'Please upload a verification document.', bank_statement: 'Please upload your bank statement.' };
                const err = document.createElement('div');
                err.className = 'error-msg js-err';
                err.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msgs[id]}`;
                zone.parentNode.insertBefore(err, zone.nextSibling);
                valid = false;
            }
        });

        // ── Client-side bank account length validation ──
        const bankSel = document.getElementById('bank_name');
        const accEl = document.getElementById('bank_acc_no');
        if (bankSel && accEl) {
            const expected = bankLengths[bankSel.value];
            if (expected && accEl.value.trim().length !== expected) {
                accEl.classList.add('is-invalid');
                const err = document.createElement('div');
                err.className = 'error-msg js-err';
                err.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/></svg> Account number must be ${expected} digits for ${bankSel.value}.`;
                accEl.parentNode.insertBefore(err, accEl.nextSibling);
                Swal.fire({icon:'warning', title:'Invalid account number', text:`Account number must be ${expected} digits for ${bankSel.value}.`});
                valid = false;
            }
        }

        if (!document.getElementById('agreed_terms').checked) {
            valid = false;
            Swal.fire({icon:'warning', title:'Terms required', text:'Please agree to the Terms & Conditions before submitting.'});
        }

        if (!valid) e.preventDefault();
    });

    // ── Init on page load ────────────────────────────────
    window.addEventListener('DOMContentLoaded', () => {
        syncSubmitBtn();
        // Re-open terms if server returned errors after T&C was checked
        <?php if (!empty($errors) && !empty($old['agreed_terms'])): ?>
        // Keep terms visible if needed
        <?php endif; ?>
            <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Application Submitted Successfully!',
                html: 'Thank you for applying. We will review your application within 2–3 business days and notify you via email.'
            });
            <?php endif; ?>

                
            <?php if (!empty($errors) && !$success): ?>
            (function(){
                let errHtml = '<ul style="text-align:left;margin:0;padding-left:1.2rem">';
                <?php foreach ($errors as $m): ?>
                errHtml += '<li><?= htmlspecialchars($m, ENT_QUOTES) ?>'; errHtml += '</li>';
                <?php endforeach; ?>
                errHtml += '</ul>';
                Swal.fire({icon:'error', title:'Errors', html: errHtml});
            })();
            <?php endif; ?>
    });

    // --- 银行账号长度自动限制脚本 ---
    const bankLengths = {
        'Maybank (MB)': 12,
        'CIMB (CIMB)': 14,
        'Public Bank (PB)': 10,
        'RHB Bank (RHB)': 14,
        'AmBank (AB)': 13,
        'AFFIN Bank (AF)': 12,
        'Alliance Bank (AB)': 15,
        'Boost (MY)': 12,
        'UOB (UOB)': 11,
        'OCBC Bank (OCBC)': 10,
        'HSBC (HB)': 12,
        'Standard Chartered (SCB)': 12,
        'DBS (DB)': 10,
        'Bank Islam (BI)': 14,
        'Islamic Bank Mal (IM)': 14,
        'Bank Muamalat (MB)': 14
    };

    const bankSelect = document.getElementById('bank_name');
    const accInput = document.getElementById('bank_acc_no');

    // 监听银行选择变化
    bankSelect.addEventListener('change', function() {
        const selectedBank = this.value;
        if (bankLengths[selectedBank]) {
            const len = bankLengths[selectedBank];
            accInput.maxLength = len; // 设置最大长度限制
            accInput.placeholder = `Enter ${len} digits`;
            
            // 如果当前输入的长度超过了新银行的限制，截断它
            if (accInput.value.length > len) {
                accInput.value = accInput.value.slice(0, len);
            }
        } else {
            accInput.removeAttribute('maxLength');
            accInput.placeholder = "e.g., 5123xxxxxxxx";
        }
    });

    // 限制只能输入数字
    accInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, ''); // 强制删除非数字字符
    });

    // --- SSM (reg_number) 纯数字限制脚本 ---
const regNumberInput = document.getElementById('reg_number');

if (regNumberInput) {
    regNumberInput.addEventListener('input', function(e) {
        // 1. 强制删除所有非数字字符 (字母、符号、空格等都会被清空)
        this.value = this.value.replace(/\D/g, '');
        
        // 2. 二次保险：如果超出12位则截断
        if (this.value.length > 12) {
            this.value = this.value.slice(0, 12);
        }
    });
}

// --- 电话号码智能格式化脚本 ---
const phoneInput = document.getElementById('phone'); // 确保 ID 对应你的 input
const form = phoneInput ? phoneInput.closest('form') : null;

if (phoneInput) {
    // 1. 当用户点击输入框时，如果是空的，自动填入 01
    phoneInput.addEventListener('focus', function() {
        if (this.value === '') {
            this.value = '01';
        }
    });

    // 2. 监听输入过程，实时格式化
    phoneInput.addEventListener('input', function(e) {
        // 提取纯数字 (移除所有非数字字符)
        let val = this.value.replace(/\D/g, '');

        // 容错处理：如果用户粘贴了 601X，自动转成 01X
        if (val.startsWith('601')) {
            val = val.substring(1);
        }

        // 强制开头必须是 01
        if (val.length > 0) {
            if (!val.startsWith('01')) {
                if (val.startsWith('1')) val = '0' + val; // 比如直接按1 -> 01
                else val = '01' + val; // 按了其他数字，直接补01在前面
            }
        }

        let formatted = '';
        
        // 当长度超过2时 (即 01X...)
        if (val.length > 2) {
            // 判断第三个数字是不是 1 (011)
            if (val[2] === '1') {
                // 格式：011-XXXX XXXX (最长 11 位数字)
                val = val.substring(0, 11);
                if (val.length > 7) {
                    formatted = val.substring(0, 3) + '-' + val.substring(3, 7) + ' ' + val.substring(7);
                } else if (val.length > 3) {
                    formatted = val.substring(0, 3) + '-' + val.substring(3);
                } else {
                    formatted = val;
                }
            } else {
                // 格式：01X-XXX XXXX (除了011，其余最长 10 位数字)
                val = val.substring(0, 10);
                if (val.length > 6) {
                    formatted = val.substring(0, 3) + '-' + val.substring(3, 6) + ' ' + val.substring(6);
                } else if (val.length > 3) {
                    formatted = val.substring(0, 3) + '-' + val.substring(3);
                } else {
                    formatted = val;
                }
            }
        } else {
            formatted = val; // 只有 0 或 01 的情况
        }

        this.value = formatted;
    });

    // 3. 处理 Backspace (如果只剩 01，允许用户按 Backspace 完全清空)
    phoneInput.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value === '01') {
            this.value = '';
            e.preventDefault();
        }
    });
}

// 4. 提交表单时，清洗格式符 (-) 和空格
if (form && phoneInput) {
    form.addEventListener('submit', function() {
        // 在发往 PHP 之前，瞬间把值变回纯数字
        phoneInput.value = phoneInput.value.replace(/\D/g, '');
    });
}

// --- 文件上传预览脚本 ---
function setupFilePreview(inputId, previewId) {
    const fileInput = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewId);
    
    if (!fileInput || !previewContainer) return;

    // 找到当前 file-zone 里的提示文字 (用来在预览时隐藏文字)
    const fileZone = fileInput.closest('.file-zone');
    const textContainer = fileZone.querySelector('.file-text');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        
        // 每次重新选择文件时，清空旧的预览
        previewContainer.innerHTML = '';
        
        if (file) {
            // 显示预览区，隐藏原本的提示文字
            previewContainer.style.display = 'block';
            if (textContainer) textContainer.style.display = 'none';

            // 1. 如果是图片 (JPG, PNG) -> 显示照片预览
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '200px'; // 限制预览高度
                    img.style.borderRadius = '8px';
                    img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                    img.style.objectFit = 'contain';
                    previewContainer.appendChild(img);
                }
                reader.readAsDataURL(file);
            } 
            // 2. 如果是 PDF -> 显示 PDF 图标和文件名
            else if (file.type === 'application/pdf') {
                previewContainer.innerHTML = `
                    <div style="padding: 10px; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; display: inline-block;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 5px;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <p style="margin: 0; font-size: 0.9rem; color: #333; font-weight: 600; word-break: break-all;">${file.name}</p>
                        <p style="margin: 0; font-size: 0.8rem; color: #666;">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                `;
            }
            // 3. 其他不支持的文件类型
            else {
                previewContainer.innerHTML = `<p style="color: #DC3545; font-size: 0.9rem;">Unsupported file format.</p>`;
            }
        } else {
            // 如果用户取消选择文件，恢复原状
            previewContainer.style.display = 'none';
            if (textContainer) textContainer.style.display = 'block';
        }
    });
}

// 激活两个上传框的预览功能
setupFilePreview('auth_doc', 'preview_auth_doc');
setupFilePreview('bank_statement', 'preview_bank_statement');

</script>


</body>
</html>
<?php include '../includes/footer.php'; ?>