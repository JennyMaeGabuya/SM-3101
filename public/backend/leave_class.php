<?php
// leave_class.php - Leave by schedule_id or by access_code (schedule join_code or room special_access_code)

// Ensure this path correctly leads to your database.php file
include __DIR__ . "/../../database.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

// Accept JSON input or form-encoded POST
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$schedule_id = isset($input['schedule_id']) && $input['schedule_id'] !== '' ? (int)$input['schedule_id'] : null;
$access_code = isset($input['access_code']) && $input['access_code'] !== '' ? trim($input['access_code']) : null;
$student_id = isset($input['student_id']) && $input['student_id'] !== '' ? (int)$input['student_id'] : null;

// Prefer session user id if available
if (empty($student_id) && isset($_SESSION['user_id'])) {
    $student_id = (int)$_SESSION['user_id'];
}

if (empty($student_id)) {
    $response['message'] = 'Missing student ID (not logged in).';
    echo json_encode($response);
    exit;
}

try {
    if ($conn->connect_errno) {
        throw new Exception('Database connection error.');
    }

    // If schedule_id provided -> delete single enrollment
    if ($schedule_id) {
        $sql = "DELETE FROM schedule_participants WHERE schedule_id = ? AND student_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('ii', $schedule_id, $student_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'You have successfully left the class.';
                $response['sync'] = true;
            } else {
                $response['message'] = 'Enrollment not found or already left.';
            }
        } else {
            $response['message'] = 'Database error during deletion: ' . $stmt->error;
        }
        $stmt->close();

        echo json_encode($response);
        exit;
    }

    // If access_code provided -> interpret as schedule join_code first, then room special_access_code
    if ($access_code) {
        // Try match schedule by join_code
        $sql = "SELECT id FROM schedules WHERE join_code = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $access_code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $target_schedule_id = (int)$row['id'];
            $stmt->close();

            $del = $conn->prepare("DELETE FROM schedule_participants WHERE schedule_id = ? AND student_id = ?");
            if ($del === false) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $del->bind_param('ii', $target_schedule_id, $student_id);
            if ($del->execute()) {
                if ($del->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'You have successfully left the class.';
                    $response['sync'] = true;
                } else {
                    $response['message'] = 'Enrollment not found or already left.';
                }
            } else {
                $response['message'] = 'Database error during deletion: ' . $del->error;
            }
            $del->close();

            echo json_encode($response);
            exit;
        }
        $stmt->close();

        // Try match room by special_access_code
        $sql = "SELECT room_id FROM rooms WHERE special_access_code = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $access_code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $room = $res->fetch_assoc()) {
            $room_id = $room['room_id'];
            $stmt->close();

            // Find schedules in this room
            $q = "SELECT id FROM schedules WHERE room = ?";
            $sel = $conn->prepare($q);
            if ($sel === false) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $sel->bind_param('s', $room_id);
            $sel->execute();
            $r2 = $sel->get_result();
            $ids = [];
            while ($r2 && $srow = $r2->fetch_assoc()) {
                $ids[] = (int)$srow['id'];
            }
            $sel->close();

            if (count($ids) === 0) {
                $response['message'] = 'No schedules found for that room.';
                echo json_encode($response);
                exit;
            }

            // Delete enrollments for all those schedule ids
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids)) . 'i'; // schedule ids then student_id
            $sqlDel = "DELETE FROM schedule_participants WHERE schedule_id IN ($placeholders) AND student_id = ?";
            $delStmt = $conn->prepare($sqlDel);
            if ($delStmt === false) {
                // Fallback: run individual deletes
                foreach ($ids as $sid) {
                    $d = $conn->prepare("DELETE FROM schedule_participants WHERE schedule_id = ? AND student_id = ?");
                    if ($d) {
                        $d->bind_param('ii', $sid, $student_id);
                        $d->execute();
                        $d->close();
                    }
                }
                $response['success'] = true;
                $response['message'] = 'You have left the schedules for that room.';
                $response['sync'] = true;
                echo json_encode($response);
                exit;
            }

            // Bind params dynamically
            $params = [];
            $bindNames = [];
            foreach ($ids as $k => $sid) {
                $params[] = $sid;
                $bindNames[] = 'i';
            }
            $params[] = $student_id;

            // mysqli_stmt::bind_param requires references
            $types = str_repeat('i', count($ids)) . 'i';
            $bindValues = [];
            $bindValues[] = &$types;
            for ($i = 0; $i < count($params); $i++) {
                $bindValues[] = &$params[$i];
            }

            call_user_func_array([$delStmt, 'bind_param'], $bindValues);
            if ($delStmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'You have left the schedules for that room.';
                $response['sync'] = true;
            } else {
                $response['message'] = 'Database error during deletion: ' . $delStmt->error;
            }
            $delStmt->close();

            echo json_encode($response);
            exit;
        }
        $stmt->close();

        $response['message'] = 'Access code not recognized.';
        echo json_encode($response);
        exit;
    }

    $response['message'] = 'No schedule_id or access_code provided.';
} catch (Exception $e) {
    error_log('Leave Class Error: ' . $e->getMessage());
    $response['message'] = 'An unexpected server error occurred.';
}

echo json_encode($response);
