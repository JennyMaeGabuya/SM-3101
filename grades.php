<?php
include_once 'connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$grades = [];
$student_ident = null;
if (!empty($_SESSION['user_id']) && isset($connection) && $connection) {
    $uid = intval($_SESSION['user_id']);
    // try to resolve student identifier (username/email/accountId)
    $u = $connection->prepare('SELECT username,email,accountId FROM accounts WHERE accountId = ? LIMIT 1');
    if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $rr = $resu->fetch_assoc(); if ($rr) $student_ident = $rr; $resu->close(); } $u->close(); }

    if ($student_ident) {
        // fetch submissions that match by accountId (as string), username, or email
        // Defensive: ensure legacy `submissions` table has grading columns so queries below won't fail
        try {
          $existingCols = [];
          $rc = $connection->query("SHOW COLUMNS FROM submissions");
          if ($rc) { while ($c = $rc->fetch_assoc()) $existingCols[] = $c['Field']; $rc->close(); }
          $alts = [];
          if (!in_array('grade', $existingCols)) $alts[] = "ADD COLUMN `grade` VARCHAR(100) NULL AFTER `status`";
          if (!in_array('graded_by', $existingCols)) $alts[] = "ADD COLUMN `graded_by` INT NULL AFTER `grade`";
          if (!in_array('graded_at', $existingCols)) $alts[] = "ADD COLUMN `graded_at` DATETIME NULL AFTER `graded_by`";
          if (!empty($alts)) { $sqlAlt = "ALTER TABLE submissions " . implode(', ', $alts); try { $connection->query($sqlAlt); } catch (Exception $e) { /* ignore */ } }
        } catch (Exception $e) { /* non-fatal */ }
        $sid1 = strval($student_ident['accountId']);
        $suser = $student_ident['username'];
        $sem = $student_ident['email'];
        $stmt = $connection->prepare("SELECT s.id, s.resource_type, s.resource_id, s.file_path, s.submitted_at, s.status, s.feedback, s.grade, t.username AS teacher FROM submissions s LEFT JOIN teachers t ON s.teacher_id = t.teacher_id WHERE s.student_id IN (?, ?, ?) OR s.student_id = ? ORDER BY s.submitted_at DESC");
        if ($stmt) {
            // bind the same values (accountId, username, email, accountId) to maximize matches
            $stmt->bind_param('ssss', $sid1, $suser, $sem, $sid1);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) { while ($r = $res->fetch_assoc()) $grades[] = $r; $res->close(); }
            $stmt->close();
        }
    }
}

      // Also include latest quiz attempts (if any) so students can see their quiz scores here.
      if (!empty($uid) && isset($connection) && $connection) {
        try {
          $q = $connection->prepare('SELECT qa.quiz_id, qa.score, qa.submitted_at, q.title AS quiz_title, t.username AS teacher FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id LEFT JOIN teachers t ON q.teacher_id = t.teacher_id WHERE qa.student_id = ? ORDER BY qa.submitted_at DESC');
          if ($q) {
            $q->bind_param('i', $uid);
            $q->execute();
            $resq = $q->get_result();
            if ($resq) {
              $seen = array();
              while ($row = $resq->fetch_assoc()) {
                $qid = intval($row['quiz_id']);
                if (in_array($qid, $seen)) continue;
                $seen[] = $qid;
                $grades[] = [
                  'id' => null,
                  'resource_type' => 'quiz',
                  'resource_id' => $qid,
                  'file_path' => null,
                  'submitted_at' => $row['submitted_at'],
                  'status' => null,
                  'feedback' => null,
                  'teacher' => $row['teacher'] ?? null,
                  'grade' => $row['score'],
                  'title' => $row['quiz_title'] ?? null
                ];
              }
              $resq->close();
            }
            $q->close();
          }
        } catch (Exception $e) { error_log('grades: quiz attempts load failed: '.$e->getMessage()); }
      }

        // Include task_submissions for the student so performance task grades appear
        if (!empty($uid) && isset($connection) && $connection) {
          try {
            $tsq = $connection->prepare("SELECT ts.id, ts.task_id, ts.file_path, ts.created_at AS submitted_at, ts.status, ts.notes AS feedback, ts.grade, ts.graded_by, ts.graded_at, pt.title AS task_title, t.username AS teacher FROM task_submissions ts LEFT JOIN performance_tasks pt ON pt.id = ts.task_id LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE ts.student_id = ? ORDER BY ts.created_at DESC");
            if ($tsq) {
              $tsq->bind_param('i', $uid);
              $tsq->execute();
              $tsr = $tsq->get_result();
              if ($tsr) {
                while ($row = $tsr->fetch_assoc()) {
                  $grades[] = [
                    'id' => $row['id'] ?? null,
                    'resource_type' => 'task',
                    'resource_id' => intval($row['task_id'] ?? 0),
                    'file_path' => $row['file_path'] ?? null,
                    'submitted_at' => $row['submitted_at'] ?? null,
                    'status' => $row['status'] ?? null,
                    'feedback' => $row['feedback'] ?? null,
                    'grade' => isset($row['grade']) ? $row['grade'] : null,
                    'teacher' => $row['teacher'] ?? null,
                    'title' => $row['task_title'] ?? null,
                    'graded_by' => $row['graded_by'] ?? null,
                    'graded_at' => $row['graded_at'] ?? null
                  ];
                }
                $tsr->close();
              }
              $tsq->close();
            }
          } catch (Exception $e) { error_log('grades: task_submissions load failed: '.$e->getMessage()); }
        }

        // Consolidated post-processing: fetch missing task titles/grades and deduplicate entries
        if (!empty($uid) && isset($connection) && $connection && !empty($grades)) {
          // 1) ensure task titles and missing task grades are filled from task_submissions
          foreach ($grades as &$g) {
            $resId = intval($g['resource_id'] ?? 0);
            if (isset($g['resource_type']) && strtolower($g['resource_type']) === 'task' && $resId > 0) {
              // fetch title if missing
              if (empty($g['title']) || $g['title'] === null) {
                try {
                  $pt = $connection->prepare('SELECT title FROM performance_tasks WHERE id = ? LIMIT 1');
                  if ($pt) {
                    $pt->bind_param('i', $resId);
                    $pt->execute();
                    $r = $pt->get_result();
                    if ($r && ($row = $r->fetch_assoc())) $g['title'] = $row['title'];
                    if ($r) $r->close();
                    $pt->close();
                  }
                } catch (Exception $e) { }
              }
              // fetch grade from task_submissions if grade missing
              if ((!isset($g['grade']) || $g['grade'] === null || $g['grade'] === '') && $resId > 0) {
                try {
                  $sq = $connection->prepare('SELECT grade,status,graded_by,graded_at FROM task_submissions WHERE task_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1');
                  if ($sq) {
                    $sq->bind_param('ii', $resId, $uid);
                    $sq->execute();
                    $sr = $sq->get_result();
                    if ($sr && ($rr = $sr->fetch_assoc())) {
                      if (isset($rr['grade']) && ($rr['grade'] !== '' || $rr['grade'] === '0')) $g['grade'] = $rr['grade'];
                      if (empty($g['status']) && !empty($rr['status'])) $g['status'] = $rr['status'];
                      if (empty($g['graded_by']) && !empty($rr['graded_by'])) $g['graded_by'] = $rr['graded_by'];
                      if (empty($g['graded_at']) && !empty($rr['graded_at'])) $g['graded_at'] = $rr['graded_at'];
                    }
                    if ($sr) $sr->close();
                    $sq->close();
                  }
                } catch (Exception $e) { }
              }
            }
          }
          unset($g);

          // 2) deduplicate by resource_type + resource_id (keep item with grade, otherwise most recent)
          $unique = array();
          foreach ($grades as $g) {
            $type = strtolower(strval($g['resource_type'] ?? ''));
            $rid = intval($g['resource_id'] ?? 0);
            $key = $type . ':' . $rid;
            if ($rid === 0 && $type === 'quiz' && isset($g['id']) && $g['id']) {
              // fallback key for strange rows
              $key = $type . ':q' . intval($g['id']);
            }
            if (!isset($unique[$key])) {
              $unique[$key] = $g;
              continue;
            }
            $existing = $unique[$key];
            $existingHasGrade = isset($existing['grade']) && $existing['grade'] !== '';
            $currentHasGrade = isset($g['grade']) && $g['grade'] !== '';
            if ($currentHasGrade && !$existingHasGrade) {
              $unique[$key] = $g;
              continue;
            }
            if ($currentHasGrade && $existingHasGrade) {
              // keep the most recent by submitted_at
              $ta = strtotime($existing['submitted_at'] ?? '0');
              $tb = strtotime($g['submitted_at'] ?? '0');
              if ($tb > $ta) $unique[$key] = $g;
              continue;
            }
            // neither has grade -> keep most recent
            $ta = strtotime($existing['submitted_at'] ?? '0');
            $tb = strtotime($g['submitted_at'] ?? '0');
            if ($tb > $ta) $unique[$key] = $g;
          }
          // reassign grades to unique list preserving order
          $grades = array_values($unique);
        }

        // If requested via AJAX, return JSON payload of grades for client-side polling
        if (!empty($_GET['ajax'])) {
          header('Content-Type: application/json');
          echo json_encode(array_values($grades));
          exit;
        }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Grades - Student Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
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
          <a href="grades.php" class="nav-link active">Grades</a>
          <a href="announcements.php" class="nav-link">Performance Tasks</a>
          <a href="schedule.php" class="nav-link">Activities</a>
          <a href="messages.php" class="nav-link">Learning Materials</a>
          </button>
          <button type="button" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</button>
        </div>
      </div>
    </nav>

    <main class="main-content">
      <section class="grades-section">
        <div class="section-header">
          <h2 class="section-title">Grades & Submissions</h2>
        </div>

        <div class="grades-list">
          <?php if (empty($grades)): ?>
            <div class="empty-state"><div class="empty-icon">📊</div><h4>No grades or submissions found</h4></div>
          <?php else: ?>
            <?php foreach ($grades as $g): ?>
              <div class="grade-item">
                <div>
                  <div class="grade-course"><?php echo htmlspecialchars( (!empty($g['title']) ? $g['title'] : (ucfirst($g['resource_type']) . ' #' . $g['resource_id'])) ); ?></div>
                  <div style="font-size:0.9rem;color:var(--text-secondary);margin-top:6px">Submitted: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($g['submitted_at']))); ?><?php if (!empty($g['teacher'])) echo ' — By: '.htmlspecialchars($g['teacher']); ?></div>
                </div>
                <div class="grade-score">
                  <?php if (isset($g['grade']) && $g['grade'] !== '') { echo htmlspecialchars($g['grade']); } else { echo htmlspecialchars($g['status'] ?? ''); } ?>
                  <?php if (empty($g['grade']) && !empty($g['graded_by'])): ?>
                    <div style="font-size:0.8rem;color:var(--text-secondary)">Graded by: <?php echo htmlspecialchars($g['graded_by']); if (!empty($g['graded_at'])) echo ' on '.htmlspecialchars($g['graded_at']); ?></div>
                  <?php endif; ?>
                </div>
                <div style="text-align:right">
                  <?php if (!empty($g['file_path'])):
                      // Use secure download endpoint when we have a known resource id
                      if (!empty($g['resource_type']) && strtolower($g['resource_type']) === 'task' && !empty($g['id'])): ?>
                        <a class="btn btn-secondary btn-download" href="download.php?resource=task_submission&id=<?php echo intval($g['id']); ?>">Download</a>
                      <?php elseif (!empty($g['resource_type']) && strtolower($g['resource_type']) === 'submission' && !empty($g['id'])): ?>
                        <a class="btn btn-secondary btn-download" href="download.php?resource=submission&id=<?php echo intval($g['id']); ?>">Download</a>
                      <?php else: ?>
                        <?php
                          // If stored path is relative (e.g. public/uploads/...), prefix app folder so link resolves under LearnHub on XAMPP.
                          $fp = ltrim($g['file_path'], '/');
                          if (strpos($fp, 'http://') === 0 || strpos($fp, 'https://') === 0) {
                            $href = $g['file_path'];
                          } else {
                            $href = '/LearnHub/' . $fp;
                          }
                        ?>
                        <a class="btn btn-secondary btn-download" href="<?php echo htmlspecialchars($href); ?>" target="_blank">View</a>
                      <?php endif; ?>
                  <?php endif; ?>
                  <?php if (!empty($g['feedback'])): ?><div style="margin-top:8px;color:var(--text-secondary)"><?php echo nl2br(htmlspecialchars($g['feedback'])); ?></div><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>

  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
  <script>
    (function(){
      // Poll for updated grades so student sees teacher grading without full refresh
      const container = document.querySelector('.grades-list');
      if (!container) return;

      function buildHtml(items) {
        if (!items || items.length === 0) return '<div class="empty-state"><div class="empty-icon">📊</div><h4>No grades or submissions found</h4></div>';
        let html = '';
        items.forEach(g => {
          const title = g.title ? g.title : ((g.resource_type||'').charAt(0).toUpperCase() + (g.resource_type||'').slice(1)) + ' #' + (g.resource_id||'');
          const submitted = g.submitted_at ? new Date(g.submitted_at).toLocaleString() : '';
          const teacher = g.teacher ? ' — By: ' + escapeHtml(g.teacher) : '';
          const grade = (typeof g.grade !== 'undefined' && g.grade !== null && g.grade !== '') ? escapeHtml(String(g.grade)) : (g.status ? escapeHtml(g.status) : '');
          html += '<div class="grade-item">';
          html += '<div><div class="grade-course">'+escapeHtml(title)+'</div><div style="font-size:0.9rem;color:var(--text-secondary);margin-top:6px">Submitted: '+escapeHtml(submitted)+teacher+'</div></div>';
          html += '<div class="grade-score">'+grade;
          if (!g.grade && g.graded_by) html += '<div style="font-size:0.8rem;color:var(--text-secondary)">Graded by: '+escapeHtml(g.graded_by)+(g.graded_at ? ' on '+escapeHtml(g.graded_at):'')+'</div>';
          html += '</div>';
          html += '<div style="text-align:right">';
            if (g.file_path) {
            // prefer download endpoint when we have a submission id for better authorization
            let link = '';
            let fp = String(g.file_path || '').trim();
            if (fp.indexOf('http://') === 0 || fp.indexOf('https://') === 0) {
              link = fp;
            } else if (fp.charAt(0) === '/') {
              link = fp; // absolute path already
            } else {
              link = '/LearnHub/' + escapeHtml(fp);
            }
            let label = 'View';
            if (g.resource_type && (g.resource_type||'').toLowerCase() === 'task' && g.id) { link = 'download.php?resource=task_submission&id=' + encodeURIComponent(g.id); label = 'Download'; }
            else if (g.resource_type && (g.resource_type||'').toLowerCase() === 'submission' && g.id) { link = 'download.php?resource=submission&id=' + encodeURIComponent(g.id); label = 'Download'; }
            html += '<a class="btn btn-secondary btn-download" href="'+link+'" target="_blank">'+label+'</a>';
          }
          if (g.feedback) html += '<div style="margin-top:8px;color:var(--text-secondary)">'+escapeHtml(g.feedback)+'</div>';
          html += '</div></div>';
        });
        return html;
      }

      function escapeHtml(s){ return String(s||'').replace(/[&"'<>]/g, function(m){return {'&':'&amp;','"':'&quot;',"'":'&#39;','<':'&lt;','>':'&gt;'}[m]; }); }

      let lastPayload = null;
      function poll() {
        fetch(window.location.pathname + '?ajax=1', {credentials:'same-origin'})
          .then(r=>r.json())
          .then(data=>{
            const json = JSON.stringify(data || []);
            if (json !== lastPayload) {
              lastPayload = json;
              container.innerHTML = buildHtml(data || []);
            }
          }).catch(()=>{});
      }

      // initial poll immediately then every 10s
      poll();
      setInterval(poll, 10000);
    })();
  </script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Grades - BatStateU Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>

    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    
    <div class="gx-orb gx-orb--small" style="right:6px; bottom:6px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:6px; top:6px; background:rgba(184,134,11,0.05);"></div>
  </section>
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
      <section class="grades-section">
        <div class="section-header">
          <h2 class="section-title">Academic Grades</h2>
        </div>

        <div class="gpa-display">
          <p class="gpa-label">Current GPA</p>
          <p class="gpa-value" id="currentGPA">3.5</p>
          <p class="gpa-scale">Scale: 0.0 - 4.0</p>
        </div>

        <div class="filter-bar">
          <button class="filter-btn active" data-filter="all">All Courses</button>
          <button class="filter-btn" data-filter="semester">This Semester</button>
        </div>

        <div class="grades-list" id="gradesList">
          <div class="empty-state">
            <div class="empty-icon">📊</div>
            <h4>No grades available</h4>
          </div>
        </div>
      </section>

  
  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
