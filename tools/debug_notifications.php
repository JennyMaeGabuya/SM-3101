<?php
// Simple debug page to inspect tasks, activities, notifications and current user mapping
// Usage: open /LearnHub/tools/debug_notifications.php in your browser while logged in as the student
require_once __DIR__ . '/../connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function fetchRows($conn, $sql) {
    $out = [];
    try {
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) $out[] = $r;
            $res->close();
        }
    } catch (Exception $e) { $out = ['error' => $e->getMessage()]; }
    return $out;
}

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html><head><meta charset="utf-8"><title>Debug Notifications</title>
<style>body{font-family:Arial,Helvetica,sans-serif;background:#111;color:#eee;padding:18px} pre{background:#222;padding:12px;border-radius:6px;overflow:auto}</style>
</head><body>
<h2>Debug: Notifications / Tasks / Activities</h2>
<p>Open this page while logged in as the student account you expect to receive targeted items.</p>
<?php
// show session mapping
echo '<h3>Session</h3><pre>'; var_export($_SESSION); echo '</pre>';

// show current user email if logged in
$currentEmail = null;
if (!empty($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $u = $connection->prepare('SELECT accountId, email, username FROM accounts WHERE accountId = ? LIMIT 1');
    if ($u) { $u->bind_param('i',$uid); $u->execute(); $resu = $u->get_result(); if ($resu) { $rr = $resu->fetch_assoc(); if ($rr) $currentEmail = $rr['email']; $resu->close(); } $u->close(); }
}
echo '<h3>Current student email</h3><pre>' . htmlspecialchars($currentEmail ?? '(none)') . '</pre>';

echo '<h3>Recent performance_tasks</h3>';
$tasks = fetchRows($connection, "SELECT id, teacher_id, title, audience, target_value, due_date, created_at FROM performance_tasks ORDER BY created_at DESC LIMIT 50");
echo '<pre>' . htmlspecialchars(var_export($tasks, true)) . '</pre>';

echo '<h3>Recent activities</h3>';
$acts = fetchRows($connection, "SELECT id, teacher_id, title, audience, target_value, scheduled_at, deadline, created_at FROM activities ORDER BY created_at DESC LIMIT 50");
echo '<pre>' . htmlspecialchars(var_export($acts, true)) . '</pre>';

echo '<h3>Recent notifications</h3>';
$notifs = fetchRows($connection, "SELECT id, sender_id, title, audience, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 100");
echo '<pre>' . htmlspecialchars(var_export($notifs, true)) . '</pre>';

echo '<h3>Accounts (sample)</h3>';
$accs = fetchRows($connection, "SELECT accountId, username, email FROM accounts ORDER BY accountId DESC LIMIT 50");
echo '<pre>' . htmlspecialchars(var_export($accs, true)) . '</pre>';

echo '<h3>Helpful checks</h3><pre>';
if ($currentEmail) {
    echo "Notifications matching current user (audience IN ('students','all') OR audience = 'user:" . $connection->real_escape_string($currentEmail) . "')\n\n";
    $q = "SELECT id, title, audience, created_at FROM notifications WHERE audience IN ('students','all') OR audience = 'user:" . $connection->real_escape_string($currentEmail) . "' ORDER BY created_at DESC LIMIT 50";
    $r = fetchRows($connection, $q);
    echo htmlspecialchars(var_export($r, true));
} else {
    echo "No student logged in (session user_id missing). Log in as the student account and reload this page.";
}
echo '</pre>';

?>
</body></html>
