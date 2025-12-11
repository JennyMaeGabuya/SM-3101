<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$teacher_id = $_SESSION['teacher_id'];
$stmt = $connection->prepare("SELECT id, title, message, created_at FROM notifications WHERE sender_id = ? ORDER BY created_at DESC");
if ($stmt) { $stmt->bind_param('s', $teacher_id); $stmt->execute(); $notes = $stmt->get_result(); $stmt->close(); } else { $notes = []; }
?>

<div class="section-header">
  <div class="gx section-title">Messages &amp; Notifications</div>
  <div><a class="btn btn-primary" href="#add-message">Send Announcement</a></div>
</div>

<div class="card" id="add-message">
  <h3>Send Announcement / Reminder</h3>
  <form method="post" action="actions/add_message.php" class="row">
    <div>
      <label>Title</label>
      <input type="text" name="title" required>
    </div>
    <div style="grid-column:1/3">
      <label>Message</label>
      <textarea name="message" rows="4" required></textarea>
    </div>
    <div>
      <label>Send To</label>
      <select name="send_to" id="msgSendTo">
        <option value="all">All Students</option>
        <option value="specific">Specific student (email)</option>
        <option value="section">Section(s)</option>
      </select>
    </div>
    <div id="msgTargetEmail" style="display:none">
      <label>Target student email</label>
      <input type="email" name="target_value" placeholder="student@batstateu.edu.ph">
    </div>
    <div id="msgTargetSections" style="display:none">
      <label>Pick Sections</label>
      <select id="msgSectionsSelect" multiple style="height:120px"></select>
      <input type="hidden" id="msgSectionsHidden">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Send</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Sent Messages</h3>
  <table>
    <thead><tr><th>Title</th><th>Message</th><th>Sent</th></tr></thead>
    <tbody>
    <?php if (!empty($notes) && $notes->num_rows>0): while($row = $notes->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
        <td><?php echo htmlspecialchars($row['message']); ?></td>
        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="3">No messages sent.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
  (function(){
    var sel = document.getElementById('msgSendTo');
    var emailWrap = document.getElementById('msgTargetEmail');
    var secWrap = document.getElementById('msgTargetSections');
    var secSel = document.getElementById('msgSectionsSelect');
    var secHidden = document.getElementById('msgSectionsHidden');
    if (!sel) return;
    sel.addEventListener('change', function(){
      if (sel.value === 'specific') { emailWrap.style.display=''; secWrap.style.display='none'; }
      else if (sel.value === 'section') { emailWrap.style.display='none'; secWrap.style.display=''; }
      else { emailWrap.style.display='none'; secWrap.style.display='none'; }
    });
    try { fetch('../api/sections.php').then(r=>r.json()).then(j=>{ if (j && j.success) { j.sections.forEach(s=>{ var o=document.createElement('option'); o.value=s.id; o.text=s.name; secSel.appendChild(o); }); } }).catch(()=>{}); } catch(e) {}
    var form = document.querySelector('#add-message form');
    if (form) {
      form.addEventListener('submit', function(){
        if (sel.value === 'section') {
          var ids=[]; for (var i=0;i<secSel.options.length;i++) if (secSel.options[i].selected) ids.push(secSel.options[i].value);
          secHidden.value = ids.join(','); secHidden.setAttribute('name','target_value');
        } else { secHidden.removeAttribute('name'); secHidden.value = ''; }
      });
    }
  })();
</script>
