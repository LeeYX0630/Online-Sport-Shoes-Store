<?php 
include '../includes/db_connection.php';
$pro_id = isset($_GET['pro_id']) ? intval($_GET['pro_id']) : 16; 
include '../includes/header.php'; 
?>

<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>

<style>
    :root { --primary-green: #008060; --nike-gray: #f5f5f5; }
    body { background-color: #fff; overflow: hidden; }
    
    .builder-layout { display: flex; height: calc(100vh - 80px); }
    
    /* 左侧 3D 舞台 */
    #viewer-box { flex: 1; background: radial-gradient(#ffffff, #e5e5e5); position: relative; }
    
    /* 核心修复：优化透明度过渡 */
    model-viewer { 
        width: 100%; 
        height: 100%; 
        outline: none; 
        transition: opacity 0.5s ease-in-out; /* 稍微加长淡入时间，增加高级感 */
        opacity: 1;
        visibility: visible;
    }

    /* 严格隐藏状态：确保模型在完全准备好前绝对不可见 */
    model-viewer.is-loading {
        opacity: 0 !important;
        visibility: hidden;
        pointer-events: none; /* 防止加载时误触 */
    }

    /* 右侧配置面板 - Nike 风格 */
    .config-panel { width: 420px; background: white; border-left: 1px solid #e5e5e5; display: flex; flex-direction: column; }
    .config-header { padding: 30px 30px 10px; }
    .config-scroll { flex: 1; overflow-y: auto; padding: 0 30px 100px; }
    
    .build-step { margin-bottom: 40px; border-bottom: 1px solid #f0f0f0; padding-bottom: 30px; }
    .step-num { font-size: 11px; font-weight: 800; color: #888; text-transform: uppercase; margin-bottom: 5px; display: block; }
    .part-name { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #111; }

    .color-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
    .swatch { 
        width: 100%; aspect-ratio: 1; border-radius: 50%; border: 2px solid transparent; 
        cursor: pointer; transition: 0.2s; position: relative; box-shadow: inset 0 0 5px rgba(0,0,0,0.05);
    }
    .swatch:hover { transform: scale(1.1); }
    .swatch.active { border-color: #000; box-shadow: 0 0 0 2px #fff, 0 0 0 4px #000; }
    
    .builder-footer { padding: 20px 30px; border-top: 1px solid #e5e5e5; background: white; }
    .btn-checkout { 
        width: 100%; background: #111; color: #fff; border: none; padding: 18px; 
        font-weight: bold; border-radius: 30px; cursor: pointer; transition: 0.3s;
    }
    .btn-checkout:hover { background: #333; transform: translateY(-2px); }

    .config-scroll::-webkit-scrollbar { width: 4px; }
    .config-scroll::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }
</style>

<div class="builder-layout">
    <div id="viewer-box">
        <model-viewer id="shoe-viewer" 
            class="is-loading"
            src="../includes/models/pair_spread_shoe1.glb" 
            camera-controls 
            touch-action="pan-y"
            shadow-intensity="2" 
            environment-image="neutral"
            exposure="1"
            camera-orbit="45deg 75deg 105%">
        </model-viewer>
    </div>

    <div class="config-panel">
        <div class="config-header">
            <h2 class="fw-bold mb-0">SS Max Power Custom</h2>
            <p class="text-muted small">Design your unique pair</p>
        </div>

        <div class="config-scroll">
            <div class="build-step">
                <span class="step-num">Display Settings</span>
                <p class="part-name">View Preference</p>
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-sm btn-outline-dark flex-grow-1" id="btn-single" onclick="switchModel('single')">Single</button>
                    <button class="btn btn-sm btn-outline-dark flex-grow-1" id="btn-pair" onclick="switchModel('pair')">Spread</button>
                    <button class="btn btn-sm btn-outline-dark flex-grow-1" id="btn-stacked" onclick="switchModel('stacked')">Stacked</button>
                </div>

                <p class="small fw-bold mb-2">Display Pose</p>
                <div class="color-grid">
                    <div class="swatch" title="Side View" style="background: #eee; border-radius: 4px;" onclick="setPose('90deg 75deg 105%', this)">S</div>
                    <div class="swatch" title="Top View" style="background: #eee; border-radius: 4px;" onclick="setPose('0deg 0deg 105%', this)">T</div>
                    <div class="swatch" title="Sole View" style="background: #eee; border-radius: 4px;" onclick="setPose('0deg 180deg 105%', this)">B</div>
                </div>
            </div>
            
            <div class="build-step">
                <span class="step-num">Step 01</span>
                <p class="part-name">Upper Material</p>
                <div class="color-grid">
                    <div class="swatch active" title="White Leather" style="background: #ffffff;" 
                         onclick="applyPremiumMaterial('Outupper', '#ffffff', '../includes/models/textures/leather_normal.jpg', 0.8, this)"></div>
                    <div class="swatch" title="Black Leather" style="background: #222;" 
                         onclick="applyPremiumMaterial('Outupper', '#222222', '../includes/models/textures/leather_normal.jpg', 0.8, this)"></div>
                    <div class="swatch" title="Grey Jersey" style="background: #888;" 
                         onclick="applyPremiumMaterial('Outupper', '#888888', '../includes/models/textures/jersey_melange_normal.jpg', 0.95, this)"></div>
                    <div class="swatch" title="Tech Blue" style="background: #2b5d8a;" 
                         onclick="applyPremiumMaterial('Outupper', '#2b5d8a', '../includes/models/textures/blue_metal_plate_normal.jpg', 0.5, this)"></div>
                    <div class="swatch" title="Volt Green" style="background: #dfff00;" 
                         onclick="applyPremiumMaterial('Outupper', '#dfff00', null, 0.6, this)"></div>
                </div>
            </div>

            <div class="build-step">
                <span class="step-num">Step 02</span>
                <p class="part-name">Side Accents (Style)</p>
                <div class="color-grid">
                    <div class="swatch" style="background: #000;" onclick="changePartColor('Style', '#000000', this)"></div>
                    <div class="swatch" style="background: #E7352B;" onclick="changePartColor('Style', '#E7352B', this)"></div>
                    <div class="swatch" style="background: #ffffff; border: 1px solid #ddd;" onclick="changePartColor('Style', '#ffffff', this)"></div>
                    <div class="swatch" style="background: #FFD700;" onclick="changePartColor('Style', '#FFD700', this)"></div>
                    <div class="swatch" style="background: #008060;" onclick="changePartColor('Style', '#008060', this)"></div>
                </div>
            </div>

            <div class="build-step">
                <span class="step-num">Step 03</span>
                <p class="part-name">Laces</p>
                <div class="color-grid">
                    <div class="swatch" style="background: #ffffff; border: 1px solid #ddd;" onclick="changePartColor('Laces', '#ffffff', this)"></div>
                    <div class="swatch" style="background: #333;" onclick="changePartColor('Laces', '#333333', this)"></div>
                    <div class="swatch" style="background: #E7352B;" onclick="changePartColor('Laces', '#E7352B', this)"></div>
                    <div class="swatch" style="background: #55acee;" onclick="changePartColor('Laces', '#55acee', this)"></div>
                    <div class="swatch" style="background: #FF6B00;" onclick="changePartColor('Laces', '#FF6B00', this)"></div>
                </div>
            </div>

            <div class="build-step">
                <span class="step-num">Step 04</span>
                <p class="part-name">Tongue</p>
                <div class="color-grid">
                    <div class="swatch" style="background: #222;" onclick="changePartColor('Tongue', '#222222', this)"></div>
                    <div class="swatch" style="background: #ffffff; border: 1px solid #ddd;" onclick="changePartColor('Tongue', '#ffffff', this)"></div>
                    <div class="swatch" style="background: #aaa;" onclick="changePartColor('Tongue', '#aaaaaa', this)"></div>
                </div>
            </div>

            <div class="build-step">
                <span class="step-num">Step 05</span>
                <p class="part-name">Midsole</p>
                <div class="color-grid">
                    <div class="swatch" style="background: #ffffff; border: 1px solid #ddd;" onclick="changePartColor('Midsole', '#ffffff', this)"></div>
                    <div class="swatch" style="background: #000;" onclick="changePartColor('Midsole', '#000000', this)"></div>
                    <div class="swatch" style="background: #eee;" onclick="changePartColor('Midsole', '#eeeeee', this)"></div>
                    <div class="swatch" style="background: #FFD700;" onclick="changePartColor('Midsole', '#FFD700', this)"></div>
                </div>
            </div>

            <div class="build-step">
                <span class="step-num">Step 06</span>
                <p class="part-name">Outsole (Traction)</p>
                <div class="color-grid">
                    <div class="swatch" style="background: #111;" onclick="changePartColor('Outsole', '#111111', this)"></div>
                    <div class="swatch" style="background: #008060;" onclick="changePartColor('Outsole', '#008060', this)"></div>
                    <div class="swatch" style="background: #E7352B;" onclick="changePartColor('Outsole', '#E7352B', this)"></div>
                    <div class="swatch" style="background: #888;" onclick="changePartColor('Outsole', '#888888', this)"></div>
                </div>
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

    let currentSelections = {
        'Outupper': { color: '#ffffff', texture: '../includes/models/textures/leather_normal.jpg', roughness: 0.8 },
        'Style': { color: '#000000', texture: null, roughness: 0.5 },
        'Laces': { color: '#ffffff', texture: null, roughness: 0.5 },
        'Tongue': { color: '#222222', texture: null, roughness: 0.5 },
        'Midsole': { color: '#525248', texture: null, roughness: 0.5 },
        'Outsole': { color: '#111111', texture: null, roughness: 0.5 }
    };

    // 初始加载逻辑：增加双重帧检查
    viewer.addEventListener('load', async () => {
        await applySavedColors();
        // 强制浏览器在显示前至少渲染两帧，确保材质已注入 GPU
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                viewer.classList.remove('is-loading');
            });
        });
    }, { once: true });

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('btn-pair').classList.add('active');
    });

    // 模型切换逻辑修复
    function switchModel(type) {
        let modelPath;
        if (type === 'single') modelPath = '../includes/models/single_shoe1.glb';
        else if (type === 'pair') modelPath = '../includes/models/pair_spread_shoe1.glb';
        else if (type === 'stacked') modelPath = '../includes/models/pair_stacked_shoe1.glb';
        
        document.getElementById('btn-single').classList.toggle('active', type === 'single');
        document.getElementById('btn-pair').classList.toggle('active', type === 'pair');
        document.getElementById('btn-stacked').classList.toggle('active', type === 'stacked');
        
        // 1. 立即隐藏当前模型
        viewer.classList.add('is-loading');
        
        // 2. 更改路径
        viewer.src = modelPath;

        // 3. 监听加载事件
        viewer.addEventListener('load', async () => {
            // 确保在新模型上应用所有保存的颜色和材质
            await applySavedColors();
            
            // 4. 关键：确保样式已应用后再淡入
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    viewer.classList.remove('is-loading');
                });
            });
        }, { once: true });
    }

    async function applySavedColors() {
        if (!viewer.model) return;
        
        const texturePromises = [];
        
        Object.keys(currentSelections).forEach(part => {
            const settings = currentSelections[part];
            const materials = viewer.model.materials.filter(m => m.name.includes(part));
            
            materials.forEach(mat => {
                mat.pbrMetallicRoughness.setBaseColorFactor(settings.color);
                mat.pbrMetallicRoughness.setRoughnessFactor(settings.roughness);
                
                if (settings.texture) {
                    const texturePromise = viewer.createTexture(settings.texture).then(texture => {
                        if (mat.normalTexture && texture) {
                            mat.normalTexture.setTexture(texture);
                        }
                    }).catch(e => console.error("Texture Load Error:", e));
                    texturePromises.push(texturePromise);
                } else {
                    if (mat.normalTexture) mat.normalTexture.setTexture(null);
                }
            });
        });
        
        await Promise.all(texturePromises);
    }

    function setPose(orbit, el) {
        viewer.cameraOrbit = orbit;
        if (el) {
            const parent = el.parentElement;
            parent.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
            el.classList.add('active');
        }
    }

    function changePartColor(materialName, colorHex, el) {
        updateUI(el);
        currentSelections[materialName] = { color: colorHex, texture: null, roughness: 0.5 };
        if (!viewer.model) return;
        const materials = viewer.model.materials.filter(m => m.name.includes(materialName));
        materials.forEach(material => {
            if (material.pbrMetallicRoughness.baseColorTexture) {
                material.pbrMetallicRoughness.baseColorTexture.setTexture(null);
            }
            material.pbrMetallicRoughness.setBaseColorFactor(colorHex);
        });
    }

    async function applyPremiumMaterial(materialName, colorHex, texturePath, roughness, el) {
        updateUI(el);
        currentSelections[materialName] = { color: colorHex, texture: texturePath, roughness: roughness };
        if (!viewer.model) return;
        const materials = viewer.model.materials.filter(m => m.name.includes(materialName));
        for (const material of materials) {
            material.pbrMetallicRoughness.setBaseColorFactor(colorHex);
            material.pbrMetallicRoughness.setRoughnessFactor(roughness);
            if (texturePath) {
                try {
                    const texture = await viewer.createTexture(texturePath);
                    if (material.normalTexture) {
                        material.normalTexture.setTexture(texture);
                        material.normalTexture.setScale(1.0); 
                    }
                } catch (e) { console.error(e); }
            } else {
                if (material.normalTexture) material.normalTexture.setTexture(null);
            }
        }
    }

    function updateUI(el) {
        const parent = el.parentElement;
        parent.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
        el.classList.add('active');
    }

    async function addToCart() {
    const { value: size } = await Swal.fire({
        title: 'Select Your Size (UK)',
        input: 'select',
        inputOptions: { '7': '7', '8': '8', '9': '9', '10': '10', '11': '11' },
        inputPlaceholder: 'Required',
        showCancelButton: true,
        confirmButtonColor: '#008060'
    });

    if (!size) return;

    // --- 【核心修改：拍照逻辑】 ---
    // 获取当前的 3D 快照 (Base64 格式的图片)
    // 注意：我们将图片缩放到较小的尺寸以节省存储
    const viewer = document.querySelector('#shoe-viewer');
    const screenshot = viewer.toDataURL('image/webp', 0.8); 

    const designData = JSON.stringify(currentSelections);
    
    const formData = new FormData();
    formData.append('pro_id', <?php echo $pro_id; ?>);
    formData.append('selected_size', size);
    formData.append('custom_design', designData);
    formData.append('custom_image', screenshot); // 将照片发送给后端
    formData.append('add_custom_cart', '1');

    try {
        const response = await fetch('save_custom_design.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Design Saved!',
                text: 'Your custom SS Max Power is in the bag.',
                confirmButtonText: 'View Cart',
                showCancelButton: true,
                cancelButtonText: 'Continue Designing'
            }).then((res) => {
                if (res.isConfirmed) window.location.href = 'cart.php';
            });
        }
    } catch (error) {
        console.error("Error saving design:", error);
    }
}

async function saveDesign() {
    const viewer = document.querySelector('#shoe-viewer');
    const screenshot = viewer.toDataURL('image/webp', 0.8); 
    const designData = JSON.stringify(currentSelections);

    const formData = new FormData();
    formData.append('pro_id', <?php echo $pro_id; ?>);
    formData.append('custom_design', designData);
    formData.append('custom_image', screenshot);
    formData.append('add_custom_cart', '1');

    try {
        const response = await fetch('save_custom_design.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Design Saved!',
                text: 'Returning to product page to select size.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // 跳转回详情页，并带上设计 ID
                window.location.href = `product_details.php?pro_id=<?php echo $pro_id; ?>&active_design=${result.design_id}`;
            });
        }
    } catch (error) { console.error(error); }
}
</script>

<?php include '../includes/footer.php'; ?>