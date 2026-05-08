<footer class="mt-auto py-5 bg-dark text-white">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <div class="container-fluid px-5">
        <div class="row gx-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="fw-bold text-warning mb-3">
                    <i class="bi bi-lightning-charge me-2"></i>Sport Shoes Store
                </h5>
                <p class="small text-white-50" style="line-height: 1.8;">
                    Empowering your every step with premium quality and performance. 
                    Your ultimate destination for professional sports footwear.
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
                    <li class="mb-2"><a href="<?php echo isset($path_root) ? $path_root : ''; ?>index.php" class="text-white-50 text-decoration-none hover-white">Home</a></li>
                    <li class="mb-2"><a href="<?php echo isset($path_mod_b) ? $path_mod_b : 'Module B/'; ?>catalogue.php" class="text-white-50 text-decoration-none hover-white">Shoe Catalogue</a></li>
                    <li class="mb-2"><a href="<?php echo isset($path_mod_a) ? $path_mod_a : 'Module A/'; ?>about_us.php" class="text-white-50 text-decoration-none hover-white">About Us</a></li>
                    <li class="mb-2"><a href="<?php echo isset($path_mod_a) ? $path_mod_a : 'Module A/'; ?>about_us.php#contact" class="text-white-50 text-decoration-none hover-white">Support Center</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h6 class="fw-bold text-white mb-3">Account</h6>
                <ul class="list-unstyled small">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="mb-2"><a href="<?php echo isset($path_mod_a) ? $path_mod_a : 'Module A/'; ?>user_dashboard.php" class="text-white-50 text-decoration-none hover-white">My Profile</a></li>
                        <li class="mb-2"><a href="<?php echo isset($path_mod_a) ? $path_mod_a : 'Module A/'; ?>logout.php" class="text-white-50 text-decoration-none hover-white">Sign Out</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="<?php echo isset($path_mod_a) ? $path_mod_a : 'Module A/'; ?>login.php" class="text-white-50 text-decoration-none hover-white">Customer Login</a></li>
                        <li class="mb-2"><a href="<?php echo isset($path_mod_a) ? $path_mod_a : 'Module A/'; ?>register.php" class="text-white-50 text-decoration-none hover-white">Register</a></li>
                        <li class="mb-2"><a href="<?php echo isset($path_mod_c) ? $path_mod_c : 'Module C/'; ?>admin_login.php" class="text-white-50 text-decoration-none hover-white">Admin Portal</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold text-white mb-3">Contact Us</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><i class="bi bi-geo-alt me-2 text-warning"></i> Multimedia University, Melaka</li>
                    <li class="mb-2"><i class="bi bi-envelope me-2 text-warning"></i> sportshoes.system@gmail.com</li>
                    <li class="mb-2"><i class="bi bi-telephone me-2 text-warning"></i> +60 12-345 6789</li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                 <h6 class="fw-bold text-white mb-3">Store Location</h6>
                 <div class="map-container rounded overflow-hidden shadow-sm border border-secondary" style="height: 150px;">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3986.8992683050414!2d102.2736803147551!3d2.24944449836054!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1f05d95454545%3A0x545454545454545!2sMultimedia%20University!5e0!3m2!1sen!2smy!4v1620000000000" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4" style="opacity: 0.3;">

        <div class="row align-items-center small text-white-50">
            <div class="col-md-6 text-center text-md-start">
                © <?php echo date("Y"); ?> Online Sport Shoes Store. All Rights Reserved.
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span>TFP4224 Final Year Project</span>
            </div>
        </div>
    </div>
</footer>

<div class="chatbot-toggler" onclick="toggleChatbot()">
    <i class="bi bi-chat-dots-fill"></i>
</div>

<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-header">
        <span><i class="bi bi-robot"></i> AI Store Assistant</span>
        <div>
            <i class="bi bi-trash3 me-3" onclick="clearChat()" style="cursor:pointer;" title="Clear Chat History"></i>
            <i class="bi bi-x-lg" onclick="toggleChatbot()" style="cursor:pointer;" title="Close"></i>
        </div>
    </div>
    <div class="chatbot-body" id="chatBody"></div>
    <div class="chatbot-footer">
        <input type="text" id="chatInput" placeholder="Type a message..." onkeypress="handleEnter(event)">
        <button onclick="sendMessage()"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<style>
    /* 你原本的 CSS 样式保持不变 */
    .hover-white:hover { color: #fff !important; text-decoration: underline !important; transition: all 0.3s; }
    .map-container { transition: transform 0.3s ease; }
    .map-container:hover { transform: scale(1.05); border-color: #FF6B00 !important; }
    body { display: flex; flex-direction: column; min-height: 100vh; }
    .chatbot-toggler { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background-color: #FF6B00; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 26px; cursor: pointer; box-shadow: 0 5px 15px rgba(255,107,0,0.4); z-index: 9999; transition: 0.3s; }
    .chatbot-toggler:hover { transform: scale(1.1); }
    .chatbot-window { position: fixed; bottom: 100px; right: 30px; width: 350px; background: #fff; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: none; flex-direction: column; overflow: hidden; z-index: 9999; border: 1px solid #eee; }
    .chatbot-header { background: #333333; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
    .chatbot-body { padding: 15px; height: 320px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; background: #f4f6f9; font-family: 'Segoe UI', sans-serif;}
    .chatbot-footer { display: flex; padding: 12px; border-top: 1px solid #eee; background: #fff;}
    .chatbot-footer input { flex: 1; border: none; outline: none; padding: 10px 15px; background: #f4f4f4; border-radius: 20px; margin-right: 10px; color: #333; font-size: 14px;}
    .chatbot-footer button { background: #FF6B00; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;}
    .message { 
        max-width: 85%; 
        padding: 12px 16px; 
        border-radius: 15px; 
        font-size: 14px; 
        line-height: 1.6; /* 稍微增加行高，提升阅读舒适度 */
        word-wrap: break-word; 
        white-space: pre-wrap; /* 关键：保留 AI 返回的换行符 */
    }
    .bot-message { background: #e9ecef; color: #333; align-self: flex-start; border-bottom-left-radius: 4px; }
    .user-message { background: #FF6B00; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
</style>

<script>
    const chatBody = document.getElementById('chatBody');
    const defaultWelcomeMsg = `<div class="message bot-message">Hi there! 👋 I'm your AI Assistant. How can I help you find the perfect pair of sport shoes today?</div>`;

    window.addEventListener('DOMContentLoaded', () => {
        const savedHistory = sessionStorage.getItem('geminiChatHistory');
        chatBody.innerHTML = savedHistory ? savedHistory : defaultWelcomeMsg;
        chatBody.scrollTop = chatBody.scrollHeight;
    });

    function saveChatHistory() {
        sessionStorage.setItem('geminiChatHistory', chatBody.innerHTML);
    }

    // --- 【统一后的 SweetAlert 清空逻辑】 ---
    function clearChat() {
        Swal.fire({
            title: 'Clear Chat History?',
            text: "All your conversations with the AI will be permanently deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF6B00',
            cancelButtonColor: '#333',
            confirmButtonText: 'Yes, clear it!'
        }).then((result) => {
            if (result.isConfirmed) {
                sessionStorage.removeItem('geminiChatHistory');
                chatBody.innerHTML = defaultWelcomeMsg;
                Swal.fire({
                    title: 'Cleared!',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }

    function toggleChatbot() {
        const chatWindow = document.getElementById('chatbotWindow');
        chatWindow.style.display = chatWindow.style.display === 'flex' ? 'none' : 'flex';
    }

    function handleEnter(e) { if (e.key === 'Enter') sendMessage(); }

    async function sendMessage() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg) return;

        chatBody.innerHTML += `<div class="message user-message">${msg}</div>`;
        input.value = '';
        chatBody.scrollTop = chatBody.scrollHeight;
        saveChatHistory();

        const loadingId = 'loading-' + Date.now();
        chatBody.innerHTML += `<div id="${loadingId}" class="message bot-message"><i class="bi bi-three-dots"></i> AI is thinking...</div>`;
        chatBody.scrollTop = chatBody.scrollHeight;

        try {
            const response = await fetch(apiPath, { ... });
            const data = await response.json();
            
            let reply = data.reply || "I'm sorry, I encountered an error.";

            reply = reply.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
            reply = reply.replace(/^\* /gm, '<br>• ');

            const sizeTagRegex = /\[RECOMMENDED_SIZE:(\d+)\]/g;
            reply = reply.replace(sizeTagRegex, (match, size) => {
                return `<br><button class="apply-size-btn" onclick="autoSelectSize('${size}')">Apply UK ${size} to order</button>`;
            });

            document.getElementById(loadingId).innerHTML = reply;
        } catch (error) {
            // --- 【统一后的 SweetAlert 错误提示】 ---
            document.getElementById(loadingId).remove();
            Swal.fire({
                icon: 'error',
                title: 'Connection Failed',
                text: 'Could not connect to the AI assistant. Please check your network.',
                confirmButtonColor: '#FF6B00'
            });
        }
        chatBody.scrollTop = chatBody.scrollHeight;
        saveChatHistory();
    }
</script>