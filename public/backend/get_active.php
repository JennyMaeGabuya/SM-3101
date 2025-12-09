<?php
// /SANROOM/public/backend/get_active.php
require_once(__DIR__ . "/../../database.php");

header("Content-Type: application/json");

try {
    $sql = "
        SELECT 
            s.id,
            s.class_name,
            s.instructor_name,
            s.instructor_email,
            s.instructor_phone,
            s.instructor_image,
            s.course_code,
            s.join_code,
            r.room_name,
            r.capacity,
            r.special_access_code,  -- Include special access code
            s.day,
            s.status,
            TIME_FORMAT(s.start_time, '%h:%i %p') AS start_time,
            TIME_FORMAT(s.end_time, '%h:%i %p') AS end_time,
            COUNT(DISTINCT sp.student_id) AS student_count
        FROM schedules s
        LEFT JOIN rooms r ON s.room_id = r.room_id
        LEFT JOIN schedule_participants sp ON s.id = sp.schedule_id
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY s.day, s.start_time
    ";

    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        $data = $res->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to load active schedules: " . $e->getMessage()
    ]);
}
