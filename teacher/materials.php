<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$teacher_id = $_SESSION['teacher_id'];
$stmt = $connection->prepare("SELECT id, title, description, file_path, link, created_at FROM learning_materials WHERE teacher_id = ? ORDER BY created_at DESC");
if ($stmt) { $stmt->bind_param('s', $teacher_id); $stmt->execute(); $materials = $stmt->get_result(); $stmt->close(); } else { $materials = []; }
?>

<div class="section-header">
  <div class="section-title">Learning Materials</div>
  <div><a class="btn btn-primary" href="#add-material">Upload Material</a></div>
</div>

<div class="card" id="add-material">
  <h3>Upload Material</h3>
  <form id="addMaterialForm" method="post" action="actions/add_material.php" class="row" enctype="multipart/form-data">
    <div>
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div>
      <label>File (optional)</label>
      <input type="file" name="file">
    </div>
    <div style="grid-column:1/3">
      <label>Link (optional)</label>
      <input type="url" name="link" placeholder="https://example.com/resource">
    </div>
    <div style="grid-column:1/3">
      <label>Description</label>
      <textarea name="description" rows="3"></textarea>
    </div>
    <div>
      <label>Send To</label>
      <select name="audience" id="audienceSelect">
        <option value="students">All students</option>
        <option value="specific">Specific student (email)</option>
        <option value="section">Section(s)</option>
      </select>
    </div>
    <div id="targetEmailWrap" style="display:none">
      <label>Target student email</label>
      <input type="email" name="target_value" id="targetEmail" placeholder="student@batstateu.edu.ph">
    </div>
    <div id="targetSectionsWrap" style="display:none">
      <label>Pick Sections</label>
      <div id="sectionsListMat" class="custom-sections-list" style="max-height:220px; overflow:auto; padding:8px; border-radius:6px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04)"></div>
      <input type="hidden" id="sectionsTargetHiddenMat">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Upload</button>
    </div>
  </form>
</div>

<script>
  (function(){
    var sel = document.getElementById('audienceSelect')
    var wrap = document.getElementById('targetEmailWrap')
    if (!sel || !wrap) return
    sel.addEventListener('change', function(){
      if (this.value === 'specific') { wrap.style.display = ''; document.getElementById('targetSectionsWrap').style.display = 'none'; }
      else if (this.value === 'section') { wrap.style.display = 'none'; document.getElementById('targetSectionsWrap').style.display = ''; }
      else { wrap.style.display = 'none'; document.getElementById('targetSectionsWrap').style.display = 'none'; }
    })
    // client-side validation: require target email when specific selected
    var form = document.getElementById('addMaterialForm');
    if (form) {
      form.addEventListener('submit', function(e){
        try {
          if (sel.value === 'specific') {
            var email = document.getElementById('targetEmail').value.trim();
            if (!email) { e.preventDefault(); alert('Please enter the target student email when "Specific student" is selected.'); return false; }
          }
          if (sel.value === 'section') {
            // collect checked section ids from the checkbox list
            var container = document.getElementById('sectionsListMat');
            var checkboxes = container ? container.querySelectorAll('input[type="checkbox"]') : [];
            var ids = [];
            for (var i=0;i<checkboxes.length;i++) if (checkboxes[i].checked) ids.push(checkboxes[i].value);
            if (ids.length === 0) { e.preventDefault(); alert('Please select at least one section when "Section(s)" is selected.'); return false; }
            var hidden = document.getElementById('sectionsTargetHiddenMat'); hidden.value = ids.join(','); hidden.setAttribute('name','target_value');
          } else {
            var hidden = document.getElementById('sectionsTargetHiddenMat'); hidden.removeAttribute('name'); hidden.value = '';
          }
        } catch(err) { /* ignore */ }
      });
    }
    // populate sections into a dark-themed checkbox list to avoid native white dropdown
    try {
      fetch('../api/sections.php').then(r=>r.json()).then(j=>{
        if (j && j.success && Array.isArray(j.sections)) {
          var container = document.getElementById('sectionsListMat');
          if (!container) return;
          container.innerHTML = '';
          j.sections.forEach(function(s){
            var row = document.createElement('div');
            row.style.display = 'flex'; row.style.alignItems = 'center'; row.style.padding = '6px 4px'; row.style.borderRadius = '4px';
            row.style.marginBottom = '4px';
            row.onmouseover = function(){ this.style.background = 'rgba(255,255,255,0.02)'; }
            row.onmouseout = function(){ this.style.background = 'transparent'; }
            var cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = s.id; cb.id = 'sec_cb_' + s.id; cb.style.marginRight = '8px';
            var lbl = document.createElement('label'); lbl.htmlFor = cb.id; lbl.textContent = s.name; lbl.style.color = 'var(--muted-text,#ddd)'; lbl.style.cursor = 'pointer';
            row.appendChild(cb); row.appendChild(lbl);
            container.appendChild(row);
          });
        }
      }).catch(()=>{});
    } catch(e) {}
  })()
</script>

<div class="card">
  <h3>Available Materials</h3>
  <table>
    <thead><tr><th>Title</th><th>File/Link</th><th>Uploaded</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!empty($materials) && $materials->num_rows>0): while($row = $materials->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
        <td>
          <?php if ($row['file_path']): ?><a href="../download.php?resource=material&id=<?php echo intval($row['id']); ?>" target="_blank">Download</a><?php endif; ?>
          <?php if ($row['link']): ?> <a href="<?php echo htmlspecialchars($row['link']); ?>" target="_blank">Link</a><?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
        <td>
          <a class="btn btn-ghost" href="edit_material.php?id=<?php echo $row['id']; ?>" title="Edit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z" fill="currentColor"/><path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>
            <span style="margin-left:8px">Edit</span>
          </a>
          <a class="btn btn-danger" href="actions/delete_material.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete material?')" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 7h12v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7z" fill="currentColor"/><path d="M9 4h6l1 2H8l1-2z" fill="currentColor"/></svg>
            <span style="margin-left:8px">Delete</span>
          </a>
        </td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="4">No materials found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
