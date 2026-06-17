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

if (!$userMessage && !in_array($mode, ['sizer', 'wear_detector', 'lifestyle_preview'])) {
    echo json_encode(['reply' => 'No message provided']);
    exit;
}

$apiKey = GEMINI_API_KEY;
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$payload = [];

// ==========================================
// 💥 业务模式 1: AI 视觉双通道尺码与脚型综合分析 (Sizer + Shape Analysis)
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
    //if (file_exists($full_path)) {
    //    @unlink($full_path); // 瞬时物理抹除，释放空间
    //}

    // 升级版 Prompt：引入脚型识别与视觉标记锚点
    $sizerPrompt = "You are a sub-millimeter level photogrammetry and computer vision engine.
    Analyze the image to determine the exact foot length and classify the foot shape type.
    
    CRITICAL TASKS:
    1. Scan pixels to isolate the standard A4 paper to compute the pixel-to-centimeter scale factor.
    2. Measure the maximum linear distance along the anatomical axis of the foot (heel to longest toe).
    3. Identify the Foot Shape Type from the following categories based on toe alignments:
       - 'Greek Foot' : Second toe is noticeably longer than the big toe.
       - 'Roman Foot' : First three toes are nearly equal in length.
       - 'Egyptian Foot' : Big toe is the longest, others taper down like a staircase.
       - 'Square Foot' : All five toes are almost equal in length, wide forefoot.
    4. Detect arch profile signs if visible (e.g., potential 'Flat Foot' signs).
    5. Provide normal 0-1 relative image coordinates (x, y) for 3 critical landmarks based on the ENTIRE image boundaries (where 0.0 is top/left and 1.0 is bottom/right):
       - 'heel_center': The exact lowest point of the bare heel or sock heel contour where it touches the paper line.
       - 'longest_toe_tip': The absolute apex tip point of the longest toe (typically the big toe for Egyptian foot).
       - 'forefoot_width_outer': The widest lateral projection point on the opposite side of the foot to form a proper width reference.

    Your response must be a strict JSON object matching this schema:
    {
      \"measured_length_cm\": 26.5,
      \"foot_shape_type\": \"Egyptian Foot\",
      \"description\": \"Big toe is the longest, others taper down like a staircase.\",
      \"landmarks\": {
        \"heel_center\": {\"x\": 0.51, \"y\": 0.85},
        \"longest_toe_tip\": {\"x\": 0.48, \"y\": 0.22},
        \"forefoot_width_outer\": {\"x\": 0.62, \"y\": 0.35}
      }
    }";

    $payload = [
      "contents" => [[
          "parts" => [
              ["text" => $sizerPrompt],
              ["inline_data" => ["mime_type" => "image/jpeg", "data" => $imgData]]
          ]
      ]],
      "generationConfig" => [
          "temperature" => 0.0,
          "responseMimeType" => "application/json"
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
elseif ($mode === 'chat') {
    // 【修改】SQL 新增了 p.Pro_Id 和 p.Pro_Image 以便构建跳转链接和图片
    $sql = "SELECT p.Pro_Id, p.Pro_Name, p.Pro_Description, p.Pro_Price, p.Pro_Image, s.Pro_Size, s.Pro_Colour, s.Quantity 
            FROM product p 
            JOIN product_stock s ON p.Pro_Id = s.Pro_Id 
            WHERE p.Pro_Status = 'Available'
            ORDER BY p.Pro_Name ASC";
    $result = $conn->query($sql);
    
    $inventoryContext = "Current Store Inventory Data:\n";
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // 【修改】注入了 Pro_Id 和 Pro_Image 作为上下文
            $inventoryContext .= "- ID: {$row['Pro_Id']} | Product: {$row['Pro_Name']} | Color: {$row['Pro_Colour']} | Size: UK {$row['Pro_Size']} | Stock Left: {$row['Quantity']} | Price: RM {$row['Pro_Price']} | Image: {$row['Pro_Image']}\n";
        }
    }

    $chatSystemPrompt = "You are the STRYDEX Sport Shoes Assistant. 
    Use the following REAL-TIME inventory data to guide customers.
    CRITICAL FORMATTING RULES:
    1. Use HTML line breaks <br> for every new point.
    2. For stock lists, use <b>bold</b> for product names and colors.
    3. If there are many sizes, organize them in a clean list or a simple HTML <table>.
    4. Product prices is unnessary to mention unless user ask for it.
    5. Be friendly.
    6. [CRITICAL NEW RULE] When recommending a specific product, you MUST include a product card tag at the end of your sentence in EXACTLY this format: [PRODUCT:{Pro_Id}|{Pro_Name}|{Pro_Price}|{Pro_Image}]

    $inventoryContext
    User Question: $userMessage";

    $payload = [
        "contents" => [["parts" => [["text" => $chatSystemPrompt]]]]
    ];
}

// ==========================================
// 💥 业务模式 4: 三视角深度磨损分析 (Wear Sole Detector - 3 Views)
// ==========================================
elseif ($mode === 'wear_detector' && isset($input['images'])) {
    $images = $input['images']; // 期望接收关联数组: ['front' => path, 'left' => path, 'right' => path]
    
    $parts = [];
    $views = ['front' => 'front', 'left' => 'left', 'right' => 'right'];

    foreach ($views as $key => $label) {
        $full_path = "../Module B/" . ($images[$key] ?? '');
        if (file_exists($full_path)) {
            $imgData = base64_encode(file_get_contents($full_path));
            $parts[] = ["text" => "This is the {$label} view of the shoe:"];
            $parts[] = ["inline_data" => ["mime_type" => "image/jpeg", "data" => $imgData]];
        }
    }

    $wearPrompt = "you are a sub-millimeter level photogrammetry and computer vision engine specialized in shoe sole wear analysis.
    
    Task:
    1. Analyze the 【front】, 【left】, and 【right】 views in detail (e.g., midsole deformation, rubber tread wear, upper material tears).
    2. Provide a wear percentage for each view (1-100, where 100% indicates complete damage and 1% indicates new).
    3. Based on the combined analysis, provide an overall rating ('Low', 'Medium', 'Critical') and a final recommendation on whether to purchase new shoes.
    
    Please return the result in the following JSON format:
    {
      \"front\": { \"wear_percent\": 45, \"detail\": \"Front view shows minor wear on the toe box area.\" },
      \"left\": { \"wear_percent\": 80, \"detail\": \"Left side reveals deep compression in the midsole and significant tread wear on the outsole.\" },
      \"right\": { \"wear_percent\": 75, \"detail\": \"Right side displays pronounced wear on the heel counter and uneven tread pattern.\" },
      \"overall_level\": \"Critical\",
      \"final_advice\": \"Your shoes are showing critical wear and should be replaced soon.\"
    }";

    $parts[] = ["text" => $wearPrompt];

    $payload = [
        "contents" => [[
            "parts" => $parts
        ]],
        "generationConfig" => [
            "temperature" => 0.2,
            "responseMimeType" => "application/json"
        ]
    ];
} elseif ($mode === 'lifestyle_preview') {
    $style = $input['style'] ?? 'casual';
    $productName = $input['product_name'] ?? 'sneakers';
    $brandName = $input['brand_name'] ?? '';
    $color = $input['current_color'] ?? 'default';
    $gender = $input['gender'] ?? 'random'; // 新增：接收性别
    
    // 1. 动态决定 Prompt 里的主语
    $subject = "a person";
    if ($gender === 'male') $subject = "a male model";
    if ($gender === 'female') $subject = "a female model";

    // 2. 数据清洗与命名规范 (必须把 gender 加进缓存文件名！)
    $cleanColor = str_replace('/', ' and ', $color);
    $safeFileName = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower("{$brandName}_{$productName}_{$cleanColor}_{$style}_{$gender}"));
    
    $cacheDir = '../Module B/uploads/lookbooks/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    $cacheFileFullPath = $cacheDir . $safeFileName . '.jpeg';
    $frontendDisplayUrl = '../Module B/uploads/lookbooks/' . $safeFileName . '.jpeg';

    if (file_exists($cacheFileFullPath)) {
        echo json_encode(['image_url' => $frontendDisplayUrl, 'is_cached' => true]);
        exit;
    }

    // 3. 构建包含性别的 Google Imagen 3 提示词
    $imagenModel = "gemini-3-pro-image-preview"; 
    $imagenApiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$imagenModel}:generateContent?key=" . $apiKey;

    // 注意这里使用了 $subject 替换了原本写死的 a person
    $prompt = "A hyper-realistic full body fashion photography of {$subject} wearing {$style} outfit on the street, wearing {$cleanColor} {$brandName} {$productName} sneakers. High-end editorial lighting, 8k resolution, photorealistic.";

    $imagenPayload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($imagenApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($imagenPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40); 
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    $parts = $result['candidates'][0]['content']['parts'][0] ?? null;
    $base64Image = $parts['inlineData']['data'] ?? ($parts['inline_data']['data'] ?? null);

    if ($httpCode == 200 && $base64Image) {
        $imgData = base64_decode($base64Image);
        file_put_contents($cacheFileFullPath, $imgData);
        
        echo json_encode(['image_url' => $frontendDisplayUrl, 'is_cached' => false]);
        exit;
    } else {
        error_log("Google Imagen 3 API Error: " . $response);
        http_response_code(500);
        echo json_encode(['error' => 'API Error', 'message' => 'Failed to generate image via Google Imagen 3 API.']);
        exit;
    }
} else {
    echo json_encode(['reply' => 'Invalid mode or missing image for sizer/wear_detector']);
    exit;
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
if ($mode === 'sizer' || $mode === 'wear_detector') {
    $cleanJson = trim(str_replace(['```json', '```'], '', $aiReply));
    echo $cleanJson; 
    exit;
}

if ($mode === 'designer') {
    $aiReply = str_replace(['```json', '```'], '', $aiReply);
}

// 输出纯净数据完美迎合前端 chatbot 渲染流
echo json_encode(['reply' => trim($aiReply)]);