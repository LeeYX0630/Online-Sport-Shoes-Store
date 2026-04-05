<?php
// Module C/add_admin.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo "<script>alert('Access Denied. Super Admin only.'); window.location.href='admin_manage_admins.php';</script>";
    exit();
}

$msg = "";
$sweetAlertCode = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']); 

    $raw_phone = trim($_POST['phone']);
    $phone = str_replace('-', '', $raw_phone);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $role = 'admin'; 

    if (empty($username) || empty($email) || empty($phone) || empty($password) || empty($full_name)) {
        $msg = "<div class='alert error'>All fields are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='alert error'>Invalid email format.</div>";
    }
    elseif (substr($email, -13) !== '@homestay.com') {
        $msg = "<div class='alert error'>Restricted Domain: Admin email must end with <b>@homestay.com</b></div>";
    }
    elseif (!preg_match('/^[0-9]{9,11}$/', $phone)) {
        $msg = "<div class='alert error'>Invalid phone number (9-11 digits required).</div>";
    }
    elseif (strlen($password) < 6) {
        $msg = "<div class='alert error'>Password must be at least 6 characters long.</div>";
    } elseif ($password !== $confirm_password) {
        $msg = "<div class='alert error'>Passwords do not match.</div>";
    } else {
        $check_sql = "SELECT admin_id FROM admins WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $msg = "<div class='alert error'>Username or Email already taken.</div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->close(); 

            $insert_sql = "INSERT INTO admins (username, email, phone, password, full_name, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssss", $username, $email, $phone, $hashed_password, $full_name, $role);

            if ($stmt->execute()) {
                $sweetAlertCode = "
                Swal.fire({
                    title: 'Admin Added!',
                    text: 'New Admin ($username) created successfully.',
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'Back to Dashboard'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'admin_dashboard.php';
                    }
                });";
            } else {
                $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Admin</title>
    <link rel="stylesheet" href="../Module A/style.css"> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f4f4; font-family: 'Segoe UI', sans-serif; }
        .form-container { max-width: 500px; margin: 50px auto; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 12px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-group input:focus { border-color: #007bff; outline: none; }
        
        .btn-submit { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; }
        .btn-submit:hover { background-color: #0056b3; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; text-align: center; font-size: 14px; }
        .error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        
        .back-link { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        .back-link:hover { color: #333; text-decoration: underline; }

        .strength-container { margin-top: 8px; height: 5px; background-color: #eee; border-radius: 3px; overflow: hidden; display: flex;}
        .strength-bar { height: 100%; width: 0%; transition: width 0.3s ease, background-color 0.3s ease; }
        .strength-text { font-size: 12px; margin-top: 5px; font-weight: bold; display: block; text-align: right; }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>
<body>

<div class="form-container">
    <h2 style="text-align:center; margin-top:0; margin-bottom: 30px; color:#333;">Add New Admin</h2>
    <?php echo $msg; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name (Position)</label>
            <input type="text" name="full_name" required placeholder="e.g. John Manager" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="For display purpose" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>

        <div class="form-group">
            <label>Email Address (For Login)</label>
            <input type="email" name="email" id="emailInput" required placeholder="username@homestay.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            <small style="color:#666; font-size:0.8em;">* Must end with @homestay.com</small>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" id="phoneInput" required placeholder="e.g. 012-3456789" 
                   maxlength="12" 
                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            <small style="color:#666; font-size:0.8em;">* Format: 01x-xxxxxxx</small>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" id="passwordInput" required placeholder="********">
            
            <div class="strength-container">
                <div class="strength-bar" id="strengthBar"></div>
            </div>
            <span class="strength-text" id="strengthText"></span>
            <small id="passwordSuggestions" style="color:#666; font-size:0.8em;"></small>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required placeholder="********">
        </div>

        <button type="submit" class="btn-submit">Create Admin Account</button>
    </form>

    <a href="admin_manage_admins.php" class="back-link">&larr; Back to Admin List</a>
</div>

<script>
    const emailInput = document.getElementById('emailInput');
    
    emailInput.addEventListener('input', function(e) {
        if (e.target.value.endsWith('@')) {
            e.target.value += 'homestay.com';
        }
    });

    const phoneInput = document.getElementById('phoneInput');
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        if (value.length > 3) value = value.slice(0, 3) + '-' + value.slice(3);
        e.target.value = value;
    });

    const passwordInput = document.getElementById('passwordInput');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordSuggestions = document.getElementById('passwordSuggestions');

    passwordInput.addEventListener('input', function() {
        const val = passwordInput.value;
        let score = 0;
        if (val.length >= 6) score += 1;
        if (val.length >= 10) score += 1;
        if (/[A-Z]/.test(val)) score += 1;
        if (/[0-9]/.test(val)) score += 1;
        if (/[^A-Za-z0-9]/.test(val)) score += 1;

        strengthBar.className = 'strength-bar';
        strengthText.textContent = '';
        passwordSuggestions.textContent = '';

        let suggestions = [];
        if (val.length === 0) {
            strengthBar.style.width = '0%';
        } else if (val.length < 6) {
            strengthBar.style.width = '20%';
            strengthBar.classList.add('strength-weak');
            strengthText.textContent = 'Too Short';
            strengthText.style.color = '#dc3545';
            suggestions.push('Password must be at least 6 characters long.');
        } else {
            if (score < 3) {
                strengthBar.style.width = '40%';
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#dc3545';
                if (!/[A-Z]/.test(val)) suggestions.push('Add uppercase letters.');
                if (!/[0-9]/.test(val)) suggestions.push('Add numbers.');
                if (!/[^A-Za-z0-9]/.test(val)) suggestions.push('Add special characters (e.g., !@#$%).');
            } else if (score === 3 || score === 4) {
                strengthBar.style.width = '70%';
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Medium';
                strengthText.style.color = '#d39e00';
                if (!/[A-Z]/.test(val)) suggestions.push('Add uppercase letters for stronger password.');
                if (!/[0-9]/.test(val)) suggestions.push('Add numbers for stronger password.');
                if (!/[^A-Za-z0-9]/.test(val)) suggestions.push('Add special characters for stronger password.');
            } else {
                strengthBar.style.width = '100%';
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#28a745';
                suggestions.push('Great password!');
            }
        }
        passwordSuggestions.textContent = suggestions.join(' ');
    });

    <?php if (!empty($sweetAlertCode)) { echo $sweetAlertCode; } ?>
</script>

</body>
</html>