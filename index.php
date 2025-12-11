<?php
include 'connection/dbsConnection.php';
// Compute dashboard stat counts for the logged-in student
$userCreatedAt = null;
if (session_status() === PHP_SESSION_NONE) session_start();
// Determine logged-in student info (email, id, created_at) and section ids
$student_email = null;
$student_id = null;
$student_section_ids = [];
if (!empty($_SESSION['user_id'])) {
  $student_id = intval($_SESSION['user_id']);
  // load email and created_at for audience filtering
  if (isset($connection) && $connection) {
    $u = $connection->prepare('SELECT email, created_at FROM accounts WHERE accountId = ? LIMIT 1');
    if ($u) {
      $u->bind_param('i', $student_id);
      $u->execute();
      $resu = $u->get_result();
      if ($resu) {
        $r = $resu->fetch_assoc();
        if ($r) {
          $student_email = $r['email'];
          $userCreatedAt = $r['created_at'];
        }
        $resu->close();
      }
      $u->close();
    }

    // load student's section ids
    try {
      $s = $connection->prepare('SELECT section_id FROM user_sections WHERE user_id = ?');
      if ($s) {
        $s->bind_param('i', $student_id);
        $s->execute();
        $rs = $s->get_result();
        if ($rs) {
          while ($rr = $rs->fetch_assoc()) {
            $student_section_ids[] = intval($rr['section_id']);
          }
          $rs->close();
        }
        $s->close();
      }
    } catch (Exception $e) { /* ignore section load errors */
    }
  }
}
$stats = [
  'quizzes_to_answer' => 0,
  'assignments_due' => 0,
  'performance_tasks' => 0,
  'activities' => 0,
  'materials' => 0,
  'courses' => 0
];
if (isset($connection) && $connection) {
  try {
    // fallback: try notifications mapping for activities
    $hasNotifResource = false;
    try {
      $c1 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'");
      if ($c1 && $c1->num_rows > 0) {
        $c2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'");
        if ($c2 && $c2->num_rows > 0) $hasNotifResource = true;
        if ($c1) $c1->close();
        if ($c2) $c2->close();
      }
    } catch (Exception $e) {
      $hasNotifResource = false;
    }
    if ($hasNotifResource && $student_email) {
      $target = 'user:' . $student_email;
      $sql = "SELECT a.id,a.title,a.description,a.scheduled_at,a.deadline, COALESCE(n.audience,'') AS audience FROM activities a JOIN notifications n ON ((n.resource_type='activity' AND n.resource_id = a.id) OR (n.title LIKE CONCAT('New Activity: ', a.title) AND n.sender_id = a.teacher_id)) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY a.scheduled_at DESC LIMIT 200";
      $stmtA = $connection->prepare($sql);
      if ($stmtA) {
        $stmtA->bind_param('s', $target);
        $stmtA->execute();
        $res = $stmtA->get_result();
        if ($res) {
          while ($r = $res->fetch_assoc()) {
            $aud = strval($r['audience'] ?? '');
            if (strpos($aud, 'section:') === 0) {
              $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
              $inter = array_intersect($sids, $student_section_ids);
              if (empty($inter)) continue;
            }
            $recent_activities[] = $r;
          }
          $res->close();
        }
        $stmtA->close();
      }
      $recent_activities = array_slice($recent_activities, 0, 6);
    } else {
      $ares = $connection->query("SELECT id,title,description,scheduled_at,deadline,created_at FROM activities ORDER BY scheduled_at DESC LIMIT 6");
      if ($ares) {
        while ($r = $ares->fetch_assoc()) $recent_activities[] = $r;
        if ($ares) $ares->close();
      }
    }

    // Performance tasks (assignments) count and assignments due (not yet submitted)
    $hasAudienceTasks = false;
    try {
      $col = $connection->query("SHOW COLUMNS FROM performance_tasks LIKE 'audience'");
      if ($col && $col->num_rows > 0) {
        $hasAudienceTasks = true;
        $col->close();
      }
    } catch (Exception $e) {
      $hasAudienceTasks = false;
    }
    if ($hasAudienceTasks && $student_email) {
      // total performance tasks
      if (!empty($student_section_ids)) {
        $place = [];
        foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, target_value)';
        $sc = implode(' OR ', $place);
        $sql = "SELECT COUNT(DISTINCT id) AS c FROM performance_tasks WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sc)))";
        $pstmt = $connection->prepare($sql);
        if ($pstmt) {
          $params = array_merge([$student_email], array_map('strval', $student_section_ids));
          $types = str_repeat('s', count($params));
          $binds = [$types];
          for ($i = 0; $i < count($params); $i++) $binds[] = &$params[$i];
          call_user_func_array([$pstmt, 'bind_param'], $binds);
          $pstmt->execute();
          $rres = $pstmt->get_result();
          if ($rres) {
            $stats['performance_tasks'] = (int)($rres->fetch_assoc()['c'] ?? 0);
            $rres->close();
          }
          $pstmt->close();
        }
      } else {
        $stmtp = $connection->prepare("SELECT COUNT(*) AS c FROM performance_tasks WHERE COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?)");
        if ($stmtp) {
          $stmtp->bind_param('s', $student_email);
          $stmtp->execute();
          $rres = $stmtp->get_result();
          if ($rres) {
            $stats['performance_tasks'] = (int)($rres->fetch_assoc()['c'] ?? 0);
            $rres->close();
          }
          $stmtp->close();
        }
      }

      // assignments due: performance tasks without a task_submissions entry for this student
      // count tasks assigned to student where no task_submissions for student exists
      if (!empty($student_section_ids)) {
        $place = [];
        foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, pt.target_value)';
        $sc = implode(' OR ', $place);
        $sql = "SELECT COUNT(DISTINCT pt.id) AS c FROM performance_tasks pt LEFT JOIN task_submissions ts ON ts.task_id = pt.id AND ts.student_id = ? WHERE (COALESCE(pt.audience,'students') IN ('students','all') OR (pt.audience = 'specific' AND pt.target_value = ?) OR (pt.audience = 'section' AND ($sc))) AND (ts.id IS NULL)";
        $stmtd = $connection->prepare($sql);
        if ($stmtd) {
          // bind: student_id, student_email, section ids...
          $params = array_merge([strval($student_id), $student_email], array_map('strval', $student_section_ids));
          $types = str_repeat('s', count($params));
          $binds = [$types];
          for ($i = 0; $i < count($params); $i++) $binds[] = &$params[$i];
          call_user_func_array([$stmtd, 'bind_param'], $binds);
          $stmtd->execute();
          $dr = $stmtd->get_result();
          if ($dr) {
            $stats['assignments_due'] = (int)($dr->fetch_assoc()['c'] ?? 0);
            $dr->close();
          }
          $stmtd->close();
        }
      } else {
        $stmtd = $connection->prepare("SELECT COUNT(pt.id) AS c FROM performance_tasks pt LEFT JOIN task_submissions ts ON ts.task_id = pt.id AND ts.student_id = ? WHERE (COALESCE(pt.audience,'students') IN ('students','all') OR (pt.audience = 'specific' AND pt.target_value = ?)) AND (ts.id IS NULL)");
        if ($stmtd) {
          $sidstr = (string)$student_id;
          $stmtd->bind_param('ss', $sidstr, $student_email);
          $stmtd->execute();
          $dr = $stmtd->get_result();
          if ($dr) {
            $stats['assignments_due'] = (int)($dr->fetch_assoc()['c'] ?? 0);
            $dr->close();
          }
          $stmtd->close();
        }
      }
    } else {
      // older schema or anonymous: count all performance_tasks and consider all as assignments due if no student
      $c = $connection->query("SELECT COUNT(*) as c FROM performance_tasks");
      if ($c) {
        $stats['performance_tasks'] = (int)($c->fetch_assoc()['c'] ?? 0);
        $c->close();
      }
      $stats['assignments_due'] = $stats['performance_tasks'];
    }

    // Activities: count upcoming activities assigned to the student
    $hasAudienceActs = false;
    try {
      $col = $connection->query("SHOW COLUMNS FROM activities LIKE 'audience'");
      if ($col && $col->num_rows > 0) {
        $hasAudienceActs = true;
        $col->close();
      }
    } catch (Exception $e) {
      $hasAudienceActs = false;
    }
    if ($hasAudienceActs && $student_email) {
      if (!empty($student_section_ids)) {
        $place = [];
        foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, target_value)';
        $sc = implode(' OR ', $place);
        $sql = "SELECT COUNT(DISTINCT id) AS c FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sc))) AND (COALESCE(deadline,scheduled_at) IS NULL OR COALESCE(deadline,scheduled_at) >= NOW())";
        $stmta = $connection->prepare($sql);
        if ($stmta = $stmta) {
          $params = array_merge([$student_email], array_map('strval', $student_section_ids));
          $types = str_repeat('s', count($params));
          $binds = [$types];
          for ($i = 0; $i < count($params); $i++) $binds[] = &$params[$i];
          call_user_func_array([$stmta, 'bind_param'], $binds);
          $stmta->execute();
          $ar = $stmta->get_result();
          if ($ar) {
            $stats['activities'] = (int)($ar->fetch_assoc()['c'] ?? 0);
            $ar->close();
          }
          $stmta->close();
        }
      } else {
        $stmta = $connection->prepare("SELECT COUNT(*) AS c FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?)) AND (COALESCE(deadline,scheduled_at) IS NULL OR COALESCE(deadline,scheduled_at) >= NOW())");
        if ($stmta) {
          $stmta->bind_param('s', $student_email);
          $stmta->execute();
          $ar = $stmta->get_result();
          if ($ar) {
            $stats['activities'] = (int)($ar->fetch_assoc()['c'] ?? 0);
            $ar->close();
          }
          $stmta->close();
        }
      }
    } else {
      $c = $connection->query("SELECT COUNT(*) as c FROM activities WHERE COALESCE(deadline,scheduled_at) >= NOW()");
      if ($c) {
        $stats['activities'] = (int)($c->fetch_assoc()['c'] ?? 0);
        $c->close();
      }
    }

    // Learning materials: use the same selection and filtering logic as `messages.php`
    $stats['materials'] = 0;
    try {
      if ($student_email) {
        $materials_map_ids = [];
        $target = 'user:' . $student_email;

        // detect whether notifications has resource mapping columns
        $hasResourceCols = false;
        try {
          $c1 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'");
          if ($c1) {
            if ($c1->num_rows > 0) $hasResourceCols = true;
            $c1->close();
          }
          $c2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'");
          if ($c2) {
            if ($c2->num_rows > 0) $hasResourceCols = $hasResourceCols && true;
            else $hasResourceCols = false;
            $c2->close();
          }
        } catch (Exception $e) {
          $hasResourceCols = false;
        }

        // detect teacher display column (try username, name, full_name)
        $teacherCol = null;
        try {
          $cols = ['username', 'name', 'full_name', 'display_name'];
          foreach ($cols as $c) {
            $qc = $connection->query("SHOW COLUMNS FROM teachers LIKE '" . $connection->real_escape_string($c) . "'");
            if ($qc && $qc->num_rows > 0) {
              $teacherCol = $c;
              $qc->close();
              break;
            }
            if ($qc) $qc->close();
          }
        } catch (Exception $e) {
          $teacherCol = null;
        }

        if ($hasResourceCols) {
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, COALESCE(n.audience,'') AS audience FROM learning_materials l LEFT JOIN teachers t ON t.teacher_id = l.teacher_id JOIN notifications n ON ((n.resource_type = 'learning_material' AND n.resource_id = l.id) OR (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title))) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%')";
          $stmtm = $connection->prepare($sql);
          if ($stmtm) {
            $stmtm->bind_param('s', $target);
            $stmtm->execute();
            $mres = $stmtm->get_result();
            if ($mres) {
              while ($mr = $mres->fetch_assoc()) {
                $mid = intval($mr['id'] ?? 0);
                if ($mid <= 0) continue;
                $aud = strval($mr['audience'] ?? '');
                if (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                  $intersect = array_intersect($sids, $student_section_ids);
                  if (empty($intersect)) continue;
                }
                $materials_map_ids[$mid] = true;
              }
              $mres->close();
            }
            $stmtm->close();
          }
        } else {
          // fallback to title-based join only
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, COALESCE(n.audience,'') AS audience FROM learning_materials l LEFT JOIN teachers t ON t.teacher_id = l.teacher_id JOIN notifications n ON (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%')";
          $stmtm = $connection->prepare($sql);
          if ($stmtm) {
            $stmtm->bind_param('s', $target);
            $stmtm->execute();
            $mres = $stmtm->get_result();
            if ($mres) {
              while ($mr = $mres->fetch_assoc()) {
                $mid = intval($mr['id'] ?? 0);
                if ($mid <= 0) continue;
                $aud = strval($mr['audience'] ?? '');
                if (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                  $intersect = array_intersect($sids, $student_section_ids);
                  if (empty($intersect)) continue;
                }
                $materials_map_ids[$mid] = true;
              }
              $mres->close();
            }
            $stmtm->close();
          }
        }

        $stats['materials'] = count($materials_map_ids);
      } else {
        $c = $connection->query("SELECT COUNT(*) as c FROM learning_materials");
        if ($c) {
          $stats['materials'] = (int)($c->fetch_assoc()['c'] ?? 0);
          $c->close();
        }
      }
    } catch (Exception $e) { /* ignore non-fatal */
    }

    // Quizzes to answer: quizzes assigned to the student that the student has not attempted and are not graded in submissions
    $hasAudience = false;
    try {
      $col = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'audience'");
      if ($col && $col->num_rows > 0) {
        $hasAudience = true;
        $col->close();
      }
    } catch (Exception $e) {
      $hasAudience = false;
    }
    // build base set of quiz ids available to the student
    $availableQuizIds = [];
    if ($hasAudience && $student_email) {
      if (!empty($student_section_ids)) {
        $place = [];
        foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, q.target_value)';
        $sc = implode(' OR ', $place);
        $sql = "SELECT DISTINCT q.id FROM quizzes q WHERE (COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?) OR (q.audience = 'section' AND ($sc)))";
        $q = $connection->prepare($sql);
        if ($q) {
          $params = array_merge([$student_email], array_map('strval', $student_section_ids));
          $types = str_repeat('s', count($params));
          $binds = [$types];
          for ($i = 0; $i < count($params); $i++) $binds[] = &$params[$i];
          call_user_func_array([$q, 'bind_param'], $binds);
          $q->execute();
          $res = $q->get_result();
          if ($res) {
            while ($r = $res->fetch_assoc()) $availableQuizIds[] = intval($r['id']);
            if ($res) $res->close();
          }
          $q->close();
        }
      } else {
        $q = $connection->prepare("SELECT DISTINCT q.id FROM quizzes q WHERE COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?)");
        if ($q) {
          $q->bind_param('s', $student_email);
          $q->execute();
          $res = $q->get_result();
          if ($res) {
            while ($r = $res->fetch_assoc()) $availableQuizIds[] = intval($r['id']);
            if ($res) $res->close();
          }
          $q->close();
        }
      }
    } else {
      $res = $connection->query("SELECT id FROM quizzes");
      if ($res) {
        while ($r = $res->fetch_assoc()) $availableQuizIds[] = intval($r['id']);
        if ($res) $res->close();
      }
    }

    if (!empty($availableQuizIds)) {
      // prepare placeholders
      $placeholders = implode(',', array_fill(0, count($availableQuizIds), '?'));
      // use prepared statement to count quizzes where student has no quiz_attempts with answers/score and no submissions graded
      $types = str_repeat('i', count($availableQuizIds));
      // We will build a manual query and bind quiz ids dynamically
      $sql = "SELECT COUNT(DISTINCT qid) AS c FROM (SELECT q.id AS qid FROM quizzes q WHERE q.id IN ($placeholders)) AS qlist LEFT JOIN (SELECT quiz_id FROM quiz_attempts WHERE student_id = ? AND ((answers IS NOT NULL AND answers <> '') OR score IS NOT NULL)) qa ON qa.quiz_id = qlist.qid LEFT JOIN (SELECT resource_id FROM submissions WHERE resource_type = 'quiz' AND (student_id = ? OR student_id = ?)) sub ON sub.resource_id = qlist.qid WHERE qa.quiz_id IS NULL AND sub.resource_id IS NULL";
      $stmt = $connection->prepare($sql);
      if ($stmt) {
        // build bind params: quiz ids, student_id (for quiz_attempts), student id string and email for submissions fallback
        $bind_types = str_repeat('i', count($availableQuizIds)) . 'i' . 'ss';
        $bind_vals = [];
        foreach ($availableQuizIds as $idv) $bind_vals[] = $idv;
        $bind_vals[] = $student_id ?? 0;
        $bind_vals[] = (string)($student_id ?? '');
        $bind_vals[] = $student_email ?? '';
        $bind_names = [];
        $bind_names[] = $bind_types;
        for ($i = 0; $i < count($bind_vals); $i++) {
          $n = 'b' . $i;
          $$n = $bind_vals[$i];
          $bind_names[] = &$$n;
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
        $stmt->execute();
        $cr = $stmt->get_result();
        if ($cr) {
          $stats['quizzes_to_answer'] = (int)($cr->fetch_assoc()['c'] ?? 0);
          $cr->close();
        }
        $stmt->close();
      }
    } else {
      $stats['quizzes_to_answer'] = 0;
    }

    // --- Build recent learning materials list (use same logic as messages.php) ---
    $materials = [];
    $materials_map = [];
    $notifications_map = [];
    try {
      // Detect whether notifications has resource mapping columns
      $hasResourceCols = false;
      try {
        $c1 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'");
        if ($c1) {
          if ($c1->num_rows > 0) $hasResourceCols = true;
          $c1->close();
        }
        $c2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'");
        if ($c2) {
          if ($c2->num_rows > 0) $hasResourceCols = $hasResourceCols && true;
          else $hasResourceCols = false;
          $c2->close();
        }
      } catch (Exception $e) {
        $hasResourceCols = false;
      }

      // Detect teacher display column (try username, name, full_name)
      $teacherCol = null;
      try {
        $cols = ['username', 'name', 'full_name', 'display_name'];
        foreach ($cols as $c) {
          $qc = $connection->query("SHOW COLUMNS FROM teachers LIKE '" . $connection->real_escape_string($c) . "'");
          if ($qc && $qc->num_rows > 0) {
            $teacherCol = $c;
            $qc->close();
            break;
          }
          if ($qc) $qc->close();
        }
      } catch (Exception $e) {
        $teacherCol = null;
      }

      if ($student_email) {
        $target = 'user:' . $student_email;
        if ($hasResourceCols) {
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, COALESCE(n.created_at, l.created_at) AS notif_created, n.audience FROM learning_materials l LEFT JOIN teachers t ON l.teacher_id = t.teacher_id JOIN notifications n ON ((n.resource_type = 'learning_material' AND n.resource_id = l.id) OR (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title))) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY n.created_at DESC LIMIT 200";
          $stmt = $connection->prepare($sql);
          if ($stmt) {
            $stmt->bind_param('s', $target);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) {
                if (!empty($r['audience']) && strpos($r['audience'], 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($r['audience'], 8))));
                  $intersect = array_intersect($sids, $student_section_ids);
                  if (empty($intersect)) continue;
                }
                if (!empty($r['id'])) $materials_map[intval($r['id'])] = $r;
                $ntitle = 'New Material: ' . ($r['title'] ?? '');
                $nkey = !empty($r['id']) ? 'm:' . intval($r['id']) : 't:' . md5($ntitle);
                if (!isset($notifications_map[$nkey])) {
                  $notifications_map[$nkey] = [
                    'id' => null,
                    'sender_id' => null,
                    'title' => $ntitle,
                    'message' => $r['description'],
                    'created_at' => $r['notif_created'],
                    'audience' => null
                  ];
                }
              }
              $res->close();
            }
            $stmt->close();
          }
        } else {
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, l.created_at AS notif_created, n.audience FROM learning_materials l LEFT JOIN teachers t ON l.teacher_id = t.teacher_id JOIN notifications n ON (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY n.created_at DESC LIMIT 200";
          $stmt = $connection->prepare($sql);
          if ($stmt) {
            $stmt->bind_param('s', $target);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) {
                if (!empty($r['audience']) && strpos($r['audience'], 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($r['audience'], 8))));
                  $intersect = array_intersect($sids, $student_section_ids);
                  if (empty($intersect)) continue;
                }
                if (!empty($r['id'])) $materials_map[intval($r['id'])] = $r;
                $ntitle = 'New Material: ' . ($r['title'] ?? '');
                $nkey = !empty($r['id']) ? 'm:' . intval($r['id']) : 't:' . md5($ntitle);
                if (!isset($notifications_map[$nkey])) {
                  $notifications_map[$nkey] = [
                    'id' => null,
                    'sender_id' => null,
                    'title' => $ntitle,
                    'message' => $r['description'],
                    'created_at' => $r['notif_created'],
                    'audience' => null
                  ];
                }
              }
              $res->close();
            }
            $stmt->close();
          }
        }
      } else {
        // not logged in: show materials sent to students/all
        if ($hasResourceCols) {
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, COALESCE(n.created_at, l.created_at) AS notif_created FROM learning_materials l LEFT JOIN teachers t ON l.teacher_id = t.teacher_id JOIN notifications n ON ((n.resource_type = 'learning_material' AND n.resource_id = l.id) OR (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title))) WHERE n.audience IN ('students','all') ORDER BY n.created_at DESC LIMIT 200";
          $stmt = $connection->prepare($sql);
          if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) {
                if (!empty($r['id'])) $materials_map[intval($r['id'])] = $r;
                $ntitle = 'New Material: ' . ($r['title'] ?? '');
                $nkey = !empty($r['id']) ? 'm:' . intval($r['id']) : 't:' . md5($ntitle);
                if (!isset($notifications_map[$nkey])) {
                  $notifications_map[$nkey] = ['id' => null, 'sender_id' => null, 'title' => $ntitle, 'message' => $r['description'], 'created_at' => $r['notif_created'], 'audience' => null];
                }
              }
              $res->close();
            }
            $stmt->close();
          }
        } else {
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, l.created_at AS notif_created FROM learning_materials l LEFT JOIN teachers t ON l.teacher_id = t.teacher_id JOIN notifications n ON (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) WHERE n.audience IN ('students','all') ORDER BY n.created_at DESC LIMIT 200";
          $stmt = $connection->prepare($sql);
          if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) {
                if (!empty($r['id'])) $materials_map[intval($r['id'])] = $r;
                $ntitle = 'New Material: ' . ($r['title'] ?? '');
                $nkey = !empty($r['id']) ? 'm:' . intval($r['id']) : 't:' . md5($ntitle);
                if (!isset($notifications_map[$nkey])) {
                  $notifications_map[$nkey] = ['id' => null, 'sender_id' => null, 'title' => $ntitle, 'message' => $r['description'], 'created_at' => $r['notif_created'], 'audience' => null];
                }
              }
              $res->close();
            }
            $stmt->close();
          }
        }
      }
    } catch (Exception $e) {
      error_log('dashboard: Fetch materials failed: ' . $e->getMessage());
    }

    // convert maps to indexed arrays for rendering (preserve ordering by notif_created where possible)
    if (!empty($materials_map)) {
      $materials = array_values($materials_map);
      usort($materials, function ($a, $b) {
        $ta = strtotime($a['notif_created'] ?? $a['created_at'] ?? '1970-01-01');
        $tb = strtotime($b['notif_created'] ?? $b['created_at'] ?? '1970-01-01');
        return $tb <=> $ta;
      });
    } else {
      $materials = [];
    }
    $stats['materials'] = count($materials);

    // --- Recent Quizzes (mirror courses.php selection logic) ---
    $recent_quizzes = [];
    try {
      $hasQuizAudience = false;
      try {
        $col = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'audience'");
        if ($col && $col->num_rows > 0) {
          $hasQuizAudience = true;
          $col->close();
        }
      } catch (Exception $e) {
        $hasQuizAudience = false;
      }
      if ($hasQuizAudience && $student_email) {
        if (!empty($student_section_ids)) {
          $place = [];
          foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, q.target_value)';
          $sc = implode(' OR ', $place);
          $sql = "SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,t.username AS teacher FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id WHERE (COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?) OR (q.audience = 'section' AND ($sc))) ORDER BY q.scheduled_at DESC LIMIT 6";
          $q = $connection->prepare($sql);
          if ($q) {
            $params = array_merge([$student_email], array_map('strval', $student_section_ids));
            $types = str_repeat('s', count($params));
            $binds = [$types];
            for ($i = 0; $i < count($params); $i++) $binds[] = &$params[$i];
            call_user_func_array([$q, 'bind_param'], $binds);
            $q->execute();
            $res = $q->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) $recent_quizzes[] = $r;
              if ($res) $res->close();
            }
            $q->close();
          }
        } else {
          $q = $connection->prepare("SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,t.username AS teacher FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id WHERE COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?) ORDER BY q.scheduled_at DESC LIMIT 6");
          if ($q) {
            $q->bind_param('s', $student_email);
            $q->execute();
            $res = $q->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) $recent_quizzes[] = $r;
              if ($res) $res->close();
            }
            $q->close();
          }
        }
      } else {
        // No audience column — try to filter using notifications mapping when possible
        $hasNotifResource = false;
        try {
          $c1 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'");
          if ($c1 && $c1->num_rows > 0) {
            $c2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'");
            if ($c2 && $c2->num_rows > 0) $hasNotifResource = true;
            if ($c1) $c1->close();
            if ($c2) $c2->close();
          }
        } catch (Exception $e) {
          $hasNotifResource = false;
        }

        if ($hasNotifResource && $student_email) {
          $target = 'user:' . $student_email;
          $sql = "SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,t.username AS teacher, COALESCE(n.audience,'') AS audience FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id JOIN notifications n ON (n.resource_type = 'quiz' AND n.resource_id = q.id) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY q.scheduled_at DESC LIMIT 200";
          $stmtQ = $connection->prepare($sql);
          if ($stmtQ) {
            $stmtQ->bind_param('s', $target);
            $stmtQ->execute();
            $res = $stmtQ->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) {
                // if audience is section:... ensure intersection
                $aud = strval($r['audience'] ?? '');
                if (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                  $inter = array_intersect($sids, $student_section_ids);
                  if (empty($inter)) continue;
                }
                $recent_quizzes[] = $r;
              }
              $res->close();
            }
            $stmtQ->close();
          }
          // limit to 6 for render
          $recent_quizzes = array_slice($recent_quizzes, 0, 6);
        } else {
          // last-resort: return latest quizzes (no audience data available)
          $res = $connection->query("SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,t.username AS teacher FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id ORDER BY q.scheduled_at DESC LIMIT 6");
          if ($res) {
            while ($r = $res->fetch_assoc()) $recent_quizzes[] = $r;
            if ($res) $res->close();
          }
        }
      }
    } catch (Exception $e) {
      error_log('dashboard: fetch quizzes failed: ' . $e->getMessage());
    }

    // --- Recent Performance Tasks (mirror announcements.php selection logic) ---
    $recent_tasks = [];
    try {
      $hasAudience = false;
      try {
        $col = $connection->query("SHOW COLUMNS FROM performance_tasks LIKE 'audience'");
        if ($col && $col->num_rows > 0) {
          $hasAudience = true;
          $col->close();
        }
      } catch (Exception $e) {
        $hasAudience = false;
      }

      // determine student email/id and created_at (already initialized earlier) and ensure sections are loaded
      $uid = $student_id ?? null;
      if ($hasAudience && $student_email) {
        // load student's sections if not loaded
        if (empty($student_section_ids) && !empty($uid)) {
          try {
            $student_section_ids = [];
            $s = $connection->prepare('SELECT section_id FROM user_sections WHERE user_id = ?');
            if ($s) {
              $s->bind_param('i', $uid);
              $s->execute();
              $rs = $s->get_result();
              if ($rs) {
                while ($rr = $rs->fetch_assoc()) $student_section_ids[] = intval($rr['section_id']);
                $rs->close();
              }
              $s->close();
            }
          } catch (Exception $e) { /* ignore */
          }
        }

        // Query performance_tasks with audience filters similar to announcements.php
        if (!empty($student_section_ids)) {
          $placeholders = [];
          foreach ($student_section_ids as $sid) {
            $placeholders[] = 'FIND_IN_SET(?, pt.target_value)';
          }
          $sectionsCond = implode(' OR ', $placeholders);
          $sql = "SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher, pt.audience, pt.target_value FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE (pt.audience IN ('students','all') OR (pt.audience = 'specific' AND pt.target_value = ?) OR (pt.audience = 'section' AND ($sectionsCond))) AND pt.created_at >= ? ORDER BY pt.due_date DESC LIMIT 6";
          $tres = $connection->prepare($sql);
          if ($tres) {
            $params = array_merge([$student_email], array_map('strval', $student_section_ids), [strval($userCreatedAt ?? '')]);
            $types = str_repeat('s', count($params));
            $bind_names = [$types];
            for ($i = 0; $i < count($params); $i++) {
              $bind_names[] = &$params[$i];
            }
            call_user_func_array(array($tres, 'bind_param'), $bind_names);
            $tres->execute();
            $resT = $tres->get_result();
            if ($resT) {
              while ($r = $resT->fetch_assoc()) $recent_tasks[] = $r;
              $resT->close();
            }
            $tres->close();
          }
        } else {
          $sql = "SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher, pt.audience, pt.target_value FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE (pt.audience IN ('students','all') OR (pt.audience = 'specific' AND pt.target_value = ?)) AND pt.created_at >= ? ORDER BY pt.due_date DESC LIMIT 6";
          $tres = $connection->prepare($sql);
          if ($tres) {
            $createdAtBind = strval($userCreatedAt ?? '');
            $tres->bind_param('ss', $student_email, $createdAtBind);
            $tres->execute();
            $resT = $tres->get_result();
            if ($resT) {
              while ($r = $resT->fetch_assoc()) $recent_tasks[] = $r;
              $resT->close();
            }
            $tres->close();
          }
        }

        // Also include performance tasks announced via notifications (match by title + sender)
        if (!empty($student_email)) {
          $emailToUse = $student_email;
          $stmt = $connection->prepare("SELECT id,sender_id,title,message,created_at,audience FROM notifications WHERE (audience IN ('students','all') OR audience = ? OR audience LIKE 'section:%') AND title LIKE 'New Performance Task:%' AND created_at >= ? ORDER BY created_at DESC LIMIT 100");
          if ($stmt) {
            $target = 'user:' . $emailToUse;
            $createdAtBindN = strval($userCreatedAt ?? '');
            $stmt->bind_param('ss', $target, $createdAtBindN);
            $stmt->execute();
            $nres = $stmt->get_result();
            if ($nres) {
              $existing_titles = array_map('strtolower', array_map('trim', array_column($recent_tasks, 'title')));
              while ($r = $nres->fetch_assoc()) {
                $t = trim($r['title'] ?? '');
                if ($t !== '' && stripos($t, 'New Performance Task:') === 0) {
                  $taskTitle = trim(substr($t, strlen('New Performance Task:')));
                  $sender = isset($r['sender_id']) ? intval($r['sender_id']) : null;
                  $aud = trim(strval($r['audience'] ?? ''));
                  if (strpos($aud, 'section:') === 0) {
                    $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                    $intersect = !empty($student_section_ids) ? array_intersect($sids, $student_section_ids) : [];
                    if (empty($intersect)) continue;
                  }
                  if ($sender && $taskTitle !== '') {
                    $normTaskTitle = strtolower(trim($taskTitle));
                    if (in_array($normTaskTitle, $existing_titles)) continue;
                    $like = '%' . $connection->real_escape_string($taskTitle) . '%';
                    $q = $connection->prepare('SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE pt.teacher_id = ? AND pt.title LIKE ? LIMIT 1');
                    if ($q) {
                      $q->bind_param('is', $sender, $like);
                      $q->execute();
                      $qr = $q->get_result();
                      if ($qr && $qr->num_rows > 0) {
                        $pt = $qr->fetch_assoc();
                        $recent_tasks[] = $pt;
                        $qr->close();
                      }
                      $q->close();
                    }
                  }
                }
              }
              $nres->close();
            }
            $stmt->close();
          }
        }
      } else {
        // no audience support or not logged in: show latest tasks
        $tres = $connection->query("SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id ORDER BY pt.due_date DESC LIMIT 6");
        if ($tres) {
          while ($r = $tres->fetch_assoc()) $recent_tasks[] = $r;
          if ($tres) $tres->close();
        }
      }
    } catch (Exception $e) {
      error_log('dashboard: fetch tasks failed: ' . $e->getMessage());
    }

    // Dev debug: show why each recent task was included when ?dev_debug=1 and student is logged in
    if (!empty($_GET['dev_debug']) && session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_GET['dev_debug']) && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
      $tasks_debug = [];
      foreach (array_slice($recent_tasks, 0, 6) as $t) {
        $tid = intval($t['id'] ?? 0);
        $tasks_debug[$tid] = ['title' => $t['title'] ?? '', 'matches' => [], 'task_audience' => null];
        try {
          $qa = $connection->prepare("SELECT audience, target_value, teacher_id FROM performance_tasks WHERE id = ? LIMIT 1");
          if ($qa) {
            $qa->bind_param('i', $tid);
            $qa->execute();
            $rqa = $qa->get_result();
            if ($rqa && ($row = $rqa->fetch_assoc())) {
              $tasks_debug[$tid]['task_audience'] = $row;
            }
            if ($rqa) $rqa->close();
            $qa->close();
          }
        } catch (Exception $e) { /* ignore */
        }

        try {
          $nq = $connection->prepare("SELECT id,sender_id,title,audience,created_at FROM notifications WHERE (resource_type='performance_task' AND resource_id = ?) OR (title LIKE CONCAT('New Performance Task: ', ?)) ORDER BY created_at DESC LIMIT 10");
          if ($nq) {
            $nq->bind_param('is', $tid, $t['title']);
            $nq->execute();
            $rn = $nq->get_result();
            if ($rn) {
              while ($nr = $rn->fetch_assoc()) {
                $aud = strval($nr['audience'] ?? '');
                $matched = false;
                if (in_array($aud, ['students', 'all'])) $matched = true;
                elseif (!empty($student_email) && $aud === ('user:' . $student_email)) $matched = true;
                elseif (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                  if (!empty(array_intersect($sids, $student_section_ids))) $matched = true;
                }
                $tasks_debug[$tid]['matches'][] = ['notif' => $nr, 'matched' => $matched];
              }
              $rn->close();
            }
            $nq->close();
          }
        } catch (Exception $e) { /* ignore */
        }
      }

      echo '<div style="padding:12px;background:#fff8c6;border:1px solid #f0e68c;margin:12px;border-radius:6px;">';
      echo '<h3 style="margin:0 0 8px 0">Dev Debug: recent_tasks include reasons</h3><pre style="white-space:pre-wrap;">' . htmlspecialchars(print_r($tasks_debug, true)) . '</pre>';
      echo '<div style="font-size:0.9em;color:#444;margin-top:6px">student_email: ' . htmlspecialchars($student_email ?? 'NULL') . ' | sections: ' . htmlspecialchars(implode(',', $student_section_ids ?? []));
      echo '</div></div>';
    }

    // --- Recent Activities (mirror schedule.php selection logic) ---
    $recent_activities = [];
    try {
      $hasAudienceActs = false;
      try {
        $col = $connection->query("SHOW COLUMNS FROM activities LIKE 'audience'");
        if ($col && $col->num_rows > 0) {
          $hasAudienceActs = true;
          $col->close();
        }
      } catch (Exception $e) {
        $hasAudienceActs = false;
      }

      if ($hasAudienceActs && $student_email) {
        if (!empty($student_section_ids)) {
          $place = [];
          foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, target_value)';
          $sc = implode(' OR ', $place);
          $sql = "SELECT id,title,description,scheduled_at,deadline,created_at FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sc))) AND created_at >= ? ORDER BY scheduled_at DESC LIMIT 6";
          $ares = $connection->prepare($sql);
          if ($ares) {
            $params = array_merge([$student_email], array_map('strval', $student_section_ids), [strval($userCreatedAt ?? '')]);
            $types = str_repeat('s', count($params));
            $binds = [$types];
            for ($i = 0; $i < count($params); $i++) $binds[] = &$params[$i];
            call_user_func_array([$ares, 'bind_param'], $binds);
            $ares->execute();
            $resA = $ares->get_result();
            if ($resA) {
              while ($r = $resA->fetch_assoc()) $recent_activities[] = $r;
              $resA->close();
            }
            $ares->close();
          }
        } else {
          $ares = $connection->prepare("SELECT id,title,description,scheduled_at,deadline,created_at FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?)) AND created_at >= ? ORDER BY scheduled_at DESC LIMIT 6");
          if ($ares) {
            $createdAtBind = strval($userCreatedAt ?? '');
            $ares->bind_param('ss', $student_email, $createdAtBind);
            $ares->execute();
            $resA = $ares->get_result();
            if ($resA) {
              while ($r = $resA->fetch_assoc()) $recent_activities[] = $r;
              $resA->close();
            }
            $ares->close();
          }
        }
      } else {
        $ares = $connection->query("SELECT id,title,description,scheduled_at,deadline,created_at FROM activities ORDER BY scheduled_at DESC LIMIT 6");
        if ($ares) {
          while ($r = $ares->fetch_assoc()) $recent_activities[] = $r;
          $ares->close();
        }
      }

      // include activity notifications fallback similar to schedule.php
      // Prefer notifications that explicitly map to resources (resource_type/resource_id). If not available,
      // fall back to title-based notifications but only include those where the notification sender matches
      // the activity's teacher (prevents unrelated teacher notifications from showing up).
      $hasNotifResource = false;
      try {
        $c1 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'");
        if ($c1 && $c1->num_rows > 0) {
          $c2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'");
          if ($c2 && $c2->num_rows > 0) $hasNotifResource = true;
          if ($c1) $c1->close();
          if ($c2) $c2->close();
        }
      } catch (Exception $e) {
        $hasNotifResource = false;
      }

      if ($hasNotifResource && !empty($student_email)) {
        $target = 'user:' . $student_email;
        $sql = "SELECT a.id,a.title,a.description,a.scheduled_at,a.deadline, COALESCE(n.audience,'') AS audience FROM activities a JOIN notifications n ON ((n.resource_type='activity' AND n.resource_id = a.id) OR (n.title LIKE CONCAT('New Activity: ', a.title) AND n.sender_id = a.teacher_id)) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY n.created_at DESC LIMIT 200";
        $stmtN = $connection->prepare($sql);
        if ($stmtN) {
          $stmtN->bind_param('s', $target);
          $stmtN->execute();
          $rn = $stmtN->get_result();
          if ($rn) {
            while ($nr = $rn->fetch_assoc()) {
              $aud = strval($nr['audience'] ?? '');
              if (strpos($aud, 'section:') === 0) {
                $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                $intersect = array_intersect($sids, $student_section_ids);
                if (empty($intersect)) continue;
              }
              $recent_activities[] = $nr;
            }
            $rn->close();
          }
          $stmtN->close();
        }
        $recent_activities = array_slice($recent_activities, 0, 6);
      } else {
        // title-based fallback: only include notifications where sender_id maps to an existing activity teacher
        if (!empty($student_email)) {
          $notifSql = "SELECT id, sender_id, title, message, audience, created_at FROM notifications WHERE title LIKE 'New Activity:%' AND (audience IN ('students','all') OR audience = ? OR audience LIKE 'section:%') ORDER BY created_at DESC LIMIT 40";
          $stmtN = $connection->prepare($notifSql);
          if ($stmtN) {
            $target = 'user:' . $student_email;
            $stmtN->bind_param('s', $target);
            $stmtN->execute();
            $rn = $stmtN->get_result();
            if ($rn) {
              $existing_titles = array_map('strtolower', array_map('trim', array_column($recent_activities, 'title')));
              while ($nr = $rn->fetch_assoc()) {
                $t = preg_replace('/^New Activity:\\s*/i', '', $nr['title']);
                if ($t === '' || in_array(strtolower(trim($t)), $existing_titles, true)) continue;
                $aud = isset($nr['audience']) ? $nr['audience'] : '';
                if (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud, 8))));
                  $intersect = array_intersect($sids, $student_section_ids);
                  if (empty($intersect)) continue;
                }
                $sender = isset($nr['sender_id']) ? intval($nr['sender_id']) : null;
                if ($sender) {
                  // find activity created by this sender with a matching title
                  $like = '%' . $connection->real_escape_string($t) . '%';
                  $qa = $connection->prepare('SELECT id,title,description,scheduled_at,deadline,created_at FROM activities WHERE teacher_id = ? AND title LIKE ? LIMIT 1');
                  if ($qa) {
                    $qa->bind_param('is', $sender, $like);
                    $qa->execute();
                    $qres = $qa->get_result();
                    if ($qres && $qres->num_rows > 0) {
                      $act = $qres->fetch_assoc();
                      if ($act) {
                        $recent_activities[] = $act;
                        $existing_titles[] = strtolower(trim($act['title']));
                      }
                      $qres->close();
                    }
                    $qa->close();
                  }
                }
              }
              $rn->close();
            }
            $stmtN->close();
          }
        } else {
          // not logged in: include general 'students' audience notifications (title-based) but avoid duplicates
          $notifSql2 = "SELECT id, sender_id, title, message, created_at FROM notifications WHERE title LIKE 'New Activity:%' AND audience IN ('students','all') ORDER BY created_at DESC LIMIT 40";
          $resN2 = $connection->query($notifSql2);
          if ($resN2) {
            $existing_titles = array_map('strtolower', array_map('trim', array_column($recent_activities, 'title')));
            while ($nr = $resN2->fetch_assoc()) {
              $t = preg_replace('/^New Activity:\\s*/i', '', $nr['title']);
              if ($t !== '' && in_array(strtolower(trim($t)), $existing_titles, true)) continue;
              $sender = isset($nr['sender_id']) ? intval($nr['sender_id']) : null;
              if ($sender) {
                $like = '%' . $connection->real_escape_string($t) . '%';
                $qa = $connection->prepare('SELECT id,title,description,scheduled_at,deadline,created_at FROM activities WHERE teacher_id = ? AND title LIKE ? LIMIT 1');
                if ($qa) {
                  $qa->bind_param('is', $sender, $like);
                  $qa->execute();
                  $qres = $qa->get_result();
                  if ($qres && $qres->num_rows > 0) {
                    $act = $qres->fetch_assoc();
                    if ($act) {
                      $recent_activities[] = $act;
                      $existing_titles[] = strtolower(trim($act['title']));
                    }
                    $qres->close();
                  }
                  $qa->close();
                }
              }
            }
            $resN2->close();
          }
        }
        // limit final list to 6
        if (!empty($recent_activities)) $recent_activities = array_slice($recent_activities, 0, 6);
      }
    } catch (Exception $e) {
      error_log('dashboard: fetch activities failed: ' . $e->getMessage());
    }
  } catch (Exception $e) {
    // non-fatal: leave stats at default 0
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BatStateU - Student Portal Dashboard</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>

<body>


  <!-- <div id="pageTransitionOverlay" class="page-transition-overlay pt-enter" aria-hidden="true"></div> -->
  <div class="app-container">

    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img"> Student Portal</h1>
        </div>
        <div class="navbar-menu">
          <a href="index.php" class="nav-link active">Dashboard</a>
          <a href="courses.php" class="nav-link">Quizzes</a>
          <a href="assignments.php" class="nav-link">Assignments</a>
          <a href="grades.php" class="nav-link">Grades</a>
          <a href="announcements.php" class="nav-link">Performance Tasks</a>
          <a href="schedule.php" class="nav-link">Activities</a>
          <a href="messages.php" class="nav-link">Learning Materials</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
          </button>

          <button type="button" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</button>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">

      <section class="hero-section" style="position:relative; overflow:visible">
        <div class="hero-content">

          <h2 class="hero-title gx-parallax gx-shimmer gx-reveal no-hide" id="greeting">Welcome back, Student</h2>

          <p class="hero-subtitle gx-reveal" data-gx-delay="120" id="userInfo">Batangas State University - Student Portal</p>

          <div style="margin-top:1rem">
            <a href="#" class="btn btn-primary gx-magnetic gx-reveal" data-gx-delay="240">ANU ANUUUU</a>
          </div>
        </div>
        <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>

        <div class="gx-orb gx-orb--small" style="right:16px; bottom:6px; background:rgba(196,30,58,0.06);"></div>
        <div class="gx-orb gx-orb--soft" style="left:6px; top:12px; background:rgba(184,134,11,0.05);"></div>
      </section>


      <section class="stats-grid">
        <!-- Top row: 4 stat cards -->
        <div class="stats-row stats-row-top">
          <div class="stat-card">
            <div class="stat-icon quizzes" aria-hidden="true">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M3 21h4l11-11-4-4L3 17v4z" fill="currentColor" opacity="0.95" />
                <path d="M14.5 6.5l3 3" stroke="rgba(255,255,255,0.9)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="stat-info">
              <p class="stat-label">Quizzes To Answer</p>
              <p class="stat-value" id="quizzesToAnswer"><?php echo intval($stats['quizzes_to_answer'] ?? 0); ?></p>
              <span class="stat-meta">Need your answers</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon due" aria-hidden="true">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor" opacity="0.95" />
                <path d="M14 2v6h6" stroke="rgba(255,255,255,0.9)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="stat-info">
              <p class="stat-label">Assignments Due</p>
              <p class="stat-value" id="dueSoon"><?php echo intval($stats['assignments_due'] ?? 0); ?></p>
              <span class="stat-meta">Not yet submitted</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon tasks" aria-hidden="true">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 7a2 2 0 0 1 2-2h3l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" fill="currentColor" opacity="0.95" />
              </svg>
            </div>
            <div class="stat-info">
              <p class="stat-label">Performance Tasks</p>
              <p class="stat-value" id="perfTasks"><?php echo intval($stats['performance_tasks'] ?? 0); ?></p>
              <span class="stat-meta">Open tasks</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon activities" aria-hidden="true">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="5" width="18" height="16" rx="2" fill="currentColor" opacity="0.95" />
                <path d="M16 3v4M8 3v4" stroke="rgba(255,255,255,0.9)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="stat-info">
              <p class="stat-label">Activities</p>
              <p class="stat-value" id="activitiesCount"><?php echo intval($stats['activities'] ?? 0); ?></p>
              <span class="stat-meta">Upcoming</span>
            </div>
          </div>
        </div>

        <!-- Bottom row: single centered stat card -->
        <div class="stats-row stats-row-bottom">
          <div class="stat-card materials-card">
            <div class="stat-icon materials" aria-hidden="true">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 6h14v12H3z" fill="currentColor" opacity="0.95" />
                <path d="M7 6v12" stroke="rgba(255,255,255,0.9)" stroke-width="1.2" stroke-linecap="round" />
              </svg>
            </div>
            <div class="stat-info">
              <p class="stat-label">Learning Materials</p>
              <p class="stat-value" id="materialsCount"><?php echo intval($stats['materials'] ?? 0); ?></p>
              <span class="stat-meta">Available resources</span>
            </div>
          </div>
        </div>
      </section>


      <!-- Recent Performance Tasks removed per user request -->

      <!-- Recent Learning Materials -->
      <section class="materials-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Recent Learning Materials</h3>
          <a href="messages.php" class="btn btn-primary">View All</a>
        </div>
        <div class="materials-list" id="dashboardMaterials">
          <?php if (empty($materials)): ?>
            <div class="empty-state">
              <div class="empty-icon">📚</div>
              <h4>No learning materials</h4>
            </div>
          <?php else: ?>
            <?php foreach (array_slice($materials, 0, 6) as $m): ?>
              <div class="material-item" style="margin-bottom:12px; padding:12px; background:var(--bg-secondary); border-radius:8px; border:1px solid var(--border-color)">
                <div style="font-weight:700"><?php echo htmlspecialchars($m['title']); ?></div>
                <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($m['description'])); ?></div>
                <div style="margin-top:8px; font-size:0.9rem; display:flex; gap:8px;">
                  <?php if (!empty($m['file_path'])): ?>
                    <a href="download.php?resource=material&id=<?php echo intval($m['id']); ?>" target="_blank" class="btn btn-secondary">Download</a>
                  <?php endif; ?>
                  <?php if (!empty($m['link'])): ?>
                    <a href="<?php echo htmlspecialchars($m['link']); ?>" target="_blank" class="btn btn-primary">Open Link</a>
                  <?php endif; ?>
                </div>
                <div style="margin-top:8px;font-size:0.85rem;color:var(--text-secondary)">Posted by: <?php echo htmlspecialchars($m['teacher'] ?? 'Teacher'); ?> — <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($m['created_at']))); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>


      <!-- Activities (schedule) section removed per user request -->

      <!-- Recent Quizzes -->
      <section class="quizzes-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Recent Quizzes</h3>
          <a href="courses.php" class="btn btn-primary">View All</a>
        </div>
        <div class="quizzes-list">
          <?php if (empty($recent_quizzes)): ?>
            <div class="empty-state">
              <div class="empty-icon">📝</div>
              <h4>No quizzes</h4>
            </div>
          <?php else: ?>
            <?php foreach ($recent_quizzes as $q): ?>
              <div class="material-item" style="margin-bottom:12px; padding:12px; background:var(--bg-secondary); border-radius:8px; border:1px solid var(--border-color)">
                <div style="font-weight:700"><?php echo htmlspecialchars($q['title']); ?></div>
                <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($q['description'])); ?></div>
                <div style="margin-top:8px; font-size:0.9rem; display:flex; gap:8px;">
                  <a class="btn btn-secondary" href="quiz.php?id=<?php echo intval($q['id']); ?>">View</a>
                  <a class="btn btn-primary" href="quiz.php?id=<?php echo intval($q['id']); ?>">Start</a>
                </div>
                <div style="margin-top:8px;font-size:0.85rem;color:var(--text-secondary)">By: <?php echo htmlspecialchars($q['teacher'] ?? 'Teacher'); ?> — <?php echo htmlspecialchars($q['scheduled_at']); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- Recent Performance Tasks -->
      <section class="tasks-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Recent Performance Tasks</h3>
          <a href="announcements.php" class="btn btn-primary">View All</a>
        </div>
        <div class="tasks-list">
          <?php if (empty($recent_tasks)): ?>
            <div class="empty-state">
              <div class="empty-icon">📂</div>
              <h4>No performance tasks</h4>
            </div>
          <?php else: ?>
            <?php foreach ($recent_tasks as $t): ?>
              <div class="material-item" style="margin-bottom:12px; padding:12px; background:var(--bg-secondary); border-radius:8px; border:1px solid var(--border-color)">
                <div style="font-weight:700"><?php echo htmlspecialchars($t['title']); ?></div>
                <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($t['description'])); ?></div>
                <div style="margin-top:8px; font-size:0.9rem; display:flex; gap:8px;">
                  <a class="btn btn-secondary" href="view_task.php?id=<?php echo intval($t['id']); ?>">View</a>
                </div>
                <div style="margin-top:8px;font-size:0.85rem;color:var(--text-secondary)">Due: <?php echo htmlspecialchars($t['due_date'] ?? $t['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- Recent Activities (short) -->
      <section class="activities-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Recent Activities</h3>
          <a href="schedule.php" class="btn btn-primary">See</a>
        </div>
        <div class="activities-list">
          <?php if (empty($recent_activities)): ?>
            <div class="empty-state">
              <div class="empty-icon">🕐</div>
              <h4>No activities</h4>
            </div>
          <?php else: ?>
            <?php foreach ($recent_activities as $a): ?>
              <div class="material-item" style="margin-bottom:12px; padding:12px; background:var(--bg-secondary); border-radius:8px; border:1px solid var(--border-color)">
                <div style="font-weight:700"><?php echo htmlspecialchars($a['title']); ?></div>
                <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($a['description'])); ?></div>
                <div style="margin-top:8px; font-size:0.9rem; display:flex; gap:8px;">
                  <a class="btn btn-primary" href="activity_events.php?item=<?php echo urlencode($a['id']); ?>">Related Events</a>
                </div>
                <div style="margin-top:8px;font-size:0.85rem;color:var(--text-secondary)">Scheduled: <?php echo htmlspecialchars($a['scheduled_at'] ?? $a['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>


      <section class="assignments-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Upcoming Assignments</h3>
          <a href="assignments.php" class="btn btn-primary">View All</a>
        </div>
        <div class="assignments-list" id="recentAssignments">
          <div class="empty-state">
            <div class="empty-icon">✍️</div>
            <h4>No assignments</h4>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="settings.js"></script>
  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>

</html>