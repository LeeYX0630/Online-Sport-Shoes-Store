<?php
// --- PHP Logic for Contact Form ---
$swalCode = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    if (empty($name)) {
        $swalCode = "Swal.fire({ title: 'Opps...', text: 'Please tell me your name', icon: 'warning', confirmButtonColor: '#FF6B00' });";
    } 
    elseif (empty($email)) {
        $swalCode = "Swal.fire({ title: 'Opps...', text: 'I haven\'t receive any email.', icon: 'warning', confirmButtonColor: '#FF6B00' });";
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $swalCode = "Swal.fire({ title: 'Invalid Email', text: 'Please enter a valid email address.', icon: 'warning', confirmButtonColor: '#FF6B00' });";
    }
    elseif (empty($message)) {
        $swalCode = "Swal.fire({ title: 'Empty Message', text: 'Please enter your message', icon: 'warning', confirmButtonColor: '#FF6B00' });";
    } 
    else {
        $swalCode = "Swal.fire({ 
            title: 'Submit Successfully', 
            text: 'Thank you! We will get back to you soon.', 
            icon: 'success', 
            confirmButtonColor: '#28a745' 
        });";
        $_POST = array(); 
        $name = $email = $message = "";
    }
}
include_once '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | STRYDEX Store</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-orange: #FF6B00;
            --brand-dark: #1e293b;
        }

        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            margin: 0; 
            padding: 0; 
            color: #1e293b; 
            background: linear-gradient(rgba(15, 23, 42, 0.82), rgba(15, 23, 42, 0.88)), 
                        url('https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=1600') no-repeat center center fixed;
            background-size: cover;
        }

        .container { max-width: 1100px; margin: 0 auto; padding: 60px 20px; position: relative; z-index: 10; }

        /* --- UI Components --- */
        .section-header {
            text-align: center;
            color: white;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .orange-line {
            width: 60px;
            height: 4px;
            background: var(--brand-orange);
            margin: 0 auto 50px;
            border-radius: 2px;
        }

        /* Glassmorphism Card Style */
        .content-card { 
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(15px);
            padding: 45px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            margin-bottom: 60px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Hero Text */
        .hero-slogan {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 10px;
            text-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        /* Brand Meaning Styles */
        .meaning-box { display: flex; gap: 20px; margin-top: 35px; flex-wrap: wrap; }
        .meaning-part { 
            flex: 1; 
            background: rgba(15, 23, 42, 0.04); 
            padding: 25px; 
            border-radius: 16px; 
            border-top: 4px solid var(--brand-orange);
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .meaning-part i {
            font-size: 1.5rem;
            color: var(--brand-orange);
            margin-bottom: 12px;
        }

        /* Philosophy Grid */
        .philosophy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 30px; }
        .ph-card { 
            background: white; 
            padding: 30px 25px; 
            border-radius: 18px; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.04);
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }
        .ph-card:hover { transform: translateY(-5px); border-bottom: 3px solid var(--brand-orange); }
        .ph-icon-box {
            font-size: 1.8rem;
            color: var(--brand-orange);
            margin-bottom: 15px;
        }
        .ph-number { 
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem; 
            font-weight: 800; 
            color: rgba(15, 23, 42, 0.06); 
        }

        /* Team Cards */
        .team-section { display: flex; justify-content: center; gap: 25px; flex-wrap: wrap; margin-bottom: 60px; }
        .team-card { 
            background: rgba(255, 255, 255, 0.95); 
            border-radius: 20px; 
            width: 280px; 
            text-align: center; 
            padding: 30px 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            transition: 0.3s; 
        }
        .team-card:hover { transform: translateY(-8px); }
        .team-card img { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 4px solid var(--brand-orange); }
        .team-card h5 { margin-bottom: 5px; color: var(--brand-dark); font-weight: 700; }
        .team-card p { color: #64748b; font-size: 0.9rem; margin-bottom: 5px; }
        .team-id { color: var(--brand-orange); font-weight: 800; font-size: 0.85rem; }

        /* Contact Section */
        .contact-container { display: flex; gap: 35px; border-bottom: none; }
        .contact-form { flex: 1.2; }
        
        /* Icon Input Styling */
        .input-group-custom {
            position: relative;
            margin-bottom: 18px;
        }
        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 16px;
            color: #94a3b8;
        }
        .input-group-custom textarea ~ i {
            top: 18px;
        }
        .input-group-custom input, .input-group-custom textarea { 
            width: 100%; padding: 14px 14px 14px 45px; border: 1px solid #e2e8f0; 
            border-radius: 12px; background: #fff; font-family: inherit; font-size: 0.95rem;
            box-sizing: border-box;
        }
        .input-group-custom input:focus, .input-group-custom textarea:focus {
            outline: none;
            border-color: var(--brand-orange);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.15);
        }

        .contact-form button { 
            background-color: var(--brand-dark); color: white; padding: 16px; border: none; 
            border-radius: 12px; cursor: pointer; font-weight: 800; text-transform: uppercase; width: 100%; 
            transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .contact-form button:hover { background-color: var(--brand-orange); transform: scale(1.02); }
        .map-container { flex: 1; border-radius: 20px; overflow: hidden; height: 420px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.6); }

        @media (max-width: 850px) { .contact-container { flex-direction: column; } .hero-slogan { font-size: 2rem; } }
    </style>
</head>
<body>

    <div class="container">
        <header style="margin-bottom: 80px; text-align: center;">
            <h5 style="color: var(--brand-orange); font-weight: 800; letter-spacing: 4px; margin-bottom: 15px;">
                <i class="fa-solid fa-bolt-lightning"></i> STRYDEX
            </h5>
            <h1 class="hero-slogan">"Every Step Shapes Tomorrow."</h1>
            <p style="color: #cbd5e1; max-width: 650px; margin: 20px auto; line-height: 1.8; font-size: 1.05rem;">
                Greatness is not found at the finish line. It is built in every step taken when no one is watching.
            </p>
        </header>

        <section class="content-card">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <h2 class="fw-800" style="color: var(--brand-orange); margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
                        <i class="fa-solid fa-book-open"></i> Our Story
                    </h2>
                    <p style="font-size: 1.1rem; line-height: 1.9; color: #334155; margin-bottom: 20px;">
                        Life is not a sprint; it's a long journey with peaks, valleys, and the quiet persistence that goes unnoticed. 
                        Many believe success comes from a single decisive moment, but what truly defines us are the small, 
                        unseen steps: the first kilometer at dawn, the extra set after training, and the courage to start again after failure.
                    </p>
                    <p style="font-size: 1.1rem; line-height: 1.9; color: #0f172a; font-weight: 700; background: rgba(255, 107, 0, 0.06); padding: 15px 20px; border-radius: 12px; border-left: 5px solid var(--brand-orange);">
                        <i class="fa-solid fa-quote-left" style="color: var(--brand-orange); margin-right: 8px;"></i>
                        STRYDEX was born from this belief. We don't exist for raw talent; we exist for those who choose to keep moving.
                    </p>

                    <div class="meaning-box">
                        <div class="meaning-part">
                            <i class="fa-solid fa-shoe-prints"></i>
                            <h5 class="fw-800" style="margin: 0 0 8px 0; color: #0f172a;">STRYDE (Stride)</h5>
                            <p class="small text-muted mb-0" style="line-height: 1.5;">Represents progress, growth, and the breakthrough in every step you take.</p>
                        </div>
                        <div class="meaning-part">
                            <i class="fa-solid fa-crosshairs"></i>
                            <h5 class="fw-800" style="margin: 0 0 8px 0; color: #0f172a;">X</h5>
                            <p class="small text-muted mb-0" style="line-height: 1.5;">Symbolizes the unknown, infinite possibilities, and the spirit of pushing beyond limits.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <h2 class="section-header">Core Philosophy</h2>
        <div class="orange-line"></div>
        <div class="philosophy-grid" style="margin-bottom: 80px;">
            <div class="ph-card">
                <div class="ph-number">01</div>
                <div class="ph-icon-box"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <h6 class="fw-800" style="font-size: 1.05rem; margin-bottom: 8px; color: #0f172a;">Progress Over Perfection</h6>
                <p class="small text-muted" style="line-height: 1.5; margin: 0;">Growth matters more than being perfect. Every step forward is a victory.</p>
            </div>
            <div class="ph-card">
                <div class="ph-number">02</div>
                <div class="ph-icon-box"><i class="fa-solid fa-cubes"></i></div>
                <h6 class="fw-800" style="font-size: 1.05rem; margin-bottom: 8px; color: #0f172a;">Every Step Matters</h6>
                <p class="small text-muted" style="line-height: 1.5; margin: 0;">Small improvements accumulate into massive changes over time.</p>
            </div>
            <div class="ph-card">
                <div class="ph-number">03</div>
                <div class="ph-icon-box"><i class="fa-solid fa-mountain"></i></div>
                <h6 class="fw-800" style="font-size: 1.05rem; margin-bottom: 8px; color: #0f172a;">Beyond Limits</h6>
                <p class="small text-muted" style="line-height: 1.5; margin: 0;">Limits are not the end; they are the starting point of real growth.</p>
            </div>
            <div class="ph-card">
                <div class="ph-number">04</div>
                <div class="ph-icon-box"><i class="fa-solid fa-dumbbell"></i></div>
                <h6 class="fw-800" style="font-size: 1.05rem; margin-bottom: 8px; color: #0f172a;">Earned, Not Given</h6>
                <p class="small text-muted" style="line-height: 1.5; margin: 0;">Every achievement is built through sweat, discipline, and persistence.</p>
            </div>
        </div>

        <h2 class="section-header">Management Team</h2>
        <div class="orange-line"></div>
        <section class="team-section">
            <div class="team-card">
                <img src="../images/picture/Lee Prof Pic.png" alt="Mr. Lee">
                <h5>Mr. Lee Yun Xiang</h5>
                <p>Founder & CEO</p>
                <span class="team-id">ID: 242DT2420T</span>
            </div>
            <div class="team-card">
                <img src="../images/picture/Cindy Prof Pic.jpeg" alt="Ms. Cindy">
                <h5>Ms. Cindy Tiong Yi Sin</h5>
                <p>Head of Business Development</p>
                <span class="team-id">ID: 242DT242BZ</span>
            </div>
            <div class="team-card">
                <img src="../images/picture/Tung Prof Pic.png" alt="Mr. Tung">
                <h5>Mr. Tung Khai Jun</h5>
                <p>Head of Finance</p>
                <span class="team-id">ID: 242DT242DB</span>
            </div>
        </section>

        <h2 class="section-header">Contact & Location</h2>
        <div class="orange-line"></div>
        <section class="content-card contact-container">
            <div class="contact-form">
                <h3 style="font-weight: 800; margin-top:0; margin-bottom: 25px; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-envelope-open-text" style="color: var(--brand-orange);"></i> Send us a message
                </h3>
                <form action="" method="post">
                    <div class="input-group-custom">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="name" placeholder="Your Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="input-group-custom">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" placeholder="Your Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="input-group-custom">
                        <i class="fa-solid fa-comment-dots"></i>
                        <textarea name="message" rows="5" placeholder="Your Message..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>

                    <button type="submit" name="submit_contact">
                        Send Message <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>

            <div class="map-container">
                <iframe 
                    width="100%" height="100%" frameborder="0" scrolling="no" 
                    marginheight="0" marginwidth="0" 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d31878.68356345992!2d102.25997637841572!3d2.2743909062602755!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1e56b9710cf4b%3A0x66b6b12b75469278!2sAyer%20Keroh%2C%20Melaka!5e0!3m2!1sen!2smy!4v1707123456789!5m2!1sen!2smy">
                </iframe>
            </div>
        </section>
    </div>

    <?php if (!empty($swalCode)) { echo "<script>$swalCode</script>"; } ?>

</body>
<?php include_once '../includes/footer.php'; ?>
</html>