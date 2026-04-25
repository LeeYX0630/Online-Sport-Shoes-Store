<?php
/**
 * STEALTH SPORT SHOES - FORGOT PASSWORD
 * Module A: User Permissions & Profile
 */

require_once '../includes/db_connection.php'; 
session_start();
ob_start();

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = strtolower(trim($_POST['email']));

    // ✅ Correct column names
    $stmt = $conn->prepare("SELECT User_ID FROM USER WHERE User_Email = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // ✅ Store user ID in session
            $_SESSION['reset_user_id'] = $user['User_ID'];
            
            // ✅ Redirect to correct file name
            header("Location: reset_password.php");
            exit();
        } else {
            $message = "This email is not registered with us.";
            $message_type = "danger";
        }

        $stmt->close();
    } else {
        $message = "Database error. Please try again.";
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Account | Stealth Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-orange: #FF6B00;
            --pure-white: #FFFFFF;
            --text-dark: #1E293B;
            --soft-gray: #F8FAFC;
        }

        body {
            background-color: var(--soft-gray);
            background-image: radial-gradient(var(--brand-orange) 0.5px, transparent 0.5px);
            background-size: 30px 30px;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .recovery-card {
            background: var(--pure-white);
            width: 100%;
            max-width: 480px;
            border-radius: 35px;
            padding: 60px 45px;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .icon-wrapper {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, var(--brand-orange), #FF8533);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 28px;
            margin: 0 auto 30px;
            font-size: 2.5rem;
        }

        h2 { color: var(--text-dark); font-weight: 800; }

        .form-control {
            height: 60px;
            border-radius: 15px;
            border: 2px solid #F1F5F9;
        }

        .btn-recover {
            background: var(--brand-orange);
            color: white;
            height: 60px;
            border-radius: 15px;
            font-weight: 700;
            width: 100%;
            border: none;
        }

        .btn-recover:hover {
            background: #E66000;
        }
    </style>
</head>

<body>

<div class="recovery-card">
    <div class="icon-wrapper">
        <i class="bi bi-shield-lock"></i>
    </div>

    <h2>Access Recovery</h2>
    <p>Enter your email to reset your password.</p>

    <?php if($message): ?>
        <div class="alert alert-danger"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <input type="email" name="email" class="form-control" placeholder="username@gmail.com" required>
        </div>

        <button type="submit" class="btn btn-recover">
            Verify Identity
        </button>
    </form>

    <br>
    <a href="login.php">← Back to Login</a>
</div>

</body>
</html>