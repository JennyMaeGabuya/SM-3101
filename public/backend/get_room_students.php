<?php
// /SANROOM/public/backend/get_room_students.php
require_once __DIR__ . "/../../database.php";

header("Content-Type: application/json");

// --- DEBUG: Log the received GET array ---
error_log("GET_STUDENTS SCRIPT: GET Parameters: " . print_r($_GET, true));

$room_id = intval($_GET['room_id'] ?? 0);

// --- DEBUG: Log the parsed room_id ---
error_log("GET_STUDENTS SCRIPT: Parsed Room ID: " . $room_id);

if ($room_id === 0) {
    // --- DEBUG: Log failure before exit ---
    error_log("GET_STUDENTS SCRIPT: Validation failed. room_id is 0.");

    echo json_encode(["success" => false, "message" => "Room ID is required and must be a number."]);
    exit;
}

try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // 💡 Fetch a single row to get the Room Name (for the message)
    $roomNameSql = "SELECT room_name FROM rooms WHERE room_id = ?";
    $roomNameStmt = $conn->prepare($roomNameSql);
    $roomNameStmt->bind_param("i", $room_id);
    $roomNameStmt->execute();
    $roomNameResult = $roomNameStmt->get_result();
    $roomNameRow = $roomNameResult->fetch_assoc();
    $roomName = $roomNameRow['room_name'] ?? 'Unknown Room';
    $roomNameStmt->close();

    // --- DEBUG: Log the Room Name retrieved ---
    error_log("GET_STUDENTS SCRIPT: Room Name retrieved: " . $roomName);

    // SQL Query: Use UNION to combine students from two sources:
    $sql = "
        -- 1. Students Enrolled in Schedules for this Room (Param 1: room_id - i)
        SELECT 
            u.user_id,
            u.full_name,
            e.joined_at,
            s.class_name AS context_name,
            'Class Enrollment' AS enrollment_type
        FROM 
            schedules s
        JOIN 
            class_enrollments e ON s.id = e.schedule_fk
        JOIN 
            users u ON e.student_id = u.user_id 
        WHERE 
            s.room_id = ? AND s.status != 'archived'
        
        UNION
        
        -- 2. Students who are only participants of the Room (Param 2: context_name - s, Param 3: room_id - i)
        SELECT
            u.user_id,
            u.full_name,
            rp.joined_at,
            ? AS context_name, -- Use Room Name for context
            'Room Participant' AS enrollment_type
        FROM 
            room_participants rp
        JOIN 
            users u ON rp.student_id = u.user_id
        WHERE 
            rp.room_id = ?

        ORDER BY joined_at DESC;
    ";

    $stmt = $conn->prepare($sql);
    // Bind parameters: 1. Schedule Room ID (i), 2. Room Name (Context) (s), 3. Room Participant Room ID (i)
    $stmt->bind_param("isi", $room_id, $roomName, $room_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    $seen_students = [];

    // Process results, ensuring each student (user_id) is listed only once for simplicity
    while ($row = $result->fetch_assoc()) {
        if (!isset($seen_students[$row['user_id']])) {
            $students[] = [
                "id" => $row['user_id'],
                "name" => $row['full_name'],
                "joined_at" => $row['joined_at'],
                "context" => $row['context_name'],
                "type" => $row['enrollment_type']
            ];
            $seen_students[$row['user_id']] = true;
        }
    }

    // --- DEBUG: Log the total number of students found ---
    error_log("GET_STUDENTS SCRIPT: Found " . count($students) . " unique students.");

    echo json_encode([
        "success" => true,
        "room_name" => $roomName,
        "student_count" => count($students),
        "data" => $students
    ]);

    $stmt->close();
    $conn->close(); // ✅ Close connection on success
} catch (Exception $e) {
    // Check if the connection is still open before trying to close it
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    // --- DEBUG: Log the database error details ---
    error_log("GET_STUDENTS SCRIPT: Database error - " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => "Database error: Failed to fetch students for room ID " . $room_id,
        "error_details" => $e->getMessage()
    ]);
}
