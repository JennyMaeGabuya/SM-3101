<?php
// backend/process_join_code.php (Renamed logic for clarity)
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure the path to your database connection is correct
require_once __DIR__ . "/../../database.php";

// Use session user id for security
$studentId = $_SESSION['user_id'] ?? null;
if (empty($studentId)) {
    echo json_encode(["success" => false, "message" => "Not authenticated. Please log in."]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
// Accept join_code or access_code
$joinCode = trim($body['join_code'] ?? $body['access_code'] ?? '');

if (empty($joinCode)) {
    echo json_encode(["success" => false, "message" => "Missing join code."]);
    exit;
}

try {
    // =========================================================
    // 🔍 PHASE 1: TRY TO JOIN SCHEDULE
    // =========================================================
    $stmt = $conn->prepare("SELECT id, status FROM schedules WHERE join_code = ? LIMIT 1");
    if (!$stmt) throw new Exception('DB prepare failed (schedule select): ' . $conn->error);
    $stmt->bind_param('s', $joinCode);
    $stmt->execute();
    $res = $stmt->get_result();
    $schedule = $res->fetch_assoc();
    $stmt->close();

    if ($schedule) {
        // Code matched a schedule
        if (($schedule['status'] ?? 'active') === 'archived') {
            echo json_encode(["success" => false, "message" => "Cannot join archived schedule."]);
            exit;
        }

        $scheduleId = intval($schedule['id']);

        // Insert participant if not exists
        $ins = $conn->prepare("INSERT IGNORE INTO schedule_participants (schedule_id, student_id) VALUES (?, ?)");
        if (!$ins) throw new Exception('DB prepare failed (schedule insert): ' . $conn->error);
        $ins->bind_param('ii', $scheduleId, $studentId);
        $ins->execute();

        echo json_encode([
            "success" => true,
            "message" => "Successfully joined schedule.",
            "data" => ["type" => "schedule", "id" => $scheduleId]
        ]);
        exit;
    }

    // =========================================================
    // 🔍 PHASE 2: TRY TO JOIN ROOM
    // (Only runs if the code was NOT a schedule code)
    // =========================================================
    $stmt = $conn->prepare("SELECT room_id, room_name FROM rooms WHERE special_access_code = ? AND is_archived = 0 LIMIT 1");
    if (!$stmt) throw new Exception('DB prepare failed (room select): ' . $conn->error);
    $stmt->bind_param('s', $joinCode);
    $stmt->execute();
    $res = $stmt->get_result();
    $room = $res->fetch_assoc();
    $stmt->close();

    if ($room) {
        // Code matched a room
        $roomId = intval($room['room_id']);

        // IMPORTANT: Update the student's record to associate them with the room.
        // Replace 'current_room_id' and 'students' with your actual table/columns.
        $upd = $conn->prepare("UPDATE students SET current_room_id = ? WHERE id = ?");
        if (!$upd) throw new Exception('DB prepare failed (room update): ' . $conn->error);
        $upd->bind_param('ii', $roomId, $studentId);
        $upd->execute();

        echo json_encode([
            "success" => true,
            "message" => "Granted access to room: " . htmlspecialchars($room['room_name']),
            "data" => ["type" => "room", "id" => $roomId]
        ]);
        exit;
    }


    // =========================================================
    // ❌ PHASE 3: NO MATCH
    // =========================================================
    echo json_encode(["success" => false, "message" => "Invalid activatin code. Please check your schedule or room code."]);
    exit;
} catch (Exception $e) {
    // Log the error message to the server, but provide a generic message to the client
    error_log("Join Code Processing Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An unexpected error occurred. Please try again."]);
    exit;
}
