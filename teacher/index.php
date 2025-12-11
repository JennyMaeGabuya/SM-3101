<?php
require_once __DIR__ . '/includes/header.php';

// Simple teacher auth check
if (empty($_SESSION['teacher_id'])) {
  header('Location: ../login.php');
  exit;
}

// Sample stats queries (counts)
$teacher_id = $_SESSION['teacher_id'];
$counts = ['quizzes'=>0,'tasks'=>0,'activities'=>0,'materials'=>0];
if (isset($connection) && $connection) {
  $q = $connection->prepare('SELECT COUNT(*) as c FROM quizzes WHERE teacher_id = ?');
  if ($q) { $q->bind_param('s', $teacher_id); $q->execute(); $r=$q->get_result()->fetch_assoc(); $counts['quizzes'] = $r['c'] ?? 0; $q->close(); }
  $q = $connection->prepare('SELECT COUNT(*) as c FROM performance_tasks WHERE teacher_id = ?');
  if ($q) { $q->bind_param('s', $teacher_id); $q->execute(); $r=$q->get_result()->fetch_assoc(); $counts['tasks'] = $r['c'] ?? 0; $q->close(); }
  $q = $connection->prepare('SELECT COUNT(*) as c FROM activities WHERE teacher_id = ?');
  if ($q) { $q->bind_param('s', $teacher_id); $q->execute(); $r=$q->get_result()->fetch_assoc(); $counts['activities'] = $r['c'] ?? 0; $q->close(); }
  $q = $connection->prepare('SELECT COUNT(*) as c FROM learning_materials WHERE teacher_id = ?');
  if ($q) { $q->bind_param('s', $teacher_id); $q->execute(); $r=$q->get_result()->fetch_assoc(); $counts['materials'] = $r['c'] ?? 0; $q->close(); }
}

?>

<section class="hero-section">
  <div class="hero-content">
    <h1 class="hero-title gx-shimmer ">Good day, <?php echo htmlspecialchars($teacherName ?? 'Teacher'); ?></h1>
    <p class="hero-subtitle">Manage your quizzes, tasks, activities, resources, and student submissions from one place.</p>
  </div>
  <div class="hero-orb orb-1" aria-hidden="true"></div>
  <div class="hero-orb orb-2" aria-hidden="true"></div>
  <div class="site-clock hero-clock" aria-hidden="true">
    <div class="clock-wrapper"><div class="time-boxes"><div class="box hour" id="hero-hour">--</div><div class="sep">:</div><div class="box minute" id="hero-minute">--</div></div></div>
  </div>
</section>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon due">📚</div>
    <div>
      <div class="stat-label">Upload Quizzes</div>
      <div class="stat-value"><?php echo (int)$counts['quizzes']; ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon completed">📝</div>
    <div>
      <div class="stat-label">Performance Tasks</div>
      <div class="stat-value"><?php echo (int)$counts['tasks']; ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon courses">📂</div>
    <div>
      <div class="stat-label">Learning Materials</div>
      <div class="stat-value"><?php echo (int)$counts['materials']; ?></div>
    </div>
  </div>
</div>

<div class="announcements-section">
  <div class="section-header">
    <div class="section-title">Recent Announcements</div>
    <div><a class="btn btn-secondary" href="messages.php">View All</a></div>
  </div>
  <div class="announcements-list">
    <?php
    // show last 5 notifications by this teacher
    if (isset($connection) && $connection) {
        $s = $connection->prepare('SELECT title, message, created_at FROM notifications WHERE sender_id = ? ORDER BY created_at DESC LIMIT 5');
        if ($s) { $s->bind_param('s', $teacher_id); $s->execute(); $res=$s->get_result(); while($n=$res->fetch_assoc()) {
            echo '<div class="announcement-item"><div class="announcement-header"><div><strong>'.htmlspecialchars($n['title']).'</strong></div><div class="announcement-date">'.htmlspecialchars($n['created_at']).'</div></div><div class="announcement-content">'.nl2br(htmlspecialchars($n['message'])).'</div></div>';
        } $s->close(); }
    } else { echo '<div class="empty-state">No announcements yet.</div>'; }
    ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
