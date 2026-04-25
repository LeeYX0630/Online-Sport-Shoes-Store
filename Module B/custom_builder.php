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
            <button class="btn-checkout" onclick="saveDesign()">SAVE DESIGN</button>
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
        const screenshot = viewer.toDataURL('image/webp', 0.8); 
        const formData = new FormData();
        formData.append('pro_id', <?php echo $pro_id; ?>);
        formData.append('custom_design', JSON.stringify(currentSelections));
        formData.append('custom_image', screenshot);
        formData.append('add_custom_cart', '1');
        
        // --- 【核心修改 3】：保存时告诉后端我们是在更新旧设计 ---
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit_design')) {
            formData.append('update_design_id', urlParams.get('edit_design'));
        }
        // ----------------------------------------------------

        const res = await fetch('save_custom_design.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            Swal.fire({ icon: 'success', title: 'Design Saved!', timer: 1500, showConfirmButton: false })
            .then(() => window.location.href = `product_details.php?pro_id=<?php echo $pro_id; ?>&active_design=${result.design_id}`);
        }
    }
</script>

<?php include '../includes/footer.php'; ?>