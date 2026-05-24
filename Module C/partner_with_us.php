<?php
// ============================================================
//  Vendor Registration — Server-side processing
// ============================================================

session_start();
require_once '../includes/db_connection.php'; 

$success  = false;
$errors   = [];
$old      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Sanitise helpers ───────────────────────────────────
    $str  = fn(string $k) => trim($_POST[$k] ?? '');
    $old  = [
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

    // Validate bank account number: digits only + required length per bank
    $clean_acc = preg_replace('/\D/', '', $old['bank_acc_no']);
    if ($clean_acc === '' || !ctype_digit($clean_acc)) {
        $errors['bank_acc_no'] = 'Please enter a valid account number (digits only).';
    } else if (!empty($old['bank_name']) && isset($bank_lengths[$old['bank_name']])) {
        $reqLen = $bank_lengths[$old['bank_name']];
        if (strlen($clean_acc) !== $reqLen) {
            $errors['bank_acc_no'] = "Please enter a valid account number ({$reqLen} digits) for {$old['bank_name']}.";
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

    // 在验证逻辑之前，把 phone 里的 - 和空格去掉，转回纯数字
    $clean_phone = str_replace(['-', ' '], '', $old['phone']);

    // 如果你之后需要验证是否全是数字，用 $clean_phone
    if ($clean_phone === '' || !ctype_digit($clean_phone)) {
        $errors['phone'] = 'Please enter a valid contact number.';
    }

// ── If valid → process (save / DB insert here) ─
    if (empty($errors)) {
        // 1. 设置上传目录
        $upload_dir = '../uploads/vendors/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // 2. 处理文件重命名与移动 (避免文件名重复)
        $auth_doc_ext  = pathinfo($_FILES['auth_doc']['name'], PATHINFO_EXTENSION);
        $auth_doc_name = "auth_" . uniqid() . "." . $auth_doc_ext;
        move_uploaded_file($_FILES['auth_doc']['tmp_name'], $upload_dir . $auth_doc_name);

        $bank_stmt_ext  = pathinfo($_FILES['bank_statement']['name'], PATHINFO_EXTENSION);
        $bank_stmt_name = "bank_" . uniqid() . "." . $bank_stmt_ext;
        move_uploaded_file($_FILES['bank_statement']['tmp_name'], $upload_dir . $bank_stmt_name);

        // 3. 插入数据库
        try {
            $sql = "INSERT INTO vendors (
                        business_name, email, phone, reg_number, 
                        auth_doc_path, bank_name, bank_acc_no, 
                        bank_statement_path, warehouse_address, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($sql);
            
            // "sssssssss" 代表 9 个字符串参数
            $stmt->bind_param("sssssssss", 
                $old['business_name'], 
                $old['email'], 
                $old['phone'], 
                $old['reg_number'], 
                $auth_doc_name, 
                $old['bank_name'], 
                $old['bank_acc_no'], 
                $bank_stmt_name, 
                $old['address']
            );

            if ($stmt->execute()) {
                $success = true;
                $old = []; // 成功后清空表单数据
            } else {
                $errors['db'] = "Database Error: " . $conn->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $errors['db'] = "System Error: " . $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* ── Reset & base ──────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            color: #212529;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
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
            color: #FF6B00;
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

        /* ── Preview container ────────────────────────── */
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            width: 100px; height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .preview-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .preview-item img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .remove-img {
            position: absolute;
            top: 2px; right: 2px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px; height: 20px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        /* ── Lightbox styles ──────────────────────────── */
        .lightbox-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            padding-top: 50px;
            left: 0; top: 0;
            width: 100%; height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(5px);
        }

        .lightbox-content {
            position: relative;
            margin: auto;
            display: block;
            width: auto;
            max-width: 90%;
            max-height: 85vh;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            animation: zoomIn 0.3s ease;
        }
        @keyframes zoomIn { from {transform:scale(0.8); opacity:0;} to {transform:scale(1); opacity:1;} }

        .lightbox-close {
            position: absolute;
            top: 15px; right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 2010;
        }
        .lightbox-close:hover { color: #FF6B00; transform: rotate(90deg); }

        .lightbox-nav {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            padding: 12px 18px;
            margin-top: -50px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: normal;
            font-size: 24px;
            -webkit-text-stroke: 0.5px rgba(255, 255, 255, 0.8);
            transition: 0.3s ease;
            user-select: none;
            background-color: rgba(0, 0, 0, 0.2);
            border: none;
            z-index: 2010;
            outline: none;
            border-radius: 8px;
        }

        .lightbox-prev { left: 20px; }
        .lightbox-next { right: 20px; }

        .lightbox-nav:hover {
            background-color: rgba(255, 107, 0, 0.3);
            color: #FF6B00;
            -webkit-text-stroke: 0.5px #FF6B00;
            transform: scale(1.1);
        }

        .lightbox-caption-area {
            text-align: center;
            color: #ccc;
            padding: 20px 0;
            width: 90%;
            margin: auto;
        }

        .lightbox-dots-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }

        .lightbox-dot {
            height: 10px; width: 10px;
            background-color: #bbb;
            border-radius: 50%;
            display: inline-block;
            transition: background-color 0.6s ease;
            cursor: pointer;
        }

        .lightbox-dot.active { background-color: #FF6B00; transform: scale(1.2); }
        .terms-check-row {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: .5rem;
        }
        .terms-check-row input[type="checkbox"] {
            width: 1.2rem; height: 1.2rem;
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
            background: none; border: none;
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
            background: none; border: none;
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
        
        /* SweetAlert2 自定义按钮颜色 */
        .swal2-confirm {
            background-color: #FF6B00 !important;
        }
    </style>
</head>

<?php include '../includes/header.php'; ?>

<body>

<main class="main-wrap">
    <div class="card">

        <div class="card-header">
            <h2>Become Our Partner</h2>
            <p>Join our marketplace and grow your business</p>
        </div>

        <div class="form-body">



            <form id="vendorForm" method="POST" action="" enctype="multipart/form-data" novalidate>

                <div class="form-group">
                    <label class="field-label" for="business_name">
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

                <div class="form-group">
                    <label class="field-label" for="business_brand">
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

                <div class="form-row">
                    <div class="form-group">
                        <label class="field-label" for="email">
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
                            placeholder="012-345 6789"
                            inputmode="numeric"
                            maxlength="13" 
                        />
                        <?= err($errors, 'phone') ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="field-label" for="reg_number">
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
                        maxlength="12"
                        inputmode="numeric"
                    />
                    <?= err($errors, 'reg_number') ?>
                </div>

                <div class="form-group">
                    <label class="field-label">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        Verification Document (SSM / IC) <span class="req">*</span>
                    </label>
                    <div class="file-zone <?= isset($errors['auth_doc']) ? 'is-invalid' : '' ?>"
                         id="auth_doc_zone">
                        <input type="file" id="auth_doc" name="auth_doc"
                               accept=".pdf,.jpg,.png"
                               onchange="handleFileSelect(this, 'auth_doc')" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round" style="display:block;margin:0 auto .75rem">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        <p class="file-label" id="auth_doc_label">Click to upload or drag &amp; drop</p>
                        <p class="file-hint">Supported formats: PDF, JPG, PNG (Max 2 MB)</p>
                    </div>
                    <div class="preview-container" id="preview_auth_doc"></div>
                    <?= err($errors, 'auth_doc') ?>
                </div>

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

                <div class="form-group">
                    <label class="field-label">
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
                    <div class="file-zone <?= isset($errors['bank_statement']) ? 'is-invalid' : '' ?>"
                         id="bank_statement_zone">
                        <input type="file" id="bank_statement" name="bank_statement"
                               accept=".pdf,.jpg,.png"
                               onchange="handleFileSelect(this, 'bank_statement')" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round" style="display:block;margin:0 auto .75rem">
                            <polyline points="16 16 12 12 8 16"/>
                            <line x1="12" y1="12" x2="12" y2="21"/>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                        </svg>
                        <p class="file-label" id="bank_statement_label">Click to upload or drag &amp; drop</p>
                        <p class="file-hint">Upload statement showing company name &amp; account number</p>
                    </div>
                    <div class="preview-container" id="preview_bank_statement"></div>
                    <?= err($errors, 'bank_statement') ?>
                </div>

                <div class="form-group">
                    <label class="field-label" for="address">
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

                    <div class="terms-panel" id="termsPanel">
                        <div class="terms-panel-header">
                            <h4>
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

                <button type="submit" class="btn-submit" id="submitBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Submit Application
                </button>

            </form>
        </div>
    </div>

    <p class="footer-note">
        After submission, we will review your application within 2–3 business days and notify you via email.
    </p>
</main>

<div id="lightboxModal" class="lightbox-modal">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <div style="position:relative; display:flex; align-items:center; justify-content:center; min-height:85vh;">
        <button type="button" class="lightbox-nav lightbox-prev" onclick="changeLightboxSlide(-1)">&#10094;</button>
        <img class="lightbox-content" id="lightboxImage">
        <button type="button" class="lightbox-nav lightbox-next" onclick="changeLightboxSlide(1)">&#10095;</button>
    </div>
    <div class="lightbox-caption-area">
        <div class="fw-bold fs-5 text-white" id="lightboxCaption">Document</div>
        <div id="lightboxDots" class="lightbox-dots-container"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // 如果 PHP 处理成功，弹出 SweetAlert2 成功提示
    <?php if ($success): ?>
        window.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                title: 'Submitted Successfully!',
                text: 'Thank you for applying. We will review your application within 2–3 business days and notify you via email.',
                icon: 'success',
                confirmButtonText: 'Ok'
            });
        });
    <?php endif; ?>

    const fileManagers = {};
    ['auth_doc', 'bank_statement'].forEach(field => {
        fileManagers[field] = new DataTransfer();
    });

    let currentLightboxIndex = 0;
    let currentLightboxField = '';
    let currentLightboxImages = [];

    function handleFileSelect(input, field) {
        const files = input.files;
        const previewContainer = document.getElementById(`preview_${field}`);
        if (files.length === 0) return;
        previewContainer.innerHTML = '';
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" onclick="openLightbox('${field}', this.parentElement)">
                    <button type="button" class="remove-img" onclick="removeFile('${field}', this)">×</button>
                `;
                previewContainer.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        }
    }

    function removeFile(field, btn) {
        const previewContainer = document.getElementById(`preview_${field}`);
        const finalInput = document.getElementById(field);
        const items = Array.from(previewContainer.querySelectorAll('.preview-item'));
        const currentIndex = items.indexOf(btn.parentNode);
        const dt = new DataTransfer();
        const { files } = fileManagers[field];
        for (let i = 0; i < files.length; i++) {
            if (i !== currentIndex) dt.items.add(files[i]);
        }
        fileManagers[field] = dt;
        finalInput.files = dt.files;
        btn.parentNode.remove();
    }

    function openLightbox(field, clickedElement) {
        const previewContainer = document.getElementById(`preview_${field}`);
        const allPreviewItems = Array.from(previewContainer.querySelectorAll('.preview-item'));
        currentLightboxIndex = allPreviewItems.indexOf(clickedElement);
        currentLightboxField = field;
        currentLightboxImages = allPreviewItems.map(item => item.querySelector('img').src);
        if (currentLightboxImages.length === 0) return;
        document.getElementById('lightboxModal').style.display = "block";
        document.body.style.overflow = 'hidden';
        updateLightboxDOM();
    }

    function closeLightbox() {
        document.getElementById('lightboxModal').style.display = "none";
        document.body.style.overflow = 'auto';
    }

    function updateLightboxDOM() {
        const imgElement = document.getElementById('lightboxImage');
        const captionElement = document.getElementById('lightboxCaption');
        const dotsContainer = document.getElementById('lightboxDots');
        imgElement.src = currentLightboxImages[currentLightboxIndex];
        captionElement.innerText = `${currentLightboxField.toUpperCase()} (${currentLightboxIndex + 1} / ${currentLightboxImages.length})`;
        const prevBtn = document.querySelector('.lightbox-prev');
        const nextBtn = document.querySelector('.lightbox-next');
        prevBtn.style.display = nextBtn.style.display = (currentLightboxImages.length <= 1) ? 'none' : 'block';
        dotsContainer.innerHTML = '';
        currentLightboxImages.forEach((_, i) => {
            const dot = document.createElement('span');
            dot.className = `lightbox-dot ${i === currentLightboxIndex ? 'active' : ''}`;
            dot.setAttribute('onclick', `setLightboxSlide(${i})`);
            dotsContainer.appendChild(dot);
        });
    }

    function changeLightboxSlide(n) {
        currentLightboxIndex += n;
        if (currentLightboxIndex >= currentLightboxImages.length) currentLightboxIndex = 0;
        if (currentLightboxIndex < 0) currentLightboxIndex = currentLightboxImages.length - 1;
        updateLightboxDOM();
    }

    function setLightboxSlide(n) {
        currentLightboxIndex = n;
        updateLightboxDOM();
    }

    document.addEventListener('keydown', function(e) {
        if (document.getElementById('lightboxModal').style.display === 'block') {
            if (e.key === 'ArrowLeft') changeLightboxSlide(-1);
            if (e.key === 'ArrowRight') changeLightboxSlide(1);
            if (e.key === 'Escape') closeLightbox();
        }
    });

    function toggleTerms() {
        const panel = document.getElementById('termsPanel');
        panel.classList.toggle('open');
    }

    function syncSubmitBtn() {
        const checked = document.getElementById('agreed_terms').checked;
        const btn = document.getElementById('submitBtn');
        btn.disabled = !checked;
        btn.classList.toggle('active', checked);
    }

    document.getElementById('phone').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        // 011 numbers can be 11 digits (3-4-4). Other 01x numbers should be 10 digits (3-3-4).
        const is011 = value.startsWith('011');
        const maxLen = is011 ? 11 : 10;
        if (value.length > maxLen) value = value.slice(0, maxLen);
        let formattedValue = '';
        if (is011) {
            if (value.length > 3) formattedValue = value.slice(0, 3) + '-' + value.slice(3, 7);
            else formattedValue = value;
            if (value.length > 7) formattedValue += ' ' + value.slice(7);
        } else {
            if (value.length > 3) formattedValue = value.slice(0, 3) + '-' + value.slice(3, 6);
            else formattedValue = value;
            if (value.length > 6) formattedValue += ' ' + value.slice(6);
        }
        e.target.value = formattedValue;
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

    document.getElementById('vendorForm').addEventListener('submit', function(e) {
        let valid = true;
        const checks = [
            { id: 'business_name', test: v => v.trim() !== '',   msg: 'Please enter your business name.' },
            { id: 'email',         test: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), msg: 'Please enter a valid email address.' },
            { id: 'phone',         test: v => v.trim() !== '',   msg: 'Please enter your contact number.' },
            { id: 'reg_number',    test: v => v.trim() !== '',   msg: 'Please enter your registration number.' },
            { id: 'bank_name',     test: v => v !== '',          msg: 'Please select a bank.' },
            { id: 'bank_acc_no',   test: v => {const bank = document.getElementById('bank_name').value; const cleanV = v.trim();
            if (!bank || !bankLengths[bank]) return cleanV !== ''; // 如果没选银行，只检查非空
            return cleanV.length === bankLengths[bank]; // 检查长度是否完全匹配
            }, 
            msg: 'Please enter the correct account number length for the selected bank.' 
            },
            { id: 'address',       test: v => v.trim() !== '',   msg: 'Please enter your warehouse address.' },
        ];

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

        ['auth_doc', 'bank_statement'].forEach(id => {
            const el = document.getElementById(id);
            const zone = el.closest('.file-zone');
            if (!el.files || el.files.length === 0) {
                zone.classList.add('is-invalid');
                const msgs = { auth_doc: 'Please upload verification document.', bank_statement: 'Please upload bank statement.' };
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

        if (!document.getElementById('agreed_terms').checked) {
            valid = false;
            // 替换 alert 为 SweetAlert2
            Swal.fire({
                title: 'Notice',
                text: 'Please agree to the Terms & Conditions before submitting.',
                icon: 'warning',
                confirmButtonColor: '#FF6B00'
            });
        }

        if (!valid) e.preventDefault();
    });

    window.addEventListener('DOMContentLoaded', () => {
        syncSubmitBtn();
    });
</script>
</body>
</html>
<?php require '../includes/footer.php'; ?>