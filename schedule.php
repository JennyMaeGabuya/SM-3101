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
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img"> BatStateU Portal</h1>
        </div>
        <div class="navbar-menu">
          <a href="index.php" class="nav-link">Dashboard</a>
          <a href="courses.php" class="nav-link">Courses</a>
          <a href="assignments.php" class="nav-link">Assignments</a>
          <a href="grades.php" class="nav-link">Grades</a>
          <a href="announcements.php" class="nav-link">Announcements</a>
          <a href="schedule.php" class="nav-link active">Schedule</a>
          <a href="messages.php" class="nav-link">Messages</a>
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
          <h2 class="section-title">Class Schedule</h2>
        </div>

        <div class="filter-bar">
          <button class="filter-btn active" data-filter="week">This Week</button>
          <button class="filter-btn" data-filter="day">Today</button>
          <button class="filter-btn" data-filter="all">All</button>
        </div>

        <div class="schedule-grid" id="scheduleList">
          <div class="empty-state">
            <div class="empty-icon">📅</div>
            <h4>No classes scheduled</h4>
          </div>
        </div>
      </section>


  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
