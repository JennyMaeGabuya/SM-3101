<?php
header('Content-Type: application/json');

require_once __DIR__ . '/connection/dbsConnection.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

$id = isset($data['id']) ? $data['id'] : null;
if ($id === null || $id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

// Ensure we have a DB connection
if (!isset($connection) || !$connection) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    exit;
}

// Support deleting by numeric accountId or by email/username.
try {
    $accountId = null;
    if (is_numeric($id)) {
        $accountId = (int)$id;
    } else {
        // try to resolve by email or username
        $q = $connection->prepare('SELECT accountId FROM accounts WHERE email = ? OR username = ? LIMIT 1');
        if ($q) {
            $q->bind_param('ss', $id, $id);
            $q->execute();
            $r = $q->get_result();
            if ($r && ($row = $r->fetch_assoc())) { $accountId = intval($row['accountId']); }
            if ($r) $r->close();
            $q->close();
        }
    }

    if (!$accountId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }

    // delete related user_sections and notification_reads to avoid orphaned references
    try {
        $d1 = $connection->prepare('DELETE FROM user_sections WHERE user_id = ?');
        if ($d1) { $d1->bind_param('i', $accountId); $d1->execute(); $d1->close(); }
    } catch (Exception $e) { error_log('delete_action: failed to delete user_sections: '.$e->getMessage()); }

    try {
        $d2 = $connection->prepare('DELETE FROM notification_reads WHERE account_id = ?');
        if ($d2) { $d2->bind_param('i', $accountId); $d2->execute(); $d2->close(); }
    } catch (Exception $e) { error_log('delete_action: failed to delete notification_reads: '.$e->getMessage()); }

    // attempt to find a teacher record that matches this account's email (case-insensitive)
    try {
        $emailStmt = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
        $accountEmail = null;
        if ($emailStmt) {
            $emailStmt->bind_param('i', $accountId);
            $emailStmt->execute();
            $resEmail = $emailStmt->get_result();
            if ($resEmail && ($rowEmail = $resEmail->fetch_assoc())) { $accountEmail = $rowEmail['email']; }
            if ($resEmail) $resEmail->close();
            $emailStmt->close();
        }
        if (!empty($accountEmail)) {
            // First, delete any resources that were specifically targeted to this email
            try {
                $tv = $accountEmail;
                $dq = $connection->prepare("DELETE FROM quizzes WHERE audience = 'specific' AND target_value = ?");
                if ($dq) { $dq->bind_param('s', $tv); $dq->execute(); $dq->close(); }
                $dt = $connection->prepare("DELETE FROM performance_tasks WHERE audience = 'specific' AND target_value = ?");
                if ($dt) { $dt->bind_param('s', $tv); $dt->execute(); $dt->close(); }
                $da = $connection->prepare("DELETE FROM activities WHERE audience = 'specific' AND target_value = ?");
                if ($da) { $da->bind_param('s', $tv); $da->execute(); $da->close(); }
                // Also remove notifications targeted to the user
                $notifTarget = 'user:' . $tv;
                try {
                    // First, collect any learning_material ids referenced by these notifications
                    $materialIds = [];
                    $mstmt = $connection->prepare("SELECT resource_id FROM notifications WHERE audience = ? AND resource_type = 'learning_material' AND resource_id IS NOT NULL");
                    if ($mstmt) {
                        $mstmt->bind_param('s', $notifTarget);
                        $mstmt->execute();
                        $mr = $mstmt->get_result();
                        if ($mr) {
                            while ($mr && ($rowm = $mr->fetch_assoc())) { $materialIds[] = intval($rowm['resource_id']); }
                            $mr->close();
                        }
                        $mstmt->close();
                    }

                    // delete notifications targeted to this user
                    $dn = $connection->prepare('DELETE FROM notifications WHERE audience = ?');
                    if ($dn) { $dn->bind_param('s', $notifTarget); $dn->execute(); $dn->close(); }

                    // delete learning_materials rows that were referenced by those notifications
                    if (!empty($materialIds)) {
                        // fetch file paths to unlink uploaded files
                        $fetchMat = $connection->prepare('SELECT id, file_path FROM learning_materials WHERE id = ? LIMIT 1');
                        $delMat = $connection->prepare('DELETE FROM learning_materials WHERE id = ?');
                        if ($fetchMat && $delMat) {
                            foreach ($materialIds as $mid) {
                                $fetchMat->bind_param('i', $mid);
                                $fetchMat->execute();
                                $r = $fetchMat->get_result();
                                if ($r && ($mrow = $r->fetch_assoc())) {
                                    $fp = $mrow['file_path'];
                                    if (!empty($fp) && file_exists(__DIR__ . '/../' . $fp)) {
                                        @unlink(__DIR__ . '/../' . $fp);
                                    }
                                }
                                if ($r) $r->close();
                                // delete DB row
                                $delMat->bind_param('i', $mid);
                                $delMat->execute();
                            }
                            $fetchMat->close();
                            $delMat->close();
                        }
                    }
                } catch (Exception $e) { error_log('delete_action: failed to remove learning materials for user-targeted notifications: '.$e->getMessage()); }
            } catch (Exception $e) { error_log('delete_action: failed to delete resources targeted to email: '.$e->getMessage()); }

            // find teacher(s) with the same email (case-insensitive)
            $tq = $connection->prepare('SELECT teacher_id FROM teachers WHERE LOWER(email) = LOWER(?)');
            if ($tq) {
                $tq->bind_param('s', $accountEmail);
                $tq->execute();
                $rt = $tq->get_result();
                $teacherIds = [];
                if ($rt) {
                    while ($tr = $rt->fetch_assoc()) { $teacherIds[] = intval($tr['teacher_id']); }
                    $rt->close();
                }
                $tq->close();

                if (!empty($teacherIds)) {
                    // Begin transaction so deletes are atomic
                    $connection->begin_transaction();
                    try {
                        // delete notifications created by these teachers
                        $delNotif = $connection->prepare('DELETE FROM notifications WHERE sender_id = ?');
                        if ($delNotif) {
                            foreach ($teacherIds as $tid) {
                                $delNotif->bind_param('i', $tid);
                                $delNotif->execute();
                            }
                            $delNotif->close();
                        }

                        // deleting the teacher row will cascade to quizzes, performance_tasks, activities, learning_materials
                        $delTeacher = $connection->prepare('DELETE FROM teachers WHERE teacher_id = ?');
                        if ($delTeacher) {
                            foreach ($teacherIds as $tid) {
                                $delTeacher->bind_param('i', $tid);
                                $delTeacher->execute();
                            }
                            $delTeacher->close();
                        }

                        $connection->commit();
                    } catch (Exception $e) {
                        error_log('delete_action: failed to remove teacher-owned content: '.$e->getMessage());
                        $connection->rollback();
                    }
                }
            }
        }
    } catch (Exception $e) { error_log('delete_action: failed to lookup or delete teacher content: '.$e->getMessage()); }

    // delete the account
    $stmt = $connection->prepare('DELETE FROM accounts WHERE accountId = ?');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $connection->error]);
        exit;
    }
    $stmt->bind_param('i', $accountId);
    $ok = $stmt->execute();
    if ($ok) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Account deleted']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Account not found']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
exit;

?>
