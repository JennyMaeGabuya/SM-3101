<?php
require_once 'connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// allow both students and teachers to view (students primarily)
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: announcements.php'); exit;
}
$task = null;
try {
    $stmt = $connection->prepare('SELECT pt.*, t.username AS teacher FROM performance_tasks pt LEFT JOIN teachers t ON pt.teacher_id = t.teacher_id WHERE pt.id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $task = $res->fetch_assoc();
            $res->close();
        }
        $stmt->close();
    }
} catch (Exception $e) { error_log('view_task: '.$e->getMessage()); }
if (!$task) { header('Location: announcements.php'); exit; }
// load current student's submission (if any)
$submission = null;
try {
  $student_id = $_SESSION['user_id'] ?? null;
  if ($student_id) {
    $qs = $connection->prepare('SELECT id, file_path, notes, status, grade, graded_by, graded_at, created_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
    if ($qs) {
      $qs->bind_param('ii', $id, $student_id);
      $qs->execute();
      $rs = $qs->get_result();
      if ($rs) { $submission = $rs->fetch_assoc(); $rs->close(); }
      $qs->close();
    }
    // if graded_by present, try resolve teacher display name
    if (!empty($submission['graded_by'])) {
      $gb = intval($submission['graded_by']);
      $gbName = null;
      try {
        $hasId = false;
        $col = $connection->query("SHOW COLUMNS FROM teachers LIKE 'id'");
        if ($col && $col->num_rows>0) { $hasId = true; $col->close(); }
      } catch (Exception $e) { }
      if ($hasId) {
        $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? OR id = ? LIMIT 1');
        if ($tq) { $tq->bind_param('ii', $gb, $gb); $tq->execute(); $tres = $tq->get_result(); if ($tres && ($tr=$tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); $gbName = $fn || $ln ? trim($fn . ' ' . $ln) : ($tr['email'] ?? null); } if ($tres) $tres->close(); $tq->close(); }
      } else {
        $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? LIMIT 1');
        if ($tq) { $tq->bind_param('i', $gb); $tq->execute(); $tres = $tq->get_result(); if ($tres && ($tr=$tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); $gbName = $fn || $ln ? trim($fn . ' ' . $ln) : ($tr['email'] ?? null); } if ($tres) $tres->close(); $tq->close(); }
      }
      if ($gbName) $submission['graded_by_name'] = $gbName;
    }
  }
} catch (Exception $e) { error_log('view_task: submission lookup: '.$e->getMessage()); }
// determine if this submission has already been graded (used to disable resubmit)
$isGraded = false;
if (!empty($submission)) {
  if (!empty($submission['graded_by']) || !empty($submission['graded_at'])) $isGraded = true;
  elseif (!empty($submission['status']) && !in_array($submission['status'], ['submitted','pending'])) $isGraded = true;
}
// check for flash
$success = $_SESSION['success'] ?? null; unset($_SESSION['success']);
$error = $_SESSION['error'] ?? null; unset($_SESSION['error']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($task['title']); ?> - View Task</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>

</div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
   
    <div class="gx-orb gx-orb--small" style="right:16px; bottom:6px; background:rgba(30, 196, 38, 0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:6px; top:12px; background:rgba(184,134,11,0.05);"></div>

  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-content">
         <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img">  Student Portal</h1>
      
        
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
      <section style="padding:28px;">
        <div style="max-width:900px;margin:0 auto;color:var(--text-primary);">
          <?php if ($success): ?><div class="flash flash-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
          <?php if ($error): ?><div class="flash flash-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

          <h2 style="margin-bottom:6px"><?php echo htmlspecialchars($task['title']); ?></h2>
          <div style="color:var(--text-secondary);margin-bottom:12px">By: <?php echo htmlspecialchars($task['teacher'] ?? 'Teacher'); ?> • Posted: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($task['created_at']))); ?></div>

          <div style="background:var(--bg-secondary);padding:18px;border-radius:8px;border:1px solid var(--border-color);">
            <div style="margin-bottom:12px;line-height:1.5;color:var(--text-secondary);"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
            <?php if (!empty($task['rubric'])): ?>
              <div style="margin-top:10px;padding:10px;background:rgba(255,255,255,0.02);border-radius:6px;font-style:italic;color:var(--text-secondary);">
                <strong>Rubric:</strong>
                <div style="margin-top:6px"><?php echo nl2br(htmlspecialchars($task['rubric'])); ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($task['due_date'])): ?>
              <div style="margin-top:14px;font-weight:700">Due: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($task['due_date']))); ?></div>
            <?php endif; ?>

            <div style="margin-top:18px;display:flex;gap:12px">
              <a class="btn btn-secondary" href="announcements.php">Back</a>
              <a class="btn btn-primary" href="#submit">Submit</a>
            </div>

          </div>

          <div id="submit" style="margin-top:22px">
            <h3>Submit your work</h3>
            <p style="color:var(--text-secondary)">Upload a file (PDF, DOCX, image) and optionally add a short note. This demo stores the uploaded file on the server and records a simple submission entry.</p>
            <?php if (!empty($submission)): ?>
              <div class="card" style="margin-bottom:12px;">
                <strong>Your submission</strong>
                <div style="margin-top:8px;color:var(--text-secondary);">
                  Status: <?php echo htmlspecialchars($submission['status'] ?? ''); ?>
                  <?php if (!empty($submission['grade'])): ?> • Grade: <strong><?php echo htmlspecialchars($submission['grade']); ?></strong><?php endif; ?>
                </div>
                <?php if (!empty($submission['file_path'])): ?>
                  <div style="margin-top:8px"><a class="btn btn-secondary" href="download.php?resource=task_submission&id=<?php echo intval($submission['id']); ?>">Download uploaded file</a></div>
                <?php endif; ?>
                <?php if (!empty($submission['notes'])): ?><div style="margin-top:8px;color:var(--text-secondary);">Notes: <?php echo nl2br(htmlspecialchars($submission['notes'])); ?></div><?php endif; ?>
                <?php if (!empty($submission['graded_by_name']) || !empty($submission['graded_at'])): ?>
                  <div style="margin-top:8px;font-size:0.95em;color:var(--text-secondary);">Graded by <?php echo htmlspecialchars($submission['graded_by_name'] ?? ''); ?> on <?php echo htmlspecialchars($submission['graded_at'] ?? ''); ?></div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <form action="actions/submit_task.php" method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;max-width:560px">
              <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
              <label style="font-weight:600">Notes (optional)</label>
              <textarea name="notes" rows="3" style="padding:8px"></textarea>
              <label style="font-weight:600">File</label>
              <input type="file" name="file">
              <div style="display:flex;gap:8px">
                <?php if (!empty($isGraded)): ?>
                  <button class="btn btn-primary" type="button" disabled title="This submission has already been graded">Already graded</button>
                <?php else: ?>
                  <button class="btn btn-primary" type="submit">Upload &amp; Submit</button>
                <?php endif; ?>
                <a class="btn btn-secondary" href="announcements.php">Cancel</a>
              </div>
            </form>
          </div>

        </div>
      </section>
    </main>
  </div>

  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
