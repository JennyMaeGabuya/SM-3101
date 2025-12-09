<?php
// /SANROOM/public/backend/unenroll_student.php
// Removes student's enrollment from a schedule or from all schedules in a room (by access code)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . "/../../database.php");

header("Content-Type: application/json");

function resp($ok, $msg, $scheduleId = null, $roomId = null)
{
    $r = ["success" => $ok, "message" => $msg];
    if ($scheduleId) $r['scheduleId'] = $scheduleId;
    if ($roomId) $r['roomId'] = $roomId;
    if ($ok) $r['sync'] = true;
    echo json_encode($r);
    exit();
}

try {
    if ($conn->connect_error) resp(false, "DB connection error: " . $conn->connect_error);

    $student_id = intval($_SESSION['user_id'] ?? 0);
    if (!$student_id) resp(false, "Authentication required. Please log in.");

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $schedule_id = isset($input['schedule_id']) ? intval($input['schedule_id']) : null;
    $access_code = trim($input['access_code'] ?? '');

    if (!$schedule_id && !$access_code) {
        resp(false, "Provide schedule_id or access_code to unenroll.");
    }

    // If schedule_id provided, remove single mapping
    if ($schedule_id) {
        $del = $conn->prepare("DELETE FROM schedule_participants WHERE schedule_id = ? AND student_id = ?");
        if (!$del) resp(false, "Prepare failed: " . $conn->error);
        $del->bind_param('ii', $schedule_id, $student_id);
        if ($del->execute()) {
            resp(true, "Left schedule successfully.", $schedule_id, null);
        } else {
            resp(false, "Failed to leave schedule: " . $del->error);
        }
        $del->close();
    }

    // Otherwise, try to match access_code to schedule join_code first
    if ($access_code) {
        // Check schedule match
        $sstmt = $conn->prepare("SELECT id FROM schedules WHERE join_code = ? LIMIT 1");
        if (!$sstmt) resp(false, "Prepare failed: " . $conn->error);
        $sstmt->bind_param('s', $access_code);
        $sstmt->execute();
        $sres = $sstmt->get_result();
        $sched = $sres->fetch_assoc();
        $sstmt->close();

        if ($sched) {
            $sid = intval($sched['id']);
            $del = $conn->prepare("DELETE FROM schedule_participants WHERE schedule_id = ? AND student_id = ?");
            if (!$del) resp(false, "Prepare failed: " . $conn->error);
            $del->bind_param('ii', $sid, $student_id);
            if ($del->execute()) {
                resp(true, "Left schedule successfully.", $sid, null);
            } else {
                resp(false, "Failed to leave schedule: " . $del->error);
            }
            $del->close();
        }

        // If no schedule match, check rooms special_access_code
        $rstmt = $conn->prepare("SELECT room_id FROM rooms WHERE special_access_code = ? AND is_archived = 0 LIMIT 1");
        if (!$rstmt) resp(false, "Prepare failed: " . $conn->error);
        $rstmt->bind_param('s', $access_code);
        $rstmt->execute();
        $rres = $rstmt->get_result();
        $room = $rres->fetch_assoc();
        $rstmt->close();

        if ($room) {
            $rid = intval($room['room_id']);

            // Find active schedules in this room
            // 🐛 FIX: Changed 'room' to 'room_id'
            $stmt = $conn->prepare("SELECT id FROM schedules WHERE room_id = ? AND status != 'archived'");
            if (!$stmt) resp(false, "Prepare failed: " . $conn->error);
            $stmt->bind_param('i', $rid);
            $stmt->execute();
            $res = $stmt->get_result();
            $ids = [];
            while ($row = $res->fetch_assoc()) $ids[] = intval($row['id']);
            $stmt->close();

            if (empty($ids)) {
                // If the student uses the room code to leave, and no schedules are found,
                // they probably meant to unenroll from the room association itself.
                // However, since this file is named unenroll_student.php, we just notify
                // that no schedules were found to unenroll from. 
                // The dedicated unenroll_room.php handles the room-only removal.
                resp(false, "No active classes were found in this room to leave. If you wish to leave the room association, please use the dedicated button on the dashboard.");
            }

            $del = $conn->prepare("DELETE FROM schedule_participants WHERE schedule_id = ? AND student_id = ?");
            if (!$del) resp(false, "Prepare failed: " . $conn->error);
            $count = 0;
            foreach ($ids as $sid) {
                $del->bind_param('ii', $sid, $student_id);
                if ($del->execute()) $count++;
            }
            $del->close();

            resp(true, "Left $count schedule(s) in the room.", null, $rid);
        }

        resp(false, "Access code not found.");
    }

    resp(false, "Unhandled path.");
} catch (Exception $e) {
    // 💡 Improvement: Log the original error but return a generic message to the user
    error_log("Unenrollment Exception: " . $e->getMessage());
    resp(false, "Server error: An unexpected error occurred.");
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
