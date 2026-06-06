<?php
// save_wear_record.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$uid = $_SESSION['user_id'];
$image_path = $input['image_path'] ?? '';
$wear_level = $input['wear_level'] ?? '';
$score = floatval($input['integrity_score'] ?? 0);
$report = $input['analysis_report'] ?? '';

if (!$image_path || !$wear_level) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO wear_records (User_Id, Image_Path, Wear_Level, Confidence_Score, Analysis_Report) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issds", $uid, $image_path, $wear_level, $score, $report);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
?>