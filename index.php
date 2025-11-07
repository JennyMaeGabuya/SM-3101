<?php
include 'connection/dbsConnection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BatStateU - Student Portal Dashboard</title>
  <link rel="stylesheet" href="styles/styles.css?v=2">
</head>
<body>
  <!-- Page transition overlay: present in DOM so initial load animation covers FOUC -->
  <div id="pageTransitionOverlay" class="page-transition-overlay pt-enter" aria-hidden="true"></div>
  <div class="app-container">
    <!-- Navigation Header -->
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img"> BatStateU Portal</h1>
        </div>
        <div class="navbar-menu">
          <a href="index.html" class="nav-link active">Dashboard</a>
          <a href="courses.html" class="nav-link">Courses</a>
          <a href="assignments.html" class="nav-link">Assignments</a>
          <a href="grades.html" class="nav-link">Grades</a>
          <a href="announcements.html" class="nav-link">Announcements</a>
          <a href="schedule.html" class="nav-link">Schedule</a>
          <a href="messages.html" class="nav-link">Messages</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
          </button>
          <!-- Added logout button -->
          <a href="login.html" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</a>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Hero Section -->
      <section class="hero-section" style="position:relative; overflow:visible">
        <div class="hero-content">
          <!-- Example: parallax + shimmer title (opt-in) -->
          <h2 class="hero-title gx-parallax gx-shimmer gx-reveal no-hide" id="greeting">Welcome back, Student</h2>
          <!-- Added user info display -->
          <p class="hero-subtitle gx-reveal" data-gx-delay="120" id="userInfo">Batangas State University - Student Portal</p>
          <!-- CTA example: magnetic hover + reveal -->
          <div style="margin-top:1rem">
            <a href="#" class="btn btn-primary gx-magnetic gx-reveal" data-gx-delay="240">ANU ANU</a>
          </div>
        </div>
        <!-- Optional ambient orbs (pure CSS classes). Position as needed in markup. -->
        <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
        <!-- Additional ambient orbs -->
        <div class="gx-orb gx-orb--small" style="right:20px; bottom:10px; background:rgba(196,30,58,0.06);"></div>
        <div class="gx-orb gx-orb--soft" style="left:10px; top:20px; background:rgba(184,134,11,0.05);"></div>
      </section>

      <!-- Stats Grid -->
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

      <!-- Announcements Section -->
      <section class="announcements-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Recent Announcements</h3>
          <a href="announcements.html" class="btn btn-primary">View All</a>
        </div>
        <div class="announcements-list" id="announcementsList">
          <div class="empty-state">
            <div class="empty-icon">📢</div>
            <h4>No announcements</h4>
          </div>
        </div>
      </section>

      <!-- Upcoming Classes -->
      <section class="schedule-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Today's Classes</h3>
          <a href="schedule.html" class="btn btn-primary">Full Schedule</a>
        </div>
        <div class="schedule-grid" id="todaySchedule">
          <div class="empty-state">
            <div class="empty-icon">🕐</div>
            <h4>No classes today</h4>
          </div>
        </div>
      </section>

      <!-- Recent Assignments -->
      <section class="assignments-section">
        <div class="section-header">
          <h3 class="hero-title gx-parallax gx-reveal section-title">Upcoming Assignments</h3>
          <a href="assignments.html" class="btn btn-primary">View All</a>
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

  <script src="auth.js"></script>
  <script src="portal-data.js"></script>
  <script src="app.js"></script>
</body>
</html>
