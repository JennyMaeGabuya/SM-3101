<?php
// get_all_rooms.php
// Fetches all room data for the room management dashboard with aggregated participant counts.

ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);
header("Content-Type: application/json");

require_once(__DIR__ . "/../../database.php"); // make sure this sets $conn correctly

try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $sql = "
    SELECT 
        r.room_id, 
        r.room_name,
        COALESCE(r.capacity, 0) AS capacity,
        r.manual_status,
        r.is_archived,
        r.is_hidden,
        r.special_access_code,
        COALESCE(
            (
                SELECT COUNT(DISTINCT sp.student_id)
                FROM schedule_participants sp
                INNER JOIN schedules s ON sp.schedule_id = s.id
                WHERE s.room_id = r.room_id AND s.status != 'archived'
            ), 0
        ) 
        +
        COALESCE(
            (
                SELECT COUNT(rp.student_id)
                FROM room_participants rp
                WHERE rp.room_id = r.room_id
            ), 0
        ) AS current_participants
    FROM rooms r
    WHERE r.is_hidden = 0
    ORDER BY r.room_name ASC
";

    $res = $conn->query($sql);
    if (!$res) {
        error_log("SQL Error in get_all_rooms.php: " . $conn->error);
        throw new Exception("Query failed: " . $conn->error);
    }

    $rooms = [];
    while ($row = $res->fetch_assoc()) {
        $capacity = intval($row['capacity']);
        $rooms[] = [
            "room_id" => $row['room_id'],
            "room_name" => $row['room_name'],
            "capacity" => $capacity,
            "capacity_set" => $capacity > 0,
            "manual_status" => $row['manual_status'],
            "is_archived" => intval($row['is_archived']),
            "special_access_code" => $row['special_access_code'],
            "current_participants" => intval($row['current_participants'])
        ];
    }

    // ⚡ Keep property name as 'data' so front-end JS works
    echo json_encode([
        "success" => true,
        "data" => $rooms
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}
