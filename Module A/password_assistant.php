<?php
/**
 * STRYDEX SPORT - AI PASSWORD ASSISTANT
 * Integrated with Stealth Sport Shoes standard header/footer.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Using the same inclusion logic as your Stealth Sport Shoes pages
include_once '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Assistant | Stealth Sport Shoes</title>
    <!-- Standard fonts used in your projects -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-orange: #FF6B00;
            --soft-pink: #FDF2F8; 
            --deep-slate: #0F172A;
        }

        .assistant-main-section {
            background-color: var(--soft-pink);
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .assistant-compact-card {
            width: 100%;
            max-width: 450px;
            background: #FFFFFF;
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            border: 1px solid #F1F5F9;
        }

        .form-label-caps {
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94A3B8;
            margin-bottom: 8px;
            display: block;
        }

        .pwd-input-field {
            height: 52px;
            border-radius: 12px;
            border: 2px solid #F1F5F9;
            background: #F8FAFC;
            font-weight: 700;
            font-family: monospace;
            font-size: 1.1rem;
            width: 100%;
            padding: 0 15px;
            outline: none;
            transition: 0.2s;
        }

        .pwd-input-field:focus {
            border-color: var(--brand-orange);
            background: #fff;
        }

        .strength-shell {
            height: 6px;
            background: #E2E8F0;
            border-radius: 10px;
            margin: 15px 0 5px 0;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.4s ease, background-color 0.4s ease;
        }

        .tag-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 20px 0;
        }

        .status-tag {
            font-size: 0.75rem;
            font-weight: 600;
            color: #94A3B8;
            padding: 8px;
            border-radius: 10px;
            background: #F8FAFC;
            border: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-tag.active {
            color: #10B981;
            background: #F0FDF4;
            border-color: #DCFCE7;
        }

        .btn-orange-full {
            background: var(--brand-orange);
            color: white;
            border: none;
            height: 50px;
            border-radius: 12px;
            font-weight: 800;
            text-transform: uppercase;
            width: 100%;
            transition: 0.3s;
        }

        .btn-orange-full:hover {
            background: #E66000;
            transform: translateY(-2px);
        }

        .btn-outline-gray {
            background: white;
            color: #64748B;
            border: 2px solid #E2E8F0;
            height: 50px;
            border-radius: 12px;
            font-weight: 800;
            text-transform: uppercase;
            width: 100%;
        }

        .btn-outline-gray:hover {
            background: #F8FAFC;
            color: var(--deep-slate);
        }
    </style>
</head>
<body>

<div class="assistant-main-section">
    <div class="assistant-compact-card">
        
        <div class="mb-4">
            <!-- Added the Shield Check icon beside the title -->
            <h4 style="font-weight: 900; color: var(--deep-slate); margin-bottom: 4px;">
                <i class="bi bi-shield-lock-fill me-2" style="color: var(--brand-orange);"></i>Security Assistant
            </h4>
            <p class="text-muted small">Verify or generate your account key.</p>
        </div>

        <div class="mb-3">
            <label class="form-label-caps">Password Input</label>
            <input type="text" id="main-pwd" class="pwd-input-field" placeholder="••••••••" autocomplete="off">
            
            <div class="strength-shell">
                <div id="bar-fill" class="strength-bar-fill"></div>
            </div>
            <div class="d-flex justify-content-between">
                <span id="strength-msg" class="fw-bold" style="font-size: 0.65rem; color: #94A3B8;">STRENGTH: EMPTY</span>
                <span id="strength-num" class="fw-bold" style="font-size: 0.65rem; color: var(--brand-orange);">0%</span>
            </div>
        </div>

        <div class="tag-grid">
            <div id="tag-l" class="status-tag"><i class="bi bi-circle"></i> 8+ Characters</div>
            <div id="tag-c" class="status-tag"><i class="bi bi-circle"></i> Mixed Case</div>
            <div id="tag-n" class="status-tag"><i class="bi bi-circle"></i> Numbers</div>
            <div id="tag-s" class="status-tag"><i class="bi bi-circle"></i> Symbols</div>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-6">
                <button id="gen-trigger" class="btn btn-outline-gray"><i class="bi bi-lightning-charge-fill me-1"></i> Suggest</button>
            </div>
            <div class="col-6">
                <button id="copy-trigger" class="btn btn-orange-full"><i class="bi bi-clipboard-plus me-1"></i> Copy Key</button>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="javascript:history.back()" class="text-muted small text-decoration-none fw-bold">Back to Previous Page</a>
        </div>

    </div>
</div>

<script>
const pwdInput = document.getElementById('main-pwd');
const fillBar = document.getElementById('bar-fill');
const msgText = document.getElementById('strength-msg');
const numText = document.getElementById('strength-num');

const tags = {
    l: document.getElementById('tag-l'),
    c: document.getElementById('tag-c'),
    n: document.getElementById('tag-n'),
    s: document.getElementById('tag-s')
};

function runAudit() {
    const val = pwdInput.value;
    const requirements = {
        l: val.length >= 8,
        c: /[a-z]/.test(val) && /[A-Z]/.test(val),
        n: /[0-9]/.test(val),
        s: /[^A-Za-z0-9]/.test(val)
    };

    Object.keys(requirements).forEach(key => {
        tags[key].className = requirements[key] ? 'status-tag active' : 'status-tag';
        tags[key].querySelector('i').className = requirements[key] ? 'bi bi-check-circle-fill' : 'bi bi-circle';
    });

    let score = Object.values(requirements).filter(Boolean).length * 25;
    fillBar.style.width = score + '%';
    numText.innerText = score + '%';

    if (val.length === 0) {
        fillBar.style.backgroundColor = '#E2E8F0';
        msgText.innerText = "STRENGTH: EMPTY";
    } else if (score < 50) {
        fillBar.style.backgroundColor = '#EF4444';
        msgText.innerText = "STRENGTH: WEAK";
    } else if (score < 100) {
        fillBar.style.backgroundColor = '#F59E0B';
        msgText.innerText = "STRENGTH: GOOD";
    } else {
        fillBar.style.backgroundColor = '#10B981';
        msgText.innerText = "STRENGTH: SECURE";
    }
}

pwdInput.addEventListener('input', runAudit);

document.getElementById('gen-trigger').addEventListener('click', () => {
    const set = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";
    for(let i=0; i<15; i++) password += set.charAt(Math.floor(Math.random() * set.length));
    pwdInput.value = password;
    runAudit();
});

document.getElementById('copy-trigger').addEventListener('click', () => {
    if(!pwdInput.value) return;
    navigator.clipboard.writeText(pwdInput.value);
    const btn = document.getElementById('copy-trigger');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> COPIED!';
    setTimeout(() => btn.innerHTML = original, 1500);
});
</script>

</body>
</html>

<?php 
include_once '../includes/footer.php'; 
?>