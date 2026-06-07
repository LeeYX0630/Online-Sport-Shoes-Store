<?php
session_start();
require_once 'includes/db_connection.php';


$sql = "SELECT * FROM product ORDER BY Pro_Id DESC LIMIT 6";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Sole 2 Soul Sport Shoes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --orange: #E8500A; --orange-light: #FF6B1A;
        --dark: #0F0F0F; --light: #F5F3EF; --muted: #888;
    }
    html { scroll-behavior: smooth; }
    body { background: var(--light); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--dark); overflow-x: hidden; }

    /* ============================================================
       SCROLL-REVEAL BASE — elements start hidden, JS adds .visible
    ============================================================ */
    .reveal {
        opacity: 0;
        transform: translateY(36px);
        transition: opacity .7s cubic-bezier(.4,0,.2,1), transform .7s cubic-bezier(.4,0,.2,1);
    }
    .reveal.visible { opacity: 1; transform: none; }
    .reveal-left  { opacity:0; transform:translateX(-48px); transition: opacity .7s cubic-bezier(.4,0,.2,1), transform .7s cubic-bezier(.4,0,.2,1); }
    .reveal-left.visible  { opacity:1; transform:none; }
    .reveal-right { opacity:0; transform:translateX(48px);  transition: opacity .7s cubic-bezier(.4,0,.2,1), transform .7s cubic-bezier(.4,0,.2,1); }
    .reveal-right.visible { opacity:1; transform:none; }
    /* stagger children */
    .stagger > *:nth-child(1) { transition-delay:.05s }
    .stagger > *:nth-child(2) { transition-delay:.15s }
    .stagger > *:nth-child(3) { transition-delay:.25s }
    .stagger > *:nth-child(4) { transition-delay:.35s }
    .stagger > *:nth-child(5) { transition-delay:.45s }
    .stagger > *:nth-child(6) { transition-delay:.55s }

    /* ============================================================
       NAVBAR
    ============================================================ */
    .ss-nav {
        background: var(--dark);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 48px; height: 68px;
        position: sticky; top: 0; z-index: 1000;
        border-bottom: 1px solid rgba(255,255,255,.06);
        animation: navSlideDown .6s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes navSlideDown { from { transform:translateY(-100%); opacity:0; } to { transform:none; opacity:1; } }

    .ss-nav-logo { display:flex; align-items:center; gap:12px; text-decoration:none; }
    .ss-nav-logo-icon { width:38px; height:38px; background:var(--orange); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .ss-nav-logo-icon img { height:26px; width:auto; object-fit:contain; filter:brightness(0) invert(1); }
    .ss-nav-brand { color:#fff; font-weight:800; font-size:16px; letter-spacing:.3px; white-space:nowrap; }
    .ss-nav-brand span { color:var(--orange); }
    .ss-nav-links { display:flex; gap:36px; list-style:none; }
    .ss-nav-links a { color:rgba(255,255,255,.6); text-decoration:none; font-size:14px; font-weight:500; transition:color .2s; padding-bottom:4px; }
    .ss-nav-links a:hover, .ss-nav-links a.active { color:#fff; }
    .ss-nav-links a.active { border-bottom:2px solid var(--orange); }
    .ss-nav-right { display:flex; align-items:center; gap:12px; }
    .ss-search-wrap { position:relative; }
    .ss-search { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.1); border-radius:22px; padding:8px 18px 8px 38px; color:#fff; font-size:13px; width:220px; outline:none; transition:background .2s, border-color .2s; }
    .ss-search:focus { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.2); }
    .ss-search::placeholder { color:rgba(255,255,255,.35); }
    .ss-search-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:rgba(255,255,255,.4); font-size:14px; pointer-events:none; }
    .ss-nav-icon { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.1); border-radius:10px; width:38px; height:38px; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.7); font-size:16px; cursor:pointer; transition:background .2s; text-decoration:none; position:relative; }
    .ss-nav-icon:hover { background:rgba(255,255,255,.15); color:#fff; }
    .ss-nav-badge { position:absolute; top:-5px; right:-5px; background:var(--orange); color:#fff; font-size:10px; font-weight:700; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--dark); }
    .ss-btn-primary { background:var(--orange); color:#fff; border:none; border-radius:10px; padding:9px 22px; font-size:14px; font-weight:700; cursor:pointer; transition:background .2s, transform .15s; text-decoration:none; display:inline-flex; align-items:center; gap:7px; }
    .ss-btn-primary:hover { background:var(--orange-light); transform:translateY(-1px); color:#fff; }
    .ss-btn-ghost { background:transparent; color:rgba(255,255,255,.7); border:1px solid rgba(255,255,255,.2); border-radius:10px; padding:9px 20px; font-size:14px; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; }
    .ss-btn-ghost:hover { background:rgba(255,255,255,.06); color:#fff; border-color:rgba(255,255,255,.4); }

    /* ============================================================
       HERO
    ============================================================ */
    .ss-hero {
        min-height: 100vh;
        display: flex; align-items: center;
        padding: 0 60px;
        position: relative; overflow: hidden;
    }
    .ss-video-bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:0; transition:opacity 1.5s ease-in-out; z-index: 1; }
    .ss-video-bg.active { opacity:1; }
    .ss-hero-overlay {
        position: absolute; inset: 0;
        background: rgba(15,15,15,.85);
        z-index: 2;
    }
    .ss-hero-bg-glow { position:absolute; width:700px; height:700px; border-radius:50%; background:radial-gradient(circle, rgba(232,80,10,.14) 0%, transparent 65%); right:-120px; top:50%; transform:translateY(-50%); pointer-events:none; z-index:1; animation:glowPulse 6s ease-in-out infinite; }
    @keyframes glowPulse { 0%,100%{opacity:.6} 50%{opacity:1} }

    /* Animated horizontal ticker line */
    .ss-hero-ticker {
        position:absolute; bottom:0; left:0; right:0; z-index:3;
        background:rgba(232,80,10,.9); padding:10px 0; overflow:hidden;
        display:flex;
    }
    .ss-ticker-inner {
        display:flex; gap:0; white-space:nowrap;
        animation: ticker 22s linear infinite;
    }
    .ss-ticker-inner span { color:#fff; font-size:12px; font-weight:700; letter-spacing:2px; text-transform:uppercase; padding:0 32px; }
    .ss-ticker-inner span::after { content:"•"; margin-left:32px; opacity:.6; }
    @keyframes ticker { from{transform:translateX(0)} to{transform:translateX(-50%)} }

    .ss-hero-content {
        position:relative; z-index:2; max-width:620px;
    }
    .ss-eyebrow {
        display:inline-flex; align-items:center; gap:8px;
        background:rgba(232,80,10,.14); border:1px solid rgba(232,80,10,.28);
        border-radius:22px; padding:6px 16px; margin-bottom:28px;
        animation: fadeInUp .8s .3s cubic-bezier(.4,0,.2,1) both;
    }
    .ss-eyebrow-dot { width:7px; height:7px; border-radius:50%; background:var(--orange); animation:ss-pulse 2s ease-in-out infinite; }
    @keyframes ss-pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
    .ss-eyebrow span { color:var(--orange); font-size:11px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; }

    .ss-hero h1 {
        font-size: clamp(44px, 7vw, 72px);
        font-weight: 900; color: #fff;
        line-height: 1.05; letter-spacing: -2.5px; margin-bottom: 22px;
        text-shadow: 0 4px 24px rgba(0,0,0,.4);
        animation: fadeInUp .8s .45s cubic-bezier(.4,0,.2,1) both;
    }
    .ss-hero h1 em { color:var(--orange); font-style:normal; z-index:2; position:relative; }
    .ss-hero h1 br { z-index: 2; position: relative; }
    /* Typewriter word swap */
    .typeword { display:inline-block; min-width:200px; }

    .ss-hero-sub {
        color:rgba(255,255,255,.6); font-size:17px; line-height:1.75;
        max-width:480px; margin-bottom:38px;
        text-shadow:0 2px 8px rgba(0,0,0,.5);
        animation: fadeInUp .8s .6s cubic-bezier(.4,0,.2,1) both;
    }
    .ss-hero-cta {
        display:flex; align-items:center; gap:14px; flex-wrap:wrap;
        animation: fadeInUp .8s .75s cubic-bezier(.4,0,.2,1) both;
        z-index:2; position:relative;
    }
    .ss-hero-stats {
        display:flex; gap:36px; margin-top:52px;
        padding-top:36px; border-top:1px solid rgba(255,255,255,.12);
        animation: fadeInUp .8s .9s cubic-bezier(.4,0,.2,1) both;
    }
    .ss-stat-num {
        color:#fff; font-size:30px; font-weight:900; letter-spacing:-1px;
        /* count-up effect via CSS counter trick – done via JS */
    }
    .ss-stat-label { color:rgba(255,255,255,.4); font-size:12px; font-weight:600; letter-spacing:.5px; margin-top:3px; }

    @keyframes fadeInUp {
        from { opacity:0; transform:translateY(30px); }
        to   { opacity:1; transform:none; }
    }

    /* Scroll indicator */
    .ss-scroll-hint {
        position:absolute; bottom:80px; right:56px; z-index:3;
        display:flex; flex-direction:column; align-items:center; gap:8px;
        animation: fadeInUp .8s 1.1s both;
    }
    .ss-scroll-hint span { color:rgba(255,255,255,.4); font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase; writing-mode:vertical-rl; }
    .ss-scroll-line { width:1px; height:48px; background:linear-gradient(to bottom, rgba(255,255,255,.4), transparent); animation:scrollLine 2s ease-in-out infinite; }
    @keyframes scrollLine { 0%{transform:scaleY(0);transform-origin:top} 50%{transform:scaleY(1);transform-origin:top} 51%{transform-origin:bottom} 100%{transform:scaleY(0);transform-origin:bottom} }

    /* ============================================================
       BRAND MARQUEE
    ============================================================ */
    .ss-brands { background:#fff; padding:40px 0; border-bottom:1px solid rgba(0,0,0,.06); position:relative; }
    .ss-brands::before, .ss-brands::after { content:''; position:absolute; top:0; bottom:0; width:120px; z-index:2; }
    .ss-brands::before { left:0;  background:linear-gradient(to right,#fff,transparent); }
    .ss-brands::after  { right:0; background:linear-gradient(to left, #fff,transparent); }
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
    .ss-marquee-wrap { display:flex; overflow:hidden; }
    .ss-marquee { display:flex; gap:0; animation:marquee 18s linear infinite; }
    .ss-marquee:hover { animation-play-state:paused; }
    .ss-marquee-item { font-size:20px; font-weight:900; letter-spacing:-.5px; color:#ccc; padding:0 40px; transition:color .25s; white-space:nowrap; cursor:default; text-decoration:none; display:flex; align-items:center; gap:16px; }
    .ss-marquee-item:hover { color:var(--dark); }
    .ss-marquee-item .sep { color:#ddd; font-size:8px; }
    @keyframes marquee { from{transform:translateX(0)} to{transform:translateX(-50%)} }

    /* ============================================================
       SECTION COMMONS
    ============================================================ */
    .ss-section { padding: 88px 60px; }
    .ss-section-label { display:inline-flex; align-items:center; gap:10px; margin-bottom:10px; }
    .ss-section-line { width:28px; height:3px; background:var(--orange); border-radius:2px; }
    .ss-section-tag { color:var(--orange); font-size:11px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; }
    .ss-section-title { font-size:38px; font-weight:900; color:var(--dark); letter-spacing:-1.2px; margin-bottom:6px; }
    .ss-section-sub { color:var(--muted); font-size:15px; }
    .ss-section-head { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:48px; }
    .ss-view-all { display:inline-flex; align-items:center; gap:6px; color:var(--orange); font-size:14px; font-weight:600; text-decoration:none; transition:gap .2s; }
    .ss-view-all:hover { gap:12px; }

    /* ============================================================
       PRODUCT CARDS — shimmer + hover lift
    ============================================================ */
    .ss-product-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
    .ss-product-card {
        background:#fff; border-radius:20px; overflow:hidden;
        border:1.5px solid rgba(0,0,0,.07);
        transition: transform .32s cubic-bezier(.4,0,.2,1), box-shadow .32s, border-color .32s;
        cursor:pointer; position:relative;
    }
    .ss-product-card:hover { transform:translateY(-10px) scale(1.01); box-shadow:0 28px 64px rgba(0,0,0,.14); border-color:var(--orange); }
    /* shimmer loading effect */
    .ss-product-card::after {
        content:''; position:absolute; inset:0;
        background:linear-gradient(105deg, transparent 40%, rgba(255,255,255,.45) 50%, transparent 60%);
        transform:translateX(-100%);
        transition:transform .5s;
        pointer-events:none;
    }
    .ss-product-card:hover::after { transform:translateX(100%); }

    .ss-product-img-wrap { height:240px; display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
    .ss-product-emoji { font-size:100px; transition:transform .4s; }
    .ss-product-card:hover .ss-product-emoji { transform:scale(1.15) rotate(-8deg); }
    .ss-product-img { width:100%; height:100%; object-fit:cover; transition:transform .4s; }
    .ss-product-card:hover .ss-product-img { transform:scale(1.08); }
    .ss-product-badge { position:absolute; top:14px; left:14px; font-size:10px; font-weight:800; letter-spacing:1px; padding:4px 12px; border-radius:20px; text-transform:uppercase; }
    .ss-badge-new { background:var(--dark); color:#fff; }
    .ss-badge-sale { background:var(--orange); color:#fff; }
    .ss-wish-btn { position:absolute; top:14px; right:14px; width:34px; height:34px; border-radius:50%; background:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(0,0,0,.12); transition:transform .2s, background .2s; }
    .ss-wish-btn:hover { transform:scale(1.15); background:#fff0f0; }
    .ss-wish-btn i { color:#ccc; font-size:15px; transition:color .2s; }
    .ss-wish-btn:hover i { color:#e74c3c; }
    .ss-product-info { padding:20px 22px; }
    .ss-product-brand { font-size:10px; font-weight:800; letter-spacing:1.8px; text-transform:uppercase; color:var(--muted); margin-bottom:4px; }
    .ss-product-name { font-size:15px; font-weight:700; color:var(--dark); margin-bottom:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ss-product-rating { display:flex; align-items:center; gap:5px; margin-bottom:14px; }
    .ss-stars { color:#F5A623; font-size:12px; letter-spacing:1px; }
    .ss-rating-num { font-size:12px; color:var(--muted); font-weight:500; }
    .ss-product-footer { display:flex; align-items:center; justify-content:space-between; }
    .ss-price { font-size:20px; font-weight:900; color:var(--dark); }
    .ss-add-btn { width:40px; height:40px; border-radius:12px; background:var(--dark); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s, transform .2s; }
    .ss-add-btn:hover { background:var(--orange); transform:scale(1.12) rotate(90deg); }
    .ss-add-btn i { color:#fff; font-size:18px; }

    /* ============================================================
       PROMO BANNER — animated border
    ============================================================ */
    .ss-promo {
        margin: 0 60px 88px;
        background: var(--dark);
        border-radius: 28px;
        padding: 64px 68px;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 48px;
        overflow: hidden;
        position: relative;
    }
    /* Animated gradient border */
    .ss-promo::before {
        content:''; position:absolute; inset:-2px; border-radius:30px; z-index:0;
        background:linear-gradient(135deg, var(--orange), #ff9a56, var(--orange));
        background-size:200% 200%;
        animation:borderFlow 4s linear infinite;
        padding:2px;
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite:exclude;
    }
    @keyframes borderFlow { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
    .ss-promo-glow { position:absolute; right:-80px; top:-80px; width:400px; height:400px; border-radius:50%; background:radial-gradient(circle,rgba(232,80,10,.22) 0%,transparent 65%); pointer-events:none; animation:glowPulse 5s ease-in-out infinite; }
    .ss-promo-grid-overlay { position:absolute; inset:0; pointer-events:none; background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px); background-size:50px 50px; }
    .ss-promo-tag { display:inline-flex; align-items:center; gap:6px; background:rgba(232,80,10,.2); border-radius:20px; padding:5px 14px; margin-bottom:18px; }
    .ss-promo-tag span { color:var(--orange); font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
    .ss-promo-title { font-size:46px; font-weight:900; color:#fff; letter-spacing:-1.5px; line-height:1.08; margin-bottom:12px; position:relative; z-index:1; }
    .ss-promo-sub { color:rgba(255,255,255,.45); font-size:15px; max-width:400px; line-height:1.75; position:relative; z-index:1; }
    .ss-promo-code-box { background:rgba(255,255,255,.06); border:1px dashed rgba(255,255,255,.22); border-radius:18px; padding:30px 40px; text-align:center; flex-shrink:0; position:relative; z-index:1; transition:background .3s; }
    .ss-promo-code-box:hover { background:rgba(255,255,255,.1); }
    .ss-code-label { color:rgba(255,255,255,.4); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; margin-bottom:10px; }
    .ss-code-value { font-size:34px; font-weight:900; color:var(--orange); letter-spacing:5px; margin-bottom:16px; }
    .ss-copy-btn { background:var(--orange); color:#fff; border:none; border-radius:10px; padding:12px 24px; font-size:13px; font-weight:700; cursor:pointer; transition:background .2s, transform .15s; width:100%; }
    .ss-copy-btn:hover { background:var(--orange-light); transform:translateY(-1px); }

    /* ============================================================
       FEATURES
    ============================================================ */
    .ss-features-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
    .ss-feature-card {
        background:#fff; border-radius:20px; padding:36px 30px;
        border:1.5px solid rgba(0,0,0,.07);
        transition:transform .28s, box-shadow .28s, border-color .28s;
        position:relative; overflow:hidden;
    }
    /* Bottom-line sweep on hover */
    .ss-feature-card::after { content:''; position:absolute; bottom:0; left:0; width:0; height:3px; background:var(--orange); border-radius:0 0 3px 3px; transition:width .4s cubic-bezier(.4,0,.2,1); }
    .ss-feature-card:hover::after { width:100%; }
    .ss-feature-card:hover { transform:translateY(-6px); box-shadow:0 20px 48px rgba(0,0,0,.09); border-color:rgba(232,80,10,.2); }
    .ss-feature-icon { width:58px; height:58px; border-radius:16px; background:#FEF0E8; display:flex; align-items:center; justify-content:center; margin-bottom:22px; font-size:28px; transition:transform .3s; }
    .ss-feature-card:hover .ss-feature-icon { transform:scale(1.1) rotate(-5deg); }
    .ss-feature-title { font-size:17px; font-weight:800; color:var(--dark); margin-bottom:10px; }
    .ss-feature-desc { font-size:14px; color:var(--muted); line-height:1.8; }

    /* ============================================================
       FOOTER
    ============================================================ */
    .ss-footer { background:var(--dark); color:#fff; padding:72px 60px 34px; }
    .ss-footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1.5fr; gap:52px; margin-bottom:52px; }
    .ss-footer-brand { font-size:22px; font-weight:900; letter-spacing:-.5px; margin-bottom:12px; }
    .ss-footer-brand span { color:var(--orange); }
    .ss-footer-desc { color:rgba(255,255,255,.38); font-size:14px; line-height:1.85; margin-bottom:22px; max-width:280px; }
    .ss-footer-social { display:flex; gap:10px; }
    .ss-social-btn { width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.07); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background .2s, transform .2s; text-decoration:none; color:rgba(255,255,255,.6); font-size:16px; }
    .ss-social-btn:hover { background:var(--orange); color:#fff; transform:translateY(-2px); }
    .ss-footer-col h4 { font-size:12px; font-weight:700; letter-spacing:.8px; margin-bottom:20px; color:rgba(255,255,255,.55); text-transform:uppercase; }
    .ss-footer-col a { display:block; color:rgba(255,255,255,.38); font-size:14px; text-decoration:none; margin-bottom:11px; transition:color .2s, padding-left .2s; }
    .ss-footer-col a:hover { color:#fff; padding-left:5px; }
    .ss-footer-bottom { border-top:1px solid rgba(255,255,255,.07); padding-top:26px; display:flex; justify-content:space-between; align-items:center; }
    .ss-footer-bottom p { color:rgba(255,255,255,.28); font-size:13px; }
    .ss-footer-badge { background:rgba(255,255,255,.06); border-radius:20px; padding:5px 16px; font-size:12px; color:rgba(255,255,255,.28); }

    /* ============================================================
       CHATBOT
    ============================================================ */
    .chatbot-toggler { position:fixed; bottom:32px; right:32px; width:58px; height:58px; background:var(--orange); color:#fff; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:24px; cursor:pointer; box-shadow:0 6px 20px rgba(232,80,10,.4); z-index:9999; transition:transform .25s; }
    .chatbot-toggler:hover { transform:scale(1.1); }

    /* ============================================================
       RESPONSIVE
    ============================================================ */
    @media (max-width:1024px) {
        .ss-nav { padding:0 28px; }
        .ss-hero { padding:0 32px; min-height:80vh; }
        .ss-hero h1 { font-size:42px; }
        .ss-product-grid { grid-template-columns:repeat(2,1fr); }
        .ss-features-grid { grid-template-columns:repeat(2,1fr); }
        .ss-section { padding:60px 32px; }
        .ss-promo { margin:0 32px 60px; grid-template-columns:1fr; padding:42px 34px; }
        .ss-footer-grid { grid-template-columns:1fr 1fr; }
        .ss-footer { padding:48px 32px 28px; }
    }
    @media (max-width:600px) {
        .ss-nav-links { display:none; }
        .ss-search { width:130px; }
        .ss-product-grid, .ss-features-grid, .ss-footer-grid { grid-template-columns:1fr; }
        .ss-hero h1 { font-size:34px; letter-spacing:-1.5px; }
        .ss-promo-title { font-size:30px; }
        .ss-scroll-hint { display:none; }
    }
    </style>
</head>
<body>

<?php
$page_title   = "Home";
$is_home_root = true;
$current_page = "index.php";
$path_root    = "";
$path_mod_a   = "Module A/";
$path_mod_b   = "Module B/";
$path_mod_c   = "Module C/";

$header_cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item)
        $header_cart_count += isset($item['qty']) ? intval($item['qty']) : 0;
}

$nav_is_logged_in = false;
$nav_user_name = "";
$nav_profile_pic = "";
if (isset($_SESSION['user_id'])) {
    $nav_is_logged_in = true;
    $uid = $_SESSION['user_id'];
    $res = $conn->query("SELECT User_Name, User_Image FROM `user` WHERE User_Id='$uid'");
    if ($res && $row_u = $res->fetch_assoc()) {
        $nav_user_name   = $row_u['User_Name'];
        $nav_profile_pic = !empty($row_u['User_Image']) ? "uploads/".$row_u['User_Image'] : "";
    }
} elseif (isset($_SESSION['admin_id'])) {
    $nav_is_logged_in = true;
    $nav_user_name = isset($_SESSION['username']) ? $_SESSION['username']." (Admin)" : "Admin";
}
?>

<!-- ========== NAVBAR ========== -->
<?php include 'includes/header.php'; ?>

<!-- ========== HERO ========== -->
<section class="ss-hero">
    <div class="ss-hero-overlay"></div>
    <video class="ss-video-bg active" id="ssvideo1" muted playsinline autoplay>
        <source src="images/Hero/Sport Shoe Video_1.mp4" type="video/mp4">
    </video>
    <video class="ss-video-bg" id="ssvideo2" muted playsinline>
        <source src="images/Hero/Sport Shoe Video_2.mp4" type="video/mp4">
    </video>
    <video class="ss-video-bg" id="ssvideo3" muted playsinline>
        <source src="images/Hero/Sport Shoe Video_3.mp4" type="video/mp4">
    </video>

    <div class="ss-hero-bg-glow"></div>

    <div class="ss-hero-content">
        <div class="ss-eyebrow">
            <div class="ss-eyebrow-dot"></div>
            <span>New Collection 2025</span>
        </div>
        <h1>Step Into<br>Your <em class="typeword">Perfect</em><br>Stride.</h1>
        <p class="ss-hero-sub">
            Premium performance footwear engineered for champions.
            Find your edge with our curated sport shoe collection.
        </p>
        <div class="ss-hero-cta">
            <a class="ss-btn-primary" href="<?= $path_mod_b ?>catalogue.php">
                Shop Now &nbsp;<i class="bi bi-arrow-right"></i>
            </a>
            <a class="ss-btn-ghost" href="<?= $path_mod_b ?>catalogue.php">View Catalogue</a>
        </div>
        <div class="ss-hero-stats"></div>
  
    </div>

    <!-- Scroll hint -->
    <div class="ss-scroll-hint">
        <div class="ss-scroll-line"></div>
        <span>Scroll</span>
    </div>

    <!-- Ticker -->
    <div class="ss-hero-ticker">
        <div class="ss-ticker-inner">
            <?php for ($i = 0; $i < 2; $i++): ?>
            <span>Free Shipping above RM 299</span>
            <span>Nike · Adidas · Puma · ASICS · New Balance</span>
            <span>New 2025 Collections Now Live</span>
            <span>High-Quality Materials</span>
            <span>100% Authentic Guarantee</span>
            <span>Exclusive Member Discounts</span>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- ========== BRAND MARQUEE ========== -->
<div class="ss-brands">
    <div class="ss-brands-label">Trusted Brands We Carry</div>
        <div class="ss-brands-row">
           <a class="ss-brand" href="Module B/catalogue.php?brand_id=1">NIKE</a>
           <a class="ss-brand" href="Module B/catalogue.php?brand_id=2">ADIDAS</a>
           <a class="ss-brand" href="Module B/catalogue.php?brand_id=3">PUMA</a>
           <a class="ss-brand" href="Module B/catalogue.php?brand_id=7">ASICS</a>
           <a class="ss-brand" href="Module B/catalogue.php?brand_id=6">NEW BALANCE</a>
        </div>
    </div>
</div>

<!-- ========== FEATURED PRODUCTS ========== -->
<section class="ss-section">
    <div class="ss-section-head reveal">
        <div>
            <div class="ss-section-label">
                <div class="ss-section-line"></div>
                <span class="ss-section-tag">Featured</span>
            </div>
            <div class="ss-section-title">Recommended Collections</div>
            <div class="ss-section-sub">Handpicked for performance and style</div>
        </div>
        <a class="ss-view-all" href="<?= $path_mod_b ?>catalogue.php">
            View All &nbsp;<i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div class="ss-product-grid stagger">
        <?php
        $card_bgs = ['#F8F6F2','#F0F5FF','#F5FFF0','#FFF8F0','#F8F0FF','#FFFAEE'];
        $count = 0;
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $count++;
                $img_path = "";
                if (!empty($row['Pro_Image'])) {
                    $base_img  = $row['Pro_Image'];
                    $pp        = pathinfo($base_img);
                    $base_name = preg_replace('/_\d+$/', '', $pp['filename']);
                    $all_files = glob("uploads/{$base_name}*.*");
                    $img_path  = ($all_files && !empty($all_files)) ? $all_files[0] : "uploads/".$base_img;
                }
                $is_new  = ($count <= 2);
                $price   = $row['Pro_Price'];
                $card_bg = $card_bgs[($count-1) % count($card_bgs)];
        ?>
        <div class="ss-product-card reveal"
             onclick="window.location='<?= $path_mod_b ?>product_details.php?pro_id=<?= $row['Pro_Id'] ?>'">
            <div class="ss-product-img-wrap" style="background:<?= $card_bg ?>">
                <?php if ($is_new): ?><span class="ss-product-badge ss-badge-new">New</span><?php endif; ?>
                <button class="ss-wish-btn" onclick="event.stopPropagation()" title="Wishlist">
                    <i class="bi bi-heart"></i>
                </button>
                <?php if ($img_path && file_exists($img_path)): ?>
                    <img src="<?= htmlspecialchars($img_path) ?>"
                         alt="<?= htmlspecialchars($row['Pro_Name']) ?>"
                         class="ss-product-img">
                <?php else: ?>
                    <div class="ss-product-emoji">👟</div>
                <?php endif; ?>
            </div>
            <div class="ss-product-info">
                <div class="ss-product-brand">Sport Shoes</div>
                <div class="ss-product-name"><?= htmlspecialchars($row['Pro_Name']) ?></div>
                <div class="ss-product-rating">
                    <span class="ss-stars">★★★★★</span>
                    <span class="ss-rating-num">5.0</span>
                </div>
                <div class="ss-product-footer">
                    <span class="ss-price">RM <?= number_format($price, 2) ?></span>
                    <button class="ss-add-btn" onclick="event.stopPropagation()" title="Add to Cart">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <p style="grid-column:1/-1;text-align:center;color:var(--muted);padding:40px 0">No featured products available.</p>
        <?php endif; ?>
    </div>
</section>


<!-- ========== WHY US ========== -->
<section class="ss-section" style="padding-top:0">
    <div class="reveal">
        <div class="ss-section-label">
            <div class="ss-section-line"></div>
            <span class="ss-section-tag">Why Us</span>
        </div>
        <div class="ss-section-title" style="margin-bottom:6px">Why Shop With Us</div>
        <div class="ss-section-sub" style="margin-bottom:44px">We go the extra mile so you can too</div>
    </div>
    <div class="ss-features-grid stagger">
        <div class="ss-feature-card reveal">
            <div class="ss-feature-icon">🚚</div>
            <div class="ss-feature-title">Free &amp; Fast Delivery</div>
            <div class="ss-feature-desc">Free shipping on all orders above RM 299. Same-day dispatch for orders placed before 3 pm.</div>
        </div>
        <div class="ss-feature-card reveal">
            <div class="ss-feature-icon">🛡️</div>
            <div class="ss-feature-title">Authentic Guarantee</div>
            <div class="ss-feature-desc">Every pair is 100% authentic, sourced directly from official brand distributors in Malaysia.</div>
        </div>
        <div class="ss-feature-card reveal">
            <div class="ss-feature-icon">📦</div>
            <div class="ss-feature-title">High-Quality Products</div>
            <div class="ss-feature-desc">We carefully curate our collection to ensure you get the best quality sports shoes for your needs.</div>
        </div>
    </div>
</section>

<!-- ========== CHATBOT ========== -->
<div class="chatbot-toggler" onclick="toggleChatbot()">
    <i class="bi bi-chat-dots-fill"></i>
</div>
<div class="chatbot-window" id="chatbotWindow" style="position:fixed;bottom:100px;right:30px;width:350px;background:#fff;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.18);display:none;flex-direction:column;overflow:hidden;z-index:9999;border:1px solid #eee">
    <div style="background:var(--dark);color:#fff;padding:15px;display:flex;justify-content:space-between;align-items:center;font-weight:700">
        <span><i class="bi bi-robot me-2"></i>AI Store Assistant</span>
        <div>
            <i class="bi bi-trash3 me-3" onclick="clearChat()" style="cursor:pointer"></i>
            <i class="bi bi-x-lg" onclick="toggleChatbot()" style="cursor:pointer"></i>
        </div>
    </div>
    <div id="chatBody" style="padding:15px;height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:12px;background:#f4f6f9"></div>
    <div style="display:flex;padding:12px;border-top:1px solid #eee;background:#fff">
        <input type="text" id="chatInput" placeholder="Type a message…"
               style="flex:1;border:none;outline:none;padding:10px 15px;background:#f4f4f4;border-radius:20px;margin-right:10px;font-size:14px"
               onkeypress="if(event.key==='Enter')sendMessage()">
        <button onclick="sendMessage()" style="background:var(--orange);color:#fff;border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

<style>
.message{max-width:85%;padding:12px 16px;border-radius:15px;font-size:14px;line-height:1.6;word-wrap:break-word;white-space:pre-wrap}
.bot-message{background:#e9ecef;color:#333;align-self:flex-start;border-bottom-left-radius:4px}
.user-message{background:var(--orange);color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ============================================================
   VIDEO SWITCHER
============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    const videos = Array.from(document.querySelectorAll('.ss-video-bg'));
    let cur = videos.findIndex(v => v.classList.contains('active'));
    if (cur < 0) cur = 0;

    const setActive = idx => {
        videos.forEach((v, i) => {
            const on = i === idx;
            v.classList.toggle('active', on);
            if (on) { v.currentTime = 0; v.play().catch(()=>{}); }
            else v.pause();
        });
    };

    videos.forEach((v, i) => {
        v.addEventListener('ended', () => {
            cur = (i + 1) % videos.length;
            setActive(cur);
        });
    });
    setActive(cur);
});

/* ============================================================
   TYPEWRITER — cycles words in hero h1
============================================================ */
(function() {
    const words = ['Perfect', 'Powerful', 'Winning', 'Ultimate'];
    const el = document.querySelector('.typeword');
    if (!el) return;
    let i = 0, charI = 0, deleting = false;

    function type() {
        const word = words[i % words.length];
        if (!deleting) {
            el.textContent = word.slice(0, ++charI);
            if (charI === word.length) { deleting = true; setTimeout(type, 2000); return; }
        } else {
            el.textContent = word.slice(0, --charI);
            if (charI === 0) { deleting = false; i++; }
        }
        setTimeout(type, deleting ? 60 : 100);
    }
    setTimeout(type, 2200);
})();

/* ============================================================
   COUNT-UP NUMBERS
============================================================ */
function countUp(el) {
    const target = parseInt(el.dataset.target, 10);
    const suffix = target >= 1000 ? 'K+' : '+';
    const display = target >= 1000 ? Math.round(target / 1000) : target;
    const duration = 1800, steps = 60;
    let step = 0;
    const timer = setInterval(() => {
        step++;
        const progress = step / steps;
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        const val = target >= 1000
            ? Math.round((target / 1000) * eased)
            : Math.round(target * eased);
        el.textContent = val + suffix;
        if (step >= steps) { el.textContent = display + suffix; clearInterval(timer); }
    }, duration / steps);
}

/* ============================================================
   SCROLL REVEAL — IntersectionObserver
============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                // Trigger count-up for stat numbers
                e.target.querySelectorAll('.ss-stat-num[data-target]').forEach(countUp);
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.14 });

    document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => obs.observe(el));

    // Also observe the hero stats section directly
    document.querySelectorAll('.ss-hero-stats').forEach(el => obs.observe(el));
});

/* ============================================================
   CHATBOT
============================================================ */
const chatBody = document.getElementById('chatBody');
const defaultMsg = `<div class="message bot-message">Hi there! 👋 I'm your AI Assistant. How can I help you find the perfect pair of sport shoes today?</div>`;

window.addEventListener('DOMContentLoaded', () => {
    chatBody.innerHTML = sessionStorage.getItem('geminiChatHistory') || defaultMsg;
    chatBody.scrollTop = chatBody.scrollHeight;
});

function saveChatHistory() { sessionStorage.setItem('geminiChatHistory', chatBody.innerHTML); }

function clearChat() {
    Swal.fire({ title:'Clear Chat History?', text:'All conversations will be deleted.', icon:'warning', showCancelButton:true, confirmButtonColor:'#E8500A', cancelButtonColor:'#333', confirmButtonText:'Yes, clear it!' })
        .then(r => { if (r.isConfirmed) { sessionStorage.removeItem('geminiChatHistory'); chatBody.innerHTML=defaultMsg; Swal.fire({title:'Cleared!',icon:'success',timer:1500,showConfirmButton:false}); } });
}

function toggleChatbot() {
    const w = document.getElementById('chatbotWindow');
    w.style.display = w.style.display === 'flex' ? 'none' : 'flex';
}

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    chatBody.innerHTML += `<div class="message user-message">${msg}</div>`;
    input.value = '';
    chatBody.scrollTop = chatBody.scrollHeight;
    saveChatHistory();
    const id = 'ld-' + Date.now();
    chatBody.innerHTML += `<div id="${id}" class="message bot-message"><i class="bi bi-three-dots"></i> AI is thinking…</div>`;
    chatBody.scrollTop = chatBody.scrollHeight;
    try {
        const res  = await fetch("<?= $path_mod_b ?>gemini_handler.php", { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({message:msg}) });
        const data = await res.json();
        let reply  = data.reply || "Sorry, I encountered an error.";
        reply = reply.replace(/\*\*(.*?)\*\*/g,'<b>$1</b>').replace(/^\* /gm,'<br>• ');
        document.getElementById(id).innerHTML = reply;
    } catch {
        document.getElementById(id).remove();
        Swal.fire({ icon:'error', title:'Connection Failed', text:'Could not connect to AI assistant.', confirmButtonColor:'#E8500A' });
    }
    chatBody.scrollTop = chatBody.scrollHeight;
    saveChatHistory();
}
</script>
</body>
<?php include 'includes/footer.php'; ?>
</html>
