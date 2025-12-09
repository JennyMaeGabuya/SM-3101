<?php
require_once(__DIR__ . "/../../database.php");

$scheduleId = intval($schedule['id']);

// Get room capacity + current participants
$stmt = $conn->prepare("
    SELECT 
        r.capacity,
        (
            SELECT COUNT(*) FROM schedule_participants sp 
            WHERE sp.schedule_id = ?
        ) AS enrolled
    FROM schedules s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE s.id = ?
");
$stmt->bind_param("ii", $scheduleId, $scheduleId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

if ($info && $info["enrolled"] >= $info["capacity"]) {
    echo json_encode([
        "success" => false,
        "message" => "This class is already full."
    ]);
    exit;
}
