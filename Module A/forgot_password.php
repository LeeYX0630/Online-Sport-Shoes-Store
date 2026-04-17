<?php
require_once '../includes/db_connection.php'; // Required as per project structure [cite: 36]
session_start();
ob_start();

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Check email in the 'user' table (lowercase as per your DB screenshot)
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['reset_user_id'] = $user['user_id'];
        header("Location: reset_password.php"); 
        exit();
    } else {
        $message = "Email address not found.";
        $message_type = "danger";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Stealth Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --dark-navy: #0A192F;
            --brand-orange: #FF6B00; /* Project Accent Color  */
        }

        body {
            background-color: #E2E8F0;
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .split-card {
            display: flex;
            width: 900px;
            min-height: 500px;
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .left-panel {
            flex: 1;
            background: #F8FAFC;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }

        .left-panel img {
            max-width: 80%;
            height: auto;
            margin-bottom: 20px;
        }

        .right-panel {
            flex: 1;
            background: var(--dark-navy);
            color: white;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-line {
            width: 40px;
            height: 4px;
            background: var(--brand-orange);
            margin-bottom: 20px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 15px;
            border-radius: 12px;
        }

        .btn-reset {
            background: var(--brand-orange);
            color: white;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            border: none;
            text-transform: uppercase;
            transition: 0.3s;
        }

        .btn-reset:hover { background: #e65c00; transform: translateY(-2px); }

        .back-link { color: rgba(255, 255, 255, 0.5); text-decoration: none; font-size: 0.9rem; margin-top: 25px; display: block; text-align: center; }
        .back-link:hover { color: var(--brand-orange); }

        @media (max-width: 850px) { .left-panel { display: none; } .split-card { width: 450px; } }
    </style>
</head>
<body>

<div class="split-card">
    <div class="left-panel">
        <img src="https://cdni.iconscout.com/illustration/premium/thumb/forgot-password-illustration-download-in-svg-png-gif-file-formats--mobile-app-unlock-security-privacy-business-pack-illustrations-4545041.png" alt="Recovery Illustration">
        <h4 class="fw-bold text-dark">Password Recovery</h4>
        <p class="text-muted small">Enter your email to verify your account and proceed to reset your password.</p>
    </div>

    <div class="right-panel">
        <div class="brand-line"></div>
        <h2 class="fw-bold mb-1">Forgot<br>Password?</h2>
        <p class="small opacity-50 mb-4">We'll send you a secure verification link.</p>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> border-0 small py-2 mb-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" id="forgotForm">
            <div class="mb-4">
                <label class="form-label small fw-bold opacity-50 text-uppercase">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="user@example.com" required>
            </div>
            <button type="submit" class="btn btn-reset">Verify Email</button>
        </form>

        <a href="login.php" class="back-link"><i class="bi bi-chevron-left"></i> Back to Login</a>
    </div>
</div>

<script>
[cite_start]// Mandatory JavaScript validation [cite: 63]
document.getElementById('forgotForm').onsubmit = function(e) {
    const email = document.getElementById('email').value.trim();
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email === "" || !emailPattern.test(email)) {
        alert("Please enter a valid email address.");
        e.preventDefault();
    }
};
</script>
</body>
</html>