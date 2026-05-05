<?php
/**
 * SS SPORT - AI PASSWORD ASSISTANT
 * Fully responsive and optimized for scrolling visibility.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once '../includes/header.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Password Security Assistant - SS Sport</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --brand-orange: #FF6B00;
            --brand-dark: #0F172A;
            --bg-light: #F1F5F9;
            --card-white: #FFFFFF;
            --border-color: #E2E8F0;
        }

        body {
            background-color: var(--bg-light);
            background-image: radial-gradient(circle at 2px 2px, #e2e8f0 1px, transparent 0);
            background-size: 40px 40px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #334155;
            min-height: 100vh;
            display: block; 
            padding: 60px 20px;
            overflow-y: auto;
        }

        .assistant-wrapper {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .assistant-card {
            background: var(--card-white);
            padding: 40px;
            border-radius: 32px;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
        }

        .assistant-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--brand-orange), #ff9d00);
        }

        .brand-logo-area {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--brand-dark);
            letter-spacing: -0.5px;
        }

        .brand-logo-area span {
            color: var(--brand-orange);
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.5px;
            color: var(--brand-dark);
            margin-top: 15px;
        }

        .section-tag {
            color: var(--brand-orange);
            font-weight: 800;
            font-size: 0.75rem;
            background: rgba(255, 107, 0, 0.1);
            padding: 8px 16px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control-custom {
            height: 64px;
            border-radius: 18px;
            border: 2px solid #F1F5F9;
            background: #F8FAFC;
            font-size: 1.15rem;
            padding: 0 20px;
            font-weight: 600;
            color: var(--brand-dark);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: monospace;
        }

        .form-control-custom:focus {
            box-shadow: 0 0 0 5px rgba(255, 107, 0, 0.15);
            border-color: var(--brand-orange);
            background: #FFFFFF;
            outline: none;
        }

        .strength-meter-container {
            background: #F8FAFC;
            padding: 20px;
            border-radius: 20px;
            margin-top: 20px;
            border: 1px solid #F1F5F9;
        }

        .strength-meter-bar {
            height: 8px;
            width: 100%;
            background-color: #E2E8F0;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.4s ease;
            border-radius: 10px;
        }

        .metric-card {
            background: #FFFFFF;
            border: 2px solid #F1F5F9;
            border-radius: 16px;
            padding: 12px 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            color: #64748B;
        }

        .metric-card.valid {
            background: #F0FDF4;
            border-color: #BBF7D0;
            color: #166534;
        }

        .metric-card i {
            font-size: 1.1rem;
            color: #CBD5E1;
        }

        .metric-card.valid i {
            color: #22C55E;
        }

        .btn-action {
            height: 58px;
            border-radius: 18px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

       
        .btn-copy {
            background: var(--brand-orange);
            color: white;
            border: none;
            width: 100%;
        }

        .btn-copy:hover {
            background: #e66000; /* Slightly darker orange on hover */
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(255, 107, 0, 0.3);
        }
        
        .btn-generate {
            background: white;
            color: var(--brand-orange);
            border: 2px solid var(--brand-orange);
            width: 100%;
        }

        .btn-generate:hover {
            background: var(--brand-orange);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(255, 107, 0, 0.2);
        }

        .btn-back {
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #E2E8F0;
            width: 100%;
            text-decoration: none;
        }

        .btn-back:hover {
            background: #E2E8F0;
            color: var(--brand-dark);
        }

        #ai-feedback {
            border: none;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        .copy-toast {
            visibility: hidden;
            background-color: var(--brand-dark);
            color: #fff;
            text-align: center;
            border-radius: 12px;
            padding: 14px 28px;
            position: fixed;
            z-index: 1000;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            font-weight: 700;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .copy-toast.show {
            visibility: visible;
            animation: slideUpFade 0.4s forwards, slideDownFade 0.4s 2.2s forwards;
        }

        @keyframes slideUpFade { from { bottom: 0; opacity: 0; } to { bottom: 40px; opacity: 1; } }
        @keyframes slideDownFade { from { bottom: 40px; opacity: 1; } to { bottom: 0; opacity: 0; } }
        
        hr { opacity: 0.1; }
    </style>
</head>
<body>

<div class="container assistant-wrapper">
    <div class="assistant-card">
        
        <div class="d-flex justify-content-between align-items-center">
            <div class="brand-logo-area"><i class="bi bi-lightning-charge-fill"></i> SS <span>SPORT</span></div>
            <span class="section-tag"><i class="bi bi-cpu"></i> AI Secured</span>
        </div>

        <div class="mt-3 mb-4">
            <h1 class="page-title">Password Assistant</h1>
            <p class="text-muted mt-2 mb-0">Security auditing and high-entropy generation for your account.</p>
        </div>

        <hr class="my-4">

        <div class="mb-4">
            <label class="form-label fw-800 text-dark text-uppercase small mb-2" style="letter-spacing: 1.5px;">Password Audit</label>
            <input type="text" id="ai-password-input" class="form-control form-control-custom" placeholder="Type or generate a secure key" autocomplete="off">
            
            <div class="strength-meter-container">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="strength-label" class="small fw-800 text-muted" style="text-transform: uppercase;">Strength: Not Entered</span>
                    <span id="strength-percent" class="badge rounded-pill bg-white text-dark border fw-800">0%</span>
                </div>
                <div class="strength-meter-bar">
                    <div id="strength-fill" class="strength-fill"></div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-4">
            <div class="col-6">
                <div id="rule-length" class="metric-card">
                    <i id="icon-length" class="bi bi-circle"></i> 8+ Char
                </div>
            </div>
            <div class="col-6">
                <div id="rule-case" class="metric-card">
                    <i id="icon-case" class="bi bi-circle"></i> A-z Mixed
                </div>
            </div>
            <div class="col-6">
                <div id="rule-number" class="metric-card">
                    <i id="icon-number" class="bi bi-circle"></i> Numbers
                </div>
            </div>
            <div class="col-6">
                <div id="rule-symbol" class="metric-card">
                    <i id="icon-symbol" class="bi bi-circle"></i> Symbols
                </div>
            </div>
        </div>

        <div id="ai-feedback" class="p-3 mb-4 rounded-4 d-none">
            <div class="d-flex gap-2 align-items-center mb-1">
                <i class="bi bi-shield-check text-orange fs-5"></i>
                <strong style="color: #9A3412; font-size: 0.9rem;">AI SECURITY ADVICE</strong>
            </div>
            <p id="ai-feedback-text" class="mb-0 text-muted" style="font-size: 0.85rem; line-height: 1.5;"></p>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <button type="button" id="btn-generate" class="btn btn-action btn-generate">
                    <i class="bi bi-stars"></i> Suggest
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" id="btn-copy" class="btn btn-action btn-copy">
                    <i class="bi bi-shield-lock"></i> Copy
                </button>
            </div>
            <div class="col-md-4">
                <!-- Fixed Close Button Logic -->
                <button type="button" id="btn-close-app" class="btn btn-action btn-back">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
        </div>

        <div class="text-center mt-4">
            <p class="small text-muted mb-0 fw-500">Securely copy and paste into the registration form.</p>
        </div>

    </div>
</div>

<div id="copy-toast" class="copy-toast">Security Key Copied to Clipboard!</div>

<script>
// Logic to handle Close vs Back based on how the window was opened
document.getElementById('btn-close-app').addEventListener('click', () => {
    // Check if the page has a referrer (where it came from)
    if (window.opener || window.history.length > 1) {
        // Try to close if it's a popup
        window.close();
        // If it's still open, go back in history
        setTimeout(() => {
            window.history.back();
        }, 100);
    } else {
        // Default to your home page if all else fails
        window.location.href = '../index.php';
    }
});

// --- START OF UNMODIFIED FUNCTIONAL LOGIC ---
const pwdInput = document.getElementById('ai-password-input');
const fill = document.getElementById('strength-fill');
const sLabel = document.getElementById('strength-label');
const sPercent = document.getElementById('strength-percent');

const ruleLength = document.getElementById('rule-length');
const ruleCase = document.getElementById('rule-case');
const ruleNumber = document.getElementById('rule-number');
const ruleSymbol = document.getElementById('rule-symbol');

const aiFeedback = document.getElementById('ai-feedback');
const aiFeedbackText = document.getElementById('ai-feedback-text');

function updateMetric(element, iconId, isValid) {
    const icon = document.getElementById(iconId);
    if (isValid) {
        element.classList.add('valid');
        icon.className = "bi bi-check-circle-fill";
    } else {
        element.classList.remove('valid');
        icon.className = "bi bi-circle";
    }
}

function evaluatePassword() {
    const val = pwdInput.value;
    
    const hasMinLength = val.length >= 8;
    const hasCase = /[a-z]/.test(val) && /[A-Z]/.test(val);
    const hasNum = /[0-9]/.test(val);
    const hasSym = /[^A-Za-z0-9]/.test(val);

    updateMetric(ruleLength, 'icon-length', hasMinLength);
    updateMetric(ruleCase, 'icon-case', hasCase);
    updateMetric(ruleNumber, 'icon-number', hasNum);
    updateMetric(ruleSymbol, 'icon-symbol', hasSym);

    let score = 0;
    if (val.length > 0) {
        if (hasMinLength) score += 25;
        if (hasCase) score += 25;
        if (hasNum) score += 25;
        if (hasSym) score += 25;
        
        if (val.length >= 12 && score >= 50) {
            score = Math.min(100, score + 15);
        }
    }

    fill.style.width = score + '%';
    sPercent.innerText = score + '%';

    if (val.length === 0) {
        sLabel.innerText = "Strength: Not Entered";
        sLabel.style.color = "#94A3B8";
        fill.style.backgroundColor = "#E2E8F0";
        aiFeedback.classList.add('d-none');
    } else if (score < 40) {
        sLabel.innerText = "Strength: Weak 🔴";
        sLabel.style.color = "#EF4444";
        fill.style.backgroundColor = "#EF4444";
        aiFeedback.classList.remove('d-none');
        aiFeedbackText.innerText = "Your password is too short or lacks complexity. It could be cracked easily by automated tools.";
    } else if (score < 75) {
        sLabel.innerText = "Strength: Moderate 🟡";
        sLabel.style.color = "#F59E0B";
        fill.style.backgroundColor = "#F59E0B";
        aiFeedback.classList.remove('d-none');
        aiFeedbackText.innerText = "Good effort! But combining both uppercase, lowercase, numbers, and symbols guarantees defense against dictionary attacks.";
    } else {
        sLabel.innerText = "Strength: AI Verified Secure 🟢";
        sLabel.style.color = "#10B981";
        fill.style.backgroundColor = "#10B981";
        aiFeedback.classList.remove('d-none');
        aiFeedbackText.innerText = "Excellent password. This length and complexity are cryptographically solid and extremely safe.";
    }
}

pwdInput.addEventListener('input', evaluatePassword);

document.getElementById('btn-generate').addEventListener('click', () => {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~";
    let generated = "";
    
    generated += "ABCDEFGHIJKLMNOPQRSTUVWXYZ"[Math.floor(Math.random() * 26)];
    generated += "abcdefghijklmnopqrstuvwxyz"[Math.floor(Math.random() * 26)];
    generated += "0123456789"[Math.floor(Math.random() * 10)];
    generated += "!@#$%^&*()"[Math.floor(Math.random() * 10)];

    const totalLength = Math.floor(Math.random() * 3) + 12;
    for (let i = generated.length; i < totalLength; i++) {
        generated += chars.charAt(Math.floor(Math.random() * chars.length));
    }

    pwdInput.value = generated.split('').sort(() => 0.5 - Math.random()).join('');
    evaluatePassword();
});

document.getElementById('btn-copy').addEventListener('click', () => {
    if (!pwdInput.value) return;
    
    navigator.clipboard.writeText(pwdInput.value).then(() => {
        const toast = document.getElementById('copy-toast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    });
});
</script>
</body>
</html>
<?php include_once '../includes/footer.php'; ?>