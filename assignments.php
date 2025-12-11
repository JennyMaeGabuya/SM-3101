<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assignments - Student Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    <div class="gx-orb gx-orb--small" style="right:10px; bottom:14px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:8px; top:22px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <?php include_once 'connection/dbsConnection.php';
  $assignments = [];
    if (isset($connection) && $connection) {
      try {
        // try to filter performance tasks by student email if possible
        if (session_status() === PHP_SESSION_NONE) session_start();
        $student_email = null;
        if (!empty($_SESSION['user_id'])) {
          $uid = intval($_SESSION['user_id']);
          $u = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
          if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $r = $resu->fetch_assoc(); if ($r) $student_email = $r['email']; $resu->close(); } $u->close(); }
        }

        // detect if performance_tasks has audience fields
        $hasAudience = false;
        try { $col = $connection->query("SHOW COLUMNS FROM performance_tasks LIKE 'audience'"); if ($col && $col->num_rows>0) { $hasAudience = true; $col->close(); } } catch (Exception $e) { $hasAudience = false; }

        // load student's sections so we can include section-targeted assignments
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
          } catch (Exception $e) { error_log('assignments: failed to load user sections: '.$e->getMessage()); }
        }

        if ($hasAudience && $student_email) {
          if (!empty($student_section_ids)) {
            $placeholders = [];
            foreach ($student_section_ids as $sid) { $placeholders[] = 'FIND_IN_SET(?, target_value)'; }
            $sectionsCond = implode(' OR ', $placeholders);
            $sql = "SELECT id,title,description,due_date,group_allowed,rubric,created_at,audience,target_value FROM performance_tasks WHERE (COALESCE(audience,'students') IN ('students','all') OR (audience = 'specific' AND target_value = ?) OR (audience = 'section' AND ($sectionsCond))) ORDER BY due_date DESC";
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
            $q = $connection->prepare("SELECT id,title,description,due_date,group_allowed,rubric,created_at,audience,target_value FROM performance_tasks WHERE COALESCE(audience,'students') = 'students' OR COALESCE(audience,'students') = 'all' OR (audience = 'specific' AND target_value = ?) ORDER BY due_date DESC");
            if ($q) { $q->bind_param('s',$student_email); $q->execute(); $res = $q->get_result(); }
          }
        } elseif ($hasAudience) {
          $res = $connection->query("SELECT id,title,description,due_date,group_allowed,rubric,created_at,audience,target_value FROM performance_tasks ORDER BY due_date DESC");
        } else {
          $res = $connection->query("SELECT id,title,description,due_date,group_allowed,rubric,created_at FROM performance_tasks ORDER BY due_date DESC");
        }

        if (isset($res) && $res) { while ($r = $res->fetch_assoc()) $assignments[] = $r; if (is_object($res)) $res->close(); }
      } catch (Exception $e) { error_log('Fetch performance_tasks failed: '.$e->getMessage()); }
    }

  ?>

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

    <main class="main-content">
      <section class="assignments-section">
        <div class="section-header">
          <h2 class="hero-title gx-parallax gx-reveal section-title">Assignments</h2>
        </div>
        <div class="assignments-list" id="assignmentsList">
          <?php if (empty($assignments)): ?>
            <div class="empty-state">
              <div class="empty-icon">✍️</div>
              <h4>No assignments</h4>
            </div>
          <?php else: ?>
            <?php foreach ($assignments as $a): ?>
              <div class="assignment-item">
                <div class="assignment-content">
                  <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
                    <div>
                      <div class="assignment-title"><?php echo htmlspecialchars($a['title']); ?></div>
                      <div style="font-size:0.9rem;color:var(--text-secondary);margin-top:6px"><?php echo nl2br(htmlspecialchars($a['description'])); ?></div>
                    </div>
                    <div style="text-align:right">
                      <div class="assignment-meta">
                        <div class="assignment-due">📅 <?php echo htmlspecialchars($a['due_date']); ?></div>
                        <div style="margin-top:6px"><span class="assignment-status"><?php echo $a['group_allowed'] ? 'Group allowed' : 'Individual'; ?></span></div>
                      </div>
                    </div>
                  </div>
                  <?php if (!empty($a['rubric'])): ?><div style="font-style:italic;color:var(--text-secondary);margin-top:0.75rem">Rubric: <?php echo nl2br(htmlspecialchars($a['rubric'])); ?></div><?php endif; ?>
                  <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end">
                    <a class="btn btn-secondary" href="#">View Details</a>
                    <a class="btn btn-primary" href="#">Submit</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
