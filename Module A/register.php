<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Online Sports Shoes Store</title>
    <style>
        :root {
            --brand-orange: #FF6B00;
            --brand-dark: #333333;
            --text-blue: #3498db;
        }

        body {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('https://images.unsplash.com/photo-1460353581641-37baddab0fa2?q=80&w=1200');
            background-size: cover;
            background-attachment: fixed;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            justify-content: center;
            padding: 40px 0;
            margin: 0;
        }

        .container {
            width: 550px;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-top: 8px solid var(--brand-orange);
        }

        h2 { color: var(--text-blue); text-transform: uppercase; margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block;
            font-weight: 700;
            color: var(--brand-dark);
            margin-bottom: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .row { display: flex; gap: 15px; }
        .col { flex: 1; }

        .btn-register {
            width: 100%;
            background: var(--brand-orange);
            color: white;
            border: none;
            padding: 15px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
            text-transform: uppercase;
        }

        .btn-otp {
            width: 100%;
            background: #3498db;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }

        .otp-section {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Personal Details</h2>

    <!-- 🔥 FORM START -->
    <form method="POST" action="">

        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>

        <div class="row">
            <div class="col form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            <div class="col form-group">
                <label class="form-label">Birth Date</label>
                <input type="date" name="dob" id="dob_input" class="form-control" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Shipping Address</label>
            <textarea name="address" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" id="email_input" class="form-control" required>
        </div>

        <div class="row">
            <div class="col form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
        </div>

        <!-- 🔥 SEND OTP -->
        <button type="submit" name="send_otp" class="btn-otp">
            Send OTP
        </button>

        <!-- 🔥 OTP INPUT -->
        <div class="otp-section">
            <div class="form-group">
                <label class="form-label">Enter OTP</label>
                <input type="text" name="otp" class="form-control" maxlength="6">
            </div>
        </div>

        <!-- 🔥 FINAL SUBMIT -->
        <button type="submit" name="register_btn" class="btn-register">
            Create Account
        </button>

    </form>
</div>

<script>
    // DOB validation
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dob_input').setAttribute('max', today);
</script>

</body>
</html>