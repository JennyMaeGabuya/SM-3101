<?php
include 'connection/dbsConnection.php';
// debug echo removed to avoid accidental output before HTML
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BatStateU - Student Portal Dashboard</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
  
 
  <div id="pageTransitionOverlay" class="page-transition-overlay pt-enter" aria-hidden="true"></div>
  <div class="app-container">
  
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img"> BatStateU Portal</h1>
        </div>
        <div class="navbar-menu">
          <a href="index.php" class="nav-link active">Dashboard</a>
          <a href="courses.php" class="nav-link">Courses</a>
          <a href="assignments.php" class="nav-link">Assignments</a>
          <a href="grades.php" class="nav-link">Grades</a>
          <a href="announcements.php" class="nav-link">Announcements</a>
          <a href="schedule.php" class="nav-link">Schedule</a>
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
      
      <section class="hero-section" style="position:relative; overflow:visible">
        <div class="hero-content">
          
          <h2 class="hero-title gx-parallax gx-shimmer gx-reveal no-hide" id="greeting">Welcome back, Student</h2>
          
          <p class="hero-subtitle gx-reveal" data-gx-delay="120" id="userInfo">Batangas State University - Student Portal</p>
          
          <div style="margin-top:1rem">
            <a href="#" class="btn btn-primary gx-magnetic gx-reveal" data-gx-delay="240">ANU ANU</a>
          </div>
      </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
   
    <div class="gx-orb gx-orb--small" style="right:16px; bottom:6px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:6px; top:12px; background:rgba(184,134,11,0.05);"></div>
  </section>

      
      <section class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon courses">📚</div>
          <div class="stat-info">
            <p class="stat-label">Enrolled Courses</p>
            <p class="stat-value" id="coursesCount">0</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon due">📋</div>
          <div class="stat-info">
            <p class="stat-label">Assignments Due</p>
            <p class="stat-value" id="dueSoon">0</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon completed">✓</div>
          <div class="stat-info">
            <p class="stat-label">Current GPA</p>
            <p class="stat-value" id="gpaDisplay">3.5</p>
          </div>
        </div>
      </section>

     
      <section class="announcements-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Recent Announcements</h3>
          <a href="announcements.php" class="btn btn-primary">View All</a>
        </div>
        <div class="announcements-list" id="announcementsList">
          <div class="empty-state">
            <div class="empty-icon">📢</div>
            <h4>No announcements</h4>
          </div>
        </div>
      </section>

      
      <section class="schedule-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Today's Classes</h3>
          <a href="schedule.php" class="btn btn-primary">Full Schedule</a>
        </div>
        <div class="schedule-grid" id="todaySchedule">
          <div class="empty-state">
            <div class="empty-icon">🕐</div>
            <h4>No classes today</h4>
          </div>
        </div>
      </section>

      
      <section class="assignments-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Upcoming Assignments</h3>
          <a href="assignments.php" class="btn btn-primary">View All</a>
        </div>
        <div class="assignments-list" id="recentAssignments">
          <div class="empty-state">
            <div class="empty-icon">✍️</div>
            <h4>No assignments</h4>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="settings.js"></script>
  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
