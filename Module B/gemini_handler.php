<?php
// 屏蔽所有 HTML 报错输出，防止破坏 JSON 格式
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/api_config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['reply' => 'Error: Empty message received.']);
    exit;
}

// ==========================================
// 核心修复：从 PRODUCT_STOCK 读取精细化库存
// ==========================================
$inventory_data = "【Store Real-time Inventory】\n";

// 查询产品、品牌、以及分尺码分颜色的具体库存
$sql = "SELECT p.Pro_Name, b.Brand_Name, p.Pro_Price, s.Pro_Size, s.Pro_Colour, s.Quantity 
        FROM product p 
        JOIN brand b ON p.Brand_Id = b.Brand_Id 
        JOIN PRODUCT_STOCK s ON p.Pro_Id = s.Pro_Id 
        WHERE p.Pro_Status = 'Available'";
        
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $inventory_data .= "- {$row['Brand_Name']} {$row['Pro_Name']} | Color: {$row['Pro_Colour']} | Size: {$row['Pro_Size']} | Stock: {$row['Quantity']} pairs | Price: RM{$row['Pro_Price']}\n";
    }
} else {
    $inventory_data .= "No stock available in the database.\n";
}

$system_instruction = "You are a professional sneaker assistant. Use the inventory data provided below to answer. 
If a specific size/color isn't listed, it means it's OUT OF STOCK. Be concise and helpful.\n\n";

$full_prompt = $system_instruction . $inventory_data . "\nUser Question: " . $userMessage;

// ==========================================

$apiKey = GEMINI_API_KEY;
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$data = [
    "contents" => [["parts" => [["text" => $full_prompt]]]]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['reply' => "Connection Error: " . curl_error($ch)]);
} else {
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        echo json_encode(['reply' => "API Error: " . $result['error']['message']]);
    } else {
        $botResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? "I'm thinking, but I can't find the right words.";
        echo json_encode(['reply' => $botResponse]);
    }
}
curl_close($ch);