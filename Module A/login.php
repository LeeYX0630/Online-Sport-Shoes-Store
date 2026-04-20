<?php
/** * Design focus: High-end sports aesthetic.
 * Colors: #FF6B00 (Primary), #FFFFFF (Secondary), #0F172A (Deep Slate for contrast).
 */
ob_start();
session_start();
require_once '../includes/db_connection.php';

// Redirection logic remains the same as your functional code...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Access | Stealth Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-orange: #FF6B00;
            --deep-bg: #F8FAFC;
            --glass-white: rgba(255, 255, 255, 0.9);
        }

        body {
            background-color: var(--deep-bg);
            background-image: radial-gradient(circle at 20% 30%, rgba(255, 107, 0, 0.05) 0%, transparent 40%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .master-container {
            width: 1050px;
            background: var(--glass-white);
            backdrop-filter: blur(15px);
            border-radius: 40px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(0,0,0,0.08);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        /* LEFT PANEL: The "Aura" Branding */
        .aura-panel {
            flex: 1.2;
            padding: 80px;
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .aura-panel h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 5rem;
            font-weight: 800;
            line-height: 0.85;
            letter-spacing: -3px;
            color: #0F172A;
            margin-bottom: 25px;
        }

        .aura-panel h1 span { color: var(--brand-orange); }

        .aura-panel p {
            font-size: 1.2rem;
            color: #64748B;
            line-height: 1.6;
            max-width: 350px;
        }

        /* RIGHT PANEL: Sleek Form */
        .form-panel {
            flex: 1;
            padding: 70px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid #f1f5f9;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group-custom i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            transition: 0.3s;
        }

        .form-label {
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #475569;
            margin-bottom: 10px;
            display: block;
        }

        .form-control {
            height: 60px;
            padding-left: 55px !important;
            border-radius: 18px;
            border: 2px solid #F1F5F9;
            background: #F8FAFC;
            font-weight: 600;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-control:focus {
            background: #fff;
            border-color: var(--brand-orange);
            box-shadow: 0 10px 25px rgba(255, 107, 0, 0.1);
        }

        .form-control:focus + i { color: var(--brand-orange); }

        .btn-access {
            background: var(--brand-orange);
            color: white;
            border: none;
            height: 64px;
            border-radius: 20px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 1rem;
            transition: 0.4s;
            margin-top: 15px;
        }

        .btn-access:hover {
            background: #E66000;
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(255, 107, 0, 0.3);
            color: white;
        }

        .badge-stealth {
            background: rgba(255, 107, 0, 0.1);
            color: var(--brand-orange);
            font-weight: 800;
            font-size: 0.7rem;
            padding: 8px 16px;
            border-radius: 100px;
            display: inline-block;
        }

        @media (max-width: 992px) {
            .aura-panel { display: none; }
            .master-container { width: 100%; max-width: 450px; }
        }
    </style>
</head>
<body>

<div class="master-container">
    <div class="aura-panel">
        <div class="mb-4"><span class="badge-stealth">2026 EDITION</span></div>
        <h1>Welcome<br>Back<span></span></h1>
        <p>Your journey to peak performance starts here. Sign in to your locker.</p>
    </div>

    <div class="form-panel">
        <div class="mb-5 text-center text-lg-start">
            <h3 class="fw-800" style="font-weight: 800;">Sign In</h3>
            <p class="text-muted small">Access your member profile</p>
        </div>

        <form method="POST">
            <div class="input-group-custom">
                <label class="form-label">Email Handle</label>
                <input type="email" name="email" class="form-control" required placeholder="your@email.com">
                <i class="bi bi-envelope-at-fill"></i>
            </div>

            <div class="input-group-custom mb-2">
                <div class="d-flex justify-content-between">
                    <label class="form-label">Security Key</label>
                    <a href="forgot_password.php" class="text-decoration-none small fw-bold" style="color: var(--brand-orange); font-size: 0.7rem;">LOST?</a>
                </div>
                <input type="password" name="password" id="p" class="form-control" required placeholder="••••••••">
                <i class="bi bi-shield-lock-fill"></i>
            </div>

            <div class="form-check mb-4 mt-3">
                <input class="form-check-input" type="checkbox" id="rem">
                <label class="form-check-label small text-muted" for="rem">Keep me authenticated</label>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn-access">Access Account</button>
            </div>
        </form>

        <div class="text-center mt-5 pt-4 border-top" style="border-color: #f1f5f9 !important;">
            <p class="small text-muted mb-2">Not a member yet?</p>
            <a href="register.php" class="fw-bold text-decoration-none" style="color: var(--brand-orange);">Apply for Membership</a>
        </div>
    </div>
</div>

</body>
</html>