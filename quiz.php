<?php
// ensure session is started before any output to avoid "headers already sent" warnings
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Learn Hub - Quiz</title>
  <link rel="stylesheet" href="styles.css?v=2">
  <link rel="stylesheet" href="css/gx-animations.css">
  <style>
    
    /* Local quiz page overrides to improve look */
    .quiz-hero { padding: 32px 18px; border-radius: 10px; background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent); display:block; position:relative; overflow:hidden }
    .quiz-hero-main .quiz-title { font-size: 1.6rem; margin:0 0 8px 0; color:var(--text-primary); }
    .quiz-hero-main .quiz-description { color:var(--text-secondary); margin:0; }
    .quiz-hero-meta { box-shadow: 0 8px 24px rgba(0,0,0,0.45); position:relative; overflow:visible; flex:0 0 300px; width:300px; background:var(--bg-secondary); border:1px solid var(--border-color); padding:18px; border-radius:12px; }
    .quiz-hero-main { flex:1; }
    .quiz-hero-meta .quiz-meta-item { color:var(--text-primary); }
    .btn-large { padding: 10px 18px; font-size:1rem; border-radius:8px; }
    .btn-primary { background: linear-gradient(180deg,#e53950,#c12739); color: #fff; border: 0; }
    .btn-primary:disabled { opacity:0.6; cursor:not-allowed }
    .quiz-container { max-width:1100px; margin:24px auto; }
    .question-card { background: var(--bg-secondary); padding:18px; border-radius:12px; box-shadow: 0 6px 18px rgba(0,0,0,0.4); }
    .option-item { display:flex; align-items:center; gap:10px; margin:8px 0; }
    .option-item input { width:18px; height:18px; }
    .progress-bar { height:8px; background:rgba(255,255,255,0.04); border-radius:8px; overflow:hidden; }
    .progress-fill { height:100%; width:0; background:linear-gradient(90deg,#ff6b6b,#ff3b3b); transition:width 260ms ease; }
    .empty-state { text-align:center; color:var(--text-secondary); padding:28px; }

    /* Start modal */
    .lh-modal { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.6); z-index:99998; }
    .lh-modal-card { width:360px; max-width:92%; background:var(--bg-primary); border-radius:12px; padding:18px; box-shadow:0 12px 36px rgba(0,0,0,0.6); text-align:center; }
    .lh-modal-card h3 { margin:0 0 8px 0; }
    .lh-modal-actions { display:flex; gap:10px; justify-content:center; margin-top:12px }
    .result-review-item { padding:10px;border-radius:8px;margin:8px 0;background:rgba(255,255,255,0.02); }
    /* Orb helper overrides so the orbs sit nicely in the hero */
    .quiz-hero .gx-orb { transform: translate3d(0,0,0); transition: transform 900ms ease; }
    .quiz-hero .gx-orb--soft { filter: blur(40px); opacity:0.95 }
    .quiz-hero .gx-orb--small { filter: blur(28px); opacity:0.9 }
  </style>
</head>
<body>
  
    </div>
     <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
        <div class="gx-orb gx-orb--small" style="right:20px; bottom:10px; background:rgba(196,30,58,0.06);"></div>
        <div class="gx-orb gx-orb--soft" style="left:10px; top:20px; background:rgba(184,134,11,0.05);"></div>
  </section>
<body>
  <div class="app-container">
     <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img">  Student Portal</h1>
        </div>
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
      <?php
      include_once 'connection/dbsConnection.php';
      $quiz_meta = null;
      $questions = [];
      $quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
      if ($quiz_id && isset($connection) && $connection) {
        try {
          // include max_attempts when present (backwards-compatible)
          $qs = $connection->prepare('SELECT id,title,description,scheduled_at,deadline, COALESCE(max_attempts,2) AS max_attempts, duration_minutes FROM quizzes WHERE id = ? LIMIT 1');
          if ($qs) { $qs->bind_param('i',$quiz_id); $qs->execute(); $r = $qs->get_result(); if ($r) { $quiz_meta = $r->fetch_assoc(); $r->close(); } $qs->close(); }
          if ($quiz_meta) {
            // detect available columns and build a compatible SELECT (handle 'question_type' vs 'type')
            $existingCols = [];
            try {
              $colRes = $connection->query("SHOW COLUMNS FROM quiz_questions");
              if ($colRes) { while ($crow = $colRes->fetch_assoc()) { $existingCols[] = $crow['Field']; } $colRes->close(); }
            } catch (Exception $e) { /* ignore and fall back to default */ }

            $selectCols = ['id', 'question_text'];
            if (in_array('question_type', $existingCols)) {
              $selectCols[] = 'question_type';
            } elseif (in_array('type', $existingCols)) {
              // alias older 'type' column to expected name
              $selectCols[] = 'type AS question_type';
            }
            if (in_array('options', $existingCols)) $selectCols[] = 'options';
            if (in_array('correct_answer', $existingCols)) $selectCols[] = 'correct_answer';

            $sql = 'SELECT '.implode(',', $selectCols).' FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC';
            $qst = $connection->prepare($sql);
            if ($qst) {
              $qst->bind_param('i', $quiz_id);
              $qst->execute();
              $resq = $qst->get_result();
              if ($resq) {
                while ($row = $resq->fetch_assoc()) {
                  // normalize options into options_arr for MCQ rendering
                  if (!empty($row['options'])) {
                    $decoded = json_decode($row['options'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $row['options_arr'] = $decoded;
                    else {
                      // maybe newline separated
                      $row['options_arr'] = preg_split('/\r?\n/', $row['options']);
                    }
                  }
                  // ensure question_type exists (fallback to 'mcq')
                  if (empty($row['question_type'])) $row['question_type'] = 'mcq';
                  $questions[] = $row;
                }
                $resq->close();
              }
              $qst->close();
            }
          }
          // determine how many attempts the current student has already made (used to disable Start button)
          $attempt_count = 0;
          $max_attempts = 2;
          if (!empty($quiz_meta) && isset($quiz_meta['max_attempts'])) {
            $max_attempts = intval($quiz_meta['max_attempts']);
          }
          if (!empty($quiz_id) && !empty($_SESSION['user_id']) && isset($connection) && $connection) {
            try {
              $countStmt = $connection->prepare('SELECT COUNT(*) AS c FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?');
              if ($countStmt) { $uid = intval($_SESSION['user_id']); $countStmt->bind_param('ii',$quiz_id,$uid); $countStmt->execute(); $rc = $countStmt->get_result(); if ($rc) { $crow = $rc->fetch_assoc(); $attempt_count = intval($crow['c'] ?? 0); $rc->close(); } $countStmt->close(); }
            } catch (Exception $e) { /* ignore */ }
          }
          $can_start = (count($questions) > 0 && $attempt_count < $max_attempts);
        } catch (Exception $e) { error_log('Load quiz failed: '.$e->getMessage()); }
      }
              // Debug helper: show DB debug when ?debug=1 is present (local dev only)
              if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                echo '<div style="max-width:1100px;margin:12px auto;padding:12px;border:1px solid #a00;background:rgba(160,0,0,0.04);border-radius:8px;color:var(--text-primary)">';
                echo '<strong>DEBUG INFO</strong><br/>';
                echo 'Questions fetched by PHP: ' . intval(count($questions)) . '<br/>';
                // show last DB error if any
                if (isset($connection) && $connection) {
                  $err = $connection->error ?: '(no connection error)';
                  echo 'DB error: ' . htmlspecialchars($err) . '<br/>';
                  // show direct count from DB
                  $qid = intval($quiz_id);
                  $cnt = null;
                  $r = $connection->query("SELECT COUNT(*) AS c FROM quiz_questions WHERE quiz_id = " . $qid);
                  if ($r) { $row = $r->fetch_assoc(); $cnt = $row['c']; $r->close(); }
                  echo 'DB count for quiz_id=' . $qid . ' : ' . intval($cnt) . '<br/>';
                  // show sample rows
                  $sample = $connection->query("SELECT id, quiz_id, question_text, options, correct_answer FROM quiz_questions WHERE quiz_id = " . $qid . " LIMIT 5");
                  if ($sample && $sample->num_rows>0) {
                    echo '<pre style="color:var(--text-secondary);background:transparent;border:0;margin-top:8px">';
                    while($sr = $sample->fetch_assoc()) { echo htmlspecialchars(print_r($sr, true)) . "\n"; }
                    echo '</pre>';
                    $sample->close();
                  } else {
                    echo 'No sample rows returned from DB for this quiz id.<br/>';
                  }
                }
                echo '</div>';
              }
      ?>
      <section class="quiz-start" id="quizStart">
        <div class="quiz-hero gx-reveal gx-parallax" style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap" data-gx-delay="80">
          <!-- Quiz meta moved to the left for a clearer layout -->
          <aside class="quiz-hero-meta gx-magnetic" data-gx-delay="120">
            <!-- small ambient orb inside the meta card -->
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    <div class="gx-orb gx-orb--small" style="right:10px; bottom:14px; background:rgba(196,30,58,0.06);"></div>
    <div class="gx-orb gx-orb--soft" style="left:8px; top:22px; background:rgba(184,134,11,0.05);"></div>
            <div class="gx-orb gx-orb--small" style="position:absolute;right:-28px;top:-18px;background:var(--gx-orb-1);width:140px;height:140px;"></div>
            <div style="font-weight:700; margin-bottom:8px">Quiz Details</div>
            <div class="quiz-meta-item" style="margin-bottom:6px"><strong><?php echo count($questions); ?></strong> Questions</div>
            <div class="quiz-meta-item" style="margin-bottom:6px"><?php echo !empty($quiz_meta['scheduled_at']) ? 'Scheduled: '.htmlspecialchars($quiz_meta['scheduled_at']) : ''; ?></div>
            <?php if (!empty($quiz_meta['duration_minutes'])): ?>
              <div class="quiz-meta-item" style="margin-bottom:6px">Duration: <strong><?php echo intval($quiz_meta['duration_minutes']); ?></strong> minute<?php echo intval($quiz_meta['duration_minutes']) !== 1 ? 's' : ''; ?></div>
            <?php endif; ?>
            <div class="quiz-meta-item" style="margin-bottom:12px"><?php echo !empty($quiz_meta['deadline']) ? 'Deadline: '.htmlspecialchars($quiz_meta['deadline']) : 'No deadline'; ?></div>
            <?php if ($can_start): ?>
              <button class="btn btn-primary btn-large" id="startQuizBtn">Start Quiz</button>
            <?php else: ?>
              <button class="btn btn-primary btn-large" disabled id="startQuizBtn">Start Quiz</button>
              <div style="margin-top:8px;color:var(--text-secondary);font-size:0.9rem">
                <?php if (count($questions) === 0): ?>
                  This quiz has no questions yet. Please check back later.
                <?php elseif ($attempt_count >= $max_attempts): ?>
                  You have used all <?php echo intval($max_attempts); ?> attempt<?php echo $max_attempts>1 ? 's' : ''; ?> for this quiz.
                <?php else: ?>
                  Start is not available.
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </aside>
          <div class="quiz-hero-main" style="flex:1;min-width:260px">
            <h2 class="quiz-title"><?php echo htmlspecialchars($quiz_meta['title'] ?? 'Quiz Center'); ?></h2>
            <p class="quiz-description"><?php echo htmlspecialchars($quiz_meta['description'] ?? 'Select a quiz to begin.'); ?></p>
          </div>
          <!-- Start confirmation modal (hidden by default) -->
          <div id="startModal" style="display:none" aria-hidden="true">
            <div class="lh-modal" id="lhModalRoot" role="dialog" aria-modal="true">
              <div class="lh-modal-card">
                <h3>Ready to begin?</h3>
                <p style="color:var(--text-secondary);">This quiz has <strong><?php echo count($questions); ?></strong> question<?php echo count($questions)!=1 ? 's' : ''; ?>. You have <strong><?php echo max(0, $max_attempts - $attempt_count); ?></strong> attempt<?php echo ($max_attempts - $attempt_count)!=1 ? 's' : ''; ?> left.</p>
                <div class="lh-modal-actions">
                  <button class="btn btn-secondary" id="cancelStartBtn">Cancel</button>
                  <button class="btn btn-primary" id="confirmStartBtn">Start Quiz</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

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

      <?php
      // Student attempt history for this quiz
      if (!empty($quiz_id) && session_status() === PHP_SESSION_NONE) session_start();
      $attempts = [];
      if (!empty($quiz_id) && !empty($_SESSION['user_id']) && isset($connection) && $connection) {
        try {
          $stmt = $connection->prepare('SELECT id, answers, score, submitted_at FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY submitted_at DESC LIMIT 20');
          if ($stmt) { $uid = intval($_SESSION['user_id']); $stmt->bind_param('ii',$quiz_id,$uid); $stmt->execute(); $res = $stmt->get_result(); if ($res) { while($row = $res->fetch_assoc()) $attempts[] = $row; $res->close(); } $stmt->close(); }
        } catch (Exception $e) { error_log('Fetch attempts failed: '.$e->getMessage()); }
      }
      ?>

      <section class="quiz-attempts" id="quizAttempts" style="margin-top:18px">
        <div class="section-header"><h3 class="section-title">Your Attempts</h3></div>
        <?php if (empty($attempts)): ?>
          <div class="empty-state">No attempts yet.</div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($attempts as $a): ?>
              <div style="padding:12px;border:1px solid var(--border-color);background:var(--bg-secondary);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div style="font-weight:700">Score: <?php echo htmlspecialchars($a['score'] ?? 'N/A'); ?></div>
                  <div style="color:var(--text-secondary);font-size:0.9rem">Submitted: <?php echo htmlspecialchars($a['submitted_at']); ?></div>
                </div>
                <div>
                  <?php $ansJson = htmlspecialchars(json_encode($a['answers'] ?? []), ENT_QUOTES); ?>
                  <button class="btn btn-secondary" data-answers='<?php echo $ansJson; ?>' onclick="(function(){ var raw = this.getAttribute('data-answers'); try{ var obj = JSON.parse(raw); var w = window.open('', '_blank'); w.document.write('<pre>'+JSON.stringify(obj, null, 2)+'</pre>'); }catch(e){ alert('Could not parse answers'); } }).call(this);">View Answers</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

  <script src="auth.js"></script>
  <script src="js/gx-animations.js" defer></script>
  <script src="app.js"></script>
  <script>
    window.__quizData = <?php echo json_encode(array('quiz'=> $quiz_meta, 'questions'=>$questions), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
    window.__quizAttempts = <?php echo json_encode(array('attempt_count'=>$attempt_count, 'max_attempts'=>$max_attempts), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

    (function(){
      var data = window.__quizData || {};
      var questions = data.questions || [];
      var current = 0; var answers = {};
      var startBtn = document.getElementById('startQuizBtn');
      var quizStart = document.getElementById('quizStart');
      var quizContainer = document.getElementById('quizContainer');
      var questionText = document.getElementById('questionText');
      var optionsGroup = document.getElementById('optionsGroup');
      var totalQuestions = document.getElementById('totalQuestions');
      var currentQuestion = document.getElementById('currentQuestion');
      var prevBtn = document.getElementById('prevBtn');
      var nextBtn = document.getElementById('nextBtn');
      var progressFill = document.getElementById('progressFill');
      var quizResults = document.getElementById('quizResults');
      var timerDisplay = null;
      var quizTimer = { remaining: 0, interval: null };

      function renderQuestion(i){
        var q = questions[i];
        if (!q) return;
        currentQuestion.textContent = i+1;
        totalQuestions.textContent = questions.length;
        questionText.innerHTML = q.question_text;
        optionsGroup.innerHTML = '';
        if (q.question_type === 'mcq') {
          var opts = q.options_arr || [];
          opts.forEach(function(opt, idx){
            var id = 'opt_' + q.id + '_' + idx;
            var div = document.createElement('div'); div.className='option-item';
            var input = document.createElement('input'); input.type='radio'; input.name='q_'+q.id; input.id=id; input.value=opt;
            if (answers[q.id] && answers[q.id] === opt) input.checked = true;
            var label = document.createElement('label'); label.htmlFor=id; label.textContent = opt;
            div.appendChild(input); div.appendChild(label);
            optionsGroup.appendChild(div);
          });
        } else {
          var ta = document.createElement('textarea'); ta.name='q_'+q.id; ta.rows=3; ta.className='fill-answer';
          if (answers[q.id]) ta.value = answers[q.id];
          optionsGroup.appendChild(ta);
        }
        progressFill.style.width = Math.round(((i)/questions.length)*100) + '%';
        prevBtn.disabled = (i===0);
        nextBtn.textContent = (i===questions.length-1)? 'Submit' : 'Next';
      }

      function collectAnswer(i){
        var q = questions[i]; if (!q) return;
        if (q.question_type === 'mcq') {
          var sel = document.querySelector('input[name="q_'+q.id+'"]:checked');
          if (sel) answers[q.id] = sel.value;
        } else {
          var ta = document.querySelector('textarea[name="q_'+q.id+'"]'); if (ta) answers[q.id] = ta.value.trim();
        }
      }

      // global toast helper (used by multiple handlers)
      function showToast(msg, timeout){
        timeout = timeout || 4000;
        try {
          var existing = document.getElementById('lh-toast'); if (existing) existing.remove();
          var div = document.createElement('div'); div.id = 'lh-toast'; div.textContent = msg;
          Object.assign(div.style, { position:'fixed', right:'18px', bottom:'18px', background:'rgba(32,32,32,0.95)', color:'#fff', padding:'10px 14px', borderRadius:'8px', boxShadow:'0 6px 18px rgba(0,0,0,0.4)', zIndex:99999 });
          document.body.appendChild(div);
          setTimeout(function(){ try{ div.remove(); }catch(e){} }, timeout);
        } catch (e) { try { alert(msg); } catch (err) {} }
      }

      // manage retake button state based on window.__quizAttempts
      function updateRetakeState(overrideUsed){
        try {
          var info = window.__quizAttempts || {};
          var used = parseInt(info.attempt_count || 0, 10);
          if (typeof overrideUsed === 'number') used = overrideUsed;
          var max = parseInt(info.max_attempts || 2, 10);
          var retakeBtn = document.getElementById('retakeBtn');
          if (!retakeBtn) return;
          var remaining = Math.max(0, max - used);
          if (remaining <= 0) {
            retakeBtn.disabled = true;
            retakeBtn.textContent = 'No more attempts';
          } else {
            retakeBtn.disabled = false;
            retakeBtn.textContent = 'Retake Quiz' + (remaining>1 ? ' ('+remaining+' left)' : '');
          }
        } catch (e) { /* ignore */ }
      }

      // initialize retake button state on load
      updateRetakeState();

      // retake behavior: reset UI and create a new server-side attempt
      var retakeBtn = document.getElementById('retakeBtn');
      if (retakeBtn) {
        retakeBtn.addEventListener('click', function(){
          if (retakeBtn.disabled) { showToast('No attempts left'); return; }
          // increment local attempt counter (server will verify on start/submit)
          try { window.__quizAttempts = window.__quizAttempts || {}; window.__quizAttempts.attempt_count = (parseInt(window.__quizAttempts.attempt_count||0,10) + 1); } catch(e){}
          updateRetakeState();
          // reset answers and UI state, then create server-side attempt
          answers = {};
          current = 0;
          if (quizResults) quizResults.style.display = 'none';
          if (quizStart) quizStart.style.display = 'none';
          // create server-side attempt and show container
          startQuiz().then(function(){
            if (quizContainer) quizContainer.style.display = 'block';
            try { var meta = (window.__quizData && window.__quizData.quiz) || {}; var mins = parseInt(meta.duration_minutes || 0, 10); if (mins && mins>0) startTimer(mins); } catch(e){}
            renderQuestion(0);
          }).catch(function(err){ console.error('Retake start failed', err); showToast('Could not start retake.'); });
        });
      }

      // helper: call server to create a server-side attempt and get attempt_id + started_at
      function startQuiz() {
        return new Promise(function(resolve, reject){
          try {
            var payload = { quiz_id: <?php echo json_encode($quiz_id); ?> };
            fetch('actions/start_quiz.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
            .then(function(r){ if (!r.ok) return r.json().then(function(err){ reject(err); }); return r.json(); })
            .then(function(res){ if (!res || !res.ok) { return reject(res || {error:'start failed'}); } try { window.__quizAttemptId = res.attempt_id || null; window.__quizStartTime = res.started_at || new Date().toISOString(); } catch(e) { window.__quizAttemptId = res.attempt_id || null; window.__quizStartTime = new Date().toISOString(); } resolve(res); })
            .catch(function(err){ reject(err); });
          } catch (e) { reject(e); }
        });
      }

      // show confirmation modal before starting the quiz
      startBtn && startBtn.addEventListener('click', function(){
        if (!questions || questions.length===0) { alert('No questions available.'); return; }
        var startModalWrap = document.getElementById('startModal');
        var lhModalRoot = document.getElementById('lhModalRoot');
        if (startModalWrap && lhModalRoot) {
          startModalWrap.style.display = 'block'; lhModalRoot.style.display = 'flex';
          // focus confirm for accessibility
          setTimeout(function(){ var c = document.getElementById('confirmStartBtn'); if(c) c.focus(); }, 120);
        } else {
          // fallback immediate start: create server-side attempt then show quiz
          startQuiz().then(function(){
            try { var meta = (window.__quizData && window.__quizData.quiz) || {}; var mins = parseInt(meta.duration_minutes || 0, 10); if (mins && mins>0) startTimer(mins); } catch(e){}
            quizStart.style.display='none'; quizContainer.style.display='block'; renderQuestion(0);
          }).catch(function(err){ console.error('Start failed', err); showToast('Could not start quiz: ' + (err && (err.error||err.detail) ? (err.error + ' ' + (err.detail||'')) : 'Server error')); });
        }
      });

      // modal controls
      var cancelStartBtn = document.getElementById('cancelStartBtn');
      var confirmStartBtn = document.getElementById('confirmStartBtn');
      cancelStartBtn && cancelStartBtn.addEventListener('click', function(){ var m = document.getElementById('startModal'); if(m) m.style.display='none'; });
      confirmStartBtn && confirmStartBtn.addEventListener('click', function(){
        var m = document.getElementById('startModal'); if(m) m.style.display='none';
        // request server to create attempt and then show quiz UI
        startQuiz().then(function(){
          quizStart.style.display='none'; quizContainer.style.display='block'; renderQuestion(0);
          try { var meta = (window.__quizData && window.__quizData.quiz) || {}; var mins = parseInt(meta.duration_minutes || 0, 10); if (mins && mins > 0) startTimer(mins); } catch (e) {}
        }).catch(function(err){ console.error('Start failed', err); showToast('Could not start quiz: ' + (err && (err.error||err.detail) ? (err.error + ' ' + (err.detail||'')) : 'Server error')); });
      });

      // add timer display into quiz header
      (function(){ var h = document.querySelector('.quiz-header'); if (h) { timerDisplay = document.createElement('div'); timerDisplay.id = 'timerDisplay'; timerDisplay.style.marginLeft = '12px'; timerDisplay.style.fontWeight = '700'; timerDisplay.style.color = 'var(--text-primary)'; timerDisplay.textContent = ''; h.appendChild(timerDisplay); } })();

      function formatTime(sec){ var m = Math.floor(sec/60); var s = sec%60; return String(m).padStart(2,'0')+':'+String(s).padStart(2,'0'); }

      function startTimer(minutes){
        // stop any existing timer
        try { if (quizTimer.interval) { clearInterval(quizTimer.interval); quizTimer.interval = null; } } catch(e){}
        quizTimer.remaining = Math.max(0, Math.floor(minutes*60));
        if (timerDisplay) timerDisplay.textContent = 'Time left: ' + formatTime(quizTimer.remaining);
        quizTimer.interval = setInterval(function(){
          quizTimer.remaining -= 1;
          if (timerDisplay) timerDisplay.textContent = 'Time left: ' + formatTime(Math.max(0, quizTimer.remaining));
          if (quizTimer.remaining <= 0) {
            clearInterval(quizTimer.interval); quizTimer.interval = null;
            // Auto-submit current answers and show time's up modal
            showToast('Time is up — submitting your answers...');
            try { submitQuizNow(true); } catch(e){ console.error('Auto-submit failed', e); }
          }
        }, 1000);
      }

      // submission extracted to function so it can be triggered by timer or by final Next click
      function submitQuizNow(fromTimer){
        // collect current page answer before submission
        try{ collectAnswer(current); } catch(e){}
        var correct = 0; var mcqCount = 0;
        questions.forEach(function(q){ if (q.question_type === 'mcq' && q.correct_answer && String(q.correct_answer).trim() !== '') { mcqCount++; var ans = answers[q.id] || ''; if (ans!=='' && String(ans) === String(q.correct_answer)) correct++; } });
        var percent = mcqCount ? Math.round((correct/mcqCount)*100) : 0;
        var payload = { quiz_id: <?php echo json_encode($quiz_id); ?>, answers: answers, score: percent, started_at: (window.__quizStartTime || null), attempt_id: (window.__quizAttemptId || null) };
        fetch('actions/submit_quiz.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
          .then(function(resp){ if (!resp.ok) { return resp.json().then(function(err){ var m = err && (err.error || err.detail) ? (err.error + (err.detail ? ': ' + err.detail : '')) : ('Server error: ' + resp.status); showToast(m); return null; }).catch(function(){ showToast('Server error: ' + resp.status); return null; }); } return resp.json(); })
          .then(function(res){ if (!res) return; try { window.__quizAttempts = window.__quizAttempts || {}; window.__quizAttempts.attempt_count = (parseInt(window.__quizAttempts.attempt_count||0,10) + 1); } catch(e){}
            try { if (typeof updateRetakeState === 'function') updateRetakeState(); } catch(e){}
            if (quizContainer) quizContainer.style.display='none'; if (quizResults) quizResults.style.display='block'; document.getElementById('scoreNumber').textContent = percent; document.getElementById('correctCount').textContent = correct; document.getElementById('totalCount').textContent = questions.length; var review = document.getElementById('resultsReview'); review.innerHTML='';
            function escapeHtml(str){ return String(str).replace(/[&<>"'`]/g,function(s){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#x60;'}[s];}); }
            questions.forEach(function(q,i){ var div = document.createElement('div'); div.className='result-review-item'; var title = document.createElement('div'); title.className='result-question'; title.innerHTML = '<strong>' + (i+1) + '. ' + escapeHtml(q.question_text) + '</strong>'; div.appendChild(title); var your = document.createElement('div'); your.className='result-your'; var yourAns = answers[q.id] || ''; your.innerHTML = 'Your answer: ' + (yourAns ? '<span class="answer-value">' + escapeHtml(yourAns) + '</span>' : '<em>no answer</em>'); div.appendChild(your); var correctDiv = document.createElement('div'); correctDiv.className='result-correct'; var correctText = (q.correct_answer && String(q.correct_answer).trim() !== '') ? q.correct_answer : null; if (correctText) { correctDiv.innerHTML = 'Correct: <span class="answer-correct">' + escapeHtml(correctText) + '</span>'; } else { correctDiv.innerHTML = 'Correct: <em>Not provided</em>'; } div.appendChild(correctDiv); if (q.question_type === 'mcq' && correctText) { var stu = answers[q.id] || ''; if (stu !== '' && String(stu) === String(correctText)) div.classList.add('answer-correct-item'); else div.classList.add('answer-incorrect-item'); } else { div.classList.add('answer-unknown-item'); } review.appendChild(div); });
          }).catch(function(e){ console.error(e); try{ showToast && showToast('Submit failed'); }catch(err){ alert('Submit failed'); } });
      }

      prevBtn && prevBtn.addEventListener('click', function(){ collectAnswer(current); if (current>0) { current--; renderQuestion(current); } });
      nextBtn && nextBtn.addEventListener('click', function(){ collectAnswer(current); if (current < questions.length-1) { current++; renderQuestion(current); } else { submitQuizNow(false); } });
    })();
  </script>
</body>
</html>
