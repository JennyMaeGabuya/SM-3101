<?php
// /SANROOM/public/backend/get_student_rooms_only.php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . "/../../database.php");
header("Content-Type: application/json");

$student_id = intval($_SESSION['user_id'] ?? $_GET['student_id'] ?? 0);

if (!$student_id) {
    echo json_encode(["success" => false, "message" => "Student ID missing or not authenticated."]);
    exit();
}

try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // SQL to find rooms joined by the student (in room_participants) 
    // AND WHERE that room does NOT have any active schedules (in schedules table)
    $sql = "
        SELECT 
            r.room_id, 
            r.room_name,
            r.special_access_code,
            rp.joined_at  -- 💡 ADDED: Include join timestamp
        FROM 
            room_participants rp
        JOIN 
            rooms r ON rp.room_id = r.room_id
        WHERE 
            rp.student_id = ?
            AND r.is_archived = 0
            -- Check if NO active schedules exist for this room
            AND NOT EXISTS (
                SELECT 1
                FROM schedules s
                WHERE s.room_id = r.room_id 
                AND s.status != 'archived'
            )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rooms_only_data = [];
    while ($row = $result->fetch_assoc()) {
        $rooms_only_data[] = [
            'roomId' => $row['room_id'],
            'room_name' => $row['room_name'],
            'special_access_code' => $row['special_access_code'],
            'joined_at' => $row['joined_at'], // 💡 Now returned in JSON
            'is_room_only' => true, // Flag for the frontend
        ];
    }
    $stmt->close();
    $conn->close();

    echo json_encode(["success" => true, "data" => $rooms_only_data]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
