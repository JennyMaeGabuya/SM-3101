<?php
require_once __DIR__ . '/includes/header.php';

// Simple teacher auth check
if (empty($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch quizzes
$stmt = $connection->prepare("SELECT id, title, description, scheduled_at, deadline, COALESCE(max_attempts,2) AS max_attempts FROM quizzes WHERE teacher_id = ? ORDER BY scheduled_at DESC");
if ($stmt) { $stmt->bind_param('s', $_SESSION['teacher_id']); $stmt->execute(); $result = $stmt->get_result(); $stmt->close(); } else { $result = []; }
?>

<div class="section-header">
  <div class="section-title">Upload Quizzes</div>
  <div><a class="btn btn-primary" href="#add-quiz">Add Quiz</a></div>
</div>

<div class="card" id="add-quiz">
  <h3>Add New Quiz</h3>
  <form method="post" action="actions/add_quiz.php" class="row">
    <div>
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div>
      <label>Scheduled At</label>
      <input type="datetime-local" name="scheduled_at" required>
    </div>
    <div style="grid-column:1/3">
      <label>Description / Instructions</label>
      <textarea name="description" rows="4"></textarea>
    </div>
    <div>
      <label>Deadline</label>
      <input type="datetime-local" name="deadline">
    </div>
    <div style="grid-column:1/3">
      <label>Send To</label>
      <select name="audience" id="audienceSelect">
        <option value="all">All students</option>
        <option value="specific">Specific student (email)</option>
        <option value="section">Section(s)</option>
      </select>
    </div>
    <div id="audienceTarget" style="display:none">
      <label>Student Email</label>
      <input type="email" name="target_value" placeholder="student@example.edu">
    </div>
    <div id="audienceSections" style="display:none; grid-column:1/3">
      <label>Pick Sections</label>
      <div id="sectionsListQuiz" class="custom-sections-list" style="max-height:220px; overflow:auto; padding:8px; border-radius:6px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04)"></div>
      <div id="sectionsSelectedDisplay_quiz" class="profile-studentid" style="margin-top:6px; display:none">Selected section(s): <span id="sectionsSelectedText_quiz">None</span></div>
      <input type="hidden" id="sectionsTargetHidden">
    </div>
    <div>
      <label>Max Attempts</label>
      <input type="number" name="max_attempts" min="1" value="2" title="Maximum attempts allowed for this quiz (default 2)">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Add Quiz</button>
    </div>
  </form>
</div>

<script>
  (function(){
    var sel = document.getElementById('audienceSelect')
    var target = document.getElementById('audienceTarget')
    var secWrap = document.getElementById('audienceSections')
    var secHidden = document.getElementById('sectionsTargetHidden')
    if (!sel || !target) return
    sel.addEventListener('change', function(){
      if (sel.value === 'specific') { target.style.display = ''; secWrap.style.display = 'none'; }
      else if (sel.value === 'section') { target.style.display = 'none'; secWrap.style.display = ''; }
      else { target.style.display = 'none'; secWrap.style.display = 'none'; }
    })

    // fetch sections and populate a dark checkbox list (avoid native white multi-select)
    try {
      fetch('../api/sections.php').then(r=>r.json()).then(j=>{
        if (j && j.success && Array.isArray(j.sections)) {
          var container = document.getElementById('sectionsListQuiz');
          if (!container) return;
          container.innerHTML = '';
          j.sections.forEach(function(s){
            var row = document.createElement('div');
            row.style.display = 'flex'; row.style.alignItems = 'center'; row.style.padding = '6px 8px'; row.style.borderRadius = '4px'; row.style.marginBottom = '6px';
            row.onmouseover = function(){ this.style.background = 'rgba(255,255,255,0.02)'; }
            row.onmouseout = function(){ this.style.background = 'transparent'; }
            var cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = s.id; cb.id = 'quiz_sec_cb_' + s.id; cb.style.marginRight = '10px'; cb.style.accentColor = 'var(--primary-red)';
            var lbl = document.createElement('label'); lbl.htmlFor = cb.id; lbl.textContent = s.name; lbl.style.color = 'var(--text-primary)'; lbl.style.cursor = 'pointer';
            row.appendChild(cb); row.appendChild(lbl); container.appendChild(row);
          });

          // update display once populated
          try { var ev = new Event('change'); container.dispatchEvent(ev); } catch(e){}
        }
      }).catch(()=>{});
    } catch(e) {}

    // show chosen section names under the selector for clarity (checkbox-driven)
    try {
      var containerQuiz = document.getElementById('sectionsListQuiz');
      var dispWrapQ = document.getElementById('sectionsSelectedDisplay_quiz');
      var dispTextQ = document.getElementById('sectionsSelectedText_quiz');
      function updateQuizSelectedSections(){
        if (!containerQuiz || !dispWrapQ || !dispTextQ) return;
        var boxes = containerQuiz.querySelectorAll('input[type="checkbox"]');
        var chosen = [];
        for (var i=0;i<boxes.length;i++) if (boxes[i].checked) {
          var lbl = containerQuiz.querySelector('label[for="' + boxes[i].id + '"]');
          chosen.push(lbl ? lbl.textContent : boxes[i].value);
        }
        if (chosen.length===0) { dispWrapQ.style.display='none'; dispTextQ.textContent='None'; } else { dispWrapQ.style.display=''; dispTextQ.textContent = chosen.join(', '); }
      }
      if (containerQuiz) { containerQuiz.addEventListener('change', updateQuizSelectedSections); var obsQ = new MutationObserver(updateQuizSelectedSections); obsQ.observe(containerQuiz, { childList:true, subtree:true }); }
    } catch(e) {}

    // on form submit, collect checked section ids into hidden input
    var form = document.querySelector('form');
    if (form) {
      form.addEventListener('submit', function(){
        if (sel.value === 'section') {
          var container = document.getElementById('sectionsListQuiz');
          var boxes = container ? container.querySelectorAll('input[type="checkbox"]') : [];
          var ids = [];
          for (var i=0;i<boxes.length;i++) if (boxes[i].checked) ids.push(boxes[i].value);
          secHidden.value = ids.join(',');
          secHidden.setAttribute('name','target_value');
        } else {
          secHidden.removeAttribute('name');
          secHidden.value = '';
        }
      });
    }
  })()
</script>

<div class="card">
  <h3>Existing Quizzes</h3>
  <table>
    <thead><tr><th>Title</th><th>Scheduled</th><th>Deadline</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!empty($result) && $result->num_rows>0): while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
        <td><?php echo htmlspecialchars($row['scheduled_at']); ?></td>
        <td><?php echo htmlspecialchars($row['deadline']); ?></td>
        <td>
          <a class="btn btn-ghost" href="edit_quiz.php?id=<?php echo $row['id']; ?>" title="Edit">
            <!-- Edit SVG -->
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z" fill="currentColor"/><path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>
            <span style="margin-left:8px">Edit</span>
          </a>
          <a class="btn btn-danger" href="actions/delete_quiz.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete quiz?')" title="Delete">
            <!-- Delete SVG -->
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 7h12v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7z" fill="currentColor"/><path d="M9 4h6l1 2H8l1-2z" fill="currentColor"/></svg>
            <span style="margin-left:8px">Delete</span>
          </a>
        </td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="4">No quizzes found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
