<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Learn Hub - Quiz</title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
  <!-- GX hero inserted for consistent animations across pages (opt-in classes only) -->
  <section class="hero-section" style="position:relative; overflow:visible">
    <div class="hero-content">
      <h2 class="hero-title gx-parallax gx-shimmer gx-reveal">Quiz Center</h2>
      <p class="hero-subtitle gx-reveal" data-gx-delay="120">Test your knowledge with interactive quizzes</p>
      <div style="margin-top:1rem">
        <a href="index.php" class="btn btn-primary gx-magnetic gx-reveal" data-gx-delay="240">Back to Dashboard</a>
      </div>
    </div>
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
  </section>
  <div class="app-container">
    <!-- Navigation Header -->
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo">Learn Hub</h1>
        </div>
        <div class="navbar-menu">
          <a href="index.php" class="nav-link">Dashboard</a>
          <a href="quiz.php" class="nav-link active">Quiz</a>
          <a href="about.php" class="nav-link">Help</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
          </button>
          <a href="login.php" class="btn-logout" id="logoutBtn" title="Logout" data-logout>🚪 Logout</a>
        </div>
      </div>
    </nav>

    <main class="main-content">
      <!-- Quiz Start Screen -->
      <section class="quiz-start" id="quizStart">
        <div class="quiz-hero">
          <h2 class="quiz-title">JavaScript Fundamentals</h2>
          <p class="quiz-description">Test your knowledge of core JavaScript concepts</p>
          <div class="quiz-meta">
            <span class="quiz-stat">10 Questions</span>
            <span class="quiz-stat">5 minutes</span>
            <span class="quiz-stat">Intermediate</span>
          </div>
        </div>
        <button class="btn btn-primary btn-large" id="startQuizBtn">Start Quiz</button>
      </section>

      <!-- Quiz Container -->
      <section class="quiz-container" id="quizContainer" style="display: none;">
        <div class="quiz-header">
          <div class="quiz-progress">
            <span id="currentQuestion">1</span> / <span id="totalQuestions">10</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
          </div>
        </div>

        <div class="question-card">
          <h3 class="question-text" id="questionText"></h3>
          <div class="options-group" id="optionsGroup"></div>
        </div>

        <div class="quiz-actions">
          <button class="btn btn-secondary" id="prevBtn" disabled>Previous</button>
          <button class="btn btn-primary" id="nextBtn">Next</button>
        </div>
      </section>

      <!-- Results Screen -->
      <section class="quiz-results" id="quizResults" style="display: none;">
        <div class="results-card">
          <div class="results-icon">🎉</div>
          <h2 class="results-title">Quiz Complete!</h2>
          
          <div class="score-display">
            <div class="score-number" id="scoreNumber">0</div>
            <div class="score-label">out of 100</div>
          </div>

          <div class="results-summary">
            <div class="summary-item">
              <span class="summary-label">Correct Answers</span>
              <span class="summary-value" id="correctCount">0</span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Total Questions</span>
              <span class="summary-value" id="totalCount">10</span>
            </div>
          </div>

          <div class="results-review" id="resultsReview"></div>

          <div class="results-actions">
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            <button class="btn btn-primary" id="retakeBtn">Retake Quiz</button>
          </div>
        </div>
      </section>

      <!-- GX Animations demo CTA (added) -->
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
