<?php
// Module B/mobile_capture.php
// 获取 URL 中的 token
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Error: Session token is missing. Please re-scan QR code.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>AI Sport Shoes Sizer</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils"></script>
    
    <style>
        body { font-family: 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background-color: #000; color: #fff; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .camera-container { flex: 1; position: relative; overflow: hidden; }
        
        #webcam { width: 100%; height: 100%; object-fit: cover; }
        
        /* 酷炫扫描线动画 */
        @keyframes scanMove { 0% { top: 10%; } 100% { top: 90%; } }
        .scanner-line { position: absolute; left: 10%; width: 80%; height: 4px; background: rgba(0, 255, 157, 0.8); box-shadow: 0 0 20px 5px rgba(0, 255, 157, 0.5); z-index: 10; animation: scanMove 2.5s linear infinite; pointer-events: none; }
        
        /* === 【关键修复 2/3】 用于绘图的关键 Canvas 标签 === */
        #output_canvas { position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: 5; pointer-events: none; }
        
        .action-bar { padding: 20px; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); display: flex; justify-content: center; border-top: 1px solid rgba(255,255,255,0.1); position: relative; z-index: 100; }
        .btn-capture { background-color: #00ff9d; color: #000; border: none; padding: 15px 30px; font-size: 18px; font-weight: 800; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 255, 157, 0.4); display: flex; align-items: center; gap: 10px; transition: transform 0.2s; }
        .btn-capture:active { transform: scale(0.95); }
    </style>
</head>
<body>
    <div class="camera-container">
        <video id="webcam" autoplay playsinline></video>
        
        <div class="scanner-line"></div>
        
        <canvas id="output_canvas"></canvas>
    </div>

    <div class="action-bar">
        <button class="btn-capture" onclick="captureAndUpload()"><i class="bi bi-camera-fill"></i>TAKE PHOTO & SYNC</button>
    </div>

    <script>
        const video = document.getElementById('webcam');
        const token = '<?php echo $token; ?>';
        const canvasElement = document.getElementById('output_canvas');
        const canvasCtx = canvasElement.getContext('2d');

        // === 【逻辑层升级 3/3】 MediaPipe 实时追踪与绘图逻辑 ===
        
        // 初始化 Pose 模型
        const pose = new Pose({locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`});
        
        // 设置模型参数（为手机优化：使用 1.5-flash 对应的检测器）
        pose.setOptions({
            modelComplexity: 1, 
            smoothLandmarks: true, 
            enableSegmentation: false,
            smoothSegmentation: true,
            minDetectionConfidence: 0.7, 
            minTrackingConfidence: 0.7
        });

        // 核心绘图逻辑：处理 AI 识别结果
        // === 【修正后的核心逻辑】 ===

// 核心绘图逻辑：处理 AI 识别结果
pose.onResults((results) => {
    canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
    
    if (results.poseLandmarks) {
        // 提取可见度
        const leftAnkle = results.poseLandmarks[27];
        const rightAnkle = results.poseLandmarks[28];

        let pointsToTrack = null;
        
        // 【语法修复点】：正确定义追踪点数组
        if (leftAnkle && leftAnkle.visibility > 0.7) {
            pointsToTrack = [results.poseLandmarks[27], results.poseLandmarks[31]]; 
        } else if (rightAnkle && rightAnkle.visibility > 0.7) {
            pointsToTrack = [results.poseLandmarks[28], results.poseLandmarks[32]];
        }

        if (pointsToTrack) {
            drawCoolFootGrid(canvasCtx, pointsToTrack);
        }
    }
});

// JavaScript 启动摄像头逻辑
async function startCamera() {
    try {
        // 检查环境
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("Missing HTTPS or camera support.");
        }

        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: "environment", width: 1280, height: 720 }, 
            audio: false 
        });
        video.srcObject = stream;
        
        video.onloadedmetadata = () => {
            canvasElement.width = video.videoWidth;
            canvasElement.height = video.videoHeight;
            onFrame(); 
        };
    } catch (err) {
        console.error("Camera error:", err);
        Swal.fire({ 
            icon: 'error', 
            title: 'Camera Access Error', 
            text: err.message 
        });
    }
}

        // === 【创意核心】 自定义酷炫网格捕捉框绘图函数 ===
        function drawCoolFootGrid(ctx, points, heelPoint) {
            if (!points || points.length < 2) return;

            const width = ctx.canvas.width;
            const height = ctx.canvas.height;

            // 1. 计算脚部的物理包围矩形坐标 (Scale normalized 0-1 to pixel coordinates)
            let minX = width, minY = height, maxX = 0, maxY = 0;
            points.forEach(p => {
                const x = p.x * width;
                const y = p.y * height;
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
            });

            // 2. 增加 Padding 并确保框不会太扁
            const padding = 50;
            const targetMinX = Math.max(0, minX - padding);
            const targetMinY = Math.max(0, minY - padding);
            const targetMaxX = Math.min(width, maxX + padding);
            const targetMaxY = Math.min(height, maxY + padding);
            const targetWidth = targetMaxX - targetMinX;
            const targetHeight = targetMaxY - targetMinY;

            // 风格：绿光外赫 (Green glow)
            ctx.shadowBlur = 15;
            ctx.shadowColor = "rgba(0, 255, 157, 0.8)";
            
            // 3. 绘制外部“科幻边角” (Corners only)
            ctx.strokeStyle = "#00ff9d";
            ctx.lineWidth = 5;
            ctx.lineJoin = "round";
            
            const cornerLen = 25; // 边角长度

            // 左上角
            ctx.beginPath(); ctx.moveTo(targetMinX, targetMinY + cornerLen); ctx.lineTo(targetMinX, targetMinY); ctx.lineTo(targetMinX + cornerLen, targetMinY); ctx.stroke();
            // 右上角
            ctx.beginPath(); ctx.moveTo(targetMaxX - cornerLen, targetMinY); ctx.lineTo(targetMaxX, targetMinY); ctx.lineTo(targetMaxX, targetMinY + cornerLen); ctx.stroke();
            // 左下角
            ctx.beginPath(); ctx.moveTo(targetMinX, targetMaxY - cornerLen); ctx.lineTo(targetMinX, targetMaxY); ctx.lineTo(targetMinX + cornerLen, targetMaxY); ctx.stroke();
            // 右下角
            ctx.beginPath(); ctx.moveTo(targetMaxX - cornerLen, targetMaxY); ctx.lineTo(targetMaxX, targetMaxY); ctx.lineTo(targetMaxX, targetMaxY - cornerLen); ctx.stroke();

            // 4. 绘制内部“拓扑网格”效果 (Cool internal grid with opacity)
            ctx.shadowBlur = 0; // 清除内部阴影以节省性能
            ctx.strokeStyle = "rgba(0, 255, 157, 0.3)"; // Faint green internal lines
            ctx.lineWidth = 1;

            const gridLines = 4; // 网格细分数
            
            // 内部垂直线
            for (let i = 1; i < gridLines; i++) {
                const x = targetMinX + (targetWidth / gridLines) * i;
                ctx.beginPath(); ctx.moveTo(x, targetMinY); ctx.lineTo(x, targetMaxY); ctx.stroke();
            }
            // 内部水平线
            for (let i = 1; i < gridLines; i++) {
                const y = targetMinY + (targetHeight / gridLines) * i;
                ctx.beginPath(); ctx.moveTo(targetMinX, y); ctx.lineTo(targetMaxX, y); ctx.stroke();
            }

            // 5. 绘制动态扫描线 (Moving scan line *inside* the dynamic box)
            const time = Date.now() / 500; // 动态时间因子
            const scanLineY = targetMinY + targetHeight * (0.5 + 0.5 * Math.sin(time)); // Oscillate
            
            ctx.strokeStyle = "rgba(255, 255, 255, 0.6)"; // 白色略透明扫描线
            ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(targetMinX, scanLineY); ctx.lineTo(targetMaxX, scanLineY); ctx.stroke();

            // 6. 添加装饰文本 (Text overlay like "TARGET ACQUIRED")
            ctx.fillStyle = "#00ff9d";
            ctx.font = "bold 14px Segoe UI, sans-serif";
            ctx.fillText("SS_SCAN_BOOT_AQ", targetMinX, targetMinY - 10);
            
            // 模拟一个深度数据
            const mockDepth = (12.3 + Math.sin(time) * 0.2).toFixed(1);
            ctx.fillStyle = "white";
            ctx.fillText(`DEPTH: ${mockDepth}CM`, targetMinX + cornerLen + 5, targetMaxY + 18);
        }

        // 核心循环：不断发送帧给 MediaPipe
        async function onFrame() {
            if (!video.paused && !video.ended) {
                await pose.send({ image: video }); // 发送当前帧给 AI 模型
            }
            requestAnimationFrame(onFrame); // 递归调用，实现实时检测
        }

        // 页面加载完成自动启动
        window.addEventListener('load', startCamera);

        // 实现拍照功能的自定义函数（这部分不需要改变）
        function takePhotoBlob() {
            return new Promise((resolve) => {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => { resolve(blob); }, 'image/jpeg', 0.95);
            });
        }

        async function captureAndUpload() {
            Swal.fire({ title: 'Uploading...', html: '<div class="spinner-border text-success"></div>', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
            try {
                const blob = await takePhotoBlob(); 
                const formData = new FormData();
                formData.append('image', blob, 'capture.jpg');
                formData.append('token', token);
                
                // 核心修复：确保 upload_bridge.php 的路径相对于 Module B 文件夹正确
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