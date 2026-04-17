<?php
// 屏蔽所有 HTML 报错输出
error_reporting(0);
ini_set('display_errors', 0);

// 【新增】开启 Session 以获取登录用户信息
session_start();

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
// 【新增】获取当前用户的钱包余额
// ==========================================
$user_id = $_SESSION['user_id'] ?? null;
$user_balance_info = "";

if ($user_id) {
    // 查询真实的 User_Balance 字段
    $u_res = $conn->query("SELECT User_Balance, User_Name FROM `USER` WHERE User_Id = '$user_id'");
    if ($u_res && $u_res->num_rows > 0) {
        $u_row = $u_res->fetch_assoc();
        $balance = number_format($u_row['User_Balance'], 2);
        $user_balance_info = "\n【Current User Info】\n- Name: {$u_row['User_Name']}\n- Wallet Balance: RM $balance\n";
    }
} else {
    $user_balance_info = "\n(User is not logged in. Advise them to login if they ask about their money.)\n";
}

// ==========================================
// 读取精细化库存 (保持原有逻辑)
// ==========================================
$inventory_data = "【Store Real-time Inventory】\n";
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
}

// 【升级提示词】：加入余额感知能力
$system_instruction = "You are a professional sneaker assistant. You have access to the user's wallet balance and the store inventory. 
Rule 1: If the user asks if they can afford an item, compare the item price with their balance. 
Rule 2: Be encouraging but honest about their budget. 
Rule 3: Keep it concise.\n\n";

$full_prompt = $system_instruction . $inventory_data . $user_balance_info . "\nUser Question: " . $userMessage;

// ==========================================
// 调用 Gemini API
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
    $botResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? "I'm thinking, but I can't find the right words.";
    echo json_encode(['reply' => $botResponse]);
}
curl_close($ch);