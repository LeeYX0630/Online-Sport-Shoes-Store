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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Online Sport Shoes Store</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --brand-orange: #FF6B00;
            --brand-dark: #333333;
        }

        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            color: #333; 
            background: linear-gradient(rgba(0,0,0,0.75), rgba(0,0,0,0.75)), 
                        url('https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=1600') no-repeat center center fixed;
            background-size: cover;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }

        /* --- IMPROVED READABILITY FOR HEADINGS --- */
        .section-header {
            text-align: center;
            color: white; /* Changed to white for background contrast */
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 10px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.8); /* Added shadow for clarity */
            background: rgba(0,0,0,0.4); /* Added a subtle dark bubble background */
            display: table;
            margin: 0 auto 10px auto;
            padding: 10px 30px;
            border-radius: 50px;
        }
        
        .orange-line {
            width: 80px;
            height: 5px;
            background: var(--brand-orange);
            margin: 0 auto 50px;
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(255, 107, 0, 0.5);
        }

        /* Intro */
        .intro-section { text-align: center; margin-bottom: 60px; line-height: 1.8; font-size: 1.15rem; color: #f0f0f0; }

        /* Content Blocks (White Cards) */
        .content-card { 
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            margin-bottom: 80px;
        }

        .brand-section { display: flex; align-items: center; gap: 40px; border-top: 6px solid var(--brand-orange); }
        .brand-text h3 { 
            margin-top: 0; 
            font-size: 26px; 
            color: var(--brand-dark); 
            border-left: 6px solid var(--brand-orange); 
            padding-left: 20px; 
            margin-bottom: 20px;
        }
        .brand-image img { width: 100%; max-width: 320px; border-radius: 12px; }

        /* Team Cards */
        .team-section { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-bottom: 80px; }
        .team-card { 
            background: white; 
            border-radius: 15px; 
            width: 300px; 
            text-align: center; 
            padding: 35px 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
            transition: 0.3s; 
        }
        .team-card:hover { transform: translateY(-10px); border: 2px solid var(--brand-orange); }
        .team-card img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 20px; border: 4px solid var(--brand-orange); }
        .team-card h5 { margin: 10px 0 5px; color: var(--brand-dark); font-size: 20px; font-weight: 700; }
        .team-card p { color: #666; font-size: 15px; margin: 3px 0; }
        .team-id { color: var(--brand-orange); font-weight: bold; font-size: 14px; margin-top: 10px; }

        /* Contact & Location */
        .contact-container { display: flex; justify-content: space-between; gap: 40px; border-bottom: 6px solid var(--brand-orange); }
        .contact-form { flex: 1; }
        .contact-form input, .contact-form textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
        .contact-form button { background-color: var(--brand-dark); color: white; padding: 15px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-transform: uppercase; width: 100%; }
        .contact-form button:hover { background-color: var(--brand-orange); }
        
        .map-container { flex: 1; height: 400px; border-radius: 15px; overflow: hidden; }

        @media (max-width: 850px) { .brand-section, .contact-container { flex-direction: column; } .brand-image { order: -1; } }
    </style>
</head>
<body>

    <div class="container">
        <section class="intro-section">
            <h1 class="section-header" style="background: none; text-shadow: 3px 3px 15px #000;">Welcome to Online Sport Shoes Store</h1>
            <div class="orange-line" style="margin-bottom: 30px;"></div>
            <p>Your one-stop destination for high-performance athletic footwear in Malacca.</p>
        </section>

        <section class="content-card brand-section">
            <div class="brand-text">
                <h3>Our Passion for Sport</h3>
                <p>
                    Online Sport Shoes Store is a premier online sports destination that combines a passion for fitness 
                    with modern convenience. Our mission is to provide runners and athletes with authentic, 
                    high-quality footwear while ensuring ease of use through our user-friendly ordering system.
                </p>
            </div>
            <div class="brand-image">
                <img src="../uploads/cindy_shoes_logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/320x200/FF6B00/FFFFFF?text=Sport+Shoes+Logo'">
            </div>
        </section> 
            
        <h2 class="section-header">The Management Team</h2>
        <div class="orange-line"></div>
        
        <section class="team-section">
            <div class="team-card">
                <img src="../Module B/picture/Lee Prof Pic.png" alt="Mr. Lee">
                <h5>Mr. Lee Yun Xiang</h5>
                <p>Founder & Chief Executive Officer</p>
                <p class="team-id">ID: 242DT2420T</p>
            </div>

            <div class="team-card">
                <img src="../Module B/picture/Chong Prof Pic.png" alt="Ms. Cindy">
                <h5>Ms. Cindy Tiong Yi Sin</h5>
                <p>Head of Business Development</p>
                <p class="team-id">ID: 242DT242BZ</p>
            </div>

            <div class="team-card">
                <img src="../Module B/picture/Tung Prof Pic.png" alt="Mr. Tung">
                <h5>Mr. Tung Khai Jun</h5>
                <p>Head of Finance</p>
                <p class="team-id">ID: 242DT242DB</p>
            </div>
        </section>

        <h2 class="section-header">Contact & Location</h2>
        <div class="orange-line"></div>
        
        <section class="content-card contact-container">
            <div class="contact-form">
                <h3 style="color:var(--brand-dark); margin-top:0;">Send us a message</h3>
                <form action="" method="post">
                    <input type="text" name="name" placeholder="Your Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    <input type="email" name="email" placeholder="Your Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <textarea name="message" rows="5" placeholder="Message"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    <button type="submit" name="submit_contact">Send Message</button>
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
</html>