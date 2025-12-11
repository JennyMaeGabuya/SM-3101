<?php
include_once 'connection/dbsConnection.php';
$materials = [];
if (isset($connection) && $connection) {
  try {
    $res = $connection->query("SELECT id,title,description,file_path,link,created_at,t.teacher_id,t.username as teacher FROM learning_materials l LEFT JOIN teachers t ON l.teacher_id = t.teacher_id ORDER BY created_at DESC");
    if ($res) { while ($r = $res->fetch_assoc()) $materials[] = $r; $res->close(); }
  } catch (Exception $e) { error_log('Fetch materials failed: '.$e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Learning Materials - Student Portal</title>
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
          <a href="courses.php" class="nav-link">Courses</a>
          <a href="assignments.php" class="nav-link">Assignments</a>
          <a href="grades.php" class="nav-link">Grades</a>
          <a href="announcements.php" class="nav-link">Announcements</a>
          <a href="schedule.php" class="nav-link">Schedule</a>
          <a href="messages.php" class="nav-link">Messages</a>
          <a href="materials.php" class="nav-link active">Learning Materials</a>
        </div>
      </div>
    </nav>

    <main class="main-content">
      <section class="materials-section">
        <div class="section-header">
          <h2 class="section-title">Learning Materials</h2>
        </div>

        <div class="materials-list">
          <?php if (empty($materials)): ?>
            <div class="empty-state"><div class="empty-icon">📚</div><h4>No learning materials available</h4></div>
          <?php else: ?>
            <?php foreach ($materials as $m): ?>
              <div class="material-item">
                <div style="font-weight:700"><?php echo htmlspecialchars($m['title']); ?></div>
                <div style="color:var(--text-secondary); margin-top:6px"><?php echo nl2br(htmlspecialchars($m['description'])); ?></div>
                <div style="margin-top:8px; font-size:0.9rem">
                  <?php if (!empty($m['file_path'])): ?>
                    <a href="download.php?resource=material&id=<?php echo intval($m['id']); ?>" target="_blank">Download File</a>
                  <?php endif; ?>
                  <?php if (!empty($m['link'])): ?>
                    <span style="margin-left:10px"><a href="<?php echo htmlspecialchars($m['link']); ?>" target="_blank">Open Link</a></span>
                  <?php endif; ?>
                </div>
                <div style="margin-top:6px; font-size:0.85rem; color:var(--text-secondary)">Posted: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($m['created_at']))); ?> — By: <?php echo htmlspecialchars($m['teacher'] ?: 'Teacher'); ?></div>
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
</body>
</html>
