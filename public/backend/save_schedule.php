<?php
require_once __DIR__ . "/../../database.php";

if (session_status() === PHP_SESSION_NONE) session_start();
header("Content-Type: application/json");

// --- DEBUG: Log the request method ---
error_log("SCHEDULE SCRIPT: Request Method is " . $_SERVER['REQUEST_METHOD']);

// --- FIX: Handle JSON input when $_POST is empty ---
$input = $_POST;
if (empty($input) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = file_get_contents("php://input");
    error_log("SCHEDULE SCRIPT: Raw JSON Data: " . $json_data);
    $decoded_data = json_decode($json_data, true);
    if (is_array($decoded_data)) {
        $input = $decoded_data;
    }
}

// --- DEBUG: Log the final input array content ---
error_log("SCHEDULE SCRIPT: Final Input Array: " . print_r($input, true));

// Collect inputs
$id = intval($input["id"] ?? 0);
$class_name = trim($input["class_name"] ?? "");
$instructor_name = trim($input["instructor_name"] ?? "");
$instructor_email = trim($input["instructor_email"] ?? "");
$instructor_phone = trim($input["instructor_phone"] ?? "");
$course_code = trim($input["course_code"] ?? "");
$join_code = trim($input["join_code"] ?? "");

// Room inputs
$room_id = intval($input["room_id"] ?? 0);
$room_name = trim($input["room_name"] ?? "");
$room_capacity = intval($input["room_capacity"] ?? 0);

$day = trim($input["day"] ?? "");
$start_time = trim($input["start_time"] ?? "");
$end_time = trim($input["end_time"] ?? "");

// Validation
$missing = [];
if (!$class_name) $missing[] = "Class Name";
if (!$instructor_name) $missing[] = "Instructor Name";
if (!$course_code) $missing[] = "Course Code";
if (!$room_id || !$room_name) $missing[] = "Room";
if (!$room_capacity) $missing[] = "Room Capacity";
if (!$day) $missing[] = "Day";
if (!$start_time) $missing[] = "Start Time";
if (!$end_time) $missing[] = "End Time";

if ($missing) {
    error_log("SCHEDULE SCRIPT: Validation Failed. Missing fields: " . implode(", ", $missing));
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: " . implode(", ", $missing)
    ]);
    exit;
}

// Fetch room special access code
$stmtRoom = $conn->prepare("SELECT special_access_code FROM rooms WHERE room_id = ?");
$stmtRoom->bind_param("i", $room_id);
$stmtRoom->execute();
$roomResult = $stmtRoom->get_result()->fetch_assoc();

if (!$roomResult) {
    error_log("SCHEDULE SCRIPT: Room ID $room_id does not exist in the database.");
    echo json_encode([
        "success" => false,
        "message" => "Selected room does not exist."
    ]);
    exit;
}

$room_special_code = $roomResult['special_access_code'] ?? null;
if ($room_special_code) {
    $join_code = $room_special_code;
}

// Conflict check
try {
    $confSql = "
        SELECT COUNT(*) AS cnt 
        FROM schedules 
        WHERE room_id = ?
          AND day = ?
          AND status <> 'archived'
          AND id <> ?
          AND NOT (end_time <= ? OR start_time >= ?)
    ";
    $confStmt = $conn->prepare($confSql);
    $confStmt->bind_param("isiss", $room_id, $day, $id, $start_time, $end_time);
    $confStmt->execute();
    $count = $confStmt->get_result()->fetch_assoc()["cnt"];

    if ($count > 0) {
        error_log("SCHEDULE SCRIPT: Conflict detected for Room ID $room_id on $day at $start_time-$end_time.");
        echo json_encode([
            "success" => false,
            "message" => "Room is already booked for this day and time."
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("SCHEDULE SCRIPT: Conflict Check Exception: " . $e->getMessage());
}

// Image upload
$upload_filename = "";
$upload_dir = __DIR__ . "/../assets/img/instructors/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

if (!empty($_FILES["instructor_image"]["name"])) {
    $tmp = $_FILES["instructor_image"]["tmp_name"];
    $clean = time() . "_" . preg_replace("/[^a-zA-Z0-9_\.-]/", "", basename($_FILES["instructor_image"]["name"]));
    move_uploaded_file($tmp, $upload_dir . $clean);
    $upload_filename = $clean;
}

// UPDATE
if ($id > 0) {
    if (!$upload_filename) {
        $res = $conn->query("SELECT instructor_image FROM schedules WHERE id=$id");
        $upload_filename = $res->fetch_assoc()["instructor_image"] ?? null;
    }

    $stmt = $conn->prepare("
        UPDATE schedules SET
            class_name=?, instructor_name=?, instructor_email=?, instructor_phone=?,
            instructor_image=?, course_code=?, join_code=?, room_id=?, room_name=?,
            room_capacity=?, day=?, start_time=?, end_time=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssssssisisssi",
        $class_name,
        $instructor_name,
        $instructor_email,
        $instructor_phone,
        $upload_filename,
        $course_code,
        $join_code,
        $room_id,
        $room_name,
        $room_capacity,
        $day,
        $start_time,
        $end_time,
        $id
    );

    $stmt->execute();
    $conn->close();
    error_log("SCHEDULE SCRIPT: Successfully updated schedule ID $id.");
    echo json_encode(["success" => true, "message" => "Schedule updated"]);
    exit;
}

// INSERT
$stmt = $conn->prepare("
    INSERT INTO schedules 
        (class_name, instructor_name, instructor_email, instructor_phone,
         instructor_image, course_code, join_code, room_id, room_name, room_capacity,
         day, start_time, end_time)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "sssssssisisss",
    $class_name,
    $instructor_name,
    $instructor_email,
    $instructor_phone,
    $upload_filename,
    $course_code,
    $join_code,
    $room_id,
    $room_name,
    $room_capacity,
    $day,
    $start_time,
    $end_time
);

$stmt->execute();
$conn->close();
error_log("SCHEDULE SCRIPT: Successfully inserted new schedule.");
echo json_encode(["success" => true, "message" => "Schedule added"]);
