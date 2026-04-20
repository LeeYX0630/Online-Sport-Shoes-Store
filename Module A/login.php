<?php
/** * Design: Stealth Sport Shoes - Light Aesthetic
 * Palette: Pink (Background), #FF6B00 (Action), #FFFFFF (Form)
 */
ob_start();
session_start();
require_once '../includes/db_connection.php';
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
            --soft-pink: #FDF2F8; /* Very light pink background */
            --deep-slate: #0F172A;
        }

        body {
            background-color: var(--soft-pink);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        /* LIGHT PINK SHOE BACKGROUND LAYER */
        body::before {
            content: "";
            position: absolute;
            width: 100%;
            height: 100%;
            /* Using a light/pink high-end shoe as requested */
            background-image: url('https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?q=80&w=2000'); 
            background-size: 60%; /* Keeps shoe scale large but subtle */
            background-repeat: no-repeat;
            background-position: -5% 50%; /* Pushes shoe to the far left background */
            opacity: 0.15; /* Keeps it very light so words are readable */
            filter: grayscale(10%) sepia(20%) hue-rotate(300deg); /* Shifts color toward pink tones */
            z-index: -1;
        }

        .master-container {
            width: 1050px;
            height: 600px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(255, 107, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        /* Left side stays clean for the Pink background to show through */
        .branding-panel {
            flex: 1;
            padding: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            z-index: 1;
        }

        .branding-panel h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 5rem;
            font-weight: 900;
            line-height: 0.85;
            color: var(--deep-slate);
        }

        .branding-panel h1 span { color: var(--brand-orange); }

        /* Form side stays pure White/Orange focus */
        .form-panel {
            flex: 1;
            padding: 80px;
            background: #FFFFFF; /* Pure white focus as requested */
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: -20px 0 50px rgba(0,0,0,0.02);
        }

        .form-label {
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94A3B8;
        }

        .form-control {
            height: 55px;
            border-radius: 15px;
            border: 2px solid #F1F5F9;
            background: #F8FAFC;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .form-control:focus {
            border-color: var(--brand-orange); /* Keep orange focus */
            background: #fff;
            box-shadow: 0 10px 20px rgba(255, 107, 0, 0.05);
        }

        .btn-access {
            background: var(--brand-orange); /* Orange button focus */
            color: white;
            height: 60px;
            border-radius: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            border: none;
            transition: 0.4s;
        }

        .btn-access:hover {
            background: #E66000;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(255, 107, 0, 0.2);
        }

        @media (max-width: 992px) {
            .branding-panel { display: none; }
            .master-container { width: 100%; max-width: 450px; }
        }
    </style>
</head>
<body>

<div class="master-container">
    <div class="branding-panel">
        <span class="badge rounded-pill mb-3" style="background: rgba(255,107,0,0.1); color: var(--brand-orange); width: fit-content; font-weight: 800;">LITE COLLECTION</span>
        <h1>Welcome<br>Back<span></span></h1>
        <p class="text-muted mt-3">Access your exclusive locker at Stealth Sport Shoes.</p>
    </div>

    <div class="form-panel">
        <div class="mb-5">
            <h2 class="fw-800" style="font-weight: 800;">Sign In</h2>
            <p class="text-muted small">Orange branding, Light aesthetic.</p>
        </div>

        <form method="POST">
            <div>
                <label class="form-label">Email Handle</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
            </div>

            <div>
                <div class="d-flex justify-content-between">
                    <label class="form-label">Security Key</label>
                    <a href="forgot_password.php" class="text-decoration-none small fw-bold" style="color: var(--brand-orange);">LOST?</a>
                </div>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-access w-100">Access Account</button>
        </form>

        <div class="text-center mt-5">
            <p class="small text-muted">New member? <a href="register.php" class="fw-bold text-decoration-none" style="color: var(--brand-orange);">Join Now</a></p>
        </div>
    </div>
</div>

</body>
</html>