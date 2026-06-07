<?php
// Module B/mobile_capture.php
$token = $_GET['token'] ?? '';
$mode = $_GET['mode'] ?? 'sizer'; // 'sizer' (量尺码) 或 'wear' (测磨损)

if (empty($token)) {
    die("Error: Session token is missing. Please re-scan QR code.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>AI Sport Shoes Scanner</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <?php if($mode === 'sizer'): ?>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils"></script>
    <?php endif; ?>
    
    <style>
        /* --- 替换后 --- */
body { 
    font-family: 'Segoe UI', Roboto, sans-serif; 
    margin: 0; 
    padding: 0; 
    background-color: #000; 
    color: #fff; 
    display: flex; 
    flex-direction: column; 
    
    /* 核心修复 1：使用动态视口高度，自动减去浏览器顶部 banner 和底部地址栏的高度 */
    height: 100%; 
    height: 100dvh; 
    
    /* 核心修复 2：将其绝对锁死在可视窗口内，防止被上方控件往下“推” */
    width: 100vw;
    position: fixed; 
    top: 0;
    left: 0;
    overflow: hidden; 
}

.action-bar { 
    /* 核心修复 3：适配 iPhone 底部那条“操作小黑条”，防止按钮和黑条重叠 */
    padding: 20px; 
    padding-bottom: calc(20px + env(safe-area-inset-bottom)); 
    
    background: rgba(0, 0, 0, 0.8); 
    backdrop-filter: blur(10px); 
    display: flex; 
    justify-content: center; 
    border-top: 1px solid rgba(255,255,255,0.1); 
    position: relative; 
    z-index: 100; 
}
        .camera-container { flex: 1; position: relative; overflow: hidden; }
        #webcam { width: 100%; height: 100%; object-fit: cover; }
        #output_canvas { position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: 5; pointer-events: none; }
        
        .btn-capture { background-color: #e67e22; color: #fff; border: none; padding: 15px 30px; font-size: 18px; font-weight: 800; border-radius: 50px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: transform 0.2s; box-shadow: 0 4px 15px rgba(230, 126, 34, 0.4); }
        .btn-capture:active { transform: scale(0.95); }
        .btn-capture.sizer-mode { background-color: #00ff9d; color: #000; box-shadow: 0 4px 15px rgba(0, 255, 157, 0.4); }

        /* 覆盖层特效 */
        #instructionOverlay { position: absolute; top: 40px; left: 0; width: 100%; text-align: center; z-index: 50; font-size: 22px; font-weight: 900; text-shadow: 0 2px 8px rgba(0,0,0,1); background: rgba(0,0,0,0.4); padding: 10px 0;}
        #flashLayer { position: absolute; inset: 0; background: white; opacity: 0; z-index: 200; pointer-events: none; transition: opacity 0.15s ease-out; }
    </style>
</head>
<body>
    <div class="camera-container">
        <video id="webcam" autoplay playsinline></video>
        <canvas id="output_canvas"></canvas>
        
        <div id="instructionOverlay" style="display: <?php echo $mode === 'wear' ? 'block' : 'none'; ?>">
            <span style="color:#e67e22;">STEP 1:</span>Take photo of the FRONT side of the shoe<br>
        </div>
        
        <div id="flashLayer"></div>
    </div>

    <div class="action-bar">
        <button id="captureBtn" class="btn-capture <?php echo $mode === 'sizer' ? 'sizer-mode' : ''; ?>" onclick="handleCapture()">
            <i class="bi bi-camera-fill"></i> <span id="btnText"><?php echo $mode === 'sizer' ? 'TAKE PHOTO & SYNC' : 'TAKE PHOTO'; ?></span>
        </button>
    </div>

    <script>
        const video = document.getElementById('webcam');
        const token = '<?php echo $token; ?>';
        const appMode = '<?php echo $mode; ?>';
        const canvasElement = document.getElementById('output_canvas');
        const canvasCtx = canvasElement.getContext('2d');

        // ==== 通用摄像头启动逻辑 ====
        async function startCamera() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error("Missing HTTPS or camera support.");
                
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: "environment", width: 1280, height: 720 }, 
                    audio: false 
                });
                video.srcObject = stream;
                
                video.onloadedmetadata = () => {
                    canvasElement.width = video.videoWidth;
                    canvasElement.height = video.videoHeight;
                    // 根据模式分发初始化任务
                    if (appMode === 'sizer') initSizerMode();
                    else initWearMode();
                };
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Camera Error', text: err.message });
            }
        }
        window.addEventListener('load', startCamera);

        // ==== 通用截取画面 Blob ====
        function takePhotoBlob() {
            return new Promise((resolve) => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = video.videoWidth; 
                canvas.height = video.videoHeight;
                // 添加轻微滤镜增强 AI 识别率
                ctx.filter = 'contrast(1.1) brightness(1.05)';
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => { resolve(blob); }, 'image/jpeg', 0.90);
            });
        }

        // 闪光灯特效
        function triggerFlash() {
            const flash = document.getElementById('flashLayer');
            flash.style.opacity = '1';
            setTimeout(() => { flash.style.opacity = '0'; }, 150);
        }

        // 统一点击事件路由
        function handleCapture() {
            triggerFlash();
            if (appMode === 'sizer') captureAndUploadSizer();
            else captureWearStep();
        }

        /* ================================================================= */
        /* 💥 模式 A: WEAR DETECTOR (测磨损 - 三视角连拍模式)                  */
        /* ================================================================= */
        let wearStepIndex = 0;
        const wearViews = ['front', 'left', 'right'];
        const wearLabels = ['(Front)', '(Left Side)', '(Right Side)'];
        const wearBlobs = {};

        function initWearMode() {
            // 为鞋子绘制一个居中的引导框
            const w = canvasElement.width, h = canvasElement.height;
            canvasCtx.clearRect(0, 0, w, h);
            canvasCtx.fillStyle = "rgba(0, 0, 0, 0.4)";
            
            // 简单的暗角遮罩引导用户聚焦
            canvasCtx.fillRect(0, 0, w, h * 0.25);
            canvasCtx.fillRect(0, h * 0.75, w, h * 0.25);
            
            canvasCtx.strokeStyle = "rgba(230, 126, 34, 0.8)";
            canvasCtx.lineWidth = 3;
            canvasCtx.setLineDash([15, 10]);
            canvasCtx.strokeRect(w * 0.15, h * 0.25, w * 0.7, h * 0.5);
        }

        async function captureWearStep() {
            const currentView = wearViews[wearStepIndex];
            wearBlobs[currentView] = await takePhotoBlob(); // 暂存图片
            wearStepIndex++;

            if (wearStepIndex < 3) {
                // 更新提示文本进入下一拍
                document.getElementById('instructionOverlay').innerHTML = `<span style="color:#e67e22;">STEP ${wearStepIndex + 1}:</span> Take photo of the ${wearLabels[wearStepIndex]}`;
                if (wearStepIndex === 2) {
                    document.getElementById('btnText').innerText = "FINISH & SYNC";
                }
            } else {
                // 3 张全部拍完，执行静默连环上传
                uploadAllWearImages();
            }
        }

        async function uploadAllWearImages() {
            Swal.fire({ title: 'Syncing Data...', html: 'Uploading 3 high-res angles to computer.<br><div class="spinner-border text-warning mt-3"></div>', allowOutsideClick: false, showConfirmButton: false });
            try {
                // 1. 分别静默上传 3 个视角的图片
                for (let view of wearViews) {
                    const subToken = `${token}_${view}`;
                    await fetch(`init_bridge.php?token=${subToken}`); 
                    let fd = new FormData();
                    fd.append('image', wearBlobs[view], 'capture.jpg');
                    fd.append('token', subToken);
                    await fetch('upload_bridge.php', { method: 'POST', body: fd });
                }

                // 2. 激活“主令牌”，让电脑端知道可以开始分析了
                await fetch(`init_bridge.php?token=${token}`);
                let fdMain = new FormData(); 
                fdMain.append('image', wearBlobs['front'], 'capture.jpg'); 
                fdMain.append('token', token);
                await fetch('upload_bridge.php', { method: 'POST', body: fdMain });

                Swal.fire({ icon: 'success', title: 'Sync Complete!', text: 'Your computer is analyzing the shoe. You can close this phone tab now.'}).then(() => window.close());
            } catch (err) {
                Swal.fire('Upload Error', err.message, 'error');
            }
        }


        /* ================================================================= */
        /* 💥 模式 B: SIZER (量脚 - MediaPipe AR 追踪模式)                     */
        /* ================================================================= */
        let pose;
        
        function initSizerMode() {
            if (typeof Pose === 'undefined') return; // 防呆
            
            pose = new Pose({locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`});
            pose.setOptions({
                modelComplexity: 1, 
                smoothLandmarks: true, 
                enableSegmentation: false,
                minDetectionConfidence: 0.7, 
                minTrackingConfidence: 0.7
            });

            pose.onResults((results) => {
                canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                drawA4ReferenceGuide(canvasCtx); // 永远绘制 A4 框
                
                if (results.poseLandmarks) {
                    const leftAnkle = results.poseLandmarks[27];
                    const rightAnkle = results.poseLandmarks[28];
                    let pointsToTrack = null;
                    
                    // 获取脚踝和脚尖坐标
                    if (leftAnkle && leftAnkle.visibility > 0.7) {
                        pointsToTrack = [results.poseLandmarks[27], results.poseLandmarks[31]]; 
                    } else if (rightAnkle && rightAnkle.visibility > 0.7) {
                        pointsToTrack = [results.poseLandmarks[28], results.poseLandmarks[32]];
                    }

                    if (pointsToTrack) drawCoolFootGrid(canvasCtx, pointsToTrack);
                }
            });
            onSizerFrame();
        }

        // 递归渲染帧
        async function onSizerFrame() {
            if (!video.paused && !video.ended) {
                await pose.send({ image: video });
            }
            requestAnimationFrame(onSizerFrame);
        }

        // 绘制高阶标准 A4 对齐框
        function drawA4ReferenceGuide(ctx) {
            const width = ctx.canvas.width;
            const height = ctx.canvas.height;
            const guideHeight = height * 0.65; 
            const guideWidth = guideHeight / 1.414;
            const x = (width - guideWidth) / 2;
            const y = (height - guideHeight) / 2;

            ctx.fillStyle = "rgba(0, 0, 0, 0.4)";
            ctx.fillRect(0, 0, width, y); 
            ctx.fillRect(0, y + guideHeight, width, height - (y + guideHeight)); 
            ctx.fillRect(0, y, x, guideHeight); 
            ctx.fillRect(x + guideWidth, y, width - (x + guideWidth), guideHeight); 

            ctx.setLineDash([10, 8]); 
            ctx.strokeStyle = "rgba(255, 255, 255, 0.7)";
            ctx.lineWidth = 3;
            ctx.strokeRect(x, y, guideWidth, guideHeight);
            ctx.setLineDash([]); 

            ctx.fillStyle = "#00ff9d"; 
            ctx.font = "bold 16px Segoe UI, Arial, sans-serif";
            ctx.textAlign = "center";
            ctx.fillText("ALIGN A4 PAPER INSIDE THIS BOX", width / 2, y - 20);
            ctx.textAlign = "left"; 
        }

        // 绘制科幻风格脚部捕捉网格
        function drawCoolFootGrid(ctx, points) {
            if (!points || points.length < 2) return;
            const width = ctx.canvas.width;
            const height = ctx.canvas.height;

            let minX = width, minY = height, maxX = 0, maxY = 0;
            points.forEach(p => {
                const x = p.x * width;
                const y = p.y * height;
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
            });

            const padding = 50;
            const targetMinX = Math.max(0, minX - padding);
            const targetMinY = Math.max(0, minY - padding);
            const targetMaxX = Math.min(width, maxX + padding);
            const targetMaxY = Math.min(height, maxY + padding);
            const targetWidth = targetMaxX - targetMinX;
            const targetHeight = targetMaxY - targetMinY;

            ctx.shadowBlur = 15;
            ctx.shadowColor = "rgba(0, 255, 157, 0.8)";
            ctx.strokeStyle = "#00ff9d";
            ctx.lineWidth = 5;
            ctx.lineJoin = "round";
            
            const cornerLen = 25; 
            ctx.beginPath(); ctx.moveTo(targetMinX, targetMinY + cornerLen); ctx.lineTo(targetMinX, targetMinY); ctx.lineTo(targetMinX + cornerLen, targetMinY); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(targetMaxX - cornerLen, targetMinY); ctx.lineTo(targetMaxX, targetMinY); ctx.lineTo(targetMaxX, targetMinY + cornerLen); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(targetMinX, targetMaxY - cornerLen); ctx.lineTo(targetMinX, targetMaxY); ctx.lineTo(targetMinX + cornerLen, targetMaxY); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(targetMaxX - cornerLen, targetMaxY); ctx.lineTo(targetMaxX, targetMaxY); ctx.lineTo(targetMaxX, targetMaxY - cornerLen); ctx.stroke();

            ctx.shadowBlur = 0; 
            ctx.strokeStyle = "rgba(0, 255, 157, 0.3)"; 
            ctx.lineWidth = 1;

            for (let i = 1; i < 4; i++) {
                const x = targetMinX + (targetWidth / 4) * i;
                ctx.beginPath(); ctx.moveTo(x, targetMinY); ctx.lineTo(x, targetMaxY); ctx.stroke();
                const y = targetMinY + (targetHeight / 4) * i;
                ctx.beginPath(); ctx.moveTo(targetMinX, y); ctx.lineTo(targetMaxX, y); ctx.stroke();
            }

            const time = Date.now() / 500;
            const scanLineY = targetMinY + targetHeight * (0.5 + 0.5 * Math.sin(time)); 
            ctx.strokeStyle = "rgba(255, 255, 255, 0.6)"; 
            ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(targetMinX, scanLineY); ctx.lineTo(targetMaxX, scanLineY); ctx.stroke();

            ctx.fillStyle = "#00ff9d";
            ctx.font = "bold 14px Segoe UI, sans-serif";
            ctx.fillText("SS_SCAN_BOOT_AQ", targetMinX, targetMinY - 10);
        }

        // 量脚模式单一照片上传
        async function captureAndUploadSizer() {
            Swal.fire({ title: 'Uploading...', html: '<div class="spinner-border text-success"></div>', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
            try {
                const blob = await takePhotoBlob(); 
                const formData = new FormData();
                formData.append('image', blob, 'capture.jpg');
                formData.append('token', token);
                
                const response = await fetch('upload_bridge.php', { method: 'POST', body: formData });
                const result = await response.json();

                if(result.success) {
                    Swal.fire({ icon: 'success', title: 'Synced with Computer!', text: 'Your computer is now analyzing. You can close this tab.'}).then(() => { window.close(); });
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to upload photo. Please try again.', 'error');
            }
        }
    </script>
</body>
</html>