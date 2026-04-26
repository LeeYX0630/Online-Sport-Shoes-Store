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

    // Prepare statement to prevent SQL Injection
    $stmt = $conn->prepare("SELECT User_ID FROM USER WHERE User_Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Security Note: We give the same generic response whether the email exists or not
    // to prevent malicious users from guessing which emails are registered.
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['reset_user_id'] = $user['User_ID'];
        header("Location: reset_password.php");
        exit();
    } else {
        // Generic message keeps your user base safe
        $message = "If this email exists in our system, you will be redirected shortly.";
        $message_type = "info";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Account | Stealth Sport Shoes</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-orange: #FF6B00;
            --brand-orange-dark: #E66000;
            --text-dark: #1E293B;
        }

        body {
            background-color: #F8FAFC;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .recovery-card {
            background: white;
            width: 100%;
            max-width: 450px;
            border-radius: 24px;
            padding: 50px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-wrapper {
            width: 80px; height: 80px;
            background: rgba(255, 107, 0, 0.1);
            color: var(--brand-orange);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            margin: 0 auto 25px;
            font-size: 2rem;
        }

        h2 { color: var(--text-dark); font-weight: 800; margin-bottom: 15px; }
        p { color: #64748B; margin-bottom: 30px; }

        .form-control {
            height: 55px;
            border-radius: 12px;
            border: 2px solid #E2E8F0;
            padding: 10px 20px;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: var(--brand-orange);
            box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1);
        }

        .btn-recover {
            background: var(--brand-orange);
            color: white;
            height: 55px;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            transition: 0.3s;
        }

        .btn-recover:hover {
            background: var(--brand-orange-dark);
            transform: translateY(-2px);
        }

        .back-link {
            display: block;
            margin-top: 20px;
            color: #64748B;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link:hover { color: var(--brand-orange); }
    </style>
</head>

<body>

<div class="recovery-card">
    <div class="icon-wrapper">
        <i class="bi bi-shield-lock-fill"></i>
    </div>

    <h2>Access Recovery</h2>
    <p>We’ll send reset instructions to your registered email address.</p>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type === 'info' ? 'info' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <input type="email" name="email" class="form-control" placeholder="username@example.com" required autocomplete="email">
        </div>

        <button type="submit" class="btn btn-recover">
            Verify Identity
        </button>
    </form>

    <a href="login.php" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Login
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>