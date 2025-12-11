<?php
// Migration script: move mistakenly uploaded teacher material files
// from `teacher/actions/public/uploads/teacher` to `public/uploads/teacher`
// and update `learning_materials.file_path` DB entries accordingly.

require_once __DIR__ . '/../connection/dbsConnection.php';

echo "Starting material uploads migration...\n";

$srcDir = __DIR__ . '/teacher/actions/public/uploads/teacher';
$dstRel = 'public/uploads/teacher';
$dstDir = __DIR__ . '/../' . $dstRel;

if (!is_dir($srcDir)) { echo "Source directory not found: $srcDir\n"; exit(1); }
if (!is_dir($dstDir)) { mkdir($dstDir, 0755, true); echo "Created destination: $dstDir\n"; }

$moved = 0; $skipped = 0;
$files = glob($srcDir . '/*');
foreach ($files as $f) {
    if (!is_file($f)) continue;
    $bn = basename($f);
    $dest = $dstDir . '/' . $bn;
    if (file_exists($dest)) { echo "Skipping, dest exists: $bn\n"; $skipped++; continue; }
    if (@rename($f, $dest)) { echo "Moved: $bn\n"; $moved++; } else { echo "Failed to move: $bn\n"; }
}

echo "Files moved: $moved, skipped: $skipped\n";

// Update DB paths if connection available
if (isset($connection) && $connection) {
    $old = 'teacher/actions/public/uploads/teacher/';
    $new = $dstRel . '/';
    $sql = "UPDATE learning_materials SET file_path = REPLACE(file_path, '" . $connection->real_escape_string($old) . "', '" . $connection->real_escape_string($new) . "') WHERE file_path LIKE '" . $connection->real_escape_string($old) . "%'";
    if ($connection->query($sql) === false) {
        echo "DB update failed: " . $connection->error . "\n";
        exit(1);
    }
    echo "DB updated, affected rows: " . $connection->affected_rows . "\n";
} else {
    echo "No DB connection available; please run SQL manually:\n";
    echo "UPDATE learning_materials SET file_path = REPLACE(file_path, 'teacher/actions/public/uploads/teacher/', 'public/uploads/teacher/') WHERE file_path LIKE 'teacher/actions/public/uploads/teacher/%';\n";
}

echo "Migration complete.\n";

?>
