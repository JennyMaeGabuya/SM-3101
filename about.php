<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Learn Hub - Help & About</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
  <section class="hero-section" style="position:relative; overflow:visible">
    <div class="hero-content">
      <h2 class="hero-title gx-parallax gx-shimmer gx-reveal">Help & Documentation</h2>
      <p class="hero-subtitle gx-reveal" data-gx-delay="120">Learn how to use the portal effectively</p>
      <div style="margin-top:1rem">
        <a href="animations-demo.php" class="btn btn-primary gx-magnetic gx-reveal" data-gx-delay="240">View animations demo</a>
      </div>
    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    <div class="gx-orb gx-orb--small" style="right:12px; bottom:8px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:14px; top:16px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo">Learn Hub</h1>
        </div>
        <div class="navbar-menu">
          <a href="index.php" class="nav-link">Dashboard</a>
          <a href="quiz.php" class="nav-link">Quiz</a>
          <a href="about.php" class="nav-link active">Help</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
          </button>
          <button type="button" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</button>
        </div>
      </div>
    </nav>

    <main class="main-content">
      <section class="about-section">
        <div class="about-hero">
          <h2 class="about-title">Help & Documentation</h2>
          <p class="about-subtitle">Learn how to use Learn Hub effectively</p>
        </div>

        <div class="help-grid">
          <div class="help-card">
            <div class="help-icon">📌</div>
            <h3 class="help-title">Getting Started</h3>
            <p class="help-text">Create activities, track your progress, and stay motivated with daily streaks. All your data is saved locally in your browser.</p>
          </div>

          <div class="help-card">
            <div class="help-icon">✏️</div>
            <h3 class="help-title">Managing Activities</h3>
            <p class="help-text">Add new learning activities, set due dates, and mark them as complete. Filter by status and search to find activities quickly.</p>
          </div>

          <div class="help-card">
            <div class="help-icon">🧠</div>
            <h3 class="help-title">Taking Quizzes</h3>
            <p class="help-text">Test your knowledge with interactive quizzes. Get instant feedback and review your answers to improve learning.</p>
          </div>

          <div class="help-card">
            <div class="help-icon">💾</div>
            <h3 class="help-title">Data Storage</h3>
            <p class="help-text">Learn Hub uses browser localStorage to save your data. Your information stays private and is never sent to a server.</p>
          </div>

          <div class="help-card">
            <div class="help-icon">🎨</div>
            <h3 class="help-title">Theme Settings</h3>
            <p class="help-text">Toggle between dark and light modes for comfortable viewing. Your preference is saved automatically.</p>
          </div>

          <div class="help-card">
            <div class="help-icon">⌨️</div>
            <h3 class="help-title">Keyboard Shortcuts</h3>
            <p class="help-text">Press 'A' to add a new activity, 'S' to search, and 'T' to toggle theme from anywhere in the app.</p>
          </div>
        </div>

        <div class="keyboard-shortcuts">
          <h3 class="section-title">Keyboard Shortcuts</h3>
          <div class="shortcuts-list">
            <div class="shortcut-item">
              <kbd>A</kbd>
              <span>Add Activity</span>
            </div>
            <div class="shortcut-item">
              <kbd>S</kbd>
              <span>Search</span>
            </div>
            <div class="shortcut-item">
              <kbd>T</kbd>
              <span>Toggle Theme</span>
            </div>
            <div class="shortcut-item">
              <kbd>Esc</kbd>
              <span>Close Modal</span>
            </div>
          </div>
        </div>
      </section>

      <section class="gx-demo-cta" aria-label="Animations demo" style="margin-top:1.25rem">
        <div class="section-header">
          <h3 class="section-title">Motion demo</h3>
          <a href="animations-demo.php" class="btn btn-secondary gx-reveal" data-gx-delay="80">View animations demo</a>
        </div>
      </section>
    </main>
  </div>

  <script src="auth.js"></script>
  <script src="app.js"></script>
</body>
</html>
