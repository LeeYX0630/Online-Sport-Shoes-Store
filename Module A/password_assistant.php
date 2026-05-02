<?php
/**
 * STEALTH SPORT SHOES - AI PASSWORD ASSISTANT
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Password Security Assistant - Stealth Sport Shoes</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --brand-orange: #FF6B00;
            --brand-dark: #0F172A;
            --bg-light: #F8FAFC;
            --card-white: #FFFFFF;
            --border-color: rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .assistant-wrapper {
            width: 100%;
            max-width: 650px;
            margin: auto;
        }

        .assistant-card {
            background: var(--card-white);
            padding: 40px;
            border-radius: 32px;
            border: 1px solid var(--border-color);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.06);
        }

        .brand-logo-area {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brand-dark);
            letter-spacing: -1px;
        }

        .brand-logo-area span {
            color: var(--brand-orange);
        }

        .page-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -1px;
            color: var(--brand-dark);
        }

        .section-tag {
            color: var(--brand-orange);
            font-weight: 800;
            font-size: 0.7rem;
            background: rgba(255, 107, 0, 0.08);
            padding: 6px 14px;
            border-radius: 50px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control-custom {
            height: 56px;
            border-radius: 14px;
            border: 1px solid #E2E8F0;
            background: #F8FAFC;
            font-size: 1.1rem;
            padding: 0 18px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1);
            border-color: var(--brand-orange);
            background: #FFFFFF;
        }

        .strength-meter-bar {
            height: 6px;
            width: 100%;
            background-color: #E2E8F0;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.4s ease, background-color 0.4s ease;
            border-radius: 10px;
        }

        .metric-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .metric-card.valid {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.2);
            color: #065F46;
        }

        .metric-card i {
            font-size: 1.2rem;
            color: #94A3B8;
        }

        .metric-card.valid i {
            color: #10B981;
        }

        .btn-action {
            height: 54px;
            border-radius: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-copy {
            background: var(--brand-dark);
            color: white;
            border: none;
            width: 100%;
        }

        .btn-copy:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }

        .btn-generate {
            background: transparent;
            color: var(--brand-orange);
            border: 2px solid var(--brand-orange);
            width: 100%;
        }

        .btn-generate:hover {
            background: rgba(255, 107, 0, 0.05);
            transform: translateY(-2px);
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
            color: #1e293b;
            transform: translateY(-2px);
        }

        .copy-toast {
            visibility: hidden;
            background-color: #0F172A;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 10px 20px;
            position: fixed;
            z-index: 100;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .copy-toast.show {
            visibility: visible;
            animation: fadein 0.3s, fadeout 0.3s 2.2s;
        }

        @keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
        @keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }
    </style>
</head>
<body>

<div class="container assistant-wrapper">
    <div class="assistant-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="brand-logo-area"><i class="bi bi-lightning-fill"></i> STEALTH <span>SPORT</span></div>
            <span class="section-tag"><i class="bi bi-robot"></i> AI Powered</span>
        </div>

        <div class="mb-4">
            <h1 class="page-title">AI Password Assistant</h1>
            <p class="text-muted mb-0">Evaluate your password security or generate a rock-solid one in a click.</p>
        </div>

        <hr style="border-color: #E2E8F0; margin-bottom: 30px;">

        <div class="mb-4">
            <label class="form-label fw-bold text-dark text-uppercase small" style="letter-spacing: 1px;">Input Password</label>
            <div class="input-group">
                <input type="text" id="ai-password-input" class="form-control form-control-custom" placeholder="Type your password or generate one below" autocomplete="off">
            </div>
            
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="strength-label" class="small fw-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Strength: Not Entered</span>
                    <span id="strength-percent" class="small fw-bold text-muted">0%</span>
                </div>
                <div class="strength-meter-bar">
                    <div id="strength-fill" class="strength-fill"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div id="rule-length" class="metric-card">
                    <i id="icon-length" class="bi bi-circle"></i> 8+ Characters
                </div>
            </div>
            <div class="col-sm-6">
                <div id="rule-case" class="metric-card">
                    <i id="icon-case" class="bi bi-circle"></i> Upper & Lower Case
                </div>
            </div>
            <div class="col-sm-6">
                <div id="rule-number" class="metric-card">
                    <i id="icon-number" class="bi bi-circle"></i> Numbers (0-9)
                </div>
            </div>
            <div class="col-sm-6">
                <div id="rule-symbol" class="metric-card">
                    <i id="icon-symbol" class="bi bi-circle"></i> Symbols (!@#$%^&*)
                </div>
            </div>
        </div>

        <div id="ai-feedback" class="p-3 mb-4 rounded-3 d-none" style="font-size: 0.85rem; border-left: 4px solid var(--brand-orange); background: #FFF7ED;">
            <strong style="color: #9A3412;"><i class="bi bi-shield-shaded"></i> AI Security Advice:</strong>
            <p id="ai-feedback-text" class="mb-0 mt-1 text-muted"></p>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <button type="button" id="btn-generate" class="btn btn-action btn-generate">
                    <i class="bi bi-magic"></i> Suggest
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" id="btn-copy" class="btn btn-action btn-copy">
                    <i class="bi bi-clipboard-check"></i> Copy & Use
                </button>
            </div>
            <div class="col-md-4">
                <button type="button" onclick="window.close();" class="btn btn-action btn-back">
                    <i class="bi bi-arrow-left"></i> Go Back
                </button>
            </div>
        </div>

        <div class="text-center mt-4 pt-2">
            <span class="small text-muted">Go back to your registration tab and paste it once satisfied.</span>
        </div>

    </div>
</div>

<div id="copy-toast" class="copy-toast">Password copied to clipboard!</div>

<script>
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