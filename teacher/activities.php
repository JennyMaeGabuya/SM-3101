<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$teacher_id = $_SESSION['teacher_id'];
$stmt = $connection->prepare("SELECT id, title, description, scheduled_at, deadline FROM activities WHERE teacher_id = ? ORDER BY scheduled_at DESC");
if ($stmt) { $stmt->bind_param('s', $teacher_id); $stmt->execute(); $acts = $stmt->get_result(); $stmt->close(); } else { $acts = []; }
?>

<div class="section-header">
  <div class="section-title">Incoming Activities</div>
  <div><a class="btn btn-primary" href="#add-activity">Add Activity</a></div>
</div>

<?php if (session_status() === PHP_SESSION_NONE) session_start(); if (!empty($_SESSION['error'])): ?>
  <div class="flash flash-error" style="margin:12px 0;padding:10px;border-radius:6px;background:#ffe6e6;color:#900"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php elseif (!empty($_SESSION['success'])): ?>
  <div class="flash flash-success" style="margin:12px 0;padding:10px;border-radius:6px;background:#e6ffed;color:#060"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="card" id="add-activity">
  <h3>Add Activity</h3>
  <form method="post" action="actions/add_activity.php" class="row">
    <div>
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div>
      <label>Scheduled At</label>
      <input type="datetime-local" name="scheduled_at">
    </div>
    <div style="grid-column:1/3">
      <label>Description</label>
      <textarea name="description" rows="3"></textarea>
    </div>
    <div>
      <label>Deadline</label>
      <input type="datetime-local" name="deadline">
    </div>
    <div style="grid-column:1/3">
      <label>Send To</label>
      <select name="audience" id="audienceSelectActivity">
        <option value="all">All students</option>
        <option value="specific">Specific student (email)</option>
        <option value="section">Section(s)</option>
      </select>
    </div>
    <div id="audienceTargetActivity" style="display:none">
      <label>Student Email</label>
      <input type="email" name="target_value" placeholder="student@example.edu">
    </div>
    <div id="audienceSectionsActivity" style="display:none; grid-column:1/3">
      <label>Pick Sections</label>
      <div id="sectionsListActivity" class="custom-sections-list" style="max-height:220px; overflow:auto; padding:8px; border-radius:6px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04)"></div>
      <div id="sectionsSelectedDisplay_activity" class="profile-studentid" style="margin-top:6px; display:none">Selected section(s): <span id="sectionsSelectedText_activity">None</span></div>
      <input type="hidden" id="sectionsTargetHiddenActivity">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Add Activity</button>
    </div>
  </form>
</div>

<script>
  (function(){
    var sel = document.getElementById('audienceSelectActivity')
    var target = document.getElementById('audienceTargetActivity')
    if (!sel || !target) return
    sel.addEventListener('change', function(){
      if (sel.value === 'specific') { target.style.display = ''; document.getElementById('audienceSectionsActivity').style.display = 'none'; }
      else if (sel.value === 'section') { target.style.display = 'none'; document.getElementById('audienceSectionsActivity').style.display = ''; }
      else { target.style.display = 'none'; document.getElementById('audienceSectionsActivity').style.display = 'none'; }
    })
    try {
      fetch('../api/sections.php').then(r=>r.json()).then(j=>{
        if (j && j.success && Array.isArray(j.sections)) {
          var container = document.getElementById('sectionsListActivity');
          if (!container) return;
          container.innerHTML = '';
          j.sections.forEach(function(s){
            var row = document.createElement('div');
            row.style.display = 'flex'; row.style.alignItems = 'center'; row.style.padding = '6px 8px'; row.style.borderRadius = '4px'; row.style.marginBottom = '6px';
            row.onmouseover = function(){ this.style.background = 'rgba(255,255,255,0.02)'; }
            row.onmouseout = function(){ this.style.background = 'transparent'; }
            var cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = s.id; cb.id = 'act_sec_cb_' + s.id; cb.style.marginRight = '10px'; cb.style.accentColor = 'var(--primary-red)';
            var lbl = document.createElement('label'); lbl.htmlFor = cb.id; lbl.textContent = s.name; lbl.style.color = 'var(--text-primary)'; lbl.style.cursor = 'pointer';
            row.appendChild(cb); row.appendChild(lbl); container.appendChild(row);
          });
        }
      }).catch(()=>{});
    } catch(e) {}

    // update and display selected section names for clarity (checkbox-driven)
    try {
      var containerAct = document.getElementById('sectionsListActivity');
      var disp = document.getElementById('sectionsSelectedDisplay_activity');
      var dispText = document.getElementById('sectionsSelectedText_activity');
      function updAct(){ if (!containerAct || !disp || !dispText) return; var boxes = containerAct.querySelectorAll('input[type="checkbox"]'); var arr=[]; for (var i=0;i<boxes.length;i++) if (boxes[i].checked) { var lbl = containerAct.querySelector('label[for="'+boxes[i].id+'"]'); arr.push(lbl ? lbl.textContent : boxes[i].value); } if (arr.length===0){ disp.style.display='none'; dispText.textContent='None'; } else { disp.style.display=''; dispText.textContent = arr.join(', '); } }
      if (containerAct) { containerAct.addEventListener('change', updAct); var obsA = new MutationObserver(updAct); obsA.observe(containerAct, { childList: true, subtree:true }); }
    } catch(e){}
    var formAct = document.querySelector('#add-activity form');
    if (formAct) {
      formAct.addEventListener('submit', function(){
        var selv = document.getElementById('audienceSelectActivity');
        if (selv && selv.value === 'section') {
          var container = document.getElementById('sectionsListActivity');
          var boxes = container ? container.querySelectorAll('input[type="checkbox"]') : [];
          var ids = [];
          for (var i=0;i<boxes.length;i++) if (boxes[i].checked) ids.push(boxes[i].value);
          var hidden = document.getElementById('sectionsTargetHiddenActivity'); hidden.value = ids.join(','); hidden.setAttribute('name','target_value');
        } else {
          var hidden = document.getElementById('sectionsTargetHiddenActivity'); hidden.removeAttribute('name'); hidden.value = '';
        }
      });
    }
  })()
</script>

<div class="card">
  <h3>Existing Activities</h3>
  <table>
    <thead><tr><th>Title</th><th>Scheduled</th><th>Deadline</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!empty($acts) && $acts->num_rows>0): while($row = $acts->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
        <td><?php echo htmlspecialchars($row['scheduled_at']); ?></td>
        <td><?php echo htmlspecialchars($row['deadline']); ?></td>
        <td>
          <a class="btn btn-ghost" href="edit_activity.php?id=<?php echo $row['id']; ?>" title="Edit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z" fill="currentColor"/><path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>
            <span style="margin-left:8px">Edit</span>
          </a>
          <a class="btn btn-danger" href="actions/delete_activity.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete activity?')" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 7h12v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7z" fill="currentColor"/><path d="M9 4h6l1 2H8l1-2z" fill="currentColor"/></svg>
            <span style="margin-left:8px">Delete</span>
          </a>
        </td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="4">No activities found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
