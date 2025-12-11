<?php
// Diagnostic script: find mismatches between notifications and learning_materials
// Usage: open http://localhost/LearnHub/tools/diag_notifications.php in your browser
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
if (empty($connection)) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection not available']);
    exit;
}
$results = ['orphan_notifications'=>[], 'materials_without_notification'=>[]];
try {
    // Notifications that refer to materials but have no matching learning_materials row
    $sql1 = "SELECT n.id, n.sender_id, n.title, n.audience, n.created_at
      FROM `learnHub`.`notifications` n
      WHERE n.title LIKE 'New Material:%'
      AND NOT EXISTS (
        SELECT 1 FROM `learnHub`.`learning_materials` l
        WHERE n.sender_id = l.teacher_id
          AND n.title = CONCAT('New Material: ', l.title)
      )
      ORDER BY n.created_at DESC
      LIMIT 200";
    $res1 = $connection->query($sql1);
    if ($res1) {
        while ($r = $res1->fetch_assoc()) {
            $results['orphan_notifications'][] = $r;
        }
        $res1->close();
    }

    // Materials that have no corresponding notification
    $sql2 = "SELECT l.id, l.teacher_id, l.title, l.created_at
      FROM `learnHub`.`learning_materials` l
      WHERE NOT EXISTS (
        SELECT 1 FROM `learnHub`.`notifications` n
        WHERE n.sender_id = l.teacher_id
          AND n.title = CONCAT('New Material: ', l.title)
      )
      ORDER BY l.created_at DESC
      LIMIT 200";
    $res2 = $connection->query($sql2);
    if ($res2) {
        while ($r = $res2->fetch_assoc()) {
            $results['materials_without_notification'][] = $r;
        }
        $res2->close();
    }
} catch (Exception $e) {
    error_log('diag_notifications error: ' . $e->getMessage());
}

echo json_encode($results, JSON_PRETTY_PRINT);

?>