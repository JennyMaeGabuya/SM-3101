<?php
require_once(__DIR__ . "/../../database.php");

// Fetch only visible rooms for Room Management
$query = "
    SELECT room_id, room_name, capacity
    FROM rooms
    WHERE is_hidden = 0
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
