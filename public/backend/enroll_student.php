<?php
// /SANROOM/public/backend/enroll_student.php - FINAL VERSION (Status: 'reserved' used consistently)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . "/../../database.php");

header("Content-Type: application/json");

const SESSION_KEY_ATTEMPTS = 'room_join_attempts';
const ATTEMPTS_LIMIT = 5;

// Function to handle JSON response with HTTP status code
function send_response($success, $message, $scheduleId = null, $roomId = null, $http_code = 200)
{
    http_response_code($http_code);

    $response = [
        "success" => $success,
        "message" => $message
    ];

    if ($success && ($scheduleId || $roomId)) {
        if ($scheduleId) $response["scheduleId"] = $scheduleId;
        if ($roomId) $response["roomId"] = $roomId;
        $response["sync"] = true;
    }

    $result_log = $success ? "SUCCESS" : "FAIL";
    error_log("[DEBUG] enroll_student: Response sent ({$result_log}) HTTP {$http_code}. Room: {$roomId}. Message: {$message}");

    echo json_encode($response);
    exit();
}

try {
    if ($conn->connect_error) {
        error_log("[DEBUG] enroll_student: DB Connection Error: " . $conn->connect_error);
        send_response(false, "Connection Error: " . $conn->connect_error, null, null, 500);
    }

    $student_id = intval($_SESSION['user_id'] ?? 0);
    if (!$student_id) {
        send_response(false, "Authentication Error: Could not identify student. Please log in.", null, null, 401);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $full_access_code = trim($input['access_code'] ?? $input['join_code'] ?? $_POST['access_code'] ?? $_POST['join_code'] ?? '');

    if (!$full_access_code) {
        send_response(false, "Missing access code. Please provide a join code or room code.", null, null, 400);
    }

    error_log("[DEBUG] enroll_student: Student {$student_id} attempting join with code: {$full_access_code}");

    $final_schedule_id = null;
    $final_room_id = null;
    $room_manual_status = null;
    $is_schedule_join = false;

    // --- New: Separate the submitted code parts (used later for Room Join only) ---
    $code_parts = explode('-', $full_access_code);
    $submitted_room_code = $code_parts[0];
    $submitted_capacity = $code_parts[1] ?? null;

    // =========================================================
    // 🔍 PHASE 1: TRY SCHEDULE JOIN (standard join_code)
    // =========================================================
    // Schedule join still uses the full code as the 'join_code' for direct lookup
    $lookup_sql = "
        SELECT 
            s.id, s.status, r.room_id, r.manual_status 
        FROM 
            schedules s
        JOIN 
            rooms r ON s.room_id = r.room_id
        WHERE 
            s.join_code = ? 
        LIMIT 1";

    if (!$lookup_stmt = $conn->prepare($lookup_sql)) {
        send_response(false, "Database schedule lookup failed: " . $conn->error, null, null, 500);
    }
    $lookup_stmt->bind_param("s", $full_access_code);
    $lookup_stmt->execute();
    $lookup_result = $lookup_stmt->get_result();
    $schedule = $lookup_result->fetch_assoc();
    $lookup_stmt->close();

    if ($schedule) {
        $final_schedule_id = intval($schedule['id']);
        $final_room_id = intval($schedule['room_id']);
        $room_manual_status = strtolower($schedule['manual_status'] ?? 'available');
        $is_schedule_join = true;

        error_log("[DEBUG] enroll_student: Found Schedule {$final_schedule_id} in Room {$final_room_id}. Status: {$room_manual_status}. Join type: Schedule.");

        if (($schedule['status'] ?? 'active') === 'archived') {
            send_response(false, "Cannot join archived schedule.");
        }

        // 💡 CRITICAL LOGIC: Invitation Check for Reserved Rooms (Schedule Join)
        if ($room_manual_status === 'reserved') { // ⬅️ UPDATED STATUS
            $invite_sql = "SELECT 1 FROM room_invitations WHERE room_id = ? AND student_id = ?";

            if (!$invite_stmt = $conn->prepare($invite_sql)) {
                throw new Exception("Schedule join invitation check preparation failed: " . $conn->error);
            }

            $invite_stmt->bind_param("ii", $final_room_id, $student_id);
            $invite_stmt->execute();
            $is_invited = $invite_stmt->get_result()->num_rows > 0;
            $invite_stmt->close();

            error_log("[DEBUG] enroll_student: RFE Check (Schedule). Room {$final_room_id}. Student {$student_id}. Invited: " . ($is_invited ? "YES" : "NO"));

            if (!$is_invited) {
                send_response(false, "Access Denied: This reserved class requires a verified invitation. 🚫", null, $final_room_id, 403);
            }
        }
    }

    // =========================================================
    // 🔍 PHASE 2: TRY ROOM JOIN (special_access_code + optional capacity)
    // =========================================================
    if (!$schedule) {

        // Lookup room using only the special_access_code part
        $room_sql = "SELECT room_id, manual_status, capacity FROM rooms WHERE special_access_code = ? AND is_archived = 0 LIMIT 1";

        if (!$room_stmt = $conn->prepare($room_sql)) {
            send_response(false, "Database room lookup failed: " . $conn->error, null, null, 500);
        }

        $room_stmt->bind_param("s", $submitted_room_code); // Use only the room code part for the database lookup
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        $room = $room_result->fetch_assoc();
        $room_stmt->close();

        if ($room) {
            $final_room_id = intval($room['room_id']);
            $room_manual_status = strtolower($room['manual_status'] ?? 'available');
            $actual_capacity = intval($room['capacity'] ?? 0);

            error_log("[DEBUG] enroll_student: Found Room {$final_room_id}. Status: {$room_manual_status}. Join type: Room Code.");

            // --- CRITICAL NEW STATUS-BASED VALIDATION ---
            if ($room_manual_status === 'reserved') { // ⬅️ UPDATED STATUS
                error_log("[DEBUG] enroll_student: Room is RFE. Enforcing combined code check.");

                // 1. Enforce CODE-CAPACITY format
                if (count($code_parts) !== 2 || is_null($submitted_capacity)) {
                    error_log("[DEBUG] enroll_student: Room code failed format validation (missing capacity component).");
                    send_response(false, "Invalid Room Code. For reserved events, the code must include the room capacity (e.g., CODE-CAPACITY).", null, null, 400);
                }

                // 2. Enforce correct capacity
                if (intval($submitted_capacity) !== $actual_capacity) {
                    error_log("[DEBUG] enroll_student: Room code failed capacity validation. Submitted: {$submitted_capacity}, Actual: {$actual_capacity}");
                    send_response(false, "Invalid Room Code. The capacity component of the code is incorrect.", null, null, 400);
                }
            }
            // If status is NOT 'reserved', we accept just the special_access_code.
            // ----------------------------------------------------
        }
    }

    // =========================================================
    // 🛑 PHASE 3: ROOM/SCHEDULE NOT FOUND
    // =========================================================
    if (!$final_room_id) {
        error_log("[DEBUG] enroll_student: Room/Schedule not found for code: {$full_access_code}");
        send_response(false, "Invalid access code. Please check your schedule or room code.");
    }

    // =========================================================
    // 🔒 PHASE 4: ROOM STATUS CHECK (CRITICAL SECURITY LOGIC)
    // =========================================================

    if (!$is_schedule_join) {
        $attempt_key = $full_access_code . '_' . $student_id;
        if (!isset($_SESSION[SESSION_KEY_ATTEMPTS])) {
            $_SESSION[SESSION_KEY_ATTEMPTS] = [];
        }
        $current_attempts = $_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]['count'] ?? 0;

        // 4a. Handle "occupied" status (Immediate Block)
        if ($room_manual_status === 'occupied') {
            unset($_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]);
            error_log("[DEBUG] enroll_student: Blocked - Room {$final_room_id} is Occupied.");
            send_response(false, "The room is currently **occupied** and cannot be joined at this time. 🚧", null, $final_room_id, 403);
        }

        // 4b. Handle "reserved" status (Verified Invitation + 5-attempt security)
        if ($room_manual_status === 'reserved') { // ⬅️ UPDATED STATUS

            // --- STEP 1: VERIFY INVITATION ---
            $invite_sql = "
                SELECT 1 
                FROM room_invitations 
                WHERE room_id = ? AND student_id = ?";

            if (!$invite_stmt = $conn->prepare($invite_sql)) {
                throw new Exception("Invitation check preparation failed: " . $conn->error);
            }

            $invite_stmt->bind_param("ii", $final_room_id, $student_id);
            $invite_stmt->execute();
            $is_invited = $invite_stmt->get_result()->num_rows > 0;
            $invite_stmt->close();

            error_log("[DEBUG] enroll_student: RFE Check (Room Code). Room {$final_room_id}. Student {$student_id}. Invited: " . ($is_invited ? "YES" : "NO"));

            if (!$is_invited) {
                // Student is not on the exclusive invitation list -> BLOCK
                unset($_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]);
                send_response(false, "Access Denied: This reserved event requires a verified invitation. 🚫", null, $final_room_id, 403);
            }

            // --- STEP 2: 5-ATTEMPT ROLLING CODE CHECK (Only runs if invited) ---
            $current_attempts++;
            $_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]['count'] = $current_attempts;
            $_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]['time'] = time();

            error_log("[DEBUG] enroll_student: Rolling Code Check. Attempts: {$current_attempts}/{$current_attempts}");

            if ($current_attempts < ATTEMPTS_LIMIT) {
                $remaining = ATTEMPTS_LIMIT - $current_attempts;
                send_response(false, "🔐 Verified access: Enter the code **" . $remaining . "** more time(s) to finalize your exclusive entry.", null, $final_room_id, 403);
            }

            unset($_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]);
            error_log("[DEBUG] enroll_student: Rolling Code check passed.");
        }
    }


    // =========================================================
    // ✅ PHASE 5: EXECUTE ENROLLMENT (If all checks passed)
    // =========================================================
    error_log("[DEBUG] enroll_student: Entering Phase 5 Enrollment.");

    if ($is_schedule_join) {
        // ... (Schedule enrollment logic, unchanged) ...
        $check_sql = "SELECT id FROM schedule_participants WHERE schedule_id = ? AND student_id = ?";
        if (!$check_stmt = $conn->prepare($check_sql)) {
            send_response(false, "Database schedule check failed: " . $conn->error, null, null, 500);
        }
        $check_stmt->bind_param("ii", $final_schedule_id, $student_id);
        $check_stmt->execute();
        $is_already_enrolled = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        if ($is_already_enrolled) {
            send_response(true, "You are already enrolled in this class. 👍", $final_schedule_id, $final_room_id);
        }

        $insert_sql = "INSERT IGNORE INTO schedule_participants (schedule_id, student_id, joined_at) VALUES (?, ?, NOW())";
        if (!$insert_stmt = $conn->prepare($insert_sql)) {
            send_response(false, "Schedule enrollment preparation failed: " . $conn->error, null, null, 500);
        }
        $insert_stmt->bind_param("ii", $final_schedule_id, $student_id);

        if ($insert_stmt->execute()) {
            send_response(true, "Successfully joined the class!", $final_schedule_id, $final_room_id);
        } else {
            send_response(false, "Schedule enrollment failed: " . $insert_stmt->error, null, null, 500);
        }
        $insert_stmt->close();
    } elseif ($final_room_id) {
        // Enrollment for the room only (and all its active schedules)

        // 5a. Associate student with the room (Room Participant)
        $roomAssocStmt = $conn->prepare("INSERT IGNORE INTO room_participants (room_id, student_id, joined_at) VALUES (?, ?, NOW())");
        if (!$roomAssocStmt) {
            send_response(false, "Failed to prepare room association: " . $conn->error, null, null, 500);
        }
        $roomAssocStmt->bind_param('ii', $final_room_id, $student_id);
        $roomAssocStmt->execute();
        $roomAssocStmt->close();

        error_log("[DEBUG] enroll_student: Student {$student_id} inserted into room_participants for Room {$final_room_id}.");

        // 5b & 5c. Enroll student in ALL schedules in this room
        $schedules_in_room_sql = "SELECT id FROM schedules WHERE room_id = ? AND status != 'archived'";

        if (!$sched_stmt = $conn->prepare($schedules_in_room_sql)) {
            send_response(false, "Database schedule query failed: " . $conn->error, null, null, 500);
        }
        $sched_stmt->bind_param("i", $final_room_id);
        $sched_stmt->execute();
        $sched_result = $sched_stmt->get_result();
        $schedules_in_room = [];
        while ($row = $sched_result->fetch_assoc()) {
            $schedules_in_room[] = intval($row['id']);
        }
        $sched_stmt->close();

        if (empty($schedules_in_room)) {
            send_response(true, "Successfully joined the room! No active classes are scheduled yet. 🥳", null, $final_room_id);
        }

        $enrolled_count = 0;
        $insert_sql = "INSERT IGNORE INTO schedule_participants (schedule_id, student_id, joined_at) VALUES (?, ?, NOW())";

        if (!$insert_stmt = $conn->prepare($insert_sql)) {
            error_log("Room bulk enrollment prepare failed: " . $conn->error);
            send_response(false, "Bulk enrollment preparation failed: " . $conn->error, null, null, 500);
        }

        foreach ($schedules_in_room as $sched_id) {
            $insert_stmt->bind_param("ii", $sched_id, $student_id);
            if ($insert_stmt->execute()) {
                $enrolled_count++;
            }
        }
        $insert_stmt->close();

        error_log("[DEBUG] enroll_student: Student {$student_id} bulk enrolled in {$enrolled_count} schedules.");

        send_response(true, "Successfully joined all classes in this room! 🎉", null, $final_room_id);
    }
} catch (Exception $e) {
    // Cleanup on error
    $temp_access_code = trim($input['access_code'] ?? $input['join_code'] ?? '');
    $temp_student_id = intval($_SESSION['user_id'] ?? 0);
    $attempt_key = $temp_access_code . '_' . $temp_student_id;

    if (isset($_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key])) {
        unset($_SESSION[SESSION_KEY_ATTEMPTS][$attempt_key]);
    }
    error_log("[FATAL] enroll_student: Exception occurred: " . $e->getMessage());
    send_response(false, "An internal error occurred: " . $e->getMessage(), null, null, 500);
}

if (isset($conn)) {
    $conn->close();
}
