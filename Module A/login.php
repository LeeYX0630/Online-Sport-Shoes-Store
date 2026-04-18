<?php
ob_start();
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
session_start();

require_once '../includes/db_connection.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: ../Module C/admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    $stmt = $conn->prepare("SELECT User_id, User_Name, User_Password FROM user WHERE User_Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['User_Password'])) {
            if ($row['status'] === 'Blocked') {
                $error = "Account suspended. Contact support.";
            } else {
                session_regenerate_id(true); 
                $_SESSION['user_id'] = $row['User_id'];
                $_SESSION['user_name'] = $row['User_Name'];
                $_SESSION['role'] = $row['role'];
                header("Location: user_dashboard.php");
                exit();
            }
        } else { $error = "Invalid password."; }
    } else { $error = "Email not found."; }
    $stmt->close();
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Stealth Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --dark-blue: #0A192F;
            --accent-orange: #FF6B00;
            --soft-white: #F8F9FA;
        }

        body {
            background-color: #E2E8F0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        /* The Main Wrapper Card */
        .master-card {
            width: 1000px;
            height: 600px;
            background: white;
            border-radius: 30px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(0,0,0,0.1);
        }

        /* Left Side: Product Showcase */
        .visual-side {
            flex: 1.2;
            background: #f1f1f1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .shoe-container {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dark-shoe {
            width: 100%;
            max-width: 450px;
            /* Using a darker shoe image URL */
            filter: drop-shadow(20px 30px 40px rgba(0,0,0,0.2));
            transform: rotate(-10deg);
        }

        .product-info {
            border-top: 2px solid #ddd;
            padding-top: 20px;
        }

        .product-info h4 {
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }

        .product-info p {
            color: #777;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Right Side: Dark Blue Form */
        .form-side {
            flex: 1;
            background: var(--dark-blue);
            color: white;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-side h2 {
            font-weight: 700;
            margin-bottom: 30px;
        }

        .form-label {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .form-control {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 5px;
        }

        .form-control:focus {
            background: rgba(255,255,255,0.1);
            border-color: var(--accent-orange);
            box-shadow: none;
            color: white;
        }

        .btn-login {
            background: var(--accent-orange);
            border: none;
            color: white;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            margin-top: 20px;
            transition: 0.3s;
        }

        .btn-login:hover {
            background: #e65c00;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 0, 0.3);
        }

        .password-eye {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255,255,255,0.3);
        }

        @media (max-width: 991px) {
            .visual-side { display: none; }
            .master-card { width: 450px; }
        }
    </style>
</head>
<body>

<div class="master-card">
    
    <div class="visual-side">
        <div class="shoe-container">
            <img src="https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?q=80&w=1000" class="dark-shoe" alt="Dark Edition Shoe">
        </div>
        
        <div class="product-info">
            <span class="badge bg-dark mb-2">LIMITED EDITION</span>
            <h4>Phantom Night Stealth</h4>
            <p>A sleek, triple-black design engineered for the street and the gym. Features ultra-grip sole technology and carbon-fiber reinforcement.</p>
        </div>
    </div>

    <div class="form-side">
        <h2>Sign In</h2>
        
        <?php if($error): ?>
            <div class="alert alert-danger border-0 bg-danger text-white py-2 small mb-4">
                <i class="bi bi-shield-exclamation me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-uppercase">Email Address</label>
                <input type="email" name="email" class="form-control" required placeholder="email@domain.com"
                       value="<?php echo isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : ''; ?>">
            </div>

            <div class="mb-4">
                <label class="form-label text-uppercase">Password</label>
                <div class="position-relative">
                    <input type="password" name="password" id="p" class="form-control" required placeholder="••••••••">
                    <i class="bi bi-eye-fill password-eye" onclick="toggle()"></i>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input bg-transparent border-secondary" type="checkbox" name="remember" id="r" <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                    <label class="form-check-label small opacity-75" for="r">Remember Me</label>
                </div>
                <a href="forgot_password.php" class="text-decoration-none small text-warning">Forgot?</a>
            </div>

            <button type="submit" class="btn btn-login">ACCESS ACCOUNT</button>
        </form>

        <div class="text-center mt-5 pt-3 border-top border-secondary">
            <p class="small opacity-50 mb-2">New to our store?</p>
            <a href="register.php" class="text-white fw-bold text-decoration-none">Create a Member Account</a>
            <div class="mt-3">
                <a href="../Module C/admin_login.php" class="text-secondary small">Staff Portal</a>
            </div>
        </div>
    </div>

</div>

<script>
    function toggle() {
        const p = document.getElementById('p');
        const icon = event.target;
        if (p.type === 'password') {
            p.type = 'text';
            icon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
        } else {
            p.type = 'password';
            icon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
        }
    }
</script>

</body>
</html>