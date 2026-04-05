<?php
// module_c/api_get_bookings.php
header('Content-Type: application/json');
require_once '../includes/db_connection.php';

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$events = [];

if ($room_id) {
    $sql = "SELECT check_in_date, check_out_date FROM bookings 
            WHERE room_id = $room_id AND booking_status != 'cancelled'";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {

        $events[] = [
            'title' => 'Booked',
            'start' => $row['check_in_date'],
            'end'   => $row['check_out_date'],
            'color' => '#808080', 
            'display' => 'background' 
        ];

        $events[] = [
            'title' => 'Cleaning',
            'start' => $row['check_out_date'],
            'end'   => $row['check_out_date'], 
            'allDay' => true,
            'color' => '#3788d8', 
            'display' => 'background',
            'overlap' => false
        ];
    }
}

echo json_encode($events);
?>