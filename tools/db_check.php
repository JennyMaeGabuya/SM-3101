<?php
// Simple DB check - open this in your browser at /LearnHub/tools/db_check.php
require_once __DIR__ . '/../connection/dbsConnection.php';
header('Content-Type: text/plain; charset=utf-8');
if (!$connection) {
    echo "DB connection not available. Check connection settings in connection/dbsConnection.php\n";
    exit(1);
}
// Show current database
$res = $connection->query("SELECT DATABASE() AS db");
$row = $res ? $res->fetch_assoc() : null;
echo "Connected to database: " . ($row['db'] ?? '<unknown>') . "\n\n";
// List tables
$res = $connection->query("SHOW TABLES");
if (!$res) {
    echo "Failed to list tables: " . $connection->error . "\n";
    exit(1);
}
$tables = [];
while ($r = $res->fetch_row()) { $tables[] = $r[0]; }
echo "Tables (" . count($tables) . "):\n";
foreach ($tables as $t) { echo " - $t\n"; }

// Quick existence checks
$needed = ['notifications','notification_reads','quizzes','quiz_questions','quiz_attempts','activities','performance_tasks'];
echo "\nQuick checks:\n";
foreach ($needed as $n) {
    $exists = in_array($n, $tables) ? 'OK' : 'MISSING';
    echo " - $n: $exists\n";
}

$connection->close();
