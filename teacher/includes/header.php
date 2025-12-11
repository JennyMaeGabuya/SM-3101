<?php
require_once __DIR__ . '/db.php';

// Fetch teacher info if available
$teacherName = null;
$teacherInitials = 'T';
if (!empty($_SESSION['teacher_id']) && isset($connection) && $connection) {
  $tid = $_SESSION['teacher_id'];
  // Detect if an `id` column exists in the teachers table to avoid referencing unknown columns
  $hasId = false;
  $col = $connection->prepare("SHOW COLUMNS FROM teachers LIKE 'id'");
  if ($col) {
    $col->execute();
    $cres = $col->get_result();
    if ($cres && $cres->num_rows > 0) $hasId = true;
    $col->close();
  }

  // Try to find the teacher by teacher_id (and id if available). Bind as strings to be permissive.
  if ($hasId) {
    $stmt = $connection->prepare('SELECT * FROM teachers WHERE teacher_id = ? OR id = ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('ss', $tid, $tid);
      $stmt->execute();
      $res = $stmt->get_result();
      $t = $res->fetch_assoc();
      $stmt->close();
    } else {
      $t = null;
    }
  } else {
    $stmt = $connection->prepare('SELECT * FROM teachers WHERE teacher_id = ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('s', $tid);
      $stmt->execute();
      $res = $stmt->get_result();
      $t = $res->fetch_assoc();
      $stmt->close();
    } else {
      $t = null;
    }
  }

  if (!empty($t)) {
    // build a display name using available columns
    $nameParts = array_filter([($t['first_name'] ?? ''), ($t['last_name'] ?? '')]);
    $teacherName = $nameParts ? implode(' ', $nameParts) : ($t['full_name'] ?? ($t['email'] ?? 'Teacher'));
    // compute initials
    $parts = preg_split('/\s+/', trim($teacherName));
    $first = strtoupper($parts[0][0] ?? 'T');
    $second = strtoupper($parts[1][0] ?? '');
    $teacherInitials = substr($first . $second, 0, 2);
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Teacher Dashboard</title>
  <!-- Use main site styles for consistent UI -->
  <link rel="stylesheet" href="/LearnHub/styles.css">
  <!-- fallback relative path in case LearnHub is served from a different root -->
  <link rel="stylesheet" href="../styles.css">
  <!-- Teacher overrides (local path) -->
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <!-- fallback absolute teacher path -->
  <link rel="stylesheet" href="/LearnHub/teacher/assets/css/dashboard.css">
  <!-- GX animations (site) -->
  <link rel="stylesheet" href="/LearnHub/css/gx-animations.css">
  <link rel="stylesheet" href="../css/gx-animations.css">
</head>
<body class="app-container">
  <header class="navbar">
    <div class="navbar-content">
      <div class="brand-logo">
        <img src="/LearnHub/assets/BatStateU-NEU-Logo-1-300x282.png" alt="logo" class="brand-logo-img">
        <span>LearnHub - Teacher</span>
      </div>
        <div class="navbar-menu">
        <a class="nav-link" href="index.php">Dashboard</a>
        <a class="nav-link" href="quizzes.php">Add Quizzes</a>
        <a class="nav-link" href="tasks.php">Add Performance Tasks</a>
        <a class="nav-link" href="activities.php">Add Activities</a>
        <a class="nav-link" href="materials.php">Add Learning Materials</a>
  <a class="nav-link" href="submissions.php">Submissions</a>
        <a class="nav-link" href="messages.php">Messages</a>
        </div>
      <div style="margin-left:auto; display:flex; align-items:center; gap:12px">
        <div class="profile-container">
          
          <?php
          // Show uploaded avatar if available, otherwise initials
          $avatarUrl = null;
          if (!empty($t) && !empty($t['avatar'])) {
            $avatarFile = __DIR__ . '/../../public/uploads/teacher/' . $t['avatar'];
            if (file_exists($avatarFile)) {
              $avatarUrl = '/LearnHub/public/uploads/teacher/' . rawurlencode($t['avatar']);
            }
          }
          ?>
          <div style="position:relative; z-index:60">
            <button id="profileBtn" class="profile-button" type="button" aria-haspopup="true" aria-expanded="false" style="display:flex;align-items:center;gap:10px; background:transparent; border:none; cursor:pointer;">
              <?php if ($avatarUrl): ?>
                <img src="<?php echo $avatarUrl; ?>" alt="avatar" class="profile-avatar-img" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" />
              <?php else: ?>
                <span class="profile-avatar" style="background:var(--primary-gold);"><?php echo htmlspecialchars($teacherInitials); ?></span>
              <?php endif; ?>
              <span class="profile-unsigned"><?php echo htmlspecialchars($teacherName ?? 'Teacher'); ?></span>
            </button>
            <div id="profileMenu" style="display:none; position:absolute; right:0; margin-top:10px; background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:8px; padding:8px; min-width:180px; box-shadow:var(--shadow-sm);">
              <a class="nav-link" href="profile.php" style="display:block;margin:6px 0">View Profile</a>
              <a class="nav-link" href="/LearnHub/logout.php" style="display:block;margin:6px 0">Logout</a>
            </div>
          </div>
          <!-- Fallback toggle script: ensures profile menu works even if external JS fails -->
          <script>
            (function(){
              try {
                var btn = document.getElementById('profileBtn');
                var menu = document.getElementById('profileMenu');
                if (!btn || !menu) return;
                function closeMenu(){ menu.style.display = 'none'; btn.setAttribute('aria-expanded','false'); }
                function openMenu(){ menu.style.display = 'block'; btn.setAttribute('aria-expanded','true'); }
                btn.addEventListener('click', function(e){ e.stopPropagation(); if (menu.style.display === 'block') closeMenu(); else openMenu(); });
                document.addEventListener('click', function(e){ if (!menu.contains(e.target) && e.target !== btn) closeMenu(); });
                document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMenu(); });
              } catch (err) { /* no-op fallback */ }
            })();
          </script>

          <!-- Visible logout fallback for teacher pages (helps if menu is inaccessible) -->
          <a href="/LearnHub/logout.php" class="btn-logout" style="margin-left:8px; text-decoration:none;">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Global orbs visible across teacher pages -->
  <div class="global-orbs" aria-hidden="true">
    <div class="hero-orb orb-1"></div>
    <div class="hero-orb orb-2"></div>
    <div class="hero-orb orb-3"></div>
  </div>

  <!-- Page fade overlay (used during navigation) -->
  <div class="page-fade-overlay" id="pageFadeOverlay" aria-hidden="true"></div>

  <div class="main-wrapper" style="display:flex;">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content" style="flex:1">
      <?php
      // Flash messages (show session or query errors/success) to teachers
      if (session_status() === PHP_SESSION_NONE) session_start();
      $flashError = $_SESSION['error'] ?? null;
      $flashSuccess = $_SESSION['success'] ?? null;
      // also check URL params
      if (isset($_GET['error']) && ! $flashError) $flashError = htmlspecialchars($_GET['error']);
      if (isset($_GET['success']) && ! $flashSuccess) $flashSuccess = htmlspecialchars($_GET['success']);
      if ($flashError): ?>
        <div class="card" style="border-left:4px solid var(--accent-danger); background: rgba(239,68,68,0.06); padding:12px; margin:12px 0; color:var(--text-primary)">
          <strong>Error</strong>
          <div style="margin-top:6px;color:var(--text-secondary)"><?php echo htmlspecialchars($flashError); ?></div>
        </div>
      <?php unset($_SESSION['error']); endif; ?>
      <?php if ($flashSuccess): ?>
        <div class="card" style="border-left:4px solid var(--primary-red); background: rgba(196,30,58,0.04); padding:12px; margin:12px 0; color:var(--text-primary)">
          <strong>Success</strong>
          <div style="margin-top:6px;color:var(--text-secondary)"><?php echo htmlspecialchars($flashSuccess); ?></div>
        </div>
      <?php unset($_SESSION['success']); endif; ?>
