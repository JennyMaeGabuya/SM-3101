<?php
// Admin tool: add resource mapping to notifications and map 'New Material' notifications to learning_materials
// Usage: open in browser or run via curl: http://localhost/LearnHub/tools/map_notifications_to_materials.php
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
if (empty($connection)) { http_response_code(500); echo json_encode(['error'=>'DB connection unavailable']); exit; }
$out = ['created_columns'=>false, 'mapped'=>[], 'errors'=>[]];
try {
    // Ensure columns exist
    $cols = [];
    $res = $connection->query("SHOW COLUMNS FROM notifications");
    if ($res) { while ($r = $res->fetch_assoc()) $cols[] = $r['Field']; $res->close(); }
    if (!in_array('resource_type', $cols) || !in_array('resource_id', $cols)) {
        $alter = "ALTER TABLE notifications ";
        $parts = [];
        if (!in_array('resource_type', $cols)) $parts[] = "ADD COLUMN resource_type VARCHAR(60) NULL AFTER audience";
        if (!in_array('resource_id', $cols)) $parts[] = "ADD COLUMN resource_id INT NULL AFTER resource_type";
        if (!empty($parts)) {
            $alter .= implode(', ', $parts);
            if ($connection->query($alter) === false) {
                $out['errors'][] = 'Alter failed: ' . $connection->error;
            } else {
                $out['created_columns'] = true;
            }
        }
    }
    // Map notifications with title 'New Material: %' to learning_materials by exact title match
    $sql = "SELECT n.id AS nid, n.sender_id, n.title FROM notifications n WHERE n.title LIKE 'New Material:%'";
    $res2 = $connection->query($sql);
    if ($res2) {
        while ($n = $res2->fetch_assoc()) {
            $title = trim(substr($n['title'], strlen('New Material:')));
            $sender = intval($n['sender_id']);
            // try exact match first
            $p = $connection->prepare('SELECT id FROM learning_materials WHERE teacher_id = ? AND title = ? LIMIT 1');
            if ($p) {
                $p->bind_param('is', $sender, $title);
                $p->execute(); $pr = $p->get_result();
                if ($pr && $pr->num_rows>0) {
                    $row = $pr->fetch_assoc();
                    $mid = intval($row['id']);
                    // update notification
                    $u = $connection->prepare('UPDATE notifications SET resource_type = ?, resource_id = ? WHERE id = ?');
                    if ($u) { $rtype = 'learning_material'; $u->bind_param('sii', $rtype, $mid, $n['nid']); $u->execute(); $u->close(); }
                    $out['mapped'][] = ['notification_id'=>intval($n['nid']),'material_id'=>$mid,'title'=>$title];
                } else {
                    // try LIKE match
                    $like = '%' . $connection->real_escape_string($title) . '%';
                    $q = $connection->prepare('SELECT id FROM learning_materials WHERE teacher_id = ? AND title LIKE ? LIMIT 1');
                    if ($q) {
                        $q->bind_param('is', $sender, $like);
                        $q->execute();
                        $qr = $q->get_result();
                        if ($qr && $qr->num_rows>0) {
                            $rw = $qr->fetch_assoc();
                            $mid = intval($rw['id']);
                            $u = $connection->prepare('UPDATE notifications SET resource_type = ?, resource_id = ? WHERE id = ?');
                            if ($u) {
                                $rtype='learning_material';
                                $u->bind_param('sii', $rtype, $mid, $n['nid']);
                                $u->execute();
                                $u->close();
                            }
                            $out['mapped'][] = ['notification_id'=>intval($n['nid']),'material_id'=>$mid,'title'=>$title,'note'=>'like-match'];
                        }
                        if (isset($qr) && $qr) $qr->close();
                        $q->close();
                    }
                }
                if ($pr) $pr->close();
                $p->close();
            }
        }
        $res2->close();
    }
} catch (Exception $e) { $out['errors'][] = $e->getMessage(); }

echo json_encode($out, JSON_PRETTY_PRINT);
?>