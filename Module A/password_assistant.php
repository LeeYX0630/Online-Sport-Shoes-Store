<?php
/**
 * STRYDEX SPORT - AI PASSWORD ASSISTANT (ORANGE & WHITE SNEAKER EDITION)
 * Integrated with Stealth Sport Shoes standard header/footer.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Using the same inclusion logic as your Stealth Sport Shoes pages
include_once '../includes/header.php'; 

$fallback_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Assistant | STRYDEX Sport Shoes</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-orange: #FF5500;
            --brand-orange-light: #FFF2EB;
            --sneaker-white: #FFFFFF;
            --bg-light: #F4F6F9;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        .assistant-main-section {
            background-color: var(--bg-light);
            /* 页面背景加入淡淡的网格线，模拟运动鞋设计的工程图纸/鞋底纹理感 */
            background-image: linear-gradient(var(--border-color) 1px, transparent 1px), linear-gradient(90deg, var(--border-color) 1px, transparent 1px);
            background-size: 40px 40px;
            background-opacity: 0.3;
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* 装饰元素：左侧浮动的大鞋子图标背景 */
        .bg-deco-left {
            position: absolute;
            left: 5%;
            top: 50%;
            transform: translateY(-50%) rotate(-15deg);
            font-size: 16rem;
            color: rgba(255, 85, 0, 0.04);
            pointer-events: none;
            display: none; /* 屏幕太小时隐藏 */
        }

        /* 装饰元素：右侧浮动的大鞋子图标背景 */
        .bg-deco-right {
            position: absolute;
            right: 5%;
            top: 40%;
            transform: translateY(-50%) rotate(20deg);
            font-size: 20rem;
            color: rgba(15, 23, 42, 0.02);
            pointer-events: none;
            display: none;
        }

        @media (min-width: 1200px) {
            .bg-deco-left, .bg-deco-right { display: block; }
        }

        .assistant-compact-card {
            width: 100%;
            max-width: 460px;
            background: var(--sneaker-white);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
            border: 2px solid #EEEEEE;
            position: relative;
            z-index: 10;
        }

        /* 顶部的运动鞋标志微章 */
        .title-badge {
            background: var(--brand-orange-light);
            color: var(--brand-orange);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 85, 0, 0.15);
        }

        .form-label-caps {
            font-weight: 800;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        /* 输入框左侧的小图标 */
        .input-icon-left {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .pwd-input-field {
            height: 56px;
            border-radius: 16px;
            border: 2px solid var(--border-color);
            background: #F8FAFC;
            font-weight: 700;
            font-family: 'Space Grotesk', monospace;
            font-size: 1.2rem;
            letter-spacing: 1px;
            width: 100%;
            padding: 0 20px 0 46px; /* 留出左边图标的位置 */
            color: var(--text-dark);
            outline: none;
            transition: all 0.25s ease;
        }

        .pwd-input-field:focus {
            border-color: var(--brand-orange);
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(255, 85, 0, 0.1);
        }

        /* 强度条 */
        .strength-shell {
            height: 8px;
            background: #E2E8F0;
            border-radius: 10px;
            margin: 18px 0 8px 0;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            border-radius: 10px;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.4s ease;
        }

        /* 条件检查卡片列表 */
        .tag-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 24px 0;
        }

        .status-tag {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            padding: 12px 14px;
            border-radius: 14px;
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .status-tag.active {
            color: #10B981;
            background: #F0FDF4;
            border-color: #BBF7D0;
        }

        /* 橙色主动作按钮 */
        .btn-orange-full {
            background: var(--brand-orange);
            color: white;
            border: none;
            height: 54px;
            border-radius: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(255, 85, 0, 0.25);
        }

        .btn-orange-full:hover {
            background: #E64D00;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 85, 0, 0.35);
            color: #fff;
        }

        /* 白色次级按钮 */
        .btn-outline-white {
            background: #FFFFFF;
            color: var(--text-dark);
            border: 2px solid var(--border-color);
            height: 54px;
            border-radius: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-outline-white:hover {
            background: #F8FAFC;
            border-color: var(--text-dark);
            color: var(--text-dark);
        }

        /* 返回链接 */
        .back-link {
            color: var(--text-muted);
            transition: all 0.2s ease;
        }
        .back-link:hover {
            color: var(--brand-orange) !important;
            transform: translateX(-2px);
        }
    </style>
</head>
<body>

<div class="assistant-main-section">
    
    <i class="bi bi-tag-fill bg-deco-left" style="transform: rotate(-15deg); opacity: 0.4;"></i>
    <div class="bg-deco-left"><i class="bi bi-layers-half"></i></div>
    <div class="bg-deco-right"><i class="bi bi-shield-shaded"></i></div>

    <div class="assistant-compact-card">
        
        <div class="text-center mb-4">
            <div class="title-badge">
                <i class="bi bi-lightning-fill"></i> STRYDEX Sport AI
            </div>
            <h3 style="font-weight: 800; color: var(--text-dark); margin-bottom: 6px; font-family: 'Space Grotesk', sans-serif; letter-spacing: -0.5px;">
                Security Assistant
            </h3>
            <p class="text-muted small">Verify or generate your pro-performance account key.</p>
        </div>

        <div class="mb-3">
            <label class="form-label-caps">
                <i class="bi bi-key-fill" style="color: var(--brand-orange)"></i> Password Input
            </label>
            <div class="input-wrapper">
                <i class="bi bi-lock-fill input-icon-left"></i>
                <input type="text" id="main-pwd" class="pwd-input-field" placeholder="••••••••" autocomplete="off">
            </div>
            
            <div class="strength-shell">
                <div id="bar-fill" class="strength-bar-fill" style="background-color: #E2E8F0;"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span id="strength-msg" class="fw-bold" style="font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px;">STRENGTH: EMPTY</span>
                <span id="strength-num" class="fw-bold" style="font-size: 0.75rem; color: var(--brand-orange);">0%</span>
            </div>
        </div>

        <div class="tag-grid">
            <div id="tag-l" class="status-tag"><i class="bi bi-dash-circle-fill"></i> 8+ Chars</div>
            <div id="tag-c" class="status-tag"><i class="bi bi-dash-circle-fill"></i> Mixed Case</div>
            <div id="tag-n" class="status-tag"><i class="bi bi-dash-circle-fill"></i> Numbers</div>
            <div id="tag-s" class="status-tag"><i class="bi bi-dash-circle-fill"></i> Symbols</div>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-6">
                <button id="gen-trigger" class="btn btn-outline-white"><i class="bi bi-arrow-repeat me-1"></i> Suggest</button>
            </div>
            <div class="col-6">
                <button id="copy-trigger" class="btn btn-orange-full"><i class="bi bi-copy me-1"></i> Copy Key</button>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="<?php echo htmlspecialchars($fallback_url); ?>" class="back-link small text-decoration-none fw-bold d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left-short" style="font-size: 1.1rem;"></i> Back to Previous Page
            </a>
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
        tags[key].querySelector('i').className = requirements[key] ? 'bi bi-check-circle-fill' : 'bi bi-dash-circle-fill';
    });

    let score = Object.values(requirements).filter(Boolean).length * 25;
    fillBar.style.width = score + '%';
    numText.innerText = score + '%';

    if (val.length === 0) {
        fillBar.style.backgroundColor = '#E2E8F0';
        msgText.innerText = "STRENGTH: EMPTY";
        msgText.style.color = 'var(--text-muted)';
    } else if (score < 50) {
        fillBar.style.backgroundColor = '#EF4444'; // 弱 -> 红色
        msgText.innerText = "STRENGTH: WEAK";
        msgText.style.color = '#EF4444';
    } else if (score < 100) {
        fillBar.style.backgroundColor = '#F59E0B'; // 中 -> 黄色
        msgText.innerText = "STRENGTH: GOOD";
        msgText.style.color = '#F59E0B';
    } else {
        fillBar.style.backgroundColor = 'var(--brand-orange)'; // 强 -> 完美融入品牌橙色！
        msgText.innerText = "STRENGTH: SECURE";
        msgText.style.color = 'var(--brand-orange)';
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