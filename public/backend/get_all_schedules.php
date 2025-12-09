<?php
require_once(__DIR__ . "/../../database.php");
header("Content-Type: application/json");

$sql = "
    SELECT 
        s.*,
        r.room_name,
        r.capacity AS room_capacity,
        r.special_access_code,
        (
            SELECT COUNT(*) FROM schedule_participants sp 
            WHERE sp.schedule_id = s.id
        ) AS participant_count
    FROM schedules s
    LEFT JOIN rooms r ON s.room_id = r.room_id
    ORDER BY s.day, s.start_time
";

$res = $conn->query($sql);
$data = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode(["success" => true, "data" => $data]);
