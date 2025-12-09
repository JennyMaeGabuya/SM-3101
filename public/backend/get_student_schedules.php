<?php
// get_student_schedules.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure this path correctly connects and provides a $conn (MySQLi connection object)
require_once __DIR__ . '/../../database.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(["success" => false, "message" => "Not authenticated", "data" => []]);
    exit;
}

try {
    // We select all columns from schedules (s.*) plus the count of participants.
    $sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM schedule_participants sp WHERE sp.schedule_id = s.id) AS participant_count,
        s.id AS schedule_id  -- Explicitly name the schedule ID for clarity in JS
    FROM schedules s
    INNER JOIN schedule_participants sp2 ON sp2.schedule_id = s.id 
    WHERE sp2.student_id = ?
    
    -- FIXED: Order by the chronological day of the week, then by start time for proper scheduling view.
    ORDER BY 
        FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        s.start_time ASC";

    $stmt = $conn->prepare($sql);

    // Check if preparation was successful
    if ($stmt === false) {
        throw new Exception("SQL statement preparation failed: " . $conn->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);
    exit;
} catch (Exception $e) {
    // Log the error message for debugging purposes
    // error_log("Schedule Fetch Error: " . $e->getMessage()); 
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage(), "data" => []]);
    exit;
}
