<?php
// Lists learning_materials and notifications for a given teacher id
// Usage: http://localhost/LearnHub/tools/list_teacher_materials_notifications.php?teacher_id=1
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
if (empty($connection)) { http_response_code(500); echo json_encode(['error'=>'DB connection unavailable']); exit; }
$tid = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 1;
$out = ['teacher_id'=>$tid, 'materials'=>[], 'notifications'=>[]];
try {
    $stmt = $connection->prepare('SELECT id,title,description,file_path,link,created_at FROM learning_materials WHERE teacher_id = ? ORDER BY created_at DESC');
    if ($stmt) {
        $stmt->bind_param('i',$tid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) { while ($r = $res->fetch_assoc()) $out['materials'][] = $r; $res->close(); }
        $stmt->close();
    }
    $stmt2 = $connection->prepare('SELECT id,sender_id,title,message,audience,created_at FROM notifications WHERE sender_id = ? AND title LIKE ? ORDER BY created_at DESC');
    if ($stmt2) {
        $like = 'New Material:%';
        $stmt2->bind_param('is',$tid,$like);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2) { while ($r = $res2->fetch_assoc()) $out['notifications'][] = $r; $res2->close(); }
        $stmt2->close();
    }
} catch (Exception $e) { error_log('list diag error: '.$e->getMessage()); }

echo json_encode($out, JSON_PRETTY_PRINT);
?>