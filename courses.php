<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Courses - BatStateU Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>

    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
   
    <div class="gx-orb gx-orb--small" style="right:16px; bottom:6px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:6px; top:12px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <?php include_once 'connection/dbsConnection.php';
  $quizzes = [];
    $resource_counts = ['quizzes'=>0,'assignments'=>0,'activities'=>0,'materials'=>0];
  if (isset($connection) && $connection) {
    try {
      // try to determine current student email from session (login_action.php sets $_SESSION['user_id'])
      if (session_status() === PHP_SESSION_NONE) session_start();
      $student_email = null;
      if (!empty($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $u = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
        if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $r = $resu->fetch_assoc(); if ($r) $student_email = $r['email']; $resu->close(); } $u->close(); }
      }

      // detect if quizzes table has audience/target_value columns
      $hasAudience = false;
      try {
        $col = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'audience'");
        if ($col && $col->num_rows>0) { $hasAudience = true; $col->close(); }
      } catch (Exception $e) { $hasAudience = false; }

      // load student's section ids once so we can include section-targeted counts
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
        } catch (Exception $e) { error_log('courses: failed to load user sections for counts: '.$e->getMessage()); }
      }

      if ($hasAudience) {
        // load student's section ids so we can include section-targeted quizzes
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
          } catch (Exception $e) { error_log('courses: failed to load user sections: '.$e->getMessage()); }
        }

        if ($student_email) {
          if (!empty($student_section_ids)) {
            $placeholders = [];
            foreach ($student_section_ids as $sid) { $placeholders[] = 'FIND_IN_SET(?, q.target_value)'; }
            $sectionsCond = implode(' OR ', $placeholders);
            $sql = "SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,q.created_at,t.username AS teacher,q.audience,q.target_value FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id WHERE (COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?) OR (q.audience = 'section' AND ($sectionsCond))) ORDER BY q.scheduled_at DESC";
            $q = $connection->prepare($sql);
            if ($q) {
              $types = str_repeat('s', 1 + count($student_section_ids));
              $params = array_merge([$student_email], array_map('strval', $student_section_ids));
              $bind_names = [];
              $bind_names[] = $types;
              for ($i=0;$i<count($params);$i++) { $bind_name = 'param'.$i; $$bind_name = $params[$i]; $bind_names[] = &$$bind_name; }
              call_user_func_array(array($q, 'bind_param'), $bind_names);
              $q->execute(); $res = $q->get_result();
              unset($bind_names);
            }
          } else {
            $q = $connection->prepare("SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,q.created_at,t.username AS teacher,q.audience,q.target_value FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id WHERE COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?) ORDER BY q.scheduled_at DESC");
            if ($q) { $q->bind_param('s',$student_email); $q->execute(); $res = $q->get_result(); }
          }
        } else {
          $res = $connection->query("SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,q.created_at,t.username AS teacher,q.audience,q.target_value FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id WHERE COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' ORDER BY q.scheduled_at DESC");
        }
      } else {
        // older schema: simple select without audience fields
        if ($student_email) {
          $q = $connection->prepare("SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,q.created_at,t.username AS teacher FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id ORDER BY q.scheduled_at DESC");
          if ($q) { $q->execute(); $res = $q->get_result(); }
        } else {
          $res = $connection->query("SELECT q.id,q.title,q.description,q.scheduled_at,q.deadline,q.created_at,t.username AS teacher FROM quizzes q LEFT JOIN teachers t ON q.teacher_id = t.teacher_id ORDER BY q.scheduled_at DESC");
        }
      }

      if (isset($res) && $res) {
        while ($r = $res->fetch_assoc()) $quizzes[] = $r;
        if (is_object($res)) $res->close();
      }
        // fetch some quick counts for resource shortcuts (include section-targeted items)
        try {
          // --- Quizzes ---
          if (!empty($hasAudience) && $hasAudience && $student_email) {
            // build dynamic query to include specific (email) and section matches
            if (!empty($student_section_ids)) {
              $placeholders = [];
              foreach ($student_section_ids as $sid) { $placeholders[] = 'FIND_IN_SET(?, q.target_value)'; }
              $sectionsCond = implode(' OR ', $placeholders);
              $sql = "SELECT COUNT(DISTINCT q.id) AS c FROM quizzes q WHERE (COALESCE(q.audience,'all') = 'all' OR q.audience = 'students' OR (q.audience = 'specific' AND q.target_value = ?) OR (q.audience = 'section' AND ($sectionsCond)))";
              $stmtc = $connection->prepare($sql);
              if ($stmtc) {
                $params = array_merge([$student_email], array_map('strval', $student_section_ids));
                $types = str_repeat('s', count($params));
                $binds = [$types];
                for ($i=0;$i<count($params);$i++) $binds[] = & $params[$i];
                call_user_func_array([$stmtc,'bind_param'],$binds);
                $stmtc->execute(); $cres = $stmtc->get_result(); if ($cres) { $resource_counts['quizzes'] = (int)($cres->fetch_assoc()['c'] ?? 0); $cres->close(); } $stmtc->close();
              }
            } else {
              $stmtc = $connection->prepare("SELECT COUNT(*) AS c FROM quizzes WHERE COALESCE(audience,'all') = 'all' OR audience = 'students' OR (audience = 'specific' AND target_value = ?)");
              if ($stmtc) { $stmtc->bind_param('s',$student_email); $stmtc->execute(); $cres = $stmtc->get_result(); if ($cres) { $resource_counts['quizzes'] = (int)($cres->fetch_assoc()['c'] ?? 0); $cres->close(); } $stmtc->close(); }
            }
          } else {
            $c = $connection->query("SELECT COUNT(*) as c FROM quizzes"); if ($c) { $resource_counts['quizzes'] = (int)($c->fetch_assoc()['c'] ?? 0); $c->close(); }
          }

          // --- Performance Tasks (assignments) ---
          $hasAudienceTasks = false;
          try { $col = $connection->query("SHOW COLUMNS FROM performance_tasks LIKE 'audience'"); if ($col && $col->num_rows>0) { $hasAudienceTasks = true; $col->close(); } } catch (Exception $e) { $hasAudienceTasks = false; }
          if ($hasAudienceTasks && $student_email) {
            if (!empty($student_section_ids)) {
              $place = [];
              foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, target_value)';
              $sc = implode(' OR ', $place);
              $sql = "SELECT COUNT(DISTINCT id) AS c FROM performance_tasks WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sc)))";
              $stmtp = $connection->prepare($sql);
              if ($stmtp) {
                $params = array_merge([$student_email], array_map('strval', $student_section_ids));
                $types = str_repeat('s', count($params));
                $binds = [$types];
                for ($i=0;$i<count($params);$i++) $binds[] = & $params[$i];
                call_user_func_array([$stmtp,'bind_param'],$binds);
                $stmtp->execute(); $rres = $stmtp->get_result(); if ($rres) { $resource_counts['assignments'] = (int)($rres->fetch_assoc()['c'] ?? 0); $rres->close(); } $stmtp->close();
              }
            } else {
              $stmtp = $connection->prepare("SELECT COUNT(*) AS c FROM performance_tasks WHERE COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?)");
              if ($stmtp) { $stmtp->bind_param('s',$student_email); $stmtp->execute(); $rres = $stmtp->get_result(); if ($rres) { $resource_counts['assignments'] = (int)($rres->fetch_assoc()['c'] ?? 0); $rres->close(); } $stmtp->close(); }
            }
          } else {
            $c = $connection->query("SELECT COUNT(*) as c FROM performance_tasks"); if ($c) { $resource_counts['assignments'] = (int)($c->fetch_assoc()['c'] ?? 0); $c->close(); }
          }

          // --- Activities ---
          $hasAudienceActs = false;
          try { $col = $connection->query("SHOW COLUMNS FROM activities LIKE 'audience'"); if ($col && $col->num_rows>0) { $hasAudienceActs = true; $col->close(); } } catch (Exception $e) { $hasAudienceActs = false; }
          if ($hasAudienceActs && $student_email) {
            if (!empty($student_section_ids)) {
              $place = [];
              foreach ($student_section_ids as $sid) $place[] = 'FIND_IN_SET(?, target_value)';
              $sc = implode(' OR ', $place);
              $sql = "SELECT COUNT(DISTINCT id) AS c FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sc)))";
              $stmta = $connection->prepare($sql);
              if ($stmta) {
                $params = array_merge([$student_email], array_map('strval', $student_section_ids));
                $types = str_repeat('s', count($params));
                $binds = [$types];
                for ($i=0;$i<count($params);$i++) $binds[] = & $params[$i];
                call_user_func_array([$stmta,'bind_param'],$binds);
                $stmta->execute(); $ares = $stmta->get_result(); if ($ares) { $resource_counts['activities'] = (int)($ares->fetch_assoc()['c'] ?? 0); $ares->close(); } $stmta->close();
              }
            } else {
              $stmta = $connection->prepare("SELECT COUNT(*) AS c FROM activities WHERE COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?)");
              if ($stmta) { $stmta->bind_param('s',$student_email); $stmta->execute(); $ares = $stmta->get_result(); if ($ares) { $resource_counts['activities'] = (int)($ares->fetch_assoc()['c'] ?? 0); $ares->close(); } $stmta->close(); }
            }
          } else {
            $c = $connection->query("SELECT COUNT(*) as c FROM activities"); if ($c) { $resource_counts['activities'] = (int)($c->fetch_assoc()['c'] ?? 0); $c->close(); }
          }

          // --- Learning materials (use notifications mapping where available) ---
          $resource_counts['materials'] = 0;
          try {
            $currentEmailForMaterials = $student_email;
            $materials_map_ids = [];
            if ($currentEmailForMaterials) {
              $target = 'user:' . $currentEmailForMaterials;
              // join learning_materials with notifications where notification audience matches
              $sql = "SELECT l.id, COALESCE(n.audience,'') AS audience FROM learning_materials l LEFT JOIN notifications n ON ((n.resource_type = 'learning_material' AND n.resource_id = l.id) OR (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title))) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%')";
              $stmtm = $connection->prepare($sql);
              if ($stmtm) {
                $stmtm->bind_param('s',$target);
                $stmtm->execute();
                $mres = $stmtm->get_result();
                if ($mres) {
                  while ($mr = $mres->fetch_assoc()) {
                    $mid = intval($mr['id'] ?? 0);
                    if ($mid <= 0) continue;
                    $aud = strval($mr['audience'] ?? '');
                    if (strpos($aud,'section:') === 0) {
                      $sids = array_filter(array_map('intval', explode(',', substr($aud,8))));
                      $intersect = array_intersect($sids, $student_section_ids);
                      if (empty($intersect)) continue;
                    }
                    $materials_map_ids[$mid] = true;
                  }
                  $mres->close();
                }
                $stmtm->close();
              }
              $resource_counts['materials'] = count($materials_map_ids);
            } else {
              $c = $connection->query("SELECT COUNT(*) as c FROM learning_materials"); if ($c) { $resource_counts['materials'] = (int)($c->fetch_assoc()['c'] ?? 0); $c->close(); }
            }
          } catch (Exception $e) { error_log('Fetch materials count failed: '.$e->getMessage()); }

        } catch (Exception $e) { error_log('Fetch resource counts failed: '.$e->getMessage()); }
    } catch (Exception $e) { error_log('Fetch quizzes failed: '.$e->getMessage()); }
  }

  ?>

  <div class="app-container">
    
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img">  Student Portal</h1>
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
        <section class="courses-section">
          <div class="section-header">
            <h2 class="hero-title gx-parallax gx-reveal section-title">Quizzes</h2>
          </div>

          <!-- Quizzes list (teacher-published quizzes) -->
          <div class="quizzes-list" style="margin-bottom:18px">
            <?php if (empty($quizzes)): ?>
              <div class="empty-state centered">
                <div class="empty-icon">📝</div>
                <h4>No quizzes published</h4>
                <p style="color:var(--text-secondary); margin-top:8px">Check back later or ask your teacher to publish quizzes.</p>
              </div>
            <?php else: ?>
              <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($quizzes as $q): ?>
                  <li style="background:var(--bg-secondary); border:1px solid var(--border-color); padding:12px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:12px">
                    <div style="flex:1; min-width:0">
                      <div style="font-weight:800; font-size:1.05rem"><?php echo htmlspecialchars($q['title']); ?></div>
                      <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($q['description'])); ?></div>
                      <div style="font-size:0.85rem; color:var(--text-secondary); margin-top:6px">By: <?php echo htmlspecialchars($q['teacher'] ?: 'Teacher'); ?> • Scheduled: <?php echo htmlspecialchars($q['scheduled_at']); ?> • Deadline: <?php echo htmlspecialchars($q['deadline']); ?></div>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center">
                      <?php
                        // compute student attempt/graded status
                        $student_account_id = !empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
                        $student_email_local = $student_email;
                        $canStart = true; $startLabel = 'Start'; $startHint = '';
                        $qid_local = intval($q['id']);
                        // determine max attempts
                        $maxAttempts = 2;
                        try {
                          $mm = $connection->prepare('SELECT max_attempts FROM quizzes WHERE id = ? LIMIT 1');
                          if ($mm) { $mm->bind_param('i',$qid_local); $mm->execute(); $mres = $mm->get_result(); if ($mres && ($mrow = $mres->fetch_assoc())) { if (!empty($mrow['max_attempts'])) $maxAttempts = intval($mrow['max_attempts']); } if ($mres) $mres->close(); $mm->close(); }
                        } catch (Exception $e) { }
                        // count attempts
                        $attempts = 0;
                        if ($student_account_id) {
                          try { $ac = $connection->prepare('SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?'); if ($ac) { $ac->bind_param('ii',$qid_local,$student_account_id); $ac->execute(); $ar = $ac->get_result(); if ($ar && ($rowc = $ar->fetch_assoc())) $attempts = intval($rowc['cnt']); if ($ar) $ar->close(); $ac->close(); } } catch (Exception $e) { }
                        }
                        // check if teacher graded this submission
                        $isGraded = false; $gradeValue = null;
                        try {
                          $ss = $connection->prepare('SELECT grade FROM submissions WHERE resource_type = ? AND resource_id = ? AND (student_id = ? OR student_id = ?) LIMIT 1');
                          if ($ss) {
                            $rtype = 'quiz';
                            $sidA = $student_account_id !== null ? (string)$student_account_id : '';
                            $sidB = $student_email_local ?? '';
                            $ss->bind_param('siss', $rtype, $qid_local, $sidA, $sidB);
                            $ss->execute(); $sres = $ss->get_result(); if ($sres && ($srow = $sres->fetch_assoc())) { if ($srow['grade'] !== null && $srow['grade'] !== '') { $isGraded = true; $gradeValue = $srow['grade']; } } if ($sres) $sres->close(); $ss->close(); }
                        } catch (Exception $e) { }
                        if ($isGraded) {
                          $canStart = false; $startLabel = 'Graded: ' . ($gradeValue ?? 'View'); $startHint = 'Your quiz is graded: ' . ($gradeValue ?? '');
                        } else if ($attempts >= $maxAttempts) {
                          $canStart = false; $startLabel = 'Limit attempt'; $startHint = 'Limit attempt! Wait for the teacher to grade your quiz.';
                        }
                      ?>
                      <?php if ($canStart): ?>
                        <a class="btn btn-primary" href="quiz.php?id=<?php echo (int)$q['id']; ?>"><?php echo htmlspecialchars($startLabel); ?></a>
                      <?php else: ?>
                        <button class="btn btn-secondary" disabled title="<?php echo htmlspecialchars($startHint); ?>"><?php echo htmlspecialchars($startLabel); ?></button>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <!-- Preview modal -->
          <div id="quizPreviewModal" class="modal" aria-hidden="true">
            <div class="modal-content">
              <div class="modal-header">
                <h3 id="previewTitle">Preview</h3>
                <button class="modal-close" id="closePreview">×</button>
              </div>
              <div id="previewBody" class="modal-text">Loading...</div>
              <div style="margin-top:12px; display:flex; justify-content:flex-end; gap:8px">
                <a id="previewStart" class="btn btn-primary" href="#">Start</a>
                <button class="btn btn-secondary" id="previewClose">Close</button>
              </div>
            </div>
          </div>

          <div class="courses-grid" id="coursesList">
            <?php if (empty($quizzes)): ?>
              <div class="empty-state centered">
                <div class="empty-icon">📝</div>
                <h4>No quizzes published</h4>
                <p style="color:var(--text-secondary); margin-top:8px">Check back later or ask your teacher to publish quizzes.</p>
              </div>
            <?php else: ?>
              <?php foreach ($quizzes as $q):
                // normalize fields
                $qid = htmlspecialchars($q['id']);
                $qtitle = htmlspecialchars($q['title']);
                $qdesc = nl2br(htmlspecialchars($q['description']));
                $qteacher = htmlspecialchars($q['teacher'] ?: 'Teacher');
                $qsched = htmlspecialchars($q['scheduled_at']);
                $qdead = htmlspecialchars($q['deadline']);
                // audience handling (raw values preserved for logic; escaped versions for output)
                $qaud_raw = $q['audience'] ?? null;
                $qaud = htmlspecialchars($qaud_raw ?? 'all');
                $qtarget_raw = $q['target_value'] ?? '';
                $qtarget = htmlspecialchars($qtarget_raw);
              ?>
                <article class="quiz-card" data-deadline="<?php echo $qdead ?: ''; ?>" data-scheduled="<?php echo $qsched ?: ''; ?>">
                  <div class="quiz-card-main">
                    <div class="quiz-card-left">
                      <div class="quiz-icon">📝</div>
                    </div>
                    <div class="quiz-card-body">
                      <div class="quiz-title"><?php echo $qtitle; ?></div>
                      <div class="quiz-desc"><?php echo $qdesc; ?></div>
                      <div class="quiz-meta">
                        <span class="meta-item">By: <?php echo $qteacher; ?></span>
                        <?php if (!empty($qsched)): ?><span class="meta-item">Scheduled: <?php echo $qsched; ?></span><?php endif; ?>
                        <?php if (!empty($qdead)): ?><span class="meta-item">Deadline: <?php echo $qdead; ?></span><?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <div class="quiz-card-footer">
                    <div class="quiz-audience">
                      <?php if (!empty($qaud_raw) && $qaud_raw === 'specific' && !empty($qtarget_raw)): ?>
                        <span class="badge-targeted">Targeted: <?php echo htmlspecialchars($qtarget_raw); ?></span>
                      <?php elseif (!empty($qaud_raw) && $qaud_raw === 'students'): ?>
                        <span class="badge-muted">All students</span>
                      <?php else: ?>
                        <!-- audience is 'all' or not set: no badge -->
                      <?php endif; ?>
                    </div>
                    <div class="quiz-actions">
                      <a class="btn btn-secondary" href="quiz.php?id=<?php echo urlencode($qid); ?>">View</a>
                      <?php
                        // compute start availability for grid cards (reuse logic)
                        $student_account_id = !empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
                        $student_email_local = $student_email;
                        $canStart = true; $startLabel = 'Start'; $startHint = '';
                        $qid_local = intval($q['id']);
                        $maxAttempts = 2;
                        try { $mm = $connection->prepare('SELECT max_attempts FROM quizzes WHERE id = ? LIMIT 1'); if ($mm) { $mm->bind_param('i',$qid_local); $mm->execute(); $mres = $mm->get_result(); if ($mres && ($mrow = $mres->fetch_assoc())) { if (!empty($mrow['max_attempts'])) $maxAttempts = intval($mrow['max_attempts']); } if ($mres) $mres->close(); $mm->close(); } } catch (Exception $e) {}
                        $attempts = 0;
                        if ($student_account_id) {
                          try { $ac = $connection->prepare('SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?'); if ($ac) { $ac->bind_param('ii',$qid_local,$student_account_id); $ac->execute(); $ar = $ac->get_result(); if ($ar && ($rowc = $ar->fetch_assoc())) $attempts = intval($rowc['cnt']); if ($ar) $ar->close(); $ac->close(); } } catch (Exception $e) { }
                        }
                        $isGraded = false; $gradeValue = null;
                        try { $ss = $connection->prepare('SELECT grade FROM submissions WHERE resource_type = ? AND resource_id = ? AND (student_id = ? OR student_id = ?) LIMIT 1'); if ($ss) { $rtype='quiz'; $sidA = $student_account_id !== null ? (string)$student_account_id : ''; $sidB = $student_email_local ?? ''; $ss->bind_param('siss', $rtype, $qid_local, $sidA, $sidB); $ss->execute(); $sres = $ss->get_result(); if ($sres && ($srow = $sres->fetch_assoc())) { if ($srow['grade'] !== null && $srow['grade'] !== '') { $isGraded = true; $gradeValue = $srow['grade']; } } if ($sres) $sres->close(); $ss->close(); } } catch (Exception $e) {}
                        if ($isGraded) { $canStart = false; $startLabel = 'Graded: '.($gradeValue ?? 'View'); $startHint = 'Your quiz is graded: '.($gradeValue ?? ''); }
                        else if ($attempts >= $maxAttempts) { $canStart = false; $startLabel = 'Limit attempt'; $startHint = 'Limit attempt! Wait for the teacher to grade your quiz.'; }
                      ?>
                      <?php if ($canStart): ?>
                        <a class="btn btn-primary" href="quiz.php?id=<?php echo urlencode($qid); ?>"><?php echo htmlspecialchars($startLabel); ?></a>
                      <?php else: ?>
                        <button class="btn btn-secondary" disabled title="<?php echo htmlspecialchars($startHint); ?>"><?php echo htmlspecialchars($startLabel); ?></button>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
  <script>
    // Small script to show relative time until deadline (client-side)
    (function(){
      function relTime(d){
        if (!d) return '';
        var ms = Date.parse(d) - Date.now();
        if (isNaN(ms)) return '';
        if (ms <= 0) return 'Closed';
        var days = Math.floor(ms / (24*60*60*1000));
        var hrs = Math.floor(ms / (60*60*1000)) % 24;
        if (days>0) return days + ' day' + (days>1?'s':'') + ' left';
        if (hrs>0) return hrs + ' hr' + (hrs>1?'s':'') + ' left';
        var mins = Math.floor(ms / (60*1000)) % 60;
        return mins + ' min' + (mins>1?'s':'') + ' left';
      }
      try {
        var cards = document.querySelectorAll('.quiz-card');
        cards.forEach(function(c){
          var dl = c.getAttribute('data-deadline');
          var sched = c.getAttribute('data-scheduled');
          if (!dl && !sched) return;
          var el = document.createElement('div'); el.className='meta-item';
          if (dl) el.textContent = 'Deadline: ' + dl + ' • ' + relTime(dl);
          else if (sched) el.textContent = 'Scheduled: ' + sched;
          var meta = c.querySelector('.quiz-meta');
          if (meta) meta.appendChild(el);
        });
      } catch(e){ console.warn('quiz time script failed', e); }
    })();
  </script>
</body>
</html>
