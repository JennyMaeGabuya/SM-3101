<?php
require_once __DIR__ . '/connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$item = isset($_GET['item']) ? $_GET['item'] : null;
$download = isset($_GET['download']) && $_GET['download'] === '1';
$evtype = isset($_GET['evtype']) ? $_GET['evtype'] : null; // main, reminder, deadline, feedback
if (!$item) { header('Location: schedule.php'); exit; }
$id = intval($item);
$data = null;
try {
    if ($id < 0) {
        $nid = abs($id);
        $q = $connection->prepare('SELECT id, title, message, created_at, audience FROM notifications WHERE id = ? LIMIT 1');
        if ($q) { $q->bind_param('i', $nid); $q->execute(); $res = $q->get_result(); if ($res) { $row = $res->fetch_assoc(); if ($row) { $data = [
                    'title' => preg_replace('/^New Activity:\s*/i', '', $row['title']),
                    'description' => $row['message'],
                    'start' => $row['created_at'],
                    'end' => null,
                ]; } $res->close(); } $q->close(); }
    } else {
        $q = $connection->prepare('SELECT id, title, description, scheduled_at, deadline FROM activities WHERE id = ? LIMIT 1');
        if ($q) { $q->bind_param('i', $id); $q->execute(); $res = $q->get_result(); if ($res) { $row = $res->fetch_assoc(); if ($row) { $data = [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'start' => $row['scheduled_at'],
                    'end' => $row['deadline'] ?: null,
                ]; } $res->close(); } $q->close(); }
    }
} catch (Exception $e) { error_log('activity_events: '.$e->getMessage()); }

if (!$data) { echo "<p>Event not found</p>"; exit; }

function to_ts($dt) { return $dt ? strtotime($dt) : null; }
function fmt_local($dt) { if (!$dt) return ''; $ts = strtotime($dt); return date('M d, Y H:i', $ts); }

$start_ts = to_ts($data['start']);
$deadline_ts = to_ts($data['end']);

// build related happenings
$happenings = [];
$happenings[] = [ 'key' => 'main', 'title' => 'Main Activity', 'desc' => $data['description'], 'start' => $data['start'], 'end' => $data['end'] ];
if ($start_ts) {
    $happenings[] = [ 'key' => 'reminder', 'title' => 'Reminder', 'desc' => 'Reminder: upcoming activity', 'start' => date('Y-m-d H:i:s', max(0, $start_ts - 24*3600)), 'end' => null ];
    $happenings[] = [ 'key' => 'quick-session', 'title' => 'Feedback / Q&A Session', 'desc' => 'Short feedback/Q&A session for this activity', 'start' => date('Y-m-d H:i:s', $start_ts + 3600), 'end' => date('Y-m-d H:i:s', $start_ts + 7200) ];
}
if ($deadline_ts) {
    $happenings[] = [ 'key' => 'deadline', 'title' => 'Deadline', 'desc' => 'Submission deadline for this activity', 'start' => date('Y-m-d H:i:s', $deadline_ts), 'end' => null ];
}

// If downloading a specific sub-event as ICS
if ($download && $evtype) {
    $ev = null; foreach ($happenings as $h) if ($h['key'] === $evtype) { $ev = $h; break; }
    if (!$ev) { header('Location: activity_events.php?item='.urlencode($item)); exit; }
    // generate ICS
    $uid = uniqid('lh-ev-');
    $summary = $ev['title'];
    $description = $ev['desc'] ?? '';
    $dtstart = $ev['start'] ? gmdate('Ymd\THis\Z', strtotime($ev['start'])) : '';
    $dtend = $ev['end'] ? gmdate('Ymd\THis\Z', strtotime($ev['end'])) : ($ev['start'] ? gmdate('Ymd\THis\Z', strtotime($ev['start']) + 3600) : '');
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="event-'.$evtype.'.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//LearnHub//EN\r\nBEGIN:VEVENT\r\nUID:".htmlspecialchars($uid)."\r\n";
    if ($dtstart) echo "DTSTART:".$dtstart."\r\n";
    if ($dtend) echo "DTEND:".$dtend."\r\n";
    echo "SUMMARY:".str_replace(["\r","\n"],' ',$summary)."\r\n";
    if ($description) echo "DESCRIPTION:".str_replace(["\r","\n"],' ',$description)."\r\n";
    echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Related Events - <?php echo htmlspecialchars($data['title']); ?></title>
  <link rel="stylesheet" href="styles.css?v=2">
  <style>
    .events-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; }
    .event-card { background:var(--bg-secondary); border:1px solid var(--border-color); padding:14px; border-radius:10px; }
    .event-actions { margin-top:12px; display:flex; gap:8px; justify-content:flex-end; }
    .badge-source { font-size:0.75rem;padding:6px 8px;border-radius:6px;background:#222;color:#ffd;border:1px solid rgba(255,215,0,0.06); }
  </style>
</head>
<body>
  <div class="app-container" style="padding:28px;">
    <nav class="navbar"><div class="navbar-content"><h1 class="brand-logo">Related Events</h1></div></nav>
    <main style="margin-top:18px">
      <section>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div>
            <h2 style="margin:0"><?php echo htmlspecialchars($data['title']); ?></h2>
            <div style="color:var(--text-secondary)"><?php echo nl2br(htmlspecialchars($data['description'])); ?></div>
          </div>
          <div style="text-align:right;color:var(--text-secondary)">Start: <?php echo htmlspecialchars($data['start']); ?><br>End: <?php echo htmlspecialchars($data['end']); ?></div>
        </div>
        <div class="events-grid">
          <?php foreach ($happenings as $h): ?>
            <div class="event-card">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div style="font-weight:800"><?php echo htmlspecialchars($h['title']); ?></div>
                  <div style="color:var(--text-secondary);margin-top:6px"><?php echo nl2br(htmlspecialchars($h['desc'] ?? '')); ?></div>
                </div>
                <div style="text-align:right;color:var(--text-secondary)"><?php echo htmlspecialchars(fmt_local($h['start'])); ?><?php if (!empty($h['end'])) echo '<br>'.htmlspecialchars(fmt_local($h['end'])); ?></div>
              </div>
              <div class="event-actions">
                <a class="btn btn-secondary" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'].'?item='.urlencode($item).'&evtype='.urlencode($h['key']).'&download=1'); ?>">Download .ics</a>
                <a class="btn btn-primary" href="schedule.php">Back</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
