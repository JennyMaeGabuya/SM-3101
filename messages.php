<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages - BatStateU Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
    </div>
        <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
        <div class="gx-orb gx-orb--small" style="right:20px; bottom:10px; background:rgba(196,30,58,0.06);"></div>
        <div class="gx-orb gx-orb--soft" style="left:10px; top:20px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <?php
  include_once 'connection/dbsConnection.php';
  // Messages page reused as Learning Materials listing for students
  $materials = [];
  $notifications = [];
  // use maps to avoid duplicate cards when notifications match by both resource mapping and title-based joins
  $materials_map = [];
  $notifications_map = [];
  $currentEmail = null;
    if (isset($connection) && $connection) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['user_id'])) {
      $uid = intval($_SESSION['user_id']);
      $u = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
      if ($u) { $u->bind_param('i', $uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $rr = $resu->fetch_assoc(); if ($rr) $currentEmail = $rr['email']; $resu->close(); } $u->close(); }
    }
    // load student's section ids so we can filter section-targeted materials/notifications
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
      } catch (Exception $e) { error_log('messages: failed to load user sections: '.$e->getMessage()); }
    }
    try {
      // Detect whether notifications has resource mapping columns (resource_type/resource_id)
      $hasResourceCols = false;
      try {
        $c1 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'"); if ($c1) { if ($c1->num_rows > 0) $hasResourceCols = true; $c1->close(); }
        $c2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'"); if ($c2) { if ($c2->num_rows > 0) $hasResourceCols = $hasResourceCols && true; else $hasResourceCols = false; $c2->close(); }
      } catch (Exception $e) { $hasResourceCols = false; }

      // Detect teacher display column (try username, name, full_name)
      $teacherCol = null;
      try {
        $cols = ['username','name','full_name','display_name'];
        foreach ($cols as $c) {
          $qc = $connection->query("SHOW COLUMNS FROM teachers LIKE '" . $connection->real_escape_string($c) . "'");
          if ($qc && $qc->num_rows > 0) { $teacherCol = $c; $qc->close(); break; }
          if ($qc) $qc->close();
        }
      } catch (Exception $e) { $teacherCol = null; }

      // Build query depending on feature availability
      if ($currentEmail) {
        $target = 'user:' . $currentEmail;
        if ($hasResourceCols) {
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, COALESCE(n.created_at, l.created_at) AS notif_created, n.audience FROM learning_materials l LEFT JOIN teachers t ON t.teacher_id = l.teacher_id JOIN notifications n ON ( (n.resource_type = 'learning_material' AND n.resource_id = l.id) OR (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) ) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY n.created_at DESC LIMIT 200";
          $stmt = $connection->prepare($sql);
          if ($stmt) {
            $stmt->bind_param('s', $target);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                  // If notification targets sections, ensure student is member of one of those sections
                  if (!empty($r['audience']) && strpos($r['audience'], 'section:') === 0) {
                    $sids = array_filter(array_map('intval', explode(',', substr($r['audience'],8))));
                    $intersect = array_intersect($sids, $student_section_ids);
                    if (empty($intersect)) continue; // not for this student
                  }
                  // prefer unique materials by id
                  if (!empty($r['id'])) $materials_map[intval($r['id'])] = $r;
                  // de-duplicate notifications by title (or material id when present)
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
          // fallback to title-based join only
          $teacherSelect = $teacherCol ? "t.`$teacherCol` AS teacher" : "NULL AS teacher";
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, l.created_at AS notif_created, n.audience FROM learning_materials l LEFT JOIN teachers t ON t.teacher_id = l.teacher_id JOIN notifications n ON (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) WHERE (n.audience IN ('students','all') OR n.audience = ? OR n.audience LIKE 'section:%') ORDER BY n.created_at DESC LIMIT 200";
          $stmt = $connection->prepare($sql);
          if ($stmt) {
            $stmt->bind_param('s', $target);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
              while ($r = $res->fetch_assoc()) {
                if (!empty($r['audience']) && strpos($r['audience'], 'section:') === 0) {
                  $sids = array_filter(array_map('intval', explode(',', substr($r['audience'],8))));
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
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, COALESCE(n.created_at, l.created_at) AS notif_created FROM learning_materials l LEFT JOIN teachers t ON t.teacher_id = l.teacher_id JOIN notifications n ON ( (n.resource_type = 'learning_material' AND n.resource_id = l.id) OR (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) ) WHERE n.audience IN ('students','all') ORDER BY n.created_at DESC LIMIT 200";
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
          $sql = "SELECT l.id, l.title, l.description, l.file_path, l.link, l.created_at, $teacherSelect, l.created_at AS notif_created FROM learning_materials l LEFT JOIN teachers t ON t.teacher_id = l.teacher_id JOIN notifications n ON (n.sender_id = l.teacher_id AND n.title = CONCAT('New Material: ', l.title)) WHERE n.audience IN ('students','all') ORDER BY n.created_at DESC LIMIT 200";
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
      }
    } catch (Exception $e) { error_log('Fetch materials failed: '.$e->getMessage()); }
  }
  // convert maps to indexed arrays for rendering (preserve ordering by notif_created where possible)
  if (!empty($notifications_map)) {
    // notifications_map keys do not preserve DB order; sort by created_at if available
    $notifications = array_values($notifications_map);
    usort($notifications, function($a,$b){
      $ta = strtotime($a['created_at'] ?? '1970-01-01');
      $tb = strtotime($b['created_at'] ?? '1970-01-01');
      return $tb <=> $ta;
    });
  } else {
    $notifications = [];
  }
  if (!empty($materials_map)) {
    $materials = array_values($materials_map);
    usort($materials, function($a,$b){
      $ta = strtotime($a['notif_created'] ?? $a['created_at'] ?? '1970-01-01');
      $tb = strtotime($b['notif_created'] ?? $b['created_at'] ?? '1970-01-01');
      return $tb <=> $ta;
    });
  } else {
    $materials = [];
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
          <a href="schedule.php" class="nav-link">Activities</a>
          <a href="messages.php" class="nav-link active">Learning Materials</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
          </button>
          <button type="button" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</button>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
      <section class="messages-section">
        <div class="section-header">
          <h2 class="section-title">Learning Materials</h2>
        </div>

        <div class="materials-list" id="materialsList">
          <?php if (!empty($materials)): ?>
            <div class="section-subtitle" style="margin-bottom:10px;color:var(--text-primary)">Recent Materials</div>
          <?php endif; ?>
          <?php if (empty($materials)): ?>
            <div class="empty-state centered">
              <div class="empty-icon" style="font-size:4rem">📚</div>
              <h3 style="margin-top:1rem;color:var(--text-primary)">No learning materials available</h3>
              <p style="color:var(--text-secondary);margin-top:0.5rem">Materials posted by your teachers will appear here. Try checking another course or ask your instructor to upload resources.</p>
            </div>
          <?php else: ?>
            <?php foreach ($materials as $m): ?>
              <div class="material-item message-item">
                <div class="message-header">
                  <div class="message-sender" style="font-weight:700"><?php echo htmlspecialchars($m['title']); ?></div>
                  <div class="message-time"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($m['created_at']))); ?></div>
                </div>
                <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($m['description'])); ?></div>
                <div style="margin-top:8px; font-size:0.9rem; display:flex; gap:8px;">
                  <?php if (!empty($m['file_path'])): ?>
                    <a href="download.php?resource=material&id=<?php echo intval($m['id']); ?>" target="_blank" class="btn btn-secondary">Download</a>
                  <?php endif; ?>
                  <?php if (!empty($m['link'])): ?>
                    <a href="<?php echo htmlspecialchars($m['link']); ?>" target="_blank" class="btn btn-primary">Open Link</a>
                  <?php endif; ?>
                </div>
                <div style="margin-top:8px;font-size:0.85rem;color:var(--text-secondary)">Posted by: <?php echo htmlspecialchars($m['teacher'] ?: 'Teacher'); ?></div>
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
