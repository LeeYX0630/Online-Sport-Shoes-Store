<footer class="mt-auto py-5 bg-dark text-white">
    <div class="container-fluid px-5">
        <div class="row gx-5">
            
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="fw-bold text-warning mb-3">
                    <i class="bi bi-house-heart me-2"></i>Teh Tarik No Tarik
                </h5>
                <p class="small text-white-50" style="line-height: 1.8;">
                    More than just a stay, it's an experience. 
                    Located in the heart of Ayer Keroh, Melaka.
                </p>
                <div class="mt-3">
                    <a href="#" class="text-white-50 me-3 hover-white"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="#" class="text-white-50 me-3 hover-white"><i class="bi bi-instagram fs-5"></i></a>
                    <a href="#" class="text-white-50 hover-white"><i class="bi bi-twitter fs-5"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h6 class="fw-bold text-white mb-3">Explore</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?php echo $path_root; ?>index.php" class="text-white-50 text-decoration-none hover-white">Home</a></li>
                    <li class="mb-2"><a href="<?php echo $path_mod_b; ?>room_catalogue.php" class="text-white-50 text-decoration-none hover-white">Rooms & Suites</a></li>
                    <li class="mb-2"><a href="<?php echo $path_mod_b; ?>about_us.php" class="text-white-50 text-decoration-none hover-white">About Us</a></li>
                    <li class="mb-2"><a href="<?php echo $path_mod_b; ?>about_us.php#contact" class="text-white-50 text-decoration-none hover-white">Contact & Location</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h6 class="fw-bold text-white mb-3">Account</h6>
                <ul class="list-unstyled small">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="mb-2"><a href="<?php echo $path_mod_a; ?>user_dashboard.php" class="text-white-50 text-decoration-none hover-white">My Dashboard</a></li>
                        <li class="mb-2"><a href="<?php echo $path_mod_a; ?>logout.php" class="text-white-50 text-decoration-none hover-white">Sign Out</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="<?php echo $path_mod_a; ?>login.php" class="text-white-50 text-decoration-none hover-white">Customer Login</a></li>
                        <li class="mb-2"><a href="<?php echo $path_mod_a; ?>register.php" class="text-white-50 text-decoration-none hover-white">Register</a></li>
                        <li class="mb-2"><a href="<?php echo $path_mod_c; ?>../Module A/admin_login.php" class="text-white-50 text-decoration-none hover-white">Admin Portal</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold text-white mb-3">Visit Us</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><i class="bi bi-geo-alt me-2 text-warning"></i> Ayer Keroh, Melaka</li>
                    <li class="mb-2"><i class="bi bi-envelope me-2 text-warning"></i> support@tehtarik.com</li>
                    <li class="mb-2"><i class="bi bi-telephone me-2 text-warning"></i> +60 12-345 6789</li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                 <h6 class="fw-bold text-white mb-3">Find Us</h6>
                 <div class="map-container rounded overflow-hidden shadow-sm border border-secondary" style="height: 150px;">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d31878.68356345992!2d102.25997637841572!3d2.2743909062602755!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1e56b9710cf4b%3A0x66b6b12b75469278!2sAyer%20Keroh%2C%20Melaka!5e0!3m2!1sen!2smy!4v1707123456789!5m2!1sen!2smy" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
                <small class="text-muted mt-1 d-block text-end fst-italic" style="font-size: 0.75rem;">
                    <i class="bi bi-arrow-up-left me-1"></i> Tap to navigate
                </small>
            </div>

        </div>

        <hr class="border-secondary my-4" style="opacity: 0.3;">

        <div class="row align-items-center small text-white-50">
            <div class="col-md-6 text-center text-md-start">
                © <?php echo date("Y"); ?> Teh Tarik No Tarik Homestay.
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span>TWP4213 Group Project</span>
            </div>
        </div>
    </div>
</footer>

<style>
    .hover-white:hover { color: #fff !important; text-decoration: underline !important; transition: all 0.3s; }
    .map-container { transition: transform 0.3s ease; }
    .map-container:hover { transform: scale(1.05); border-color: #f0ad4e !important; }
    body { display: flex; flex-direction: column; min-height: 100vh; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>