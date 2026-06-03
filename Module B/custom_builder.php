<?php 
include '../includes/db_connection.php';
$pro_id = isset($_GET['pro_id']) ? intval($_GET['pro_id']) : 16; 
include '../includes/header.php'; 
require_once '../includes/material_configs.php';

// --- 【核心修改 1】：从数据库获取旧设计数据 ---
$edit_id = isset($_GET['edit_design']) ? $conn->real_escape_string($_GET['edit_design']) : '';
$existing_json = 'null';

if (!empty($edit_id)) {
    $sql_edit = "SELECT Design_JSON FROM user_saved_designs WHERE Design_Id = '$edit_id'";
    $res_edit = $conn->query($sql_edit);
    if ($res_edit && $res_edit->num_rows > 0) {
        $row_edit = $res_edit->fetch_assoc();
        $existing_json = $row_edit['Design_JSON']; // 这是一个 JSON 字符串
    }
}
// --------------------------------------------

$color_names = [
    '#ffffff' => 'White', '#000000' => 'Black', '#222222' => 'Anthracite',
    '#333333' => 'Dark Grey', '#888888' => 'Cool Grey', '#aaaaaa' => 'Silver',
    '#eeeeee' => 'Pure Platinum', '#E7352B' => 'Bright Crimson', '#FF6B00' => 'Total Orange',
    '#FFD700' => 'University Gold', '#008060' => 'Volt Green', '#55acee' => 'Blue Fury',
    '#2b5d8a' => 'Binary Blue', '#dfff00' => 'Electric Green', '#525248' => 'Olive Flak'
];
?>

<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* ... 你原有的样式保持不变 ... */
    :root { --primary-green: #008060; --nike-gray: #f5f5f5; }
    body { background-color: #fff; overflow: hidden; }
    .builder-layout { 
        display: flex; 
        height: calc(100vh - 80px); 
        background: #fff;
    }

    /* 左侧主区域：上部 3D，下部材质 */
    .main-builder-area {
        flex: 1;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e5e5e5;
    }

    #viewer-box { 
        flex: 1.2; /* 3D 视图占据大部分空间 */
        background: radial-gradient(#ffffff, #e5e5e5); 
        position: relative; 
    }

    /* 底部材质选择区 */
    .bottom-config-area {
        flex: 0.3; /* 给予充足的横向高度 */
        background: #fff;
        padding: 20px 40px;
        border-top: 1px solid #eee;
        overflow-y: auto;
    }

    /* 侧边栏：仅保留导航和保存 */
    .config-panel { 
        width: 350px; 
        background: white; 
        display: flex; 
        flex-direction: column; 
    }

    /* 放大材质球 (Swatches) */
    .color-grid { 
    display: grid !important; 
    /* 将列数从 3 改为 6 */
    grid-template-columns: repeat(6, 1fr) !important; 
    gap: 25px; 
    justify-items: center; 
    margin: 0 auto; 
    /* 关键：必须大幅增加或取消最大宽度，否则 6 个格子会挤在一起或溢出 */
    max-width: 1000px !important; 
    padding: 10px;
}
    @media (max-width: 768px) {
    .color-grid {
        gap: 10px;
        grid-template-columns: repeat(auto-fit, minmax(60px, 1fr)) !important;
    }
    .swatch {
        width: 70px !important;
        height: 70px !important;
    }
}

    .swatch { 
        width: 110px;  /* 从 80px 放大到 110px */
        height: 110px; 
        border-radius: 12px; /* 改为圆角矩形，更具质感 */
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .part-name { 
        font-size: 28px; 
        margin-bottom: 30px; 
        letter-spacing: -1px;
    }
    model-viewer { width: 100%; height: 100%; outline: none; transition: opacity 0.5s ease-in-out; opacity: 1; visibility: visible; }
    model-viewer.is-loading { opacity: 0 !important; visibility: hidden; pointer-events: none; }

    .config-panel { width: 450px; background: white; border-left: 1px solid #e5e5e5; display: flex; flex-direction: column; }
    .fixed-nav-area { border-bottom: 1px solid #eee; background: #fff; z-index: 10; }
    .fixed-section { padding: 15px 30px; border-bottom: 1px solid #f9f9f9; }
    .fixed-section:last-child { border-bottom: none; }
    .section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; color: #aaa; margin-bottom: 12px; text-align: center; letter-spacing: 1px; }
    .nav-grid { display: flex; justify-content: center; gap: 10px; }
    .nav-btn { flex: 1; padding: 12px 5px; border-radius: 8px; border: 1px solid #eee; background: #fff; font-size: 12px; font-weight: 800; cursor: pointer; transition: 0.3s; color: #333; text-align: center; }
    .nav-btn:hover { border-color: #000; background: #f8f8f8; }
    .nav-btn.active { background: #000; color: #fff; border-color: #000; }

    .step-nav-header { display: flex; align-items: center; justify-content: space-between; background: #fff; }
    .nav-arrow { font-size: 28px; cursor: pointer; color: #111; transition: 0.2s; background: none; border: none; padding: 5px 20px; }
    .nav-arrow:hover { color: var(--primary-green); transform: scale(1.2); }
    .step-indicator { font-size: 15px; font-weight: 900; color: #111; text-transform: uppercase; }

    .config-scroll { overflow-y: auto; padding: 5px 10px 20px; position: relative; }
    .build-step { display: none; text-align: center; animation: fadeInStep 0.4s ease-out forwards; }
    .build-step.active { display: block; }
    @keyframes fadeInStep { from { opacity: 0; transform: translateX(15px); } to { opacity: 1; transform: translateX(0); } }

    .part-name { font-size: 24px; font-weight: 800; margin-bottom: 25px; color: #111; }
    .color-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; justify-items: center; margin: 0 auto; max-width: 320px; }
    .swatch { width: 80px; height: 80px; border-radius: 50%; border: 1px solid transparent; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center; overflow: hidden;}
    .swatch img { width: 100%; height: 100%; object-fit: cover; }
    .swatch:hover { transform: scale(1.08); }
    .swatch.active { border-color: #000; box-shadow: 0 0 0 4px #fff, 0 0 0 7px #000; }
    .selected-color-name { margin-top: 30px; font-size: 17px; font-weight: bold; color: #333; }

    .builder-footer { padding: 25px 30px; border-top: 1px solid #e5e5e5; background: white; }
    .btn-checkout { width: 100%; background: #111; color: #fff; border: none; padding: 18px; font-weight: bold; font-size: 16px; border-radius: 40px; cursor: pointer; transition: 0.3s; }
    .btn-checkout:hover { background: #333; transform: translateY(-2px); }

    .price-summary-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .price-display-group { position: relative; display: inline-flex; align-items: center; gap: 8px; }
    .price-info-icon {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 1px solid #ddd;
        background: #fff;
        color: #333;
        font-weight: 900;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: help;
        user-select: none;
        transition: all 0.2s ease;
    }
    .price-display-group:hover .price-info-icon,
    .price-display-group:focus-within .price-info-icon {
        background: #111;
        color: #fff;
        border-color: #111;
    }
    .price-info-popover {
        position: absolute;
        right: 0;
        top: calc(100% + 10px);
        width: 250px;
        padding: 12px 14px;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid #eee;
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.12);
        z-index: 20;
        opacity: 0;
        pointer-events: none;
        transform: translateY(-4px);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .price-display-group:hover .price-info-popover,
    .price-display-group:focus-within .price-info-popover {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }
    .price-breakdown-title {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #777;
        margin-bottom: 10px;
    }
    .price-breakdown-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        font-size: 13px;
        color: #333;
        margin-bottom: 8px;
    }
    .price-breakdown-row:last-child {
        margin-bottom: 0;
    }
    .price-breakdown-row.total-row {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #eee;
        font-weight: 800;
    }

    .ai-input-wrapper { display: flex; gap: 8px; align-items: center; }
    .ai-tag-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
    .ai-tag-btn {
        border: 1px solid #e5e5e5;
        background: linear-gradient(135deg, #ffffff, #f7f7f7);
        color: #111;
        border-radius: 999px;
        padding: 10px 12px;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    .ai-tag-btn:hover {
        border-color: var(--primary-green);
        color: var(--primary-green);
        transform: translateY(-1px);
    }
    .ai-tag-btn:focus-visible {
        outline: 2px solid var(--primary-green);
        outline-offset: 2px;
    }

    /* --- 增强版教学系统样式 --- */
/* --- 顶级沉浸式教学系统 --- */
#tutorial-overlay {
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.8); /* 足够黑，突出重点 */
    z-index: 10000; display: none;
}

/* 增强高亮效果 */
.tutorial-highlight {
    position: relative !important;
    z-index: 10001 !important;
    background: #fff !important;
    /* 增加明亮的边框色，确保在白色背景下也能看出提亮 */
    box-shadow: 0 0 0 10px #fff, 0 0 50px rgba(0, 128, 96, 0.8) !important; 
    pointer-events: none;
}

/* 提示气泡说明框 */
.tutorial-tooltip {
    position: fixed; z-index: 10005;
    background: #fff; color: #111;
    padding: 25px; border-radius: 20px;
    width: 320px; box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    display: none; border: 3px solid var(--primary-green);
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* 指向箭头 */
.tutorial-arrow {
    position: absolute; width: 0; height: 0;
    border-style: solid; z-index: 10006; display: none;
}
.arrow-right { border-width: 15px 0 15px 20px; border-color: transparent transparent transparent var(--primary-green); }
.arrow-left  { border-width: 15px 20px 15px 0; border-color: transparent var(--primary-green) transparent transparent; }
.arrow-top   { border-width: 0 15px 20px 15px; border-color: transparent transparent var(--primary-green) transparent; }
.arrow-bottom { border-width: 20px 15px 0 15px; border-color: var(--primary-green) transparent transparent transparent; }

/* 修改 Skip 按钮位置，防止挡住提示 */
.tutorial-skip-fixed {
    position: fixed; 
    bottom: 40px; 
    left: 40px; /* 移到左下角，避免干扰右侧配置面板 */
    z-index: 10010; 
    background: #ff4757;
    color: white; border: none;
    padding: 10px 25px; border-radius: 50px;
    font-weight: 900; box-shadow: 0 10px 20px rgba(255,71,87,0.3);
    cursor: pointer; display: none;
}


.tutorial-skip-fixed:hover { background: #ff6b81; transform: scale(1.05); }

/* --- 右上角场景预览全新高端样式 --- */
.preview-controls-container {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.btn-preview-trigger {
    background: #111;
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 30px;
    font-weight: 800;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.btn-preview-trigger:hover {
    background: var(--primary-green);
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(0, 128, 96, 0.3);
}

.preview-menu {
    display: none;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid #eee;
    border-radius: 14px;
    padding: 6px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.12);
    flex-direction: column;
    gap: 4px;
    min-width: 150px;
    animation: previewPop 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

@keyframes previewPop {
    from { transform: scale(0.9) translateY(-10px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}

.preview-menu-btn {
    background: transparent;
    border: none;
    padding: 10px 14px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #333;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-menu-btn:hover {
    background: #f5f5f5;
    color: var(--primary-green);
}

.preview-menu-btn.active {
    background: #000;
    color: #fff;
}

.preview-menu-btn.exit-btn {
    border-top: 1px solid #eee;
    margin-top: 4px;
    color: #ff4757;
    border-radius: 8px;
}

.preview-menu-btn.exit-btn:hover {
    background: #ffe8e8;
    color: #ff4757;
}
</style>

<div id="tutorial-overlay"></div>
<div id="tutorial-arrow" class="tutorial-arrow"></div> <div id="tutorialTooltip" class="tutorial-tooltip">
    <h5 id="tourTitle"></h5>
    <p id="tourContent"></p>
    <div class="tutorial-controls d-flex justify-content-between align-items-center">
        <span id="stepProgress" style="font-size: 12px; color: #888;"></span>
        <button class="btn-next btn btn-dark btn-sm fw-bold px-3" onclick="nextTourStep()">Next Step</button>
    </div>
</div>
<button id="skipTutorialBtn" class="tutorial-skip-fixed" onclick="endTutorial()">SKIP TUTORIAL</button>

<div class="builder-layout">
    
    <div class="main-builder-area">
        <div id="viewer-box">
            <model-viewer id="shoe-viewer" class="is-loading" src="../includes/models/pair_spread_shoe1.glb" 
                camera-controls touch-action="pan-y" shadow-intensity="2" environment-image="neutral" exposure="1" camera-orbit="45deg 75deg 105%">
            </model-viewer>
            <div class="preview-controls-container">
                <button type="button" class="btn-preview-trigger" onclick="togglePreviewMenu(event)">
                    <i class="bi bi-bezier2"></i> Live Preview
                </button>
                <div class="preview-menu" id="previewSceneMenu">
                    <button type="button" class="preview-menu-btn active" id="btn-scene-default" onclick="applyScene('default', this)">👟 3D Shoe (Studio)</button>
                    <button type="button" class="preview-menu-btn" onclick="applyScene('park', this)">🌳 Park Scene</button>
                    <button type="button" class="preview-menu-btn" onclick="applyScene('gym', this)">🏋️ Gym Room</button>
                    <button type="button" class="preview-menu-btn" onclick="applyScene('city', this)">🏙️ City Street</button>
                </div>
            </div>
        </div>

        <div class="bottom-config-area">
            <div class="step-nav-header">
                <button class="nav-arrow" onclick="changeStep(-1)"><i class="bi bi-arrow-left-circle-fill"></i></button>
                <div class="step-indicator" id="stepCounter">Material 1 / 7</div>
                <button class="nav-arrow" onclick="changeStep(1)"><i class="bi bi-arrow-right-circle-fill"></i></button>
            </div>

            <div class="config-scroll">
                <div class="build-step active" id="step-1">
                    <p class="part-name">Upper Material</p>
                    <div class="color-grid">
                        <div class="swatch active" title="Crepe Satin" style="background: #525248;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/crepe_satin_normal.jpg', 0.7, this)">
                            <small style="font-weight:900; font-size:10px;">SATIN</small>
                        </div>
                        <div class="swatch" title="Smooth Leather" style="background: #eee;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/leather_normal.jpg', 0.8, this)">
                            <small style="font-weight:900; font-size:10px;">LEATHER</small>
                        </div>
                        <div class="swatch" title="Grey Jersey" style="background: #888;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/jersey_melange_normal.jpg', 0.95, this)">
                            <small style="font-weight:900; font-size:10px;">JERSEY</small>
                        </div>
                        <div class="swatch" title="Tech Blue" style="background: #2b5d8a;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/corrugated_iron_normal.jpg', 0.5, this)">
                            <small style="font-weight:900; font-size:10px;">TECH</small>
                        </div>
                        <div class="swatch" title="Canvas Weave" style="background: #d1c7b7;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/fabric_pattern_07_normal.jpg', 0.9, this)">
                            <small style="font-weight:900; font-size:10px;">FABRIC</small>
                        </div>
                        <div class="swatch" title="Carbon Tech" style="background: #111;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/concrete_tile_facade_normal.jpg', 0.4, this)">
                            <small style="font-weight:900; font-size:10px; color:#fff;">TILE</small>
                        </div>
                    </div>
                    <p class="selected-color-name">Smooth Leather</p>
                </div>

                <div class="build-step" id="step-2">
                    <p class="part-name">Upper Colour</p>
                    <div class="color-grid">
                        <div class="swatch active" title="White" style="background: #ffffff;" onclick="changeOnlyColor('Outupper', '#ffffff', this)"></div>
                        <div class="swatch" title="Black" style="background: #222;" onclick="changeOnlyColor('Outupper', '#222222', this)"></div>
                        <div class="swatch" title="Brown" style="background: #895129;" onclick="changeOnlyColor('Outupper', '#895129', this)"></div>
                        <div class="swatch" title="Bright Crimson" style="background: #E7352B;" onclick="changeOnlyColor('Outupper', '#E7352B', this)"></div>
                        <div class="swatch" title="Olive Flak" style="background: #525248;" onclick="changeOnlyColor('Outupper', '#525248', this)"></div>
                        <div class="swatch" title="Volt Green" style="background: #dfff00;" onclick="changeOnlyColor('Outupper', '#dfff00', this)"></div>
                    </div>
                    <p class="selected-color-name">White</p>
                </div>

                <div class="build-step" id="step-3">
                    <p class="part-name">Side Accents</p>
                    <div class="color-grid">
                        <div class="swatch" title="Black" style="background: #000;" onclick="changePartColor('Style', '#000000', this)"></div>
                        <div class="swatch" title="Bright Crimson" style="background: #E7352B;" onclick="changePartColor('Style', '#E7352B', this)"></div>
                        <div class="swatch" title="White" style="background: #ffffff;" onclick="changePartColor('Style', '#ffffff', this)"></div>
                        <div class="swatch" title="University Gold" style="background: #FFD700;" onclick="changePartColor('Style', '#FFD700', this)"></div>
                        <div class="swatch" title="Volt Green" style="background: #008060;" onclick="changePartColor('Style', '#008060', this)"></div>
                        <div class="swatch" title="Blue Fury" style="background: #55acee;" onclick="changePartColor('Style', '#55acee', this)"></div>
                    </div>
                    <p class="selected-color-name">Black</p>
                </div>

                <div class="build-step" id="step-4">
                    <p class="part-name">Laces Color</p>
                    <div class="color-grid">
                        <div class="swatch" title="White" style="background: #ffffff;" onclick="changePartColor('Laces', '#ffffff', this)"></div>
                        <div class="swatch" title="Black" style="background: #333;" onclick="changePartColor('Laces', '#333333', this)"></div>
                        <div class="swatch" title="Bright Crimson" style="background: #E7352B;" onclick="changePartColor('Laces', '#E7352B', this)"></div>
                        <div class="swatch" title="Blue Fury" style="background: #55acee;" onclick="changePartColor('Laces', '#55acee', this)"></div>
                        <div class="swatch" title="Total Orange" style="background: #FF6B00;" onclick="changePartColor('Laces', '#FF6B00', this)"></div>
                        <div class="swatch" title="Cool Grey" style="background: #888888;" onclick="changePartColor('Laces', '#888888', this)"></div>
                    </div>
                    <p class="selected-color-name">White</p>
                </div>

                <div class="build-step" id="step-5">
                    <p class="part-name">Tongue Style</p>
                    <div class="color-grid">
                        <div class="swatch" title="Anthracite" style="background: #222;" onclick="changePartColor('Tongue', '#222222', this)"></div>
                        <div class="swatch" title="White" style="background: #ffffff;" onclick="changePartColor('Tongue', '#ffffff', this)"></div>
                        <div class="swatch" title="Silver" style="background: #aaaaaa;" onclick="changePartColor('Tongue', '#aaaaaa', this)"></div>
                        <div class="swatch" title="Black" style="background: #000000;" onclick="changePartColor('Tongue', '#000000', this)"></div>
                        <div class="swatch" title="Bright Crimson" style="background: #E7352B;" onclick="changePartColor('Tongue', '#E7352B', this)"></div>
                        <div class="swatch" title="University Gold" style="background: #FFD700;" onclick="changePartColor('Tongue', '#FFD700', this)"></div>
                    </div>
                    <p class="selected-color-name">Anthracite</p>
                </div>

                <div class="build-step" id="step-6">
                    <p class="part-name">Midsole Finish</p>
                    <div class="color-grid">
                        <div class="swatch" title="White" style="background: #ffffff;" onclick="changePartColor('Midsole', '#ffffff', this)"></div>
                        <div class="swatch" title="Black" style="background: #000000;" onclick="changePartColor('Midsole', '#000000', this)"></div>
                        <div class="swatch" title="Pure Platinum" style="background: #eeeeee;" onclick="changePartColor('Midsole', '#eeeeee', this)"></div>
                        <div class="swatch" title="University Gold" style="background: #FFD700;" onclick="changePartColor('Midsole', '#FFD700', this)"></div>
                        <div class="swatch" title="Cool Grey" style="background: #888888;" onclick="changePartColor('Midsole', '#888888', this)"></div>
                        <div class="swatch" title="Total Orange" style="background: #FF6B00;" onclick="changePartColor('Midsole', '#FF6B00', this)"></div>
                    </div>
                    <p class="selected-color-name">White</p>
                </div>

                <div class="build-step" id="step-7">
                    <p class="part-name">Outsole Grip</p>
                    <div class="color-grid">
                        <div class="swatch" title="Black" style="background: #111111;" onclick="changePartColor('Outsole', '#111111', this)"></div>
                        <div class="swatch" title="Volt Green" style="background: #008060;" onclick="changePartColor('Outsole', '#008060', this)"></div>
                        <div class="swatch" title="Bright Crimson" style="background: #E7352B;" onclick="changePartColor('Outsole', '#E7352B', this)"></div>
                        <div class="swatch" title="Cool Grey" style="background: #888888;" onclick="changePartColor('Outsole', '#888888', this)"></div>
                        <div class="swatch" title="University Gold" style="background: #FFD700;" onclick="changePartColor('Outsole', '#FFD700', this)"></div>
                        <div class="swatch" title="White" style="background: #ffffff;" onclick="changePartColor('Outsole', '#ffffff', this)"></div>
                    </div>
                    <p class="selected-color-name">Black</p>
                </div>
            </div> </div> </div> <div class="config-panel">
        <div class="fixed-section ai-header">
             <p class="section-title" style="color: #008060;"><i class="bi bi-magic"></i> AI Dream Generator</p>
            <div class="ai-input-wrapper">
                <input type="text" id="aiStyleInput" class="input-field" placeholder="e.g. Cyberpunk 2077..." style="flex: 1; height: 40px; margin-bottom: 0;">
                <button type="button" class="nav-btn active" id="aiGenBtn" onclick="generateAIDesign()" style="width: 60px;">Generate</button>
            </div>
            <div class="ai-tag-grid">
                <button type="button" class="ai-tag-btn" onclick="applyInspirationTag('Cyberpunk')">#Cyberpunk</button>
                <button type="button" class="ai-tag-btn" onclick="applyInspirationTag('Harajuku Retro')">#Harajuku Retro</button>
                <button type="button" class="ai-tag-btn" onclick="applyInspirationTag('Minimalist')">#Minimalist</button>
                <button type="button" class="ai-tag-btn" onclick="applyInspirationTag('Volcanic Lava')">#Volcanic Lava</button>
            </div>
        </div>

        <div class="fixed-nav-area">
            <div class="fixed-section">
                <p class="section-title">Presentation</p>
                <div class="nav-grid">
                    <div class="nav-btn" onclick="switchModel('single', this)">SINGLE</div>
                    <div class="nav-btn active" onclick="switchModel('pair', this)">SPREAD</div>
                    <div class="nav-btn" onclick="switchModel('stacked', this)">STACKED</div>
                </div>
            </div>
            <div class="fixed-section">
                <p class="section-title">Perspective</p>
                <div class="nav-grid">
                    <div class="nav-btn active" onclick="setPose('90deg 75deg 105%', this)">SIDE</div>
                    <div class="nav-btn" onclick="setPose('0deg 0deg 105%', this)">TOP</div>
                    <div class="nav-btn" onclick="setPose('0deg 180deg 105%', this)">SOLE</div>
                </div>
            </div>
            <div class="fixed-section">
                <p class="section-title">History Control</p>
                <div class="nav-grid">
                    <button id="undoBtn" class="nav-btn" onclick="undo()" disabled><i class="bi bi-arrow-counterclockwise"></i> UNDO</button>
                    <button id="redoBtn" class="nav-btn" onclick="redo()" disabled><i class="bi bi-arrow-clockwise"></i> REDO</button>
                </div>
            </div>
        </div>

        <div class="builder-footer">
            <div class="price-summary-row mb-3">
                <span class="fw-bold">Total Price</span>
                <div class="price-display-group">
                    <span class="h4 fw-bold mb-0" id="totalPriceDisplay">RM 829.00</span>
                    <span class="price-info-icon" tabindex="0" aria-label="Price breakdown">i</span>
                    <div class="price-info-popover" id="priceInfoPopover" role="tooltip">
                        <div class="price-breakdown-title">Price Breakdown</div>
                        <div class="price-breakdown-row">
                            <span id="basePriceLabel">Base Price</span>
                            <span id="basePriceLine">RM 829.00</span>
                        </div>
                        <div class="price-breakdown-row" id="materialBreakdownRow" style="display: none;">
                            <span id="materialNameLine">Material Upgrade</span>
                            <span id="materialSurchargeLine">RM 0.00</span>
                        </div>
                        <div class="price-breakdown-row total-row">
                            <span>Total</span>
                            <span id="breakdownTotalLine">RM 829.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-checkout" onclick="saveDesign(event)">SAVE DESIGN</button>
        </div>
    </div>
</div>

<script>
    const viewer = document.querySelector('#shoe-viewer');
    let currentStep = 1;
    const totalSteps = 7;

    // --- 【核心修改 2】：接收 PHP 传来的旧设计数据 ---
    const editDesignData = <?php echo $existing_json; ?>;

    let currentSelections = {
        'Outupper': { color: '#ffffff', texture: '../includes/models/textures/leather_normal.jpg', roughness: 0.8 },
        'Style': { color: '#000000', texture: null, roughness: 0.5 },
        'Laces': { color: '#ffffff', texture: null, roughness: 0.5 },
        'Tongue': { color: '#222222', texture: null, roughness: 0.5 },
        'Midsole': { color: '#ffffff', texture: null, roughness: 0.5 },
        'Outsole': { color: '#111111', texture: null, roughness: 0.5 }
    };

const MATERIAL_SURCHARGES = <?php echo json_encode($MATERIAL_SURCHARGES); ?>;
const BASE_PRICE = <?php echo BASE_CUSTOM_PRICE; ?>;
const MATERIAL_DETAIL_LABELS = {
    '../includes/models/textures/leather_normal.jpg': 'Premium Leather',
    '../includes/models/textures/jersey_melange_normal.jpg': 'Technical Jersey',
    '../includes/models/textures/corrugated_iron_normal.jpg': 'Tech Mesh',
    '../includes/models/textures/crepe_satin_normal.jpg': 'Crepe Satin',
    '../includes/models/textures/fabric_pattern_07_normal.jpg': 'Textured Fabric',
    '../includes/models/textures/concrete_tile_facade_normal.jpg': 'Concrete Tile Finish'
};

function updateTotalPriceUI() {
    let currentSurcharge = 0;
    let materialLabel = 'Base Material';
    const upperTexture = currentSelections['Outupper'].texture;
    if (upperTexture && MATERIAL_SURCHARGES[upperTexture]) {
        currentSurcharge = MATERIAL_SURCHARGES[upperTexture];
        materialLabel = MATERIAL_DETAIL_LABELS[upperTexture] || 'Premium Upper Material';
    }
    const finalTotal = BASE_PRICE + currentSurcharge;

    const displayEl = document.getElementById('totalPriceDisplay');
    const basePriceLine = document.getElementById('basePriceLine');
    const materialBreakdownRow = document.getElementById('materialBreakdownRow');
    const materialNameLine = document.getElementById('materialNameLine');
    const materialSurchargeLine = document.getElementById('materialSurchargeLine');
    const breakdownTotalLine = document.getElementById('breakdownTotalLine');

    if (displayEl) {
        displayEl.innerText = `RM ${finalTotal.toFixed(2)}`;
        displayEl.style.transition = 'color 0.3s';
        displayEl.style.color = currentSurcharge > 0 ? 'var(--primary-green)' : '#000';
    }

    if (basePriceLine) {
        basePriceLine.innerText = `RM ${BASE_PRICE.toFixed(2)}`;
    }

    if (materialBreakdownRow && materialNameLine && materialSurchargeLine && breakdownTotalLine) {
        if (currentSurcharge > 0) {
            materialBreakdownRow.style.display = 'flex';
            materialNameLine.innerText = materialLabel;
            materialSurchargeLine.innerText = `RM ${currentSurcharge.toFixed(2)}`;
        } else {
            materialBreakdownRow.style.display = 'none';
        }
        breakdownTotalLine.innerText = `RM ${finalTotal.toFixed(2)}`;
    }
}

    const historyStack = [];
    const redoStack = [];
    const MAX_HISTORY = 50; // 最大记录步数

    // 记录当前状态到历史堆栈（在修改之前调用）
    function recordHistory() {
        // 深拷贝当前配置，防止引用关联
        historyStack.push(JSON.parse(JSON.stringify(currentSelections)));
        if (historyStack.length > MAX_HISTORY) historyStack.shift();
        
        // 只要有了新的手动操作，就清空重做堆栈
        redoStack.length = 0;
        updateUndoRedoButtons();
    }

    function undo() {
        if (historyStack.length === 0) return;
        
        // 将当前状态存入重做堆栈
        redoStack.push(JSON.parse(JSON.stringify(currentSelections)));
        
        // 恢复上一个状态
        currentSelections = historyStack.pop();
        applySavedColors(); // 物理应用到 3D 模型
        updateUndoRedoButtons();
        updateTotalPriceUI();
    }

    function redo() {
        if (redoStack.length === 0) return;
        
        // 将当前状态存回历史堆栈
        historyStack.push(JSON.parse(JSON.stringify(currentSelections)));
        
        // 恢复重做状态
        currentSelections = redoStack.pop();
        applySavedColors();
        updateUndoRedoButtons();
        updateTotalPriceUI();
    }

    function updateUndoRedoButtons() {
        const uBtn = document.getElementById('undoBtn');
        const rBtn = document.getElementById('redoBtn');
        if(uBtn) uBtn.disabled = (historyStack.length === 0);
        if(rBtn) rBtn.disabled = (redoStack.length === 0);
        
        // 视觉反馈：变灰
        if(uBtn) uBtn.style.opacity = uBtn.disabled ? "0.3" : "1";
        if(rBtn) rBtn.style.opacity = rBtn.disabled ? "0.3" : "1";
    }

    // 如果有旧数据，覆盖默认值
    if (editDesignData) {
        currentSelections = editDesignData;
    }
    // ----------------------------------------------

    viewer.addEventListener('load', async () => {
        await applySavedColors();
        updateTotalPriceUI();
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                viewer.classList.remove('is-loading');
            });
        });
    }, { once: true });

async function applySavedColors() {
    // 【实时重定向修复】：永远从当前 DOM 活动的最新节点提取多边形模型
    const activeViewer = document.querySelector('#shoe-viewer');
    if (!activeViewer || !activeViewer.model) return;
    const promises = [];
    
    Object.keys(currentSelections).forEach(part => {
        const set = currentSelections[part];
        
        const mats = activeViewer.model.materials.filter(m => 
            m.name && m.name.toLowerCase().includes(part.toLowerCase())
        );
        
        mats.forEach(mat => {
            if (mat.pbrMetallicRoughness.baseColorTexture) {
                mat.pbrMetallicRoughness.baseColorTexture.setTexture(null);
            }
            if (mat.normalTexture) {
                mat.normalTexture.setTexture(null);
            }

            mat.pbrMetallicRoughness.setBaseColorFactor(set.color);
            mat.pbrMetallicRoughness.setRoughnessFactor(set.roughness);
            
            if (set.texture) {
                promises.push(viewer.createTexture(set.texture).then(t => { 
                    if(mat.normalTexture) mat.normalTexture.setTexture(t); 
                }));
            }
        });
    });
    await Promise.all(promises);
}

    function updateFixedUI(el) {
        if (!el) return;
        const parent = el.parentElement;
        parent.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
    }

    function updateStepUI(el) {
        const parent = el.closest('.build-step');
        parent.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
        el.classList.add('active');
        parent.querySelector('.selected-color-name').innerText = el.getAttribute('title');
    }

    function setPose(orbit, el) {
        viewer.cameraOrbit = orbit;
        updateFixedUI(el);
    }

    function changePartColor(materialName, colorHex, el) {
        recordHistory(); // 记录旧状态
        updateStepUI(el);
        currentSelections[materialName].color = colorHex;
        currentSelections[materialName].texture = null;
        if (!viewer.model) return;
        viewer.model.materials.filter(m => m.name.includes(materialName)).forEach(m => {
            if (m.pbrMetallicRoughness.baseColorTexture) m.pbrMetallicRoughness.baseColorTexture.setTexture(null);
            m.pbrMetallicRoughness.setBaseColorFactor(colorHex);
        });
    }

    async function applyPremiumMaterial(materialName, colorHex, texturePath, roughness, el) {
        recordHistory(); // 记录旧状态
        updateStepUI(el);
        currentSelections[materialName] = { color: colorHex, texture: texturePath, roughness: roughness };
        
        updateTotalPriceUI();
        if (!viewer.model) return;
        for (const m of viewer.model.materials.filter(m => m.name.includes(materialName))) {
            m.pbrMetallicRoughness.setBaseColorFactor(colorHex);
            m.pbrMetallicRoughness.setRoughnessFactor(roughness);
            if (texturePath) {
                const t = await viewer.createTexture(texturePath);
                if (m.normalTexture) m.normalTexture.setTexture(t);
            }
        }
    }
    
    function changeOnlyColor(part, colorHex, el) {
        recordHistory(); // 记录旧状态
        updateStepUI(el);
        currentSelections[part].color = colorHex;
        if (!viewer.model) return;
        const materials = viewer.model.materials.filter(m => m.name.includes(part));
        materials.forEach(m => {
            m.pbrMetallicRoughness.setBaseColorFactor(colorHex);
        });
    }
    
    function changeStep(direction) {
        const steps = document.querySelectorAll('.build-step');
        steps[currentStep - 1].classList.remove('active');
        currentStep += direction;
        if (currentStep > totalSteps) currentStep = 1;
        if (currentStep < 1) currentStep = totalSteps;
        steps[currentStep - 1].classList.add('active');
        
        document.getElementById('stepCounter').innerText = `Material ${currentStep} / ${totalSteps}`;

        if(currentStep === 1 || currentStep === 2) {
            viewer.cameraOrbit = '90deg 75deg 105%';
        } else if(currentStep === 7) {
            viewer.cameraOrbit = '0deg 180deg 105%';
        } else if(currentStep === 6) {
            viewer.cameraOrbit = '90deg 90deg 105%';
        }
    }

    async function saveDesign() {
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit_design'); // 检查是否正在编辑旧设计
    
    // 如果是全新创建，直接执行保存；如果是编辑旧设计，弹出选择
    if (editId) {
        Swal.fire({
            title: 'Save Design Options',
            text: 'Would you like to overwrite your current design or save it as a new copy?',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Overwrite Current',
            denyButtonText: 'Save as New',
            confirmButtonColor: '#000',
            denyButtonColor: '#555',
        }).then((result) => {
            if (result.isConfirmed) {
                // 覆盖当前：发送旧 ID
                executeSave(editId);
            } else if (result.isDenied) {
                // 另存为新：不发送 ID
                executeSave(null);
            }
        });
    } else {
        executeSave(null);
    }
}

async function executeSave(targetId) {
    Swal.fire({
        title: 'Processing...',
        text: 'Checking for duplicates and generating 3D snapshot...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const screenshot = viewer.toDataURL('image/webp', 0.8); 
        const formData = new FormData();
        formData.append('pro_id', <?php echo $pro_id; ?>);
        formData.append('custom_design', JSON.stringify(currentSelections));
        formData.append('custom_image', screenshot);
        formData.append('add_custom_cart', '1');

        if (targetId) formData.append('update_design_id', targetId);

        const res = await fetch('save_custom_design.php', { method: 'POST', body: formData });
        const result = await res.json();
        
        if (result.success) {
            // --- 【交互修复：智能提示】 ---
            const isDuplicate = result.is_duplicate;
            Swal.fire({ 
                icon: isDuplicate ? 'info' : 'success', 
                title: isDuplicate ? 'Already Exists' : 'Design Saved!', 
                text: isDuplicate ? 'This exact design is already in your collection.' : 'Redirecting to product page...',
                timer: 1500, 
                showConfirmButton: false 
            }).then(() => {
                window.location.href = `product_details.php?pro_id=<?php echo $pro_id; ?>&active_design=${result.design_id}`;
            });
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to save your design.', 'error');
    }
}

    async function switchModel(type, el) {
        // 1. 更新 UI 按钮状态
        updateFixedUI(el);
        
        // 2. 显示加载动画
        viewer.classList.add('is-loading');

        // 3. 根据类型确定模型路径 (请确保路径与你服务器上的文件名一致)
        let modelPath = "../includes/models/";
        if (type === 'single') {
            modelPath += "single_shoe1.glb";
        } else if (type === 'stacked') {
            modelPath += "pair_stacked_shoe1.glb";
        } else {
            modelPath += "pair_spread_shoe1.glb"; // 默认的 SPREAD 视图
        }

        // 4. 更改模型源
        viewer.src = modelPath;

        // 5. 【核心】：监听新模型加载完成事件，重新应用当前的颜色和材质
        viewer.addEventListener('load', async () => {
            await applySavedColors(); // 将用户当前的配置重新涂装到新模型上
            
            // 6. 移除加载遮罩
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    viewer.classList.remove('is-loading');
                });
            });
        }, { once: true });
    }

    function applyInspirationTag(tag) {
        const input = document.getElementById('aiStyleInput');
        if (!input) return;

        input.value = tag;
        input.focus();
        input.select();
    }

    async function generateAIDesign() {
    const input = document.getElementById('aiStyleInput');
    const btn = document.getElementById('aiGenBtn');
    const style = input.value.trim();

    if (!style) {
        Swal.fire('Input Required', 'Please describe your style first!', 'info');
        return;
    }

    // UI 反馈：开始加载
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    viewer.classList.add('is-loading'); // 利用你现有的加载遮罩[cite: 24]

    try {
        const response = await fetch('gemini_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: style, mode: 'designer' })
        });
        
        const data = await response.json();
        const design = JSON.parse(data.reply); // 解析 AI 返回的颜色 JSON
        recordHistory();
        
        for (const part in design) {
        // 转换为和你 currentSelections 一致的键名检查
        if (currentSelections[part]) {
            const config = design[part];
            
            // 【核心修复】：更新颜色的同时，必须清空材质纹理，否则颜色会被图片遮挡
            currentSelections[part].color = config.color;
            currentSelections[part].roughness = config.roughness || 0.5;
            currentSelections[part].texture = null; // 清除纹理

            const materials = viewer.model.materials.filter(m => m.name.includes(part));
            materials.forEach(m => {
                // 物理移除模型上的法线贴图
                if (m.normalTexture) m.normalTexture.setTexture(null);
                
                m.pbrMetallicRoughness.setBaseColorFactor(config.color);
                m.pbrMetallicRoughness.setRoughnessFactor(config.roughness || 0.5);
            });
        }
    }
    // 生成后同步价格（因为纹理被清空，价格应回到基础价）
    updateTotalPriceUI();

        Swal.fire({
            icon: 'success',
            title: 'Design Generated!',
            text: 'AI has applied the "' + style + '" theme to your shoe.',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });

    } catch (error) {
        console.error('AI Design Error:', error);
        Swal.fire('AI Error', 'Failed to generate design. Please try a different description.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'GEN';
        viewer.classList.remove('is-loading');
    }
}
// --- 【全新 Workflow 引导逻辑】 ---
const tourSteps = [
    {
        target: '#viewer-box',
        title: '👟 3D Showcase',
        content: 'Welcome! Rotate the shoe 360° to inspect your design. Use the mouse wheel to zoom in on textures.',
        pos: 'center' // 修改点：第一步改用居中，避免被底部遮挡
    },
    {
        target: '.ai-header',
        title: '✨ AI Magic',
        content: 'Type styles like "Volcano" or "Ocean" and hit GEN to get instant inspiration.',
        pos: 'left' // 气泡在左，箭头向右指
    },
    {
        target: '.fixed-nav-area',
        title: '🕒 History Control',
        content: 'Experiment freely! You can always UNDO or REDO your material changes here.',
        pos: 'left'
    },
    {
        target: '.step-nav-header',
        title: '🎯 Step-by-Step',
        content: 'Navigate through all 7 parts of the shoe. Each part can be customized individually.',
        pos: 'top' // 气泡在上，箭头向下指
    },
    {
        target: '.color-grid',
        title: '🎨 Texture & Color',
        content: 'Pick from Leather, Fabric, or Tech Blue, then choose your perfect shade.',
        pos: 'top'
    },
    {
        target: '.builder-footer',
        title: '💾 Save Masterpiece',
        content: 'Finished your design? Save it to your collection and it will appear in the shop!',
        pos: 'left'
    }
];

let currentTourIdx = 0;

function startTutorial() {
    // 移除所有 localStorage 检查，直接显示遮罩和步骤
    document.getElementById('tutorial-overlay').style.display = 'block';
    document.getElementById('skipTutorialBtn').style.display = 'block';
    
    // 重置进度索引（防止从之前中断的地方开始）
    currentTourIdx = 0; 
    showTourStep();
}

function showTourStep() {
    const step = tourSteps[currentTourIdx];
    const targetEl = document.querySelector(step.target);
    const tooltip = document.getElementById('tutorialTooltip');
    const arrow = document.getElementById('tutorial-arrow');
    
    // 1. 高亮切换
    document.querySelectorAll('.tutorial-highlight').forEach(el => el.classList.remove('tutorial-highlight'));
    targetEl.classList.add('tutorial-highlight');
    
    // 2. 内容更新
    document.getElementById('tourTitle').innerText = step.title;
    document.getElementById('tourContent').innerText = step.content;
    document.getElementById('stepProgress').innerText = `${currentTourIdx + 1} / ${tourSteps.length}`;
    
    // 3. 动态定位计算
    const rect = targetEl.getBoundingClientRect();
    tooltip.style.display = 'block';
    arrow.style.display = 'block';
    arrow.className = 'tutorial-arrow'; // 重置箭头样式

    const gap = 30; // 气泡与目标的距离
    
    if (step.pos === 'bottom') {
        tooltip.style.top = (rect.bottom + gap) + 'px';
        tooltip.style.left = (rect.left + rect.width/2 - 160) + 'px';
        arrow.classList.add('arrow-top');
        arrow.style.top = (rect.bottom + 10) + 'px';
        arrow.style.left = (rect.left + rect.width/2 - 15) + 'px';
    } 
    else if (step.pos === 'left') {
        tooltip.style.top = rect.top + 'px';
        tooltip.style.left = (rect.left - 320 - gap) + 'px';
        arrow.classList.add('arrow-right');
        arrow.style.top = (rect.top + 20) + 'px';
        arrow.style.left = (rect.left - 20) + 'px';
    } 
    else if (step.pos === 'top') {
        tooltip.style.top = (rect.top - 220) + 'px';
        tooltip.style.left = (rect.left + rect.width/2 - 160) + 'px';
        arrow.classList.add('arrow-bottom');
        arrow.style.top = (rect.top - 20) + 'px';
        arrow.style.left = (rect.left + rect.width/2 - 15) + 'px';
    }
}

function nextTourStep() {
    currentTourIdx++;
    if (currentTourIdx < tourSteps.length) {
        showTourStep();
    } else {
        endTutorial();
    }
}

function endTutorial() {
    document.getElementById('tutorial-overlay').style.display = 'none';
    document.getElementById('tutorialTooltip').style.display = 'none';
    document.getElementById('tutorial-arrow').style.display = 'none';
    document.getElementById('skipTutorialBtn').style.display = 'none';
    document.querySelectorAll('.tutorial-highlight').forEach(el => el.classList.remove('tutorial-highlight'));
    localStorage.setItem('ss_tour_done', 'true');
}

// 自动启动：检测模型加载状态
window.addEventListener('load', () => {
    setTimeout(startTutorial, 1000); 
});

// =================================================================
// 【统一封装】：全新 3D 模特场景与摄影棚自由切换引擎（已加固防死锁）
// =================================================================
let backupModelSrc = "";
let isScenePreviewing = false;
let isDraggingModel = false;
let previousMouseX = 0;
let modelRotationY = 0;

function togglePreviewMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('previewSceneMenu');
    if (!menu) return;
    menu.style.display = (menu.style.display === 'flex') ? 'none' : 'flex';
}

// =================================================================
// 【终极防丢失重构】：多场景无缝自由流转引擎（100% 锁定用户色彩配置）
// =================================================================
async function applyScene(sceneType, el) {
    const viewerBox = document.getElementById('viewer-box');
    let modelViewer = document.querySelector('#shoe-viewer');
    if (!viewerBox || !modelViewer) return;

    // 1. 更新菜单内按钮的高亮视觉状态
    document.querySelectorAll('.preview-menu-btn').forEach(btn => btn.classList.remove('active'));
    if (el) el.classList.add('active');

    // ==========================================
    // 情况 A：用户点击回归初始的 "3D Shoe (Studio)"
    // ==========================================
    if (sceneType === 'default') {
        if (!isScenePreviewing) return; // 如果已经是初始鞋子，不做任何操作

        // 激活平滑过渡遮罩并标记状态结束
        modelViewer.classList.add('is-loading');
        isScenePreviewing = false;
        isDraggingModel = false;

        // 确定需要复原的静态鞋子路径
        const fallbackSrc = backupModelSrc || "../includes/models/pair_spread_shoe1.glb";

        // 物理消灭死锁：暴力销毁带有复杂骨骼的小人模型，彻底回收 WebGL 显存
        modelViewer.remove();

        // 原地重新搭建纯净、无任何动画残留的初始渲染器
        const newViewer = document.createElement('model-viewer');
        newViewer.id = 'shoe-viewer';
        newViewer.className = 'is-loading';
        newViewer.setAttribute('camera-controls', '');
        newViewer.setAttribute('touch-action', 'pan-y');
        newViewer.setAttribute('shadow-intensity', '2');
        newViewer.setAttribute('environment-image', 'neutral');
        newViewer.setAttribute('exposure', '1');
        newViewer.setAttribute('camera-orbit', '45deg 75deg 105%');

        // 【关键修复点 1】：强行重置页面最顶部可能冲突的所有指针作用域
        window.viewer = newViewer;

        // 重新为主流元素动态绑定手势点下追踪器
        newViewer.addEventListener('pointerdown', (e) => {
            if (!isScenePreviewing) return;
            isDraggingModel = true;
            previousMouseX = e.clientX;
            newViewer.style.cursor = 'grabbing';
        });

        // 【关键修复点 2】：引入递归材质网格校验引擎，替代不稳定的随机延时
        const safelyApplyDesign = () => {
            // 如果底层 WebGL 材质树尚未解析出来，等 30ms 重新检测，直到百分百抓到网格为止
            if (!newViewer.model || !newViewer.model.materials || newViewer.model.materials.length === 0) {
                setTimeout(safelyApplyDesign, 30);
                return;
            }
            
            // 材质完全就绪，开始喷涂用户的设计配置
            if (typeof applySavedColors === 'function') {
                applySavedColors(); 
            }
            
            // 材质喷涂完后，给予渲染管线一帧的双重缓冲重绘时间，随后丝滑淡入
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    newViewer.classList.remove('is-loading');
                });
            });
        };

        // 先挂载单次 load 监听，鞋子一就绪立刻还原材质颜色
        newViewer.addEventListener('load', safelyApplyDesign, { once: true });

        // 将全新干净的鞋子视图装载进网页
        viewerBox.appendChild(newViewer);
        newViewer.src = fallbackSrc;
        return;
    }

    // ==========================================
    // 情况 B：用户在 Park / Gym / City 等环境间切换
    // ==========================================
    const envMapPath = `../includes/models/environments/${sceneType}.jpg`;

    if (isScenePreviewing) {
        // 如果已经在户外场景，只替换背景和环境光，绝对不重复重载小人 src
        modelViewer.setAttribute('skybox-image', envMapPath);
        modelViewer.setAttribute('environment-image', envMapPath);
        modelViewer.classList.remove('is-loading');
        return;
    }

    // 首次从鞋子切入小人跑动场景时的初始化
    modelViewer.classList.add('is-loading');
    backupModelSrc = modelViewer.src; // 备份当前鞋子是 SINGLE, SPREAD 还是 STACKED
    isScenePreviewing = true;

    modelViewer.src = "../includes/models/running with ss.glb";
    modelViewer.autoplay = true;
    modelViewer.animationName = 'run';

    // 剥离摄影棚原生镜头控制，改用鼠标捕捉控制人体 Y 轴原地自转
    modelViewer.removeAttribute('camera-controls');
    modelRotationY = 0;
    modelViewer.setAttribute('orientation', `0deg 0deg ${modelRotationY}deg`);
    modelViewer.cameraTarget = 'auto';

    modelViewer.setAttribute('skybox-image', envMapPath);
    modelViewer.setAttribute('environment-image', envMapPath);
    modelViewer.cameraOrbit = '45deg 75deg 150%';

    // 首次加载跑动小人模型的异步上色监听器（同样进行双重固化检测）
    const safelyApplyModelDesign = () => {
        if (!modelViewer.model || !modelViewer.model.materials || modelViewer.model.materials.length === 0) {
            setTimeout(safelyApplyModelDesign, 30);
            return;
        }
        if (typeof applySavedColors === 'function') {
            applySavedColors(); // 给跑步小人脚上的鞋子上色
        }
        modelViewer.play();
        
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                modelViewer.classList.remove('is-loading');
            });
        });
    };

    modelViewer.addEventListener('load', safelyApplyModelDesign, { once: true });
}

// ==========================================
// 【全动态代理】：兼容动态重组后的手势捕捉驱动引擎
// ==========================================
document.addEventListener('pointerdown', (e) => {
    const activeViewer = document.querySelector('#shoe-viewer');
    if (!isScenePreviewing || !activeViewer || e.target !== activeViewer) return;
    isDraggingModel = true;
    previousMouseX = e.clientX;
    activeViewer.style.cursor = 'grabbing';
});

window.addEventListener('pointermove', (e) => {
    if (!isScenePreviewing || !isDraggingModel) return;
    
    const activeViewer = document.querySelector('#shoe-viewer');
    if (!activeViewer) return;

    const deltaX = e.clientX - previousMouseX;
    previousMouseX = e.clientX;

    modelRotationY += deltaX * 0.6; 
    activeViewer.setAttribute('orientation', `0deg 0deg ${modelRotationY}deg`);
});

window.addEventListener('pointerup', () => {
    const activeViewer = document.querySelector('#shoe-viewer');
    if (activeViewer) {
        isDraggingModel = false;
        if (isScenePreviewing) activeViewer.style.cursor = 'grab';
    }
});

// 辅助：点击空白处自动收起场景选择下拉菜单
document.addEventListener('click', () => {
    const menu = document.getElementById('previewSceneMenu');
    if (menu) menu.style.display = 'none';
});
</script>