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
    <title>AI Foot Scanner</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background-color: #000; color: #fff; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .camera-container { flex: 1; position: relative; overflow: hidden; }
        #webcam { width: 100%; height: 100%; object-fit: cover; }
        @keyframes scanMove { 0% { top: 10%; } 100% { top: 90%; } }
        .scanner-line { position: absolute; left: 10%; width: 80%; height: 4px; background: rgba(0, 255, 157, 0.8); box-shadow: 0 0 20px 5px rgba(0, 255, 157, 0.5); z-index: 10; animation: scanMove 2.5s linear infinite; pointer-events: none; }
        #guide-box { position: absolute; top: 20%; left: 15%; width: 70%; height: 60%; border: 3px dashed rgba(0, 255, 157, 0.6); border-radius: 15px; box-shadow: 0 0 0 1000px rgba(0,0,0,0.6); z-index: 5; pointer-events: none; display: flex; justify-content: center; align-items: center; }
        #guide-box span { color: #00ff9d; font-weight: bold; font-size: 14px; background: rgba(0,0,0,0.7); padding: 5px 10px; border-radius: 5px; }
        .action-bar { padding: 20px; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); display: flex; justify-content: center; border-top: 1px solid rgba(255,255,255,0.1); }
        .btn-capture { background-color: #00ff9d; color: #000; border: none; padding: 15px 30px; font-size: 18px; font-weight: 800; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 255, 157, 0.4); display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <div class="camera-container">
        <video id="webcam" autoplay playsinline></video>
        <div class="scanner-line"></div>
        <div id="guide-box"><span>ALIGN A4 PAPER HERE</span></div>
    </div>
    <div class="action-bar">
        <button class="btn-capture" onclick="captureAndUpload()"><i class="bi bi-camera-fill"></i>TAKE PHOTO & SYNC</button>
    </div>

    <script>
        const video = document.getElementById('webcam');
        const token = '<?php echo $token; ?>';

        // 【核心修复 2】：JavaScript 启动摄像头逻辑[cite: 30]
        async function startCamera() {
            try {
                // 请求后置摄像头权限
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
                video.srcObject = stream;
            } catch (err) {
                console.error("Camera access denied:", err);
                Swal.fire({ icon: 'error', title: 'Camera Error', text: 'Please allow camera permissions.' });
            }
        }
        window.addEventListener('load', startCamera);

        // 【核心修复 3】：实现拍照功能的自定义函数
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
                // 调用 takePhotoBlob 抓取视频帧
                const blob = await takePhotoBlob(); 
                
                const formData = new FormData();
                formData.append('image', blob, 'capture.jpg'); // 核心修复 4：指定文件名
                formData.append('token', token);
                
                // 将数据发送到同一文件夹下的 upload_bridge.php
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