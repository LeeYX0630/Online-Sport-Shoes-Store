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
            --brand-dark: #0f172a;
            --brand-gradient: linear-gradient(135deg, #FF6B00 0%, #FF8E3C 100%);
        }

        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            margin: 0; 
            padding: 0; 
            color: #1e293b; 
            background: linear-gradient(rgba(15, 23, 42, 0.88), rgba(15, 23, 42, 0.92)), 
                        url('https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=1600') no-repeat center center fixed;
            background-size: cover;
            overflow-x: hidden;
            position: relative;
        }

        /* --- 高級感背景氛围灯光 --- */
        body::before, body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255, 107, 0, 0.15);
            filter: blur(120px);
            z-index: 1;
            pointer-events: none;
        }
        body::before { top: 10%; left: -100px; }
        body::after { top: 60%; right: -100px; background: rgba(56, 189, 248, 0.08); }

        .container { max-width: 1100px; margin: 0 auto; padding: 60px 20px; position: relative; z-index: 10; }

        /* --- 滚动淡入动效类名 --- */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- UI Components --- */
        .section-header {
            text-align: center;
            color: white;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 3px;
        }
        
        .orange-line {
            width: 50px;
            height: 4px;
            background: var(--brand-gradient);
            margin: 0 auto 50px;
            border-radius: 10px;
        }

        /* Glassmorphism Card Style */
        .content-card { 
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(20px);
            padding: 50px;
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.4), inset 0 1px 1px rgba(255,255,255,0.3);
            margin-bottom: 60px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Hero Text & Bounce Animation */
        .hero-slogan {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 15px;
            letter-spacing: -1px;
            white-space: nowrap; /* 修复关键：防止英文字母因为包裹span而自动换行 */
        }

        /* 每一个单词强制不换行，保证整体美观 */
        .word {
            display: inline-block;
            white-space: nowrap;
        }

        /* 文字跳动特效 + 渐变色 */
        .hero-slogan span:not(.word) {
            display: inline-block;
            animation: bounce 2.5s infinite ease-in-out;
            background: linear-gradient(to bottom, #ffffff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); filter: drop-shadow(0 2px 5px rgba(0,0,0,0.3)); }
            50% { 
                transform: translateY(-18px); 
                background: var(--brand-gradient);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                filter: drop-shadow(0 10px 15px rgba(255,107,0,0.4));
            }
        }

        /* Brand Meaning Styles */
        .meaning-box { display: flex; gap: 20px; margin-top: 35px; flex-wrap: wrap; }
        .meaning-part { 
            flex: 1; 
            background: rgba(15, 23, 42, 0.03); 
            padding: 30px; 
            border-radius: 16px; 
            border: 1px solid rgba(15, 23, 42, 0.05);
            border-top: 4px solid var(--brand-orange);
            transition: 0.3s;
        }
        .meaning-part:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.05);
        }
        .meaning-part i {
            font-size: 1.8rem;
            color: var(--brand-orange);
            margin-bottom: 15px;
        }

        /* Philosophy Grid & Hover Line Effect */
        .philosophy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-top: 30px; }
        .ph-card { 
            background: white; 
            padding: 35px 25px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.02);
        }
        /* 卡片底部线条从中间向两边延展的高级动效 */
        .ph-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 4px;
            background: var(--brand-gradient);
            transition: all 0.4s ease;
        }
        .ph-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 25px 50px rgba(255, 107, 0, 0.12);
        }
        .ph-card:hover::after {
            width: 100%;
            left: 0;
        }
        .ph-icon-box {
            font-size: 2rem;
            color: var(--brand-orange);
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .ph-card:hover .ph-icon-box {
            transform: scale(1.1) rotate(5deg);
        }
        .ph-number { 
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.8rem; 
            font-weight: 800; 
            color: rgba(15, 23, 42, 0.04); 
        }

        /* Team Cards & Glow Avatar Effect */
        .team-section { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-bottom: 60px; }
        .team-card { 
            background: white; 
            border-radius: 24px; 
            width: 290px; 
            text-align: center; 
            padding: 35px 25px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.25); 
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); 
            border: 1px solid rgba(255,255,255,0.8);
        }
        .team-card:hover { 
            transform: translateY(-10px) scale(1.02); 
            box-shadow: 0 30px 60px rgba(255, 107, 0, 0.2); 
        }
        .team-card img { 
            width: 120px; 
            height: 120px; 
            border-radius: 50%; 
            object-fit: cover; 
            margin-bottom: 20px; 
            border: 4px solid #fff; 
            box-shadow: 0 0 0 0 rgba(255, 107, 0, 0.4);
            transition: all 0.4s ease;
        }
        /* 悬浮时头像出现橙色呼吸灯发光圈 */
        .team-card:hover img {
            box-shadow: 0 0 20px 8px rgba(255, 107, 0, 0.25);
            transform: rotate(3deg);
        }
        .team-card h5 { margin: 0 0 8px 0; color: var(--brand-dark); font-size: 1.15rem; font-weight: 700; }
        .team-card p { color: #64748b; font-size: 0.9rem; margin: 0 0 12px 0; }
        .team-id { 
            color: var(--brand-orange); 
            font-weight: 800; 
            font-size: 0.8rem; 
            background: rgba(255, 107, 0, 0.08);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        /* Contact Section */
        .contact-container { display: flex; gap: 40px; border-bottom: none; }
        .contact-form { flex: 1.2; }
        
        /* Icon Input Styling with Interactive Icon Animation */
        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group-custom i {
            position: absolute;
            left: 18px;
            top: 16px;
            color: #94a3b8;
            transition: all 0.3s ease;
        }
        .input-group-custom textarea ~ i { top: 18px; }
        .input-group-custom input, .input-group-custom textarea { 
            width: 100%; padding: 15px 15px 15px 50px; border: 1px solid #e2e8f0; 
            border-radius: 14px; background: #f8fafc; font-family: inherit; font-size: 0.95rem;
            box-sizing: border-box; transition: all 0.3s ease;
        }
        .input-group-custom input:focus, .input-group-custom textarea:focus {
            outline: none;
            background: #fff;
            border-color: var(--brand-orange);
            box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.12);
        }
        /* 点击输入框时，Icon 会有个酷炫跳动并变色 */
        .input-group-custom input:focus ~ i, .input-group-custom textarea:focus ~ i {
            color: var(--brand-orange);
            transform: scale(1.2) translateY(-2px);
        }

        .contact-form button { 
            background: var(--brand-dark); color: white; padding: 16px; border: none; 
            border-radius: 14px; cursor: pointer; font-weight: 800; text-transform: uppercase; width: 100%; 
            transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
        }
        .contact-form button:hover { background: var(--brand-gradient); transform: translateY(-2px); box-shadow: 0 15px 30px rgba(255, 107, 0, 0.3); }
        .contact-form button i { transition: 0.3s; }
        .contact-form button:hover i { transform: translateX(5px) scale(1.1); } /* 按钮悬浮时飞机飞出感 */

        .map-container { flex: 1; border-radius: 24px; overflow: hidden; height: 440px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.6); transition: 0.4s; }
        .map-container:hover { box-shadow: 0 25px 50px rgba(0,0,0,0.3); }

        @media (max-width: 850px) { .contact-container { flex-direction: column; } .hero-slogan { font-size: 2.3rem; white-space: normal; } .map-container { height: 300px; } }
    </style>
</head>
<body>

    <div class="container">
        <header style="margin-bottom: 80px; text-align: center;">
            <h5 style="color: var(--brand-orange); font-weight: 800; letter-spacing: 5px; margin-bottom: 20px;">
                <i class="fa-solid fa-bolt-lightning"></i> STRYDEX
            </h5>
            
            <h1 class="hero-slogan">
                <span class="word">
                    <span style="animation-delay: 0.05s;">E</span><span style="animation-delay: 0.10s;">v</span><span style="animation-delay: 0.15s;">e</span><span style="animation-delay: 0.20s;">r</span><span style="animation-delay: 0.25s;">y</span>
                </span>
                &nbsp;
                <span class="word">
                    <span style="animation-delay: 0.30s;">S</span><span style="animation-delay: 0.35s;">t</span><span style="animation-delay: 0.40s;">e</span><span style="animation-delay: 0.45s;">p</span>
                </span>
                &nbsp;
                <span class="word">
                    <span style="animation-delay: 0.50s;">S</span><span style="animation-delay: 0.55s;">h</span><span style="animation-delay: 0.60s;">a</span><span style="animation-delay: 0.65s;">p</span><span style="animation-delay: 0.70s;">e</span><span style="animation-delay: 0.75s;">s</span>
                </span>
                &nbsp;
                <span class="word">
                    <span style="animation-delay: 0.80s;">T</span><span style="animation-delay: 0.85s;">o</span><span style="animation-delay: 0.90s;">m</span><span style="animation-delay: 0.95s;">o</span><span style="animation-delay: 1.00s;">r</span><span style="animation-delay: 1.05s;">r</span><span style="animation-delay: 1.10s;">o</span><span style="animation-delay: 1.15s;">w</span><span style="animation-delay: 1.20s;">.</span>
                </span>
            </h1>

            <p style="color: #cbd5e1; max-width: 650px; margin: 20px auto; line-height: 1.9; font-size: 1.1rem; font-weight: 300;">
                Greatness is not found at the finish line. It is built in every step taken when no one is watching.
            </p>
        </header>

        <section class="content-card reveal">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <h2 class="fw-800" style="color: var(--brand-orange); margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-weight: 800;">
                        <i class="fa-solid fa-book-open"></i> Our Story
                    </h2>
                    <p style="font-size: 1.1rem; line-height: 1.9; color: #334155; margin-bottom: 25px;">
                        Life is not a sprint; it's a long journey with peaks, valleys, and the quiet persistence that goes unnoticed. 
                        Many believe success comes from a single decisive moment, but what truly defines us are the small, 
                        unseen steps: the first kilometer at dawn, the extra set after training, and the courage to start again after failure.
                    </p>
                    <p style="font-size: 1.1rem; line-height: 1.9; color: #0f172a; font-weight: 700; background: rgba(255, 107, 0, 0.05); padding: 20px; border-radius: 14px; border-left: 5px solid var(--brand-orange);">
                        <i class="fa-solid fa-quote-left" style="color: var(--brand-orange); margin-right: 8px;"></i>
                        STRYDEX was born from this belief. We don't exist for raw talent; we exist for those who choose to keep moving.
                    </p>

                    <div class="meaning-box">
                        <div class="meaning-part">
                            <i class="fa-solid fa-shoe-prints"></i>
                            <h5 style="margin: 0 0 10px 0; color: #0f172a; font-weight:700;">STRYDE (Stride)</h5>
                            <p style="line-height: 1.6; color:#64748b; font-size:0.95rem; margin:0;">Represents progress, growth, and the breakthrough in every step you take.</p>
                        </div>
                        <div class="meaning-part">
                            <i class="fa-solid fa-crosshairs"></i>
                            <h5 style="margin: 0 0 10px 0; color: #0f172a; font-weight:700;">X</h5>
                            <p style="line-height: 1.6; color:#64748b; font-size:0.95rem; margin:0;">Symbolizes the unknown, infinite possibilities, and the spirit of pushing beyond limits.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="reveal">
            <h2 class="section-header">Core Philosophy</h2>
            <div class="orange-line"></div>
        </div>
        
        <div class="philosophy-grid reveal" style="margin-bottom: 80px;">
            <div class="ph-card">
                <div class="ph-number">01</div>
                <div class="ph-icon-box"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <h6 style="font-size: 1.1rem; margin:0 0 10px 0; color: #0f172a; font-weight:800;">Progress Over Perfection</h6>
                <p style="line-height: 1.6; margin: 0; color: #64748b; font-size:0.9rem;">Growth matters more than being perfect. Every step forward is a victory.</p>
            </div>
            <div class="ph-card">
                <div class="ph-number">02</div>
                <div class="ph-icon-box"><i class="fa-solid fa-cubes"></i></div>
                <h6 style="font-size: 1.1rem; margin:0 0 10px 0; color: #0f172a; font-weight:800;">Every Step Matters</h6>
                <p style="line-height: 1.6; margin: 0; color: #64748b; font-size:0.9rem;">Small improvements accumulate into massive changes over time.</p>
            </div>
            <div class="ph-card">
                <div class="ph-number">03</div>
                <div class="ph-icon-box"><i class="fa-solid fa-mountain"></i></div>
                <h6 style="font-size: 1.1rem; margin:0 0 10px 0; color: #0f172a; font-weight:800;">Beyond Limits</h6>
                <p style="line-height: 1.6; margin: 0; color: #64748b; font-size:0.9rem;">Limits are not the end; they are the starting point of real growth.</p>
            </div>
            <div class="ph-card">
                <div class="ph-number">04</div>
                <div class="ph-icon-box"><i class="fa-solid fa-dumbbell"></i></div>
                <h6 style="font-size: 1.1rem; margin:0 0 10px 0; color: #0f172a; font-weight:800;">Earned, Not Given</h6>
                <p style="line-height: 1.6; margin: 0; color: #64748b; font-size:0.9rem;">Every achievement is built through sweat, discipline, and persistence.</p>
            </div>
        </div>

        <div class="reveal">
            <h2 class="section-header">Management Team</h2>
            <div class="orange-line"></div>
        </div>
        
        <section class="team-section reveal">
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

        <div class="reveal">
            <h2 class="section-header">Contact & Location</h2>
            <div class="orange-line"></div>
        </div>
        
        <section class="content-card contact-container reveal">
            <div class="contact-form">
                <h3 style="font-weight: 800; margin-top:0; margin-bottom: 25px; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-envelope-open-text" style="color: var(--brand-orange);"></i> Send us a message
                </h3>
                <form action="" method="post">
                    <div class="input-group-custom">
                        <input type="text" name="name" placeholder="Your Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    
                    <div class="input-group-custom">
                        <input type="email" name="email" placeholder="Your Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fa-solid fa-envelope"></i>
                    </div>

                    <div class="input-group-custom">
                        <textarea name="message" rows="5" placeholder="Your Message..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        <i class="fa-solid fa-comment-dots"></i>
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

    <script>
        function revealElements() {
            var reveals = document.querySelectorAll(".reveal");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 100;

                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                }
            }
        }
        window.addEventListener("scroll", revealElements);
        window.addEventListener("DOMContentLoaded", revealElements);
    </script>
</body>
<?php include_once '../includes/footer.php'; ?>
</html>