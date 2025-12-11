<?php
require_once __DIR__ . '/../connection/dbsConnection.php';

if (!isset($connection) || !$connection) {
    echo "Database connection unavailable. Start MySQL and check `connection/dbsConnection.php`.\n";
    exit(1);
}

$items = [
    [
        'table' => 'accounts',
        'username' => 'student',
        'email' => 'student@batstateu.edu.ph',
        'password' => 'Demo@2024'
    ],
    [
        'table' => 'teachers',
        'username' => 'teacher',
        'email' => 'teacher@batstateu.edu.ph',
        'password' => 'Teach@2024',
        'first_name' => 'Teacher',
        'last_name' => 'Account',
        'department' => 'Computer Science'
    ]
];

foreach ($items as $item) {
    $email = $item['email'];
    $username = $item['username'];
    $plain = $item['password'];
    $hash = password_hash($plain, PASSWORD_DEFAULT);

    if ($item['table'] === 'accounts') {
        $check = $connection->prepare('SELECT accountId FROM accounts WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $res = $check->get_result();
        $row = $res->fetch_assoc();
        $check->close();

        if ($row) {
            $upd = $connection->prepare('UPDATE accounts SET username = ?, email = ?, password = ?, updated_at = NOW() WHERE accountId = ?');
            $upd->bind_param('sssi', $username, $email, $hash, $row['accountId']);
            $ok = $upd->execute();
            $upd->close();
            echo "Updated account: {$email} - " . ($ok ? "OK\n" : "FAILED\n");
        } else {
            $ins = $connection->prepare('INSERT INTO accounts (username, email, password) VALUES (?, ?, ?)');
            $ins->bind_param('sss', $username, $email, $hash);
            $ok = $ins->execute();
            $ins->close();
            echo "Inserted account: {$email} - " . ($ok ? "OK\n" : "FAILED\n");
        }

    } else {
        // teachers
        $check = $connection->prepare('SELECT teacher_id FROM teachers WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $res = $check->get_result();
        $row = $res->fetch_assoc();
        $check->close();

        if ($row) {
            $upd = $connection->prepare('UPDATE teachers SET username = ?, email = ?, password = ?, first_name = ?, last_name = ?, department = ?, updated_at = NOW() WHERE teacher_id = ?');
            $upd->bind_param('ssssssi', $username, $email, $hash, $item['first_name'], $item['last_name'], $item['department'], $row['teacher_id']);
            $ok = $upd->execute();
            $upd->close();
            echo "Updated teacher: {$email} - " . ($ok ? "OK\n" : "FAILED\n");
        } else {
            $ins = $connection->prepare('INSERT INTO teachers (username, email, password, first_name, last_name, department) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->bind_param('ssssss', $username, $email, $hash, $item['first_name'], $item['last_name'], $item['department']);
            $ok = $ins->execute();
            $ins->close();
            echo "Inserted teacher: {$email} - " . ($ok ? "OK\n" : "FAILED\n");
        }
    }
}

echo "Done. You can now login with the demo credentials.\n";

?>
