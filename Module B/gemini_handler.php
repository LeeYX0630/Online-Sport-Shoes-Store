<?php
// gemini_handler.php
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

// ==========================================
// 模式 1: AI 视觉尺码测量 (Sizer)
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

    $payload = [
        "contents" => [[
            "parts" => [
                ["text" => "Task: Measure foot length. 
                1. Check if a human foot and an A4 paper are both clearly visible. 
                2. If either is missing, return 'ERROR'. 
                3. Use the A4 paper (21cm x 29.7cm) as a scale to measure the foot from heel to toe. 
                4. Return ONLY the number in cm (e.g. 24.5). No other text."],
                ["inline_data" => ["mime_type" => "image/jpeg", "data" => $imgData]]
            ]
        ]]
    ];
}
// ==========================================
// 模式 2: 3D 定制设计师 (Designer)
// ==========================================
elseif ($mode === 'designer') {
    $systemPrompt = "You are a world-class sneaker designer. Provide a JSON color scheme for: Outupper, Style, Laces, Tongue, Midsole, Outsole. Return ONLY JSON.";
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