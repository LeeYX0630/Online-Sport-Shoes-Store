<?php
// includes/set_size_session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_systems = ['UK', 'US-M', 'US-F', 'EUR'];
$system = isset($_GET['system']) ? strtoupper(trim($_GET['system'])) : 'UK';

if (in_array($system, $allowed_systems)) {
    $_SESSION['size_system'] = $system;
    echo json_encode(['success' => true, 'system' => $system]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid size system']);
}
exit;