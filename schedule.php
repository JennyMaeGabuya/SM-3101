<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule - BatStateU Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
    </div>
        <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
        <div class="gx-orb gx-orb--small" style="right:20px; bottom:10px; background:rgba(196,30,58,0.06);"></div>
        <div class="gx-orb gx-orb--soft" style="left:10px; top:20px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <?php include_once 'connection/dbsConnection.php';
  $activities = [];
  if (isset($connection) && $connection) {
    try {
      // try to determine student email for filtering
      if (session_status() === PHP_SESSION_NONE) session_start();
      $student_email = null;
      if (!empty($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $u = $connection->prepare('SELECT email, created_at FROM accounts WHERE accountId = ? LIMIT 1');
        if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $r = $resu->fetch_assoc(); if ($r) { $student_email = $r['email']; $userCreatedAt = $r['created_at']; } $resu->close(); } $u->close(); }
      }

      // Also load student's section ids to support section-targeted activities
      $student_section_ids = [];
      if (!empty($_SESSION['user_id'])) {
        try {
          $s = $connection->prepare('SELECT section_id FROM user_sections WHERE user_id = ?');
          if ($s) {
            $s->bind_param('i', $uid);
            $s->execute();
            $rs = $s->get_result();
            if ($rs) {
              while ($rr = $rs->fetch_assoc()) { $student_section_ids[] = intval($rr['section_id']); }
              $rs->close();
            }
            $s->close();
          }
        } catch (Exception $e) { error_log('schedule: failed to load user sections: '.$e->getMessage()); }
      }

      $hasAudience = false;
      try { $col = $connection->query("SHOW COLUMNS FROM activities LIKE 'audience'"); if ($col && $col->num_rows>0) { $hasAudience = true; $col->close(); } } catch (Exception $e) { $hasAudience = false; }

      if ($hasAudience && $student_email) {
        // If student has sections, include section-targeted activities where their section id is present in target_value
        if (!empty($student_section_ids)) {
          $placeholders = [];
          foreach ($student_section_ids as $sid) { $placeholders[] = 'FIND_IN_SET(?, target_value)'; }
          $sectionsCond = implode(' OR ', $placeholders);
          $sql = "SELECT id,title,description,scheduled_at,deadline,created_at FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sectionsCond))) AND created_at >= ? ORDER BY scheduled_at DESC";
          $ares = $connection->prepare($sql);
          if ($ares) {
            // bind student_email then each section id
            $types = str_repeat('s', 1 + count($student_section_ids) + 1);
            $params = array_merge([$student_email], array_map('strval', $student_section_ids), [strval($userCreatedAt ?? '')]);
            $bind_names = array();
            $bind_names[] = $types;
            for ($i=0;$i<count($params);$i++) { $bind_name = 'param'.$i; $$bind_name = $params[$i]; $bind_names[] = &$$bind_name; }
            call_user_func_array(array($ares, 'bind_param'), $bind_names);
            $ares->execute(); $resA = $ares->get_result(); if ($resA) { while ($r = $resA->fetch_assoc()) $activities[] = $r; $resA->close(); } $ares->close();
            unset($bind_names);
          }
        } else {
          // no sections for student; simple query matching students/all or specific, and only items created after account creation
          $ares = $connection->prepare("SELECT id,title,description,scheduled_at,deadline,created_at FROM activities WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?)) AND created_at >= ? ORDER BY scheduled_at DESC");
          if ($ares) { $createdAtBind = strval($userCreatedAt ?? ''); $ares->bind_param('ss',$student_email, $createdAtBind); $ares->execute(); $resA = $ares->get_result(); if ($resA) { while ($r = $resA->fetch_assoc()) $activities[] = $r; $resA->close(); } $ares->close(); }
        }
      } elseif ($hasAudience) {
        // no logged-in student: show everything (legacy behaviour)
        $ares = $connection->query("SELECT id,title,description,scheduled_at,deadline,created_at FROM activities ORDER BY scheduled_at DESC");
        if ($ares) { while ($r = $ares->fetch_assoc()) $activities[] = $r; $ares->close(); }
      } else {
        $ares = $connection->query("SELECT id,title,description,scheduled_at,deadline,created_at FROM activities ORDER BY scheduled_at DESC");
        if ($ares) { while ($r = $ares->fetch_assoc()) $activities[] = $r; $ares->close(); }
      }
      // build existing title set to avoid duplicate notification-derived cards
      $existing_titles = [];
      foreach ($activities as $at) { if (!empty($at['title'])) $existing_titles[] = strtolower(trim($at['title'])); }
      // Also include teacher-created activity notifications (fallback for older installs or when activities rows are not present)
      try {
        if (!empty($student_email)) {
          $notifSql = "SELECT id, title, message, audience, created_at FROM notifications WHERE title LIKE 'New Activity:%' AND (audience IN ('students','all') OR audience = ? OR audience LIKE 'section:%') AND created_at >= ? ORDER BY created_at DESC LIMIT 50";
          $stmtN = $connection->prepare($notifSql);
          if ($stmtN) {
            $target = 'user:' . $student_email;
            $createdAtBindN = strval($userCreatedAt ?? '');
            $stmtN->bind_param('ss', $target, $createdAtBindN);
            $stmtN->execute();
            $rn = $stmtN->get_result();
            if ($rn) {
              while ($nr = $rn->fetch_assoc()) {
                // normalize into activities array shape, but skip if title already exists
                $t = preg_replace('/^New Activity:\s*/i', '', $nr['title']);
                if ($t === '' || in_array(strtolower(trim($t)), $existing_titles, true)) continue;
                // If notification is section-targeted, ensure student is member of at least one of those sections
                $aud = isset($nr['audience']) ? $nr['audience'] : '';
                if (strpos($aud, 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($aud,8))));
                  $intersect = array_intersect($sids, $student_section_ids);
                  if (empty($intersect)) continue; // not for this student
                }
                $activities[] = [
                  'id' => -1 * intval($nr['id']),
                  'title' => $t,
                  'description' => $nr['message'],
                  'scheduled_at' => $nr['created_at'],
                  'deadline' => null,
                  'created_at' => $nr['created_at']
                ];
              }
              $rn->close();
            }
            $stmtN->close();
          }
        } else {
          // not logged in student: include broadcast new-activity notifications
          $notifSql2 = "SELECT id, title, message, created_at FROM notifications WHERE title LIKE 'New Activity:%' AND audience IN ('students','all') ORDER BY created_at DESC LIMIT 50";
          $resN2 = $connection->query($notifSql2);
          if ($resN2) { while ($nr = $resN2->fetch_assoc()) { $t = preg_replace('/^New Activity:\s*/i','',$nr['title']); if ($t !== '' && in_array(strtolower(trim($t)), $existing_titles, true)) continue; $activities[] = ['id'=> -1*intval($nr['id']), 'title'=> $t, 'description'=>$nr['message'], 'scheduled_at'=>$nr['created_at'], 'deadline'=>null, 'created_at'=>$nr['created_at'] ]; } $resN2->close(); }
        }
      } catch (Exception $e) { error_log('schedule: load activity notifications failed: '.$e->getMessage()); }
    } catch (Exception $e) { error_log('Fetch activities failed: '.$e->getMessage()); }
    // Dev debug: when ?dev_debug=1 show activities payload for logged-in students
    if (!empty($_GET['dev_debug']) && session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_GET['dev_debug']) && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
      echo '<div style="padding:12px;background:#fff8c6;border:1px solid #f0e68c;margin:12px;border-radius:6px;">';
      echo '<h3 style="margin:0 0 8px 0">Dev Debug: activities payload</h3><pre style="white-space:pre-wrap;">'.htmlspecialchars(print_r($activities, true)).'</pre>';
      echo '<div style="font-size:0.9em;color:#444;margin-top:6px">student_email: '.htmlspecialchars($student_email ?? 'NULL').' | hasAudience: '.($hasAudience ? 'true' : 'false').'</div>';
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
          <a href="announcements.php" class="nav-link">Performance Tasks</a>
          <a href="schedule.php" class="nav-link active">Activities</a>
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
      <section class="schedule-section">
        <div class="section-header">
          <h2 class="section-title">Activities</h2>
          <div style="margin-left:auto">
          </div>
</div>

        <div class="schedule-grid" id="scheduleList">
          <?php if (empty($activities)): ?>
            <div class="empty-state centered">
              <div class="empty-icon" style="font-size:4rem">📅</div>
              <h3 style="margin-top:1rem;color:var(--text-primary)">No Activities Scheduled</h3>
              <p style="color:var(--text-secondary);margin-top:0.5rem">Your teacher will post activities here. Use the calendar button to add important events.</p>
            </div>
          <?php else: ?>
            <div class="activities-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
            <?php foreach ($activities as $s): ?>
              <article class="activity-card" style="background:var(--bg-secondary);border:1px solid var(--border-color);padding:14px;border-radius:10px;display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div>
                    <div style="font-weight:800;font-size:1.05rem"><?php echo htmlspecialchars($s['title']); ?></div>
                    <div style="font-size:0.9rem;color:var(--text-secondary);margin-top:6px"><?php echo nl2br(htmlspecialchars($s['description'])); ?></div>
                  </div>
                  <div style="font-size:0.85rem;color:var(--text-secondary)"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($s['scheduled_at']))); ?></div>
                </div>
                <?php if (!empty($s['deadline'])): ?><div style="font-weight:700">Deadline: <?php echo htmlspecialchars($s['deadline']); ?></div><?php endif; ?>
                <div style="margin-top:auto;display:flex;gap:8px;justify-content:flex-end;align-items:center">
                  <?php if (isset($s['id']) && intval($s['id']) < 0): ?>
                    <span style="background:#2b2b2b;color:#ffd;font-size:0.75rem;padding:6px 8px;border-radius:6px;margin-right:8px;border:1px solid rgba(255,215,0,0.06)">From notification</span>
                  <?php endif; ?>
                  <a class="btn btn-primary" href="activity_events.php?item=<?php echo urlencode($s['id']); ?>" target="_blank" style="padding:8px 12px">Related Events</a>
                </div>
              </article>
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
