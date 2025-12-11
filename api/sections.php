<?php
// Return JSON list of sections
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';

try {
    if (!isset($connection) || !$connection) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection not available']);
        exit;
    }
    $rows = [];
    $res = $connection->query('SELECT id, name FROM sections ORDER BY name ASC');
    if ($res) {
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $res->close();
    }
    echo json_encode(['success' => true, 'sections' => $rows]);
} catch (Exception $e) {
    error_log('api/sections error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

?>
