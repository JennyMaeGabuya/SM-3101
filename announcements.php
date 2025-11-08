<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements - BatStateU Portal</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    <div class="gx-orb gx-orb--small" style="right:8px; bottom:12px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:20px; top:18px; background:rgba(184,134,11,0.05);"></div>
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
          <a href="announcements.php" class="nav-link active">Announcements</a>
          <a href="schedule.php" class="nav-link">Schedule</a>
          <a href="messages.php" class="nav-link">Messages</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
          </button>
          <a href="login.php" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</a>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
      <section class="announcements-section">
        <div class="section-header">
          <h2 class="hero-title gx-parallax gx-reveal section-title">Announcements</h2>
        </div>

        <div class="filter-bar">
          <button class="filter-btn active" data-filter="all">All</button>
          <button class="filter-btn" data-filter="university">University</button>
          <button class="filter-btn" data-filter="course">Course</button>
        </div>

        <div class="search-box">
          <input type="text" id="searchAnnouncements" placeholder="Search announcements..." class="search-input">
          <span class="search-icon">🔍</span>
        </div>

        <div class="announcements-list" id="announcementsList">
          <div class="empty-state">
            <div class="empty-icon">📢</div>
            <h4>No announcements</h4>
          </div>
        </div>
      </section>


  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
