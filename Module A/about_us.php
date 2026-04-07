<?php
include '../includes/db_connection.php'; 
include '../includes/header.php';

$swalCode = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    // --- Validation ---
    if (empty($name)) {
        $swalCode = "Swal.fire({ title: 'Opps...', text: 'Please tell me your name', icon: 'warning', confirmButtonColor: '#333' });";
    } 
    elseif (empty($email)) {
        $swalCode = "Swal.fire({ title: 'Opps...', text: 'I haven\'t receive any email.', icon: 'warning', confirmButtonColor: '#333' });";
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $swalCode = "Swal.fire({ title: 'Invalid Email', text: 'Please enter a valid email address.', icon: 'warning', confirmButtonColor: '#333' });";
    }
    elseif (empty($message)) {
        $swalCode = "Swal.fire({ title: 'Empty Message', text: 'Please enter your message', icon: 'warning', confirmButtonColor: '#333' });";
    } 
    else {
        // --- Insert into database for contant us---
        $clean_name = $conn->real_escape_string($name);
        $clean_email = $conn->real_escape_string($email);
        $clean_message = $conn->real_escape_string($message);

        $sql = "INSERT INTO contact_us (name, email, message) VALUES ('$clean_name', '$clean_email', '$clean_message')";

        if ($conn->query($sql) === TRUE) {
            // Success Alert
            $swalCode = "Swal.fire({ 
                title: 'Submit Successfully', 
                text: 'Thank you! We will get back to you soon.', 
                icon: 'success', 
                confirmButtonColor: '#28a745' 
            });";

            // Clear form data after successful submission
            $_POST = array(); 
            $name = "";
            $email = "";
            $message = "";

        } else {
            $swalCode = "Swal.fire({ title: 'Error', text: '" . $conn->error . "', icon: 'error', confirmButtonColor: '#333' });";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Homestay Reservation System</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #FFFFFF; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        h1, h2 { text-align: center; color: #333; }
        .intro-section { text-align: center; margin-bottom: 60px; line-height: 1.6; }
        .brand-section { display: flex; align-items: center; gap: 40px; margin-bottom: 60px; }
        .brand-text { flex: 1; text-align: justify; line-height: 1.8; }
        .brand-text h3 { margin-top: 0; font-size: 24px; color: #333; border-left: 5px solid #333; padding-left: 15px; }
        .brand-image { flex: 1; text-align: center; }
        .brand-image img { width: 100%; max-width: 300px; height: auto; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); object-fit: cover; }
        @media (max-width: 768px) { .brand-section { flex-direction: column; } .brand-text h3 { border-left: none; border-bottom: 3px solid #333; padding-bottom: 10px; padding-left: 0; } }
        .team-section { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-bottom: 60px; }
        .team-card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; width: 300px; text-align: center; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .team-card:hover { transform: translateY(-5px); }
        .team-card img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid #333; }
        .team-card h5 { margin: 10px 0 5px; color: #333; font-size: 18px; font-weight: bold; }
        .team-card p { color: #666; font-size: 14px; margin: 5px 0; }
        .contact-container { display: flex; justify-content: space-between; gap: 40px; background-color: #f4f4f4; padding: 40px; border-radius: 10px; }
        .contact-form { flex: 1; }
        .contact-form input, .contact-form textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }
        .contact-form button { background-color: #333; color: white; padding: 10px 20px; border: none; cursor: pointer; transition: background 0.3s; }
        .contact-form button:hover { background-color: #555; }
        .map-container { flex: 1; height: 300px; background-color: #e0e0e0; display: flex; align-items: center; justify-content: center; border-radius: 4px; overflow: hidden; }
    </style>
</head>
<body>

    <div class="container">
        <section class="intro-section">
            <h1>Welcome</h1>
            <p>
                Experience the best comfort and hospitality in Melaka. Our homestay reservation system 
                is designed to provide users with a seamless booking experience. 
                Whether you are looking for a cozy single room or a luxurious suite, 
                we have everything you need for a perfect getaway.
            </p>
        </section>

        <section class="brand-section">
            <div class="brand-text">
                <h3>About Teh Tarik No Tarik</h3>
                <p>
                    Teh Tarik No Tarik is a unique homestay reservation platform that combines the warmth of traditional hospitality 
                    with modern convenience. Our mission is to provide travelers with an authentic experience while ensuring 
                    ease of use through our user-friendly booking system. Whether you're here for leisure or business, 
                    we strive to make your stay memorable and comfortable.
                </p>
            </div>
            <div class="brand-image">
                <img src="../uploads/tehtariklogo.jpg" alt="Logo">
            </div>
        </section> 
            
        <h2>Teh Tarik No Tarik Team</h2>
        
        <section class="team-section">
            <div class="team-card">
                <img src="../Module B/picture/Lee Prof Pic.png" alt="Mr. Lee">
                <h5>Mr. Lee Yun Xiang</h5>
                <p>Founder & Chief Executive Officer</p>
                <p>ID: 242DT2420T</p>
            </div>

            <div class="team-card">
                <img src="../Module B/picture/Chong Prof Pic.png" alt="Mr. Chong">
                <h5>Mr. Chong Yang Yang</h5>
                <p>Head of Business Development</p>
                <p>ID: 242DT2425H</p>
            </div>

            <div class="team-card">
                <img src="../Module B/picture/Tung Prof Pic.png" alt="Mr. Tung">
                <h5>Mr. Tung Khai Jun</h5>
                <p>Head of Finance</p>
                <p>ID: 242DT242DB</p>
            </div>
        </section>

        <h2>Contact & Location</h2>
        <section class="contact-container">
            <div class="contact-form">
                <h3>Send us a message</h3>
                
                <form action="" method="post">
                    <input type="text" name="name" placeholder="Your Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    <input type="email" name="email" placeholder="Your Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <textarea name="message" rows="5" placeholder="Message"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    <button type="submit" name="submit_contact">Send Message</button>
                </form>
            </div>

            <div class="map-container">
                <iframe 
                    width="100%" 
                    height="100%" 
                    frameborder="0" 
                    scrolling="no" 
                    marginheight="0" 
                    marginwidth="0" 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d31878.68356345992!2d102.25997637841572!3d2.2743909062602755!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1e56b9710cf4b%3A0x66b6b12b75469278!2sAyer%20Keroh%2C%20Melaka!5e0!3m2!1sen!2smy!4v1707123456789!5m2!1sen!2smy">
                </iframe>
            </div>
        </section>
    </div>

    <?php if (!empty($swalCode)) { echo "<script>$swalCode</script>"; } ?>

<?php 
include_once '../includes/footer.php'; 
?>

</body>
</html>