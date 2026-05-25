<?php
include '../includes/db_connection.php'; 
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brands | Online Sport Shoes Store</title>
    <style>
    /* ===== RESET & BASE ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --orange:       #E8500A;
        --orange-light: #FF6B1A;
        --dark:         #0F0F0F;
        --dark2:        #1A1A1A;
        --dark3:        #242424;
        --mid:          #3A3A3A;
        --light:        #F5F3EF;
        --muted:        #888888;
    }
    html { scroll-behavior: smooth; }
    body {
        background: var(--light);
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        color: var(--dark);
    }

    /* ===== NAVBAR ===== */
    .ss-nav {
        background: var(--dark);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 48px;
        height: 68px;
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .ss-nav-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
    .ss-nav-logo-icon {
        width: 38px; height: 38px;
        background: var(--orange);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .ss-nav-logo-icon img { height: 26px; width: auto; object-fit: contain; filter: brightness(0) invert(1); }
    .ss-nav-brand { color: #fff; font-weight: 800; font-size: 16px; letter-spacing: .3px; white-space: nowrap; }
    .ss-nav-brand span { color: var(--orange); }
    .ss-nav-links { display: flex; gap: 36px; list-style: none; }
    .ss-nav-links a {
        color: rgba(255,255,255,.6);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        letter-spacing: .3px;
        transition: color .2s;
        padding-bottom: 4px;
    }
    .ss-nav-links a:hover, .ss-nav-links a.active { color: #fff; }
    .ss-nav-links a.active { border-bottom: 2px solid var(--orange); }
    .ss-nav-right { display: flex; align-items: center; gap: 12px; }
    .ss-search-wrap { position: relative; }
    .ss-search {
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 22px;
        padding: 8px 18px 8px 38px;
        color: #fff;
        font-size: 13px;
        width: 220px;
        outline: none;
        transition: background .2s, border-color .2s;
    }
    .ss-search:focus { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.2); }
    .ss-search::placeholder { color: rgba(255,255,255,.35); }
    .ss-search-icon {
        position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
        color: rgba(255,255,255,.4); font-size: 14px; pointer-events: none;
    }
    .ss-nav-icon {
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 10px;
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        color: rgba(255,255,255,.7);
        font-size: 16px;
        cursor: pointer;
        transition: background .2s;
        text-decoration: none;
        position: relative;
    }
    .ss-nav-icon:hover { background: rgba(255,255,255,.15); color: #fff; }
    .ss-nav-badge {
        position: absolute; top: -5px; right: -5px;
        background: var(--orange); color: #fff;
        font-size: 10px; font-weight: 700;
        width: 18px; height: 18px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid var(--dark);
    }
    .ss-btn-primary {
        background: var(--orange); color: #fff; border: none;
        border-radius: 10px; padding: 9px 22px;
        font-size: 14px; font-weight: 700; cursor: pointer;
        transition: background .2s, transform .15s;
        text-decoration: none; display: inline-flex; align-items: center; gap: 7px;
    }
    .ss-btn-primary:hover { background: var(--orange-light); transform: translateY(-1px); color: #fff; }
    .ss-btn-ghost {
        background: transparent; color: rgba(255,255,255,.7);
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 10px; padding: 9px 20px;
        font-size: 14px; font-weight: 600; cursor: pointer;
        transition: all .2s; text-decoration: none;
    }
    .ss-btn-ghost:hover { background: rgba(255,255,255,.06); color: #fff; border-color: rgba(255,255,255,.4); }

    /* ===== HERO ===== */
    .ss-hero {
        min-height: 620px;
        display: grid;
        grid-template-columns: 1fr;
        align-items: center;
        padding: 80px 60px 60px;
        position: relative;
        overflow: hidden;
    }
    /* Video background */
    .ss-video-bg {
        position: absolute;
        inset: 0; width: 100%; height: 100%;
        object-fit: cover;
        opacity: 0;
        transition: opacity 1.5s ease-in-out;
        z-index: 1;

    }
    .ss-video-bg.active { opacity: 1; }

    .ss-hero-overlay {
        position: absolute; inset: 0;
        background: rgba(15,15,15,.85);
        z-index: 2;
    }

    .ss-hero-bg-glow {
        position: absolute;
        width: 600px; height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(232,80,10,.18) 0%, transparent 65%);
        right: -80px; top: 50%; transform: translateY(-50%);
        pointer-events: none;
        z-index: 2;
    }
    .ss-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(232,80,10,.14);
        border: 1px solid rgba(232,80,10,.28);
        border-radius: 22px; padding: 6px 16px;
        margin-bottom: 28px;
        z-index: 2; position: relative;
    }
    .ss-eyebrow-dot {
        width: 7px; height: 7px; border-radius: 50%; background: var(--orange);
        animation: ss-pulse 2s ease-in-out infinite;
        z-index: 2; position: relative;
    }
    @keyframes ss-pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
    .ss-eyebrow span { color: var(--orange); font-size: 11px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; z-index: 2; position: relative; }
    .ss-hero h1 {
        font-size: 58px; font-weight: 900; color: #fff;
        line-height: 1.07; letter-spacing: -2px; margin-bottom: 22px; 
    }
    .ss-hero h1 em { text-shadow: 0 2px 8px rgba(0,0,0,.6); color: var(--orange); font-style: normal; z-index: 2; position: relative; }
    .ss-hero h1 br { z-index: 2; position: relative; }
    .ss-hero-sub {
        text-shadow: 0 2px 8px rgba(0,0,0,.6);
        color: rgba(255,255,255,.48); font-size: 16px; line-height: 1.75;
        max-width: 430px; margin-bottom: 38px;
        z-index: 2; position: relative;
    }
    .ss-hero-cta { text-shadow: 0 2px 8px rgba(0,0,0,.6); display: flex; align-items: center; gap: 14px; flex-wrap: wrap; z-index: 2; position: relative; }
    .ss-hero-stats {
        display: flex; gap: 36px; margin-top: 44px;
        padding-top: 36px; border-top: 1px solid rgba(255,255,255,.08);
    }
    .ss-stat-num { color: #fff; font-size: 28px; font-weight: 900; letter-spacing: -1px; }
    .ss-stat-label { color: rgba(255,255,255,.38); font-size: 12px; font-weight: 500; letter-spacing: .5px; margin-top: 2px; z-index: 2; position: relative; }

    /* Hero visual */
    .ss-hero-visual {
        position: relative; display: flex;
        justify-content: center; align-items: center; height: 460px;
    }
    .ss-hero-ring {
        width: 380px; height: 380px; border-radius: 50%;
        background: rgba(255,255,255,.025);
        border: 1px solid rgba(255,255,255,.07);
        display: flex; align-items: center; justify-content: center;
        animation: ss-spin 30s linear infinite;
    }
    @keyframes ss-spin { to { transform: rotate(360deg); } }
    .ss-hero-inner {
        width: 260px; height: 260px; border-radius: 50%;
        background: linear-gradient(135deg, rgba(232,80,10,.14), rgba(232,80,10,.04));
        border: 1px solid rgba(232,80,10,.18);
        display: flex; align-items: center; justify-content: center;
        animation: ss-spin 30s linear infinite reverse;
    }
    .ss-hero-shoe { font-size: 130px; line-height: 1; filter: drop-shadow(0 24px 48px rgba(0,0,0,.6)); }
    .ss-float-badge {
        position: absolute; background: #fff; border-radius: 14px;
        padding: 11px 16px; display: flex; align-items: center; gap: 10px;
        box-shadow: 0 8px 32px rgba(0,0,0,.18);
        animation: ss-float 4s ease-in-out infinite;
        z-index: 2; position: relative;
    }
    .ss-float-badge.b1 { top: 30px; right: 10px; animation-delay: 0s; }
    .ss-float-badge.b2 { bottom: 70px; left: 5px; animation-delay: 2s; }
    @keyframes ss-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    .ss-badge-icon {
        width: 36px; height: 36px; border-radius: 9px;
        background: var(--orange); display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .ss-badge-val { font-size: 14px; color: var(--dark); font-weight: 800; }
    .ss-badge-label { font-size: 11px; color: #999; font-weight: 500; margin-top: 1px; }

    /* ===== BRANDS ===== */
    .ss-brands {
        background: #fff; padding: 44px 60px;
        border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .ss-brands-label {
        text-align: center; color: var(--muted);
        font-size: 11px; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; margin-bottom: 28px;
    }
    .ss-brands-row {
        display: flex; justify-content: center; align-items: center;
        gap: 52px; flex-wrap: wrap;
    }
    .ss-brand {
        font-size: 18px; font-weight: 900; letter-spacing: -.5px;
        color: #ccc; transition: color .25s; cursor: default; user-select: none;
        text-decoration: none;
    }
    .ss-brand:hover { color: var(--dark); }

    /* ===== SECTION COMMONS ===== */
    .ss-section { padding: 80px 60px; }
    .ss-section-label {
        display: inline-flex; align-items: center; gap: 10px; margin-bottom: 10px;
    }
    .ss-section-line { width: 28px; height: 3px; background: var(--orange); border-radius: 2px; }
    .ss-section-tag { color: var(--orange); font-size: 11px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; }
    .ss-section-title { font-size: 36px; font-weight: 900; color: var(--dark); letter-spacing: -1.2px; margin-bottom: 6px; }
    .ss-section-sub { color: var(--muted); font-size: 15px; }
    .ss-section-head { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 44px; }
    .ss-view-all {
        display: inline-flex; align-items: center; gap: 6px;
        color: var(--orange); font-size: 14px; font-weight: 600;
        text-decoration: none; cursor: pointer; transition: gap .2s;
    }
    .ss-view-all:hover { gap: 10px; color: var(--orange); }

    /* ===== PRODUCT GRID ===== */
    .ss-product-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
    }
    .ss-product-card {
        background: #fff; border-radius: 20px; overflow: hidden;
        border: 1px solid rgba(0,0,0,.07);
        transition: transform .28s cubic-bezier(.4,0,.2,1), box-shadow .28s;
        cursor: pointer; position: relative;
    }
    .ss-product-card:hover { transform: translateY(-7px); box-shadow: 0 24px 56px rgba(0,0,0,.13); }
    .ss-product-img-wrap {
        height: 230px; display: flex; align-items: center; justify-content: center;
        position: relative; overflow: hidden;
    }
    .ss-product-emoji { font-size: 100px; transition: transform .35s; }
    .ss-product-card:hover .ss-product-emoji { transform: scale(1.12) rotate(-6deg); }
    .ss-product-img {
        width: 100%; height: 100%; object-fit: cover;
        transition: transform .35s;
    }
    .ss-product-card:hover .ss-product-img { transform: scale(1.06); }
    .ss-product-badge {
        position: absolute; top: 14px; left: 14px;
        font-size: 10px; font-weight: 800; letter-spacing: 1px;
        padding: 4px 12px; border-radius: 20px; text-transform: uppercase;
    }
    .ss-badge-new { background: var(--dark); color: #fff; }
    .ss-badge-sale { background: var(--orange); color: #fff; }
    .ss-wish-btn {
        position: absolute; top: 14px; right: 14px;
        width: 34px; height: 34px; border-radius: 50%;
        background: #fff; border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 10px rgba(0,0,0,.12); transition: transform .2s;
    }
    .ss-wish-btn:hover { transform: scale(1.1); }
    .ss-wish-btn i { color: #ccc; font-size: 15px; }
    .ss-product-info { padding: 20px 22px; }
    .ss-product-brand { font-size: 10px; font-weight: 800; letter-spacing: 1.8px; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .ss-product-name { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ss-product-rating { display: flex; align-items: center; gap: 5px; margin-bottom: 14px; }
    .ss-stars { color: #F5A623; font-size: 12px; letter-spacing: 1px; }
    .ss-rating-num { font-size: 12px; color: var(--muted); font-weight: 500; }
    .ss-product-footer { display: flex; align-items: center; justify-content: space-between; }
    .ss-price { font-size: 19px; font-weight: 900; color: var(--dark); }
    .ss-price-old { font-size: 13px; color: #bbb; text-decoration: line-through; margin-right: 6px; }
    .ss-price-sale { font-size: 19px; font-weight: 900; color: var(--orange); }
    .ss-add-btn {
        width: 38px; height: 38px; border-radius: 11px;
        background: var(--dark); border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s, transform .15s;
    }
    .ss-add-btn:hover { background: var(--orange); transform: scale(1.08); }
    .ss-add-btn i { color: #fff; font-size: 18px; }

    /* ===== PROMO BANNER ===== */
    .ss-promo {
        margin: 0 60px 80px;
        background: var(--dark);
        border-radius: 28px;
        padding: 60px 64px;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 48px;
        overflow: hidden;
        position: relative;
    }
    .ss-promo-glow {
        position: absolute; right: -80px; top: -80px;
        width: 360px; height: 360px; border-radius: 50%;
        background: radial-gradient(circle, rgba(232,80,10,.2) 0%, transparent 65%);
        pointer-events: none;
    }
    .ss-promo-grid-overlay {
        position: absolute; inset: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
        background-size: 50px 50px;
    }
    .ss-promo-tag {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(232,80,10,.2); border-radius: 20px;
        padding: 5px 14px; margin-bottom: 18px;
    }
    .ss-promo-tag span { color: var(--orange); font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .ss-promo-title { font-size: 44px; font-weight: 900; color: #fff; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 12px; }
    .ss-promo-sub { color: rgba(255,255,255,.45); font-size: 15px; max-width: 400px; line-height: 1.7; }
    .ss-promo-code-box {
        background: rgba(255,255,255,.06);
        border: 1px dashed rgba(255,255,255,.2);
        border-radius: 16px; padding: 28px 36px; text-align: center;
        flex-shrink: 0; position: relative;
    }
    .ss-code-label { color: rgba(255,255,255,.4); font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 10px; }
    .ss-code-value { font-size: 32px; font-weight: 900; color: var(--orange); letter-spacing: 4px; margin-bottom: 14px; }
    .ss-copy-btn {
        background: var(--orange); color: #fff; border: none;
        border-radius: 10px; padding: 11px 24px;
        font-size: 13px; font-weight: 700; cursor: pointer;
        transition: background .2s; width: 100%;
    }
    .ss-copy-btn:hover { background: var(--orange-light); }

    /* ===== FEATURES ===== */
    .ss-features-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; }
    .ss-feature-card {
        background: #fff; border-radius: 20px;
        padding: 32px 28px; border: 1px solid rgba(0,0,0,.07);
        transition: transform .25s, box-shadow .25s;
    }
    .ss-feature-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,.08); }
    .ss-feature-icon {
        width: 54px; height: 54px; border-radius: 14px;
        background: #FEF0E8; display: flex; align-items: center; justify-content: center;
        margin-bottom: 20px; font-size: 26px;
    }
    .ss-feature-title { font-size: 16px; font-weight: 800; color: var(--dark); margin-bottom: 9px; }
    .ss-feature-desc { font-size: 14px; color: var(--muted); line-height: 1.75; }

    /* ===== FOOTER ===== */
    .ss-footer { background: var(--dark); color: #fff; padding: 68px 60px 32px; }
    .ss-footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr; gap: 52px; margin-bottom: 52px; }
    .ss-footer-brand { font-size: 22px; font-weight: 900; letter-spacing: -.5px; margin-bottom: 12px; }
    .ss-footer-brand span { color: var(--orange); }
    .ss-footer-desc { color: rgba(255,255,255,.38); font-size: 14px; line-height: 1.85; margin-bottom: 22px; max-width: 280px; }
    .ss-footer-social { display: flex; gap: 10px; }
    .ss-social-btn {
        width: 38px; height: 38px; border-radius: 10px;
        background: rgba(255,255,255,.07);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: background .2s; text-decoration: none; color: rgba(255,255,255,.6); font-size: 16px;
    }
    .ss-social-btn:hover { background: var(--orange); color: #fff; }
    .ss-footer-col h4 { font-size: 12px; font-weight: 700; letter-spacing: .8px; margin-bottom: 20px; color: rgba(255,255,255,.55); text-transform: uppercase; }
    .ss-footer-col a { display: block; color: rgba(255,255,255,.38); font-size: 14px; text-decoration: none; margin-bottom: 11px; transition: color .2s; }
    .ss-footer-col a:hover { color: #fff; }
    .ss-footer-bottom {
        border-top: 1px solid rgba(255,255,255,.07); padding-top: 26px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .ss-footer-bottom p { color: rgba(255,255,255,.28); font-size: 13px; }
    .ss-footer-badge {
        background: rgba(255,255,255,.06); border-radius: 20px;
        padding: 5px 16px; font-size: 12px; color: rgba(255,255,255,.28);
    }

    /* ===== CHATBOT (unchanged from original) ===== */
    .chatbot-toggler {
        position: fixed; bottom: 32px; right: 32px;
        width: 58px; height: 58px; background: var(--orange); color: #fff;
        border-radius: 50%; display: flex; justify-content: center; align-items: center;
        font-size: 24px; cursor: pointer;
        box-shadow: 0 6px 20px rgba(232,80,10,.4); z-index: 9999; transition: transform .25s;
    }
    .chatbot-toggler:hover { transform: scale(1.1); }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
        .ss-nav { padding: 0 28px; }
        .ss-hero { grid-template-columns: 1fr; padding: 60px 32px 48px; }
        .ss-hero-visual { display: none; }
        .ss-hero h1 { font-size: 42px; }
        .ss-product-grid { grid-template-columns: repeat(2,1fr); }
        .ss-features-grid { grid-template-columns: repeat(2,1fr); }
        .ss-section { padding: 60px 32px; }
        .ss-promo { margin: 0 32px 60px; grid-template-columns: 1fr; padding: 40px 32px; }
        .ss-footer-grid { grid-template-columns: 1fr 1fr; }
        .ss-footer { padding: 48px 32px 28px; }
        .ss-brands { padding: 36px 32px; }
    }
    @media (max-width: 600px) {
        .ss-nav-links { display: none; }
        .ss-search { width: 140px; }
        .ss-product-grid { grid-template-columns: 1fr; }
        .ss-features-grid { grid-template-columns: 1fr; }
        .ss-footer-grid { grid-template-columns: 1fr; }
        .ss-hero h1 { font-size: 34px; }
        .ss-promo-title { font-size: 30px; }
    }
    </style>
</head>
<body>

<div class="brands-container">
    <div class="page-header">
        <h2>Shop By Brand</h2>
        <p>Choose your favorite brand to explore their latest collections.</p>
    </div>

    <div class="brand-grid">
        <?php
        $brand_sql = "SELECT * FROM brand ORDER BY Brand_Name ASC";
        $brand_res = $conn->query($brand_sql);
        
        if ($brand_res && $brand_res->num_rows > 0) {
            while($b = $brand_res->fetch_assoc()) {
                if (!empty($b['Brand_Logo'])) {
                    $logo = "../images/brands/" . $b['Brand_Logo'];
                } else {
                    $logo = "../images/brands/placeholder.png";
                }

            // 加入 onerror 机制，如果照片不存在则自动加载 placeholder
            echo '
            <a href="catalogue.php?brand_id='.$b['Brand_Id'].'" class="brand-card">
                <div class="brand-image-wrapper">
                    <img src="'.$logo.'" alt="'.$b['Brand_Name'].'" onerror="this.onerror=null; this.src=\'../images/brands/placeholder.png\'">
                </div>
                <div class="brand-name">'.$b['Brand_Name'].'</div>
            </a>';
            }
        } else {
            echo "<p style='grid-column: 1/-1; text-align: center;'>No brands available at the moment.</p>";
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>