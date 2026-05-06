<?php 
include '../includes/db_connection.php';
$pro_id = isset($_GET['pro_id']) ? intval($_GET['pro_id']) : 16; 
include '../includes/header.php'; 

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
    .builder-layout { display: flex; height: calc(100vh - 80px); }
    #viewer-box { flex: 1; background: radial-gradient(#ffffff, #e5e5e5); position: relative; }
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

    .step-nav-header { padding: 20px 30px; display: flex; align-items: center; justify-content: space-between; background: #fff; }
    .nav-arrow { font-size: 28px; cursor: pointer; color: #111; transition: 0.2s; background: none; border: none; padding: 5px 20px; }
    .nav-arrow:hover { color: var(--primary-green); transform: scale(1.2); }
    .step-indicator { font-size: 15px; font-weight: 900; color: #111; text-transform: uppercase; }

    .config-scroll { flex: 1; overflow-y: auto; padding: 10px 30px 40px; position: relative; }
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
</style>

<div class="builder-layout">
    <div id="viewer-box">
        <model-viewer id="shoe-viewer" class="is-loading" src="../includes/models/pair_spread_shoe1.glb" 
            camera-controls touch-action="pan-y" shadow-intensity="2" environment-image="neutral" exposure="1" camera-orbit="45deg 75deg 105%">
        </model-viewer>
    </div>

    <div class="config-panel">
        <div class="fixed-section" style="background: linear-gradient(135deg, #f0f7f4 0%, #ffffff 100%); border-bottom: 2px solid #008060;">
            <p class="section-title" style="color: #008060;"><i class="bi bi-magic"></i> AI Dream Generator</p>
            <div style="display: flex; gap: 8px;">
                <input type="text" id="aiStyleInput" class="input-field" 
                    placeholder="e.g. Cyberpunk 2077, Mars Exploration..." 
                    style="margin-bottom: 0; flex: 1; height: 40px; font-size: 13px;">
                <button type="button" class="nav-btn active" id="aiGenBtn" onclick="generateAIDesign()" 
                        style="width: 60px; height: 40px; padding: 0;">GEN</button>
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
        </div>

        <div class="step-nav-header">
            <button class="nav-arrow" onclick="changeStep(-1)"><i class="bi bi-arrow-left-circle-fill"></i></button>
            <div class="step-indicator" id="stepCounter">Material 1 / 7</div>
            <button class="nav-arrow" onclick="changeStep(1)"><i class="bi bi-arrow-right-circle-fill"></i></button>
        </div>

        <div class="config-scroll">
            <div class="build-step active" id="step-1">
                <p class="part-name">Upper Material</p>
                <div class="color-grid">
                    <div class="swatch active" title="Smooth Leather" style="background: #eee;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/leather_normal.jpg', 0.8, this)">
                        <small style="font-weight:900; font-size:10px;">LEATHER</small>
                    </div>
                    <div class="swatch" title="Grey Jersey" style="background: #888;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/jersey_melange_normal.jpg', 0.95, this)">
                        <small style="font-weight:900; font-size:10px;">JERSEY</small>
                    </div>
                    <div class="swatch" title="Tech Blue" style="background: #2b5d8a;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/corrugated_iron_normal.jpg', 0.5, this)">
                        <small style="font-weight:900; font-size:10px;">TECH</small>
                    </div>
                    <div class="swatch" title="Crepe Satin" style="background: #525248;" onclick="applyPremiumMaterial('Outupper', currentSelections['Outupper'].color, '../includes/models/textures/crepe_satin_normal.jpg', 0.7, this)">
                        <small style="font-weight:900; font-size:10px;">SATIN</small>
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
                </div>
                <p class="selected-color-name">Black</p>
            </div>
        </div> 

        <div class="builder-footer">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold">Total Price</span>
                <span class="h4 fw-bold mb-0">RM 829.00</span>
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

    // 如果有旧数据，覆盖默认值
    if (editDesignData) {
        currentSelections = editDesignData;
    }
    // ----------------------------------------------

    viewer.addEventListener('load', async () => {
        await applySavedColors();
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                viewer.classList.remove('is-loading');
            });
        });
    }, { once: true });

    // ... 你原有的 JavaScript 函数保持不变 (applySavedColors, changeStep, etc.) ...
    async function applySavedColors() {
        if (!viewer.model) return;
        const promises = [];
        Object.keys(currentSelections).forEach(part => {
            const set = currentSelections[part];
            const mats = viewer.model.materials.filter(m => m.name.includes(part));
            mats.forEach(mat => {
                mat.pbrMetallicRoughness.setBaseColorFactor(set.color);
                mat.pbrMetallicRoughness.setRoughnessFactor(set.roughness);
                if (set.texture) {
                    promises.push(viewer.createTexture(set.texture).then(t => { if(mat.normalTexture) mat.normalTexture.setTexture(t); }));
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
        updateStepUI(el);
        currentSelections[materialName] = { color: colorHex, texture: texturePath, roughness: roughness };
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

        // --- 核心联动：将 AI 的建议涂装到模型上 ---
        for (const part in design) {
            if (currentSelections[part]) {
                const config = design[part];
                
                // 1. 更新内部数据对象[cite: 24]
                currentSelections[part].color = config.color;
                currentSelections[part].roughness = config.roughness;

                // 2. 物理应用到 3D 模型[cite: 24]
                const materials = viewer.model.materials.filter(m => m.name.includes(part));
                materials.forEach(m => {
                    m.pbrMetallicRoughness.setBaseColorFactor(config.color);
                    m.pbrMetallicRoughness.setRoughnessFactor(config.roughness);
                });
            }
        }

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
</script>

<?php include '../includes/footer.php'; ?>