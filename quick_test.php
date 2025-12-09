<?php
// Direct verification without curl
session_start();
require_once(__DIR__ . "/database.php");

header('Content-Type: application/json');

// Test join first
if ($_GET['test'] === 'join') {
    $_SESSION['user_id'] = 100; // Test user

    $input = ['join_code' => $_GET['code'] ?? ''];

    // Find schedule
    $stmt = $conn->prepare("SELECT id, status FROM schedules WHERE join_code = ? LIMIT 1");
    $stmt->bind_param('s', $input['join_code']);
    $stmt->execute();
    $res = $stmt->get_result();
    $schedule = $res->fetch_assoc();
    $stmt->close();

    if (!$schedule) {
        echo json_encode(['error' => 'Schedule not found', 'code' => $input['join_code']]);
        exit;
    }

    $schedule_id = intval($schedule['id']);
    $student_id = intval($_SESSION['user_id']);

    // Insert
    $ins = $conn->prepare("INSERT IGNORE INTO schedule_participants (schedule_id, student_id, joined_at) VALUES (?, ?, NOW())");
    $ins->bind_param('ii', $schedule_id, $student_id);
    $result = $ins->execute();
    $ins->close();

    echo json_encode([
        'success' => $result,
        'schedule_id' => $schedule_id,
        'student_id' => $student_id,
        'message' => $result ? 'Joined successfully' : 'Join failed'
    ]);
    exit;
}

// Test room query
if ($_GET['test'] === 'rooms') {
    $sql = "
        SELECT 
            r.room_id, 
            r.room_name,
            r.capacity,
            COALESCE(
                (
                    SELECT COUNT(DISTINCT sp.student_id)
                    FROM schedule_participants sp
                    INNER JOIN schedules s ON sp.schedule_id = s.id
                    WHERE r.room_name = r.room_id 
                    AND s.status != 'archived'
                ),
                0
            ) AS current_participants
        FROM rooms r
        ORDER BY r.room_name ASC
    ";

    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// Test check columns
if ($_GET['test'] === 'schema') {
    $schedules_cols = $conn->query("SHOW COLUMNS FROM schedules");
    $rooms_cols = $conn->query("SHOW COLUMNS FROM rooms");

    $sch = [];
    while ($col = $schedules_cols->fetch_assoc()) {
        $sch[] = $col['Field'];
    }

    $rm = [];
    while ($col = $rooms_cols->fetch_assoc()) {
        $rm[] = $col['Field'];
    }

    echo json_encode(['schedules_columns' => $sch, 'rooms_columns' => $rm]);
    exit;
}

// Default: show all data
$stats = [];
$result = $conn->query("SELECT COUNT(*) as cnt FROM schedule_participants");
$stats['participants'] = $result->fetch_assoc()['cnt'];

$result = $conn->query("SELECT COUNT(*) as cnt FROM schedules WHERE status != 'archived'");
$stats['active_schedules'] = $result->fetch_assoc()['cnt'];

$result = $conn->query("SELECT COUNT(*) as cnt FROM rooms");
$stats['rooms'] = $result->fetch_assoc()['cnt'];

echo json_encode(['stats' => $stats, 'usage' => 'Add ?test=join&code=CODE or ?test=rooms or ?test=schema']);
