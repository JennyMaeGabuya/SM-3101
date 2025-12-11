<?php
// Simple JSON endpoint returning notifications for students
header('Content-Type: application/json; charset=utf-8');
// Allow local development same-origin requests
// include DB
require_once __DIR__ . '/../connection/dbsConnection.php';

$out = ['ok' => false, 'notifications' => []];
if (!isset($connection) || !$connection) {
    echo json_encode($out);
    exit;
}

try {
    // If a student is logged in, include notifications targeted to that student's email
    if (session_status() === PHP_SESSION_NONE) session_start();
    $email = null;
    $userCreatedAt = null;
    if (!empty($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $u = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
        if ($u) { $u->bind_param('i', $uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $rr = $resu->fetch_assoc(); if ($rr) $email = $rr['email']; $resu->close(); } $u->close(); }
    }

    // also fetch user's account creation time so we can avoid showing notifications that predate the account
    if (!empty($uid)) {
        try {
            $q = $connection->prepare('SELECT created_at FROM accounts WHERE accountId = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $uid);
                $q->execute();
                $r = $q->get_result();
                if ($r && ($rowc = $r->fetch_assoc())) {
                    $userCreatedAt = $rowc['created_at'];
                }
                if ($r) $r->close();
                $q->close();
            }
        } catch (Exception $e) { /* ignore */ }
    }

    // If logged in as a student, fetch their email and sections to include section-targeted notifications
    $userSections = [];
    if ($email) {
        // Pull user's sections
        try {
            $ps = $connection->prepare('SELECT section_id FROM user_sections WHERE user_id = ?');
            if ($ps) {
                $ps->bind_param('i', $uid);
                $ps->execute();
                $rs = $ps->get_result();
                if ($rs) {
                    while ($r = $rs->fetch_assoc()) { $userSections[] = (int)$r['section_id']; }
                    $rs->close();
                }
                $ps->close();
            }
        } catch (Exception $e) { error_log('api/notifications: failed to fetch user sections: ' . $e->getMessage()); }

        // Fetch unread notifications including those with audience starting with 'section:'
        // We exclude notifications that the user already has in notification_reads so read ones are not returned.
        $sql = "SELECT n.id, n.sender_id, n.title, n.message, n.created_at, n.audience, COALESCE(t.username, 'Teacher') AS sender_name
                FROM notifications n
                LEFT JOIN teachers t ON n.sender_id = t.teacher_id
                LEFT JOIN notification_reads rr ON rr.notification_id = n.id AND rr.account_id = ?
                WHERE rr.notification_id IS NULL AND (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%')
                ORDER BY n.created_at DESC LIMIT 400";
        $stmt = $connection->prepare($sql);
        if ($stmt) {
            $target = 'user:' . $email;
            $stmt->bind_param('is', $uid, $target);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            // fallback: run safe query without prepared param binding for rr (best-effort)
            $targetEsc = $connection->real_escape_string('user:' . $email);
            $q = "SELECT n.id, n.sender_id, n.title, n.message, n.created_at, n.audience, COALESCE(t.username, 'Teacher') AS sender_name FROM notifications n LEFT JOIN teachers t ON n.sender_id = t.teacher_id WHERE (n.audience IN ('students','all') OR n.audience = '" . $targetEsc . "' OR n.audience LIKE 'section:%') ORDER BY n.created_at DESC LIMIT 400";
            $res = $connection->query($q);
        }
    } else {
        $sql = "SELECT n.id, n.sender_id, n.title, n.message, n.created_at, n.audience, COALESCE(t.username, 'Teacher') AS sender_name FROM notifications n LEFT JOIN teachers t ON n.sender_id = t.teacher_id WHERE n.audience IN ('students','all') ORDER BY n.created_at DESC LIMIT 200";
        $res = $connection->query($sql);
    }
    if ($res) {
            // Deduplicate notifications that may appear multiple times due to JOINs
            $seen = [];
            while ($row = $res->fetch_assoc()) {
                // build a stable key: prefer explicit resource mapping when available
                $rkey = null;
                try {
                    if (isset($row['resource_type']) || isset($row['resource_id'])) {
                        $rt = isset($row['resource_type']) ? (string)$row['resource_type'] : '';
                        $rid = isset($row['resource_id']) ? (string)$row['resource_id'] : '';
                        $rkey = $rt . ':' . $rid;
                    }
                } catch (Exception $e) { $rkey = null; }
                if (empty($rkey)) {
                    $rkey = (isset($row['sender_id']) ? (int)$row['sender_id'] : 0) . '|' . strtolower(trim(preg_replace('/\s+/', ' ', $row['title'] ?? '')));
                }
                if (isset($seen[$rkey])) continue; // skip duplicate
                $seen[$rkey] = true;
                // If the user just created their account, avoid showing notifications older than their account
                try {
                    if (!empty($userCreatedAt) && !empty($row['created_at'])) {
                        if (strtotime($row['created_at']) < strtotime($userCreatedAt)) {
                            continue; // skip notifications older than account
                        }
                    }
                } catch (Exception $e) { /* ignore parse errors */ }
            // If this notification targets sections, check membership before including
            try {
                if (!empty($row['audience']) && strpos($row['audience'], 'section:') === 0) {
                    // parse section ids from audience value
                    $parts = explode(':', $row['audience'], 2);
                    $okForUser = false;
                    if (isset($parts[1]) && trim($parts[1]) !== '') {
                        $targetIds = array_filter(array_map('intval', explode(',', $parts[1])));
                        if (!empty($userSections) && !empty($targetIds)) {
                            foreach ($targetIds as $tid) { if (in_array($tid, $userSections, true)) { $okForUser = true; break; } }
                        }
                    }
                    if (!$okForUser) {
                        // skip this notification for this user
                        continue;
                    }
                }
            } catch (Exception $e) { /* ignore parse errors and fall through */ }
            $notif = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'sender' => $row['sender_name'],
                'related' => null,
                'read' => false,
            ];

            // Attempt to detect related resource by title patterns
            try {
                $t = $row['title'];
                if (stripos($t, 'New Quiz:') === 0) {
                    $qtitle = trim(substr($t, strlen('New Quiz:')));
                    $q = $connection->prepare('SELECT id FROM quizzes WHERE title LIKE ? ORDER BY created_at DESC LIMIT 1');
                    if ($q) { $like = "%" . $qtitle . "%"; $q->bind_param('s', $like); $q->execute(); $rr = $q->get_result(); if ($rr && ($r=$rr->fetch_assoc())) { $notif['related'] = ['type' => 'quiz', 'href' => 'quiz.php?id=' . (int)$r['id'] ]; } if ($rr) $rr->close(); $q->close(); }
                } elseif (stripos($t, 'New Activity:') === 0) {
                    $atitle = trim(substr($t, strlen('New Activity:')));
                    $q = $connection->prepare('SELECT id FROM activities WHERE title LIKE ? ORDER BY created_at DESC LIMIT 1');
                    if ($q) { $like = "%" . $atitle . "%"; $q->bind_param('s', $like); $q->execute(); $rr = $q->get_result(); if ($rr && ($r=$rr->fetch_assoc())) { $notif['related'] = ['type' => 'activity', 'href' => 'schedule.php#activity-' . (int)$r['id'] ]; } if ($rr) $rr->close(); $q->close(); }
                } elseif (stripos($t, 'New Performance Task:') === 0) {
                    $ptitle = trim(substr($t, strlen('New Performance Task:')));
                    $q = $connection->prepare('SELECT id FROM performance_tasks WHERE title LIKE ? ORDER BY created_at DESC LIMIT 1');
                    if ($q) { $like = "%" . $ptitle . "%"; $q->bind_param('s', $like); $q->execute(); $rr = $q->get_result(); if ($rr && ($r=$rr->fetch_assoc())) { $notif['related'] = ['type' => 'task', 'href' => 'view_task.php?id=' . (int)$r['id'] ]; } if ($rr) $rr->close(); $q->close(); }
                }
            } catch (Exception $e) { /* ignore related lookup errors */ }

            // mark read status if user is logged in
            try {
                if (!empty($_SESSION['user_id'])) {
                    $acctId = intval($_SESSION['user_id']);
                    $rchk = $connection->prepare('SELECT 1 FROM notification_reads WHERE notification_id = ? AND account_id = ? LIMIT 1');
                    if ($rchk) { $rchk->bind_param('ii', $row['id'], $acctId); $rchk->execute(); $rres = $rchk->get_result(); if ($rres && $rres->num_rows>0) { $notif['read'] = true; } if ($rres) $rres->close(); $rchk->close(); }
                }
            } catch (Exception $e) { /* ignore */ }

            $out['notifications'][] = $notif;
        }
        $res->close();
    }
    $out['ok'] = true;
} catch (Exception $e) {
    error_log('api/notifications error: '.$e->getMessage());
}

// Ensure we return ok=true when we have notifications collected even if an
// unexpected non-fatal issue occurred earlier. This prevents clients from
// ignoring returned notifications when the top-level ok flag remained false.
if (!empty($out['notifications'])) {
    $out['ok'] = true;
}

echo json_encode($out);
exit;
