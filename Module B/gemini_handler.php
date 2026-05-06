<?php
include '../includes/api_config.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['message'] ?? '';
$mode = $input['mode'] ?? 'chat'; // 新增：识别是聊天还是设计模式

if (!$userMessage) {
    echo json_encode(['reply' => 'No message provided']);
    exit;
}

$apiKey = GEMINI_API_KEY; 
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

// --- 核心：设计师专用系统提示词 ---
if ($mode === 'designer') {
    $systemPrompt = "You are a world-class sneaker designer. 
    Analyze the user's style description and provide a complete color/material scheme for a 3D shoe model.
    The model has 6 parts: Outupper, Style, Laces, Tongue, Midsole, Outsole.
    You MUST return ONLY a valid JSON object in this exact format, no conversational text:
    {
      \"Outupper\": {\"color\": \"#hex\", \"roughness\": 0.8, \"reason\": \"description\"},
      \"Style\": {\"color\": \"#hex\", \"roughness\": 0.4, \"reason\": \"description\"},
      \"Laces\": {\"color\": \"#hex\", \"roughness\": 0.5, \"reason\": \"description\"},
      \"Tongue\": {\"color\": \"#hex\", \"roughness\": 0.5, \"reason\": \"description\"},
      \"Midsole\": {\"color\": \"#hex\", \"roughness\": 0.9, \"reason\": \"description\"},
      \"Outsole\": {\"color\": \"#hex\", \"roughness\": 0.3, \"reason\": \"description\"}
    }";
    $payloadMessage = $systemPrompt . "\n\nUser Style Description: " . $userMessage;
} else {
    $payloadMessage = $userMessage; // 保持你原有的尺寸建议逻辑[cite: 26]
}

$data = [
    "contents" => [["parts" => [["text" => $payloadMessage]]]]
];

// 使用 CURL 发送请求 (保持你原有的通信逻辑)[cite: 26]
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

$aiReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Error processing AI response';

// 如果是设计模式，清理 AI 可能返回的 Markdown 标记
if ($mode === 'designer') {
    $aiReply = str_replace(['```json', '```'], '', $aiReply);
}

echo json_encode(['reply' => trim($aiReply)]);