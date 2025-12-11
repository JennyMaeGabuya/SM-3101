<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements - Student Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    <div class="gx-orb gx-orb--small" style="right:8px; bottom:12px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:20px; top:18px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <?php include_once 'connection/dbsConnection.php';
  $announcements = [];
  // keep a set of resource titles already added (normalized) to avoid duplicate cards
  $existing_titles = array();
  // also track performance_task ids already added to avoid duplicates
  $existing_task_ids = array();
  // predeclare student sections array so it's available in notification parsing
  $student_section_ids = array();
  // Primary: show Performance Tasks (server-side) with clear task card design
  if (isset($connection) && $connection) {
    try {
      // fetch performance tasks but respect audience targeting if columns are present
      $student_email = null;
      if (session_status() === PHP_SESSION_NONE) session_start();
      if (!empty($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $u = $connection->prepare('SELECT email, created_at FROM accounts WHERE accountId = ? LIMIT 1');
        if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $r = $resu->fetch_assoc(); if ($r) { $student_email = $r['email']; $userCreatedAt = $r['created_at']; } $resu->close(); } $u->close(); }
      }

      $hasAudience = false;
      try { $col = $connection->query("SHOW COLUMNS FROM performance_tasks LIKE 'audience'"); if ($col && $col->num_rows>0) { $hasAudience = true; $col->close(); } } catch (Exception $e) { $hasAudience = false; }

      if ($hasAudience && $student_email) {
        // load student's sections to include section-targeted performance tasks
        $student_section_ids = [];
        if (!empty($_SESSION['user_id'])) {
          try {
            $s = $connection->prepare('SELECT section_id FROM user_sections WHERE user_id = ?');
            if ($s) {
              $s->bind_param('i', $uid);
              $s->execute();
              $rs = $s->get_result();
              if ($rs) { while ($rr = $rs->fetch_assoc()) { $student_section_ids[] = intval($rr['section_id']); } $rs->close(); }
              $s->close();
            }
          } catch (Exception $e) { error_log('announcements: failed to load user sections: '.$e->getMessage()); }
        }

        error_log("[announcements] student_email = " . $student_email);
        $resT = null; $tres = null;
        if (!empty($student_section_ids)) {
          $placeholders = [];
          foreach ($student_section_ids as $sid) { $placeholders[] = 'FIND_IN_SET(?, pt.target_value)'; }
          $sectionsCond = implode(' OR ', $placeholders);
          $sql = "SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE (pt.audience IN ('students','all') OR (pt.audience = 'specific' AND pt.target_value = ?) OR (pt.audience = 'section' AND ($sectionsCond))) AND pt.created_at >= ? ORDER BY pt.due_date DESC";
          $tres = $connection->prepare($sql);
          if ($tres) {
            $params = array_merge([$student_email], array_map('strval', $student_section_ids), [strval($userCreatedAt ?? '')]);
            $types = str_repeat('s', count($params));
            $bind_names = array();
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
              $bind_names[] = & $params[$i];
            }
            call_user_func_array(array($tres, 'bind_param'), $bind_names);
            $tres->execute();
            $resT = $tres->get_result();
          } else {
            error_log('[announcements] prepare performance_tasks failed: ' . $connection->error);
          }
        } else {
          $sql = "SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE (pt.audience IN ('students','all') OR (pt.audience = 'specific' AND pt.target_value = ?)) AND pt.created_at >= ? ORDER BY pt.due_date DESC";
          $tres = $connection->prepare($sql);
          if ($tres) {
            $createdAtBind = strval($userCreatedAt ?? '');
            $tres->bind_param('ss',$student_email, $createdAtBind);
            $tres->execute();
            $resT = $tres->get_result();
          } else {
            error_log('[announcements] prepare performance_tasks failed: ' . $connection->error);
          }
        }

        if ($resT) {
          $count = 0;
          while ($r = $resT->fetch_assoc()) {
            // skip if this task id was already added
            $tid0 = intval($r['id'] ?? 0);
            if ($tid0 && in_array($tid0, $existing_task_ids)) continue;
            // attach student's submission/grade if available
            if (!empty($uid)) {
              $subq = $connection->prepare('SELECT grade,status,graded_by,graded_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
              if ($subq) { $tid = $tid0; $subq->bind_param('ii',$tid,$uid); $subq->execute(); $sres = $subq->get_result(); if ($sres && ($sr = $sres->fetch_assoc())) { $r['submission_grade'] = $sr['grade']; $r['submission_status'] = $sr['status']; $r['submission_graded_by'] = $sr['graded_by']; $r['submission_graded_at'] = $sr['graded_at']; $sres->close(); } $subq->close(); }
            }
            $announcements[] = array_merge($r, ['src' => 'task']);
            // record normalized title to prevent duplicate notification cards
            if (!empty($r['title'])) $existing_titles[] = strtolower(trim($r['title']));
            if ($tid0) $existing_task_ids[] = $tid0;
            $count++;
          }
          error_log('[announcements] fetched performance_tasks count=' . $count);
          $resT->close();
        }
        if ($tres) $tres->close();
      } else {
        $tres = $connection->query("SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id ORDER BY pt.due_date DESC");
        if ($tres) {
          while ($r = $tres->fetch_assoc()) {
            $tid0 = intval($r['id'] ?? 0);
            if ($tid0 && in_array($tid0, $existing_task_ids)) continue;
            if (!empty($uid)) {
              $subq = $connection->prepare('SELECT grade,status,graded_by,graded_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
              if ($subq) { $tid = $tid0; $subq->bind_param('ii',$tid,$uid); $subq->execute(); $sres = $subq->get_result(); if ($sres && ($sr = $sres->fetch_assoc())) { $r['submission_grade'] = $sr['grade']; $r['submission_status'] = $sr['status']; $r['submission_graded_by'] = $sr['graded_by']; $r['submission_graded_at'] = $sr['graded_at']; $sres->close(); } $subq->close(); }
            }
            $announcements[] = array_merge($r, ['src' => 'task']);
            if (!empty($r['title'])) $existing_titles[] = strtolower(trim($r['title']));
            if ($tid0) $existing_task_ids[] = $tid0;
          }
          $tres->close();
        }
      }

      // Only include notifications that announce new Performance Tasks. Materials,
      // quizzes or activities should not be displayed on the Performance Tasks page.
      if (session_status() === PHP_SESSION_NONE) session_start();
      $email = null;
      if (!empty($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $u = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
        if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $rr = $resu->fetch_assoc(); if ($rr) $email = $rr['email']; $resu->close(); } $u->close(); }
      }

      if ($email || $student_email) {
        $emailToUse = $email ?: $student_email;
        // include notifications targeted to sections as well
        $stmt = $connection->prepare("SELECT id,sender_id,title,message,created_at,'notification' AS src,audience FROM notifications WHERE (audience IN ('students','all') OR audience = ? OR audience LIKE 'section:%') AND title LIKE 'New Performance Task:%' AND created_at >= ? ORDER BY created_at DESC LIMIT 100");
        if ($stmt) {
          $target = 'user:' . $emailToUse;
          $createdAtBindN = strval($userCreatedAt ?? '');
          $stmt->bind_param('ss',$target,$createdAtBindN);
          $stmt->execute();
          $nres = $stmt->get_result();
          if ($nres) {
            while ($r = $nres->fetch_assoc()) {
              // verify the referenced performance task still exists
              $t = trim($r['title'] ?? '');
              if ($t !== '' && stripos($t, 'New Performance Task:') === 0) {
                $taskTitle = trim(substr($t, strlen('New Performance Task:')));
                $sender = isset($r['sender_id']) ? intval($r['sender_id']) : null;
                // if notification targets sections, verify student is a member
                $aud = trim(strval($r['audience'] ?? ''));
                if (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud,8))));
                  $intersect = !empty($student_section_ids) ? array_intersect($sids, $student_section_ids) : [];
                  if (empty($intersect)) {
                    continue; // not for this student
                  }
                }
                if ($sender && $taskTitle !== '') {
                  // Ensure notification is meant for this user (or for all students)
                  $aud = trim(strval($r['audience'] ?? ''));
                  $isForAll = ($aud === 'students' || $aud === 'all');
                  $isForThisUser = ($aud === $target || $aud === $emailToUse || $aud === ('user:' . $emailToUse) || strcasecmp($aud, $emailToUse) === 0);
                  // also allow audiences that include the email (handle malformed/no-prefix cases)
                  if (!$isForAll && !$isForThisUser) {
                    if (stripos($aud, '@') !== false) {
                      if (stripos($aud, $emailToUse) === false) {
                        continue; // targeted at a different email/user — skip
                      }
                    } else {
                      // audience is neither global nor user-specific for this email — skip
                      continue;
                    }
                  }

                  // skip if a performance task with the same title is already added
                  $normTaskTitle = strtolower(trim($taskTitle));
                  if (in_array($normTaskTitle, $existing_titles)) {
                    continue;
                  }
                  $like = '%' . $connection->real_escape_string($taskTitle) . '%';
                  $q = $connection->prepare('SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE pt.teacher_id = ? AND pt.title LIKE ? LIMIT 1');
                  if ($q) {
                    $q->bind_param('is', $sender, $like);
                    $q->execute();
                    $qr = $q->get_result();
                    if ($qr && $qr->num_rows>0) {
                      $pt = $qr->fetch_assoc();
                      // attach student's submission/grade if available
                      if (!empty($uid)) {
                        $subq = $connection->prepare('SELECT grade,status,graded_by,graded_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
                        if ($subq) { $tid = intval($pt['id']); $subq->bind_param('ii',$tid,$uid); $subq->execute(); $sres = $subq->get_result(); if ($sres && ($sr = $sres->fetch_assoc())) { $pt['submission_grade'] = $sr['grade']; $pt['submission_status'] = $sr['status']; $pt['submission_graded_by'] = $sr['graded_by']; $pt['submission_graded_at'] = $sr['graded_at']; $sres->close(); } $subq->close(); }
                      }
                      // skip if this task id was already added
                      $ptid = intval($pt['id'] ?? 0);
                      if ($ptid && in_array($ptid, $existing_task_ids)) {
                        // already added
                      } else {
                        $announcements[] = array_merge($pt, ['src' => 'task']);
                        if (!empty($pt['title'])) $existing_titles[] = strtolower(trim($pt['title']));
                        if ($ptid) $existing_task_ids[] = $ptid;
                      }
                    }
                    if ($qr) $qr->close();
                    $q->close();
                  }
                }
              }
            }
            $nres->close();
          }
          $stmt->close();
        }
      } else {
        $nres = $connection->query("SELECT id,sender_id,title,message,created_at,'notification' AS src,audience FROM notifications WHERE audience IN ('students','all') AND title LIKE 'New Performance Task:%' ORDER BY created_at DESC LIMIT 100");
        if ($nres) {
          while ($r = $nres->fetch_assoc()) {
            $t = trim($r['title'] ?? '');
            if ($t !== '' && stripos($t, 'New Performance Task:') === 0) {
              $taskTitle = trim(substr($t, strlen('New Performance Task:')));
              $sender = isset($r['sender_id']) ? intval($r['sender_id']) : null;
              if ($sender && $taskTitle !== '') {
                // When no logged-in email is available we only include global student notifications
                $aud = trim(strval($r['audience'] ?? ''));
                if (stripos($aud, '@') !== false || stripos($aud, 'user') === 0) {
                  // skip user-targeted notifications when there is no matching logged-in user
                  continue;
                }
                // skip if a performance task with the same title is already added
                $normTaskTitle = strtolower(trim($taskTitle));
                if (in_array($normTaskTitle, $existing_titles)) {
                  continue;
                }
                $like = '%' . $connection->real_escape_string($taskTitle) . '%';
                $q = $connection->prepare('SELECT pt.id, pt.title, pt.description, pt.due_date, pt.group_allowed, pt.rubric, pt.created_at, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE pt.teacher_id = ? AND pt.title LIKE ? LIMIT 1');
                if ($q) {
                  $q->bind_param('is', $sender, $like);
                  $q->execute();
                  $qr = $q->get_result();
                  if ($qr && $qr->num_rows>0) {
                    $pt = $qr->fetch_assoc();
                    if (!empty($uid)) {
                      $subq = $connection->prepare('SELECT grade,status,graded_by,graded_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
                      if ($subq) { $tid = intval($pt['id']); $subq->bind_param('ii',$tid,$uid); $subq->execute(); $sres = $subq->get_result(); if ($sres && ($sr = $sres->fetch_assoc())) { $pt['submission_grade'] = $sr['grade']; $pt['submission_status'] = $sr['status']; $pt['submission_graded_by'] = $sr['graded_by']; $pt['submission_graded_at'] = $sr['graded_at']; $sres->close(); } $subq->close(); }
                    }
                    $ptid = intval($pt['id'] ?? 0);
                    if ($ptid && in_array($ptid, $existing_task_ids)) {
                      // already added
                    } else {
                      $announcements[] = array_merge($pt, ['src' => 'task']);
                      if (!empty($pt['title'])) $existing_titles[] = strtolower(trim($pt['title']));
                      if ($ptid) $existing_task_ids[] = $ptid;
                    }
                  }
                  if ($qr) $qr->close();
                  $q->close();
                }
              }
            }
          }
          $nres->close();
        }
      }

      // sort by created_at/due_date desc
      usort($announcements, function($a,$b){
        $ta = strtotime($a['created_at'] ?? $a['due_date'] ?? '0');
        $tb = strtotime($b['created_at'] ?? $b['due_date'] ?? '0');
        return $tb - $ta;
      });
    } catch (Exception $e) { error_log('Fetch announcements failed: '.$e->getMessage()); }
    // debug toggle: append a visible dump when ?dev_debug=1 is present (only for logged-in students)
    if (!empty($_GET['dev_debug']) && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
      echo '<div style="padding:12px;background:#fff8c6;border:1px solid #f0e68c;margin:12px;border-radius:6px;">';
      echo '<h3 style="margin:0 0 8px 0">Dev Debug: announcements payload</h3><pre style="white-space:pre-wrap;">'.htmlspecialchars(print_r($announcements, true)).'</pre>';
      echo '</div>';
    }
  }

  ?>

  <div class="app-container">
    <nav class="navbar">
        <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img">  Student Portal</h1>
        </div>
         <div class="navbar-menu">
           <a href="index.php" class="nav-link">Dashboard</a>
          <a href="courses.php" class="nav-link">Quizzes</a>
          <a href="assignments.php" class="nav-link">Assignments</a>
          <a href="grades.php" class="nav-link">Grades</a>
          <a href="announcements.php" class="nav-link active">Performance Tasks</a>
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
      <section class="announcements-section">
        <div class="section-header">
          <h2 class="hero-title gx-parallax gx-reveal section-title">Performance Tasks</h2>
          <div style="margin-left:auto; display:flex; gap:8px; align-items:center">
            <div class="filter-bar" style="margin-right:12px">
            </div>
          </div>
        </div>

        <div class="announcements-list" id="announcementsList">
          <?php if (empty($announcements)): ?>
            <div class="empty-state centered">
              <div class="empty-icon" style="font-size:4rem">📝</div>
              <h3 style="margin-top:1rem;color:var(--text-primary)">No Performance Task Scheduled</h3>
              <p style="color:var(--text-secondary);margin-top:0.5rem">Your instructor will post tasks here. Check back later or contact your teacher.</p>
            </div>
          <?php else: ?>
            <div class="tasks-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
            <?php foreach ($announcements as $a): ?>
              <?php if (($a['src'] ?? '') === 'task'): ?>
                <article class="task-card" style="background:var(--bg-secondary);border:1px solid var(--border-color);padding:14px;border-radius:10px;display:flex;flex-direction:column;gap:8px">
                  <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                      <div style="font-weight:800;font-size:1.05rem"><?php echo htmlspecialchars($a['title']); ?></div>
                      <div style="font-size:0.9rem;color:var(--text-secondary);margin-top:6px">By: <?php echo htmlspecialchars($a['teacher'] ?? 'Teacher'); ?></div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
                      <div style="font-size:0.85rem;color:var(--text-secondary)"><?php echo htmlspecialchars(date('M d, Y', strtotime($a['created_at']))); ?></div>
                      <?php if (isset($a['submission_grade']) && $a['submission_grade'] !== ''): ?>
                        <div style="background:#0ea5a4;color:#031018;padding:6px 10px;border-radius:8px;font-weight:700">Grade: <?php echo htmlspecialchars($a['submission_grade']); ?></div>
                      <?php elseif (!empty($a['submission_status'])): ?>
                        <div style="background:rgba(255,255,255,0.03);color:var(--text-secondary);padding:6px 10px;border-radius:8px;font-weight:700"><?php echo htmlspecialchars($a['submission_status']); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div style="color:var(--text-secondary);min-height:48px"><?php echo nl2br(htmlspecialchars($a['description'] ?? '')); ?></div>
                  <?php if (!empty($a['due_date'])): ?>
                    <div style="font-weight:700">Due: <?php echo htmlspecialchars(date('M d, Y', strtotime($a['due_date']))); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($a['rubric'])): ?><div style="color:var(--text-secondary);font-style:italic">Rubric: <?php echo nl2br(htmlspecialchars($a['rubric'])); ?></div><?php endif; ?>
                  <div style="margin-top:auto;display:flex;gap:8px;justify-content:flex-end">
                    <a class="btn btn-secondary" href="view_task.php?id=<?php echo urlencode($a['id']); ?>">View Task</a>
                  </div>
                </article>
              <?php else: ?>
                <article class="notif-card" style="background:var(--bg-secondary);border:1px solid var(--border-color);padding:12px;border-radius:10px;">
                  <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                      <div style="font-weight:700"><?php echo htmlspecialchars($a['title'] ?: ($a['message'] ?? 'Notification')); ?></div>
                      <?php if (!empty($a['audience'])): ?><div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px">For: <?php echo htmlspecialchars($a['audience']); ?></div><?php endif; ?>
                    </div>
                    <div style="font-size:0.85rem;color:var(--text-secondary)"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($a['created_at']))); ?></div>
                  </div>
                  <div style="margin-top:8px;color:var(--text-secondary)"><?php echo nl2br(htmlspecialchars($a['message'] ?? '')); ?></div>
                </article>
              <?php endif; ?>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>


  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
