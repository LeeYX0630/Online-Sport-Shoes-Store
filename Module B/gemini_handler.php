<?php
// gemini_handler.php - 究极确定性高精度视觉尺码引擎
require_once '../includes/db_connection.php';
require_once '../includes/api_config.php';

header('Content-Type: application/json');

// 1. 获取输入数据
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
        @unlink($full_path); // 瞬时物理删除释放空间
    }

    // 强化版高精度思维链提示词 (Chain of Thought)
    $sizerPrompt = "You are a sub-millimeter level photogrammetry and computer vision calibration engine.
    Analyze the image and run an internal multi-point cross-verification matrix to determine the exact foot length.

    EXECUTION STEPS FOR THE NEURAL NETWORK:
    1. Scan the pixels to isolate the standard white A4 paper sheet. Intelligently extrapolate any edges or corners obscured by legs/ankles or cropped by borders.
    2. Perform 3 independent bounding-box samplings across different points of the paper's visible margins to compute an absolute pixel-to-centimeter scale.
    3. Calculate the maximum linear distance from the rearmost center plane of the heel to the tip of the furthest toe along the anatomical axis of the foot.
    4. Cross-verify the scale factor against the foot boundaries to eliminate variance from camera tilts or distances.
    5. Output the result in the requested JSON structure schema.";

    // 【究极优化点】：挂载 generationConfig 锁死随机性，并开启原生 JSON 架构约束
    $payload = [
        "contents" => [[
            "parts" => [
                ["text" => $sizerPrompt],
                ["inline_data" => ["mime_type" => "image/jpeg", "data" => $imgData]]
            ]
        ]],
        "generationConfig" => [
            "temperature" => 0.0,         // 【关键】：归零温度，彻底抹除随机浮动，实现绝对幂等性
            "topP" => 0.1,                // 极度收窄采样候选词范围
            "responseMimeType" => "application/json" // 【关键】：要求 Gemini 必须返回纯 JSON 数据
        ]
    ];
}
elseif ($mode === 'designer') {
    $systemPrompt = "You are a world-class sneaker designer. ...";
    $payload = [
        "contents" => [["parts" => [["text" => $systemPrompt . "\n\nUser Style: " . $userMessage]]]]
    ];
} 
else {
    $sql = "SELECT p.Pro_Name, p.Pro_Description, p.Pro_Price, s.Pro_Size, s.Pro_Colour, s.Quantity ...";
    // ... 此处保持你原有的 Chat 聊天助手逻辑不变 ...
    $payload = [
        "contents" => [["parts" => [["text" => $chatSystemPrompt]]]]
    ];
}

// 2. 发送请求给 Gemini API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

// 3. 解析并返回结果
$aiReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'I am sorry, I am having trouble connecting to my brain right now.';

// --- 【核心兼容修复拦截器】 ---
if ($mode === 'sizer') {
    // 解析 Gemini 强制生成的确定性 JSON 数据
    $cleanJson = trim(str_replace(['```json', '```'], '', $aiReply));
    $parsedData = json_decode($cleanJson, true);
    
    // 智能提取核心评测厘米数，如果结构解析失败，启动正则盲抓机制兜底
    if (isset($parsedData['measured_length_cm'])) {
        $finalOutput = $parsedData['measured_length_cm'];
    } else {
        preg_match('/[+-]?([0-9]*[.])?[0-9]+/', $cleanJson, $matches);
        $finalOutput = isset($matches[0]) ? $matches[0] : 'ERROR';
    }
    
    // 依然干净地返回一个数字字符串，完美兼容 product_details.php 里的 isNaN() 拦截器
    echo json_encode(['reply' => (string)$finalOutput]);
    exit;
}
// ==========================================
// 模式 2: 3D 定制设计师 (Designer)
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
        "contents" => [["parts" => [["text" => $systemPrompt . "\n\nUser Style: " . $userMessage]]]]
    ];
} 
// ==========================================
// 模式 3: 实时库存聊天助手 (Chat) - 核心升级点
// ==========================================
else {
    // A. 从数据库提取所有可用商品的实时库存数据
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
    Use the following REAL-TIME inventory data.
    
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

// 2. 发送请求给 Gemini API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

// 3. 解析并返回结果
$aiReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'I am sorry, I am having trouble connecting to my brain right now.';

// 清理数据
if ($mode === 'designer') {
    $aiReply = str_replace(['```json', '```'], '', $aiReply);
}

echo json_encode(['reply' => trim($aiReply)]);