<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../connection/dbsConnection.php';
$out = ['ok'=>false,'marked'=>0];
if (!isset($connection) || !$connection) { echo json_encode($out); exit; }
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode($out); exit; }
$acct = intval($_SESSION['user_id']);

// accept JSON or form
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$ids = [];
if (is_array($data) && isset($data['ids']) && is_array($data['ids'])) {
    $ids = array_map('intval', $data['ids']);
} elseif (isset($_POST['id'])) {
    $ids = [intval($_POST['id'])];
} elseif ($raw && is_numeric($raw)) {
    $ids = [intval($raw)];
}
if (empty($ids)) { echo json_encode($out); exit; }

try {
    // ensure table exists
    $connection->query("CREATE TABLE IF NOT EXISTS notification_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        account_id INT NOT NULL,
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_nr (notification_id, account_id),
        INDEX idx_account (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $marked = 0;
    $stmt = $connection->prepare('INSERT INTO notification_reads (notification_id, account_id, read_at) VALUES (?, ?, NOW())');
    if ($stmt) {
        foreach ($ids as $nid) {
            try {
                $stmt->bind_param('ii', $nid, $acct);
                $res = $stmt->execute();
                if ($res) $marked++;
            } catch (Exception $e) {
                // ignore duplicates/other errors
            }
        }
        $stmt->close();
    }
    $out['ok'] = true;
    $out['marked'] = $marked;
} catch (Exception $e) {
    error_log('notifications_mark_read error: '.$e->getMessage());
}

echo json_encode($out);
exit;
