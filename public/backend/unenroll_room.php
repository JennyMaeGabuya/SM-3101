<?php
// /SANROOM/public/backend/unenroll_room.php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . "/../../database.php");
header("Content-Type: application/json");

$student_id = intval($_SESSION['user_id'] ?? 0);

if (!$student_id) {
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$room_id = intval($input['room_id'] ?? 0);

if (!$room_id) {
    echo json_encode(["success" => false, "message" => "Missing Room ID."]);
    exit();
}

try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $sql = "DELETE FROM room_participants WHERE room_id = ? AND student_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $room_id, $student_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Successfully left the room."]);
        } else {
            echo json_encode(["success" => true, "message" => "Room association already removed or did not exist."]);
        }
    } else {
        throw new Exception("Failed to leave room: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "An error occurred: " . $e->getMessage()]);
}
