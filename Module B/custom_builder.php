<?php include '../includes/header.php'; ?>

<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>

<style>
    .builder-layout { display: flex; height: 85vh; background: #f8f9fa; }
    
    /* 左侧 3D 展示区 */
    #viewer-box { flex: 1; position: relative; cursor: grab; }
    model-viewer { width: 100%; height: 100%; --poster-color: transparent; }

    /* 右侧配置面板 */
    .config-panel { width: 380px; background: white; border-left: 1px solid #ddd; padding: 30px; overflow-y: auto; }
    .step-title { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
    .part-name { font-size: 20px; font-weight: bold; margin-bottom: 20px; }

    .color-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 30px; }
    .swatch { 
        width: 100%; aspect-ratio: 1; border-radius: 50%; border: 2px solid transparent; 
        cursor: pointer; transition: 0.2s; position: relative;
    }
    .swatch:hover { transform: scale(1.1); }
    .swatch.active { border-color: #000; }
    
    .btn-checkout { 
        width: 100%; background: #000; color: #fff; border: none; padding: 18px; 
        font-weight: bold; border-radius: 5px; cursor: pointer; margin-top: 20px;
    }
</style>

<div class="builder-layout">
    <div id="viewer-box">
        <model-viewer id="shoe-viewer" 
            src="../includes/models/shoe2.glb" 
            camera-controls 
            auto-rotate 
            shadow-intensity="1" 
            environment-image="neutral"
            exposure="1">
        </model-viewer>
    </div>

    <div class="config-panel">
        <h3 class="fw-bold mb-4">Shoe Customizer</h3>
        
        <div class="custom-group">
            <p class="step-title">Step 1</p>
            <p class="part-name">Main Body (Upper)</p>
            <div class="color-grid">
                <div class="swatch active" style="background: #ffffff;" onclick="changePartColor('Upper', '#ffffff', this)"></div>
                <div class="swatch" style="background: #222222;" onclick="changePartColor('Upper', '#222222', this)"></div>
                <div class="swatch" style="background: #E7352B;" onclick="changePartColor('Upper', '#E7352B', this)"></div>
                <div class="swatch" style="background: #008060;" onclick="changePartColor('Upper', '#008060', this)"></div>
            </div>
        </div>

        <div class="custom-group">
        <p class="part-name">Upper Material & Color</p>
            <div class="color-grid">
                <div class="swatch" 
                    style="background: #eee; border: 1px solid #ddd;" 
                    onclick="applyPremiumMaterial('Upper', '#ffffff', '../includes/models/textures/leather_normal.jpg', 0.8, this)">
                </div>

                <div class="swatch" 
                    style="background: #E7352B;" 
                    onclick="applyPremiumMaterial('Upper', '#E7352B', '../includes/models/textures/leather_normal.jpg', 0.9, this)">
                </div>

                <div class="swatch" 
                    style="background: #111;" 
                    onclick="applyPremiumMaterial('Upper', '#111111', null, 0.05, this)">
                </div>
            </div>
        </div>

        <div class="mt-5 pt-4 border-top">
            <div class="d-flex justify-content-between h4 fw-bold">
                <span>Total</span>
                <span>RM 829.00</span>
            </div>
            <button class="btn-checkout">ADD TO CART</button>
        </div>
    </div>
</div>

<script>
    /**
     * @param {string} materialName 模型内部的材质名（第一步查出来的）
     * @param {string} colorHex 要变的颜色
     * @param {HTMLElement} el 选中的 DOM 元素
     */
    function changePartColor(materialName, colorHex, el) {
        const viewer = document.querySelector('#shoe-viewer');
        
        // 1. UI 反馈：切换选中状态
        const parent = el.parentElement;
        parent.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
        el.classList.add('active');

        // 2. 核心逻辑：修改模型材质
        if (!viewer.model) return;

        // 查找包含指定名称的材质
        const material = viewer.model.materials.find(m => m.name.includes(materialName));
        
        if (material) {
            // 设置基础颜色因子 (RGBA 格式，代码会自动转换 Hex)
            material.pbrMetallicRoughness.setBaseColorFactor(colorHex);
        } else {
            console.error("找不到材质: " + materialName + "。请确认第一步查出的名字是否正确。");
        }
    }

        /**
     * @param {string} materialName 材质名 (如 'Upper')
     * @param {string} colorHex 颜色
     * @param {string} texturePath 贴图路径 (如果是 null 则移除纹理)
     * @param {float} roughness 粗糙度 (0: 亮面, 1: 全哑光)
     */
    async function applyPremiumMaterial(materialName, colorHex, texturePath, roughness, el) {
        const viewer = document.querySelector('#shoe-viewer');
        if (!viewer.model) return;

        // UI 反馈
        el.parentElement.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
        el.classList.add('active');

        const material = viewer.model.materials.find(m => m.name.includes(materialName));
        
        if (material) {
            // 1. 设置颜色
            material.pbrMetallicRoughness.setBaseColorFactor(colorHex);
            
            // 2. 设置粗糙度 (皮革通常 0.7-0.9, 漆皮 0.1)
            material.pbrMetallicRoughness.setRoughnessFactor(roughness);

            // 3. 动态加载并应用法线贴图 (纹理)
            if (texturePath) {
                const texture = await viewer.createTexture(texturePath);
                material.normalTexture.setTexture(texture);
                // 调整纹理强度 (0 到 1 之间)
                material.normalTexture.setScale(0.8); 
            } else {
                material.normalTexture.setTexture(null); // 恢复平滑
            }
        }
    }
</script>

<?php include '../includes/footer.php'; ?>