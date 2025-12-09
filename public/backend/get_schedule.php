<?php
require_once(__DIR__ . "/../../database.php");

// Fetch all rooms for schedules dropdown (hidden included)
$query = "
    SELECT room_id, room_name, capacity
    FROM rooms
    ORDER BY room_name ASC
";

$result = mysqli_query($conn, $query);
$rooms = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rooms[] = $row;
}

echo json_encode([
    "success" => true,
    "rooms" => $rooms
]);
