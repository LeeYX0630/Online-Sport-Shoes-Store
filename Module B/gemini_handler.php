<?php
// gemini_handler.php - 统一多模态高性能 AI 业务控制中枢
require_once '../includes/db_connection.php';
require_once '../includes/api_config.php';

header('Content-Type: application/json');

// 1. 安全接收并解析前端输入流
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['message'] ?? '';
$mode = $input['mode'] ?? 'chat';
$image_path = $input['image_path'] ?? null;

if (!$userMessage && $mode !== 'sizer') {
    echo json_encode(['reply' => 'No message provided']);
    exit;
}

$apiKey = GEMINI_API_KEY;
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$payload = [];

// ==========================================
// 💥 业务模式 1: AI 视觉双通道尺码测量 (Sizer)
// ==========================================
if ($mode === 'sizer' && $image_path) {
    $full_path = $image_path;
    if (!file_exists($full_path)) {
        $full_path = "../Module B/" . $image_path;
    }
    if (!file_exists($full_path)) {
        echo json_encode(['reply' => 'ERROR', 'error' => 'Image not found at ' . $full_path]);
        exit;
    }

    $imgData = base64_encode(file_get_contents($full_path));
    if (file_exists($full_path)) {
        @unlink($full_path); // 加载至内存后瞬时物理抹除，释放硬盘空间
    }

    $sizerPrompt = "You are a sub-millimeter level photogrammetry and computer vision calibration engine.
    Analyze the image and run an internal multi-point cross-verification matrix to determine the exact foot length.
    EXECUTION STEPS FOR THE NEURAL NETWORK:
    1. Scan the pixels to isolate the standard white A4 paper sheet. Intelligently extrapolate any edges or corners obscured by legs/ankles or cropped by borders.
    2. Perform 3 independent bounding-box samplings across different points of the paper's visible margins to compute an absolute pixel-to-centimeter scale.
    3. Calculate the maximum linear distance from the rearmost center plane of the heel to the tip of the furthest toe along the anatomical axis of the foot.
    4. Cross-verify the scale factor against the foot boundaries to eliminate variance from camera tilts or distances.
    5. Output the result in the requested JSON structure schema.";

    $payload = [
        "contents" => [[
            "parts" => [
                ["text" => $sizerPrompt],
                ["inline_data" => ["mime_type" => "image/jpeg", "data" => $imgData]]
            ]
        ]],
        "generationConfig" => [
            "temperature" => 0.0, // 锁死随机性，保证幂等精度
            "topP" => 0.1,
            "responseMimeType" => "application/json" // 强约束输出为原生 JSON 架构
        ]
    ];
}
// ==========================================
// 💥 业务模式 2: 3D 创意定制设计师 (Designer)
// ==========================================
elseif ($mode === 'designer') {
    $systemPrompt = "You are a world-class sneaker designer. 
    Provide a JSON color scheme for exactly these keys: 'Outupper', 'Style', 'Laces', 'Tongue', 'Midsole', 'Outsole'.
    RULES:
    1. Keys must be CASE-SENSITIVE (e.g., 'Outupper', not 'outupper').
    2. Colors must be in HEX format (e.g., '#FF0000').
    3. Roughness must be a float between 0.0 and 1.0.
    4. Return ONLY the raw JSON object. No markdown, no explanation.";

    $payload = [
        "contents" => [[
            "parts" => [["text" => $systemPrompt . "\n\nUser Style: " . $userMessage]]
        ]],
        "generationConfig" => [
            "responseMimeType" => "application/json"
        ]
    ];
} 
// ==========================================
// 💥 业务模式 3: 智能实时库存客服助手 (Chat)
// ==========================================
else {
    $sql = "SELECT p.Pro_Name, p.Pro_Description, p.Pro_Price, s.Pro_Size, s.Pro_Colour, s.Quantity 
            FROM product p 
            JOIN product_stock s ON p.Pro_Id = s.Pro_Id 
            WHERE p.Pro_Status = 'Available'
            ORDER BY p.Pro_Name ASC";
    $result = $conn->query($sql);
    
    $inventoryContext = "Current Store Inventory Data:\n";
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $inventoryContext .= "- Product: {$row['Pro_Name']} | Color: {$row['Pro_Colour']} | Size: UK {$row['Pro_Size']} | Stock Left: {$row['Quantity']} | Price: RM {$row['Pro_Price']}\n";
        }
    }

    $chatSystemPrompt = "You are the SS Sport Shoes Assistant. 
    Use the following REAL-TIME inventory data to guide customers.
    CRITICAL FORMATTING RULES:
    1. Use HTML line breaks <br> for every new point.
    2. For stock lists, use <b>bold</b> for product names and colors.
    3. If there are many sizes, organize them in a clean list or a simple HTML <table>.
    4. Product prices is unnessary to mention unless user ask for it.
    5. Be friendly.
    
    $inventoryContext
    User Question: $userMessage";

    $payload = [
        "contents" => [["parts" => [["text" => $chatSystemPrompt]]]]
    ];
}

// 2. 统一分流发送 cURL 事务给 Google 骨干网络
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

$aiReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'I am sorry, I am having trouble connecting to my brain right now.';

// 3. 结果统一数据净化清洗层
if ($mode === 'sizer') {
    $cleanJson = trim(str_replace(['```json', '```'], '', $aiReply));
    $parsedData = json_decode($cleanJson, true);
    
    if (isset($parsedData['measured_length_cm'])) {
        $finalOutput = $parsedData['measured_length_cm'];
    } else {
        preg_match('/[+-]?([0-9]*[.])?[0-9]+/', $cleanJson, $matches);
        $finalOutput = isset($matches[0]) ? $matches[0] : 'ERROR';
    }
    echo json_encode(['reply' => (string)$finalOutput]);
    exit;
}

if ($mode === 'designer') {
    $aiReply = str_replace(['```json', '```'], '', $aiReply);
}

// 输出纯净数据完美迎合前端 chatbot 渲染流
echo json_encode(['reply' => trim($aiReply)]);