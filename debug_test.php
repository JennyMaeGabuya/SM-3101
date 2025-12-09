<?php
// Test script to verify data flow
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once(__DIR__ . "/database.php");

echo "<h2>SANROOM Debug Test</h2>";

// Test 1: Check if schedule_participants table exists
echo "<h3>1. Check schedule_participants table:</h3>";
$result = $conn->query("SELECT COUNT(*) as cnt FROM schedule_participants");
$row = $result->fetch_assoc();
echo "Records in schedule_participants: " . $row['cnt'] . "<br>";

// Test 2: Check schedules with room info
echo "<h3>2. Schedules with room assignments (first 5):</h3>";
$result = $conn->query("SELECT id, join_code, room, status FROM schedules LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Schedule ID: {$row['id']}, Code: {$row['join_code']}, Room: {$row['room']}, Status: {$row['status']}<br>";
    }
} else {
    echo "No schedules found<br>";
}

// Test 3: Check rooms
echo "<h3>3. Rooms in database (first 5):</h3>";
$result = $conn->query("SELECT room_id, room_name, capacity FROM rooms LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Room ID: {$row['room_id']}, Name: {$row['room_name']}, Capacity: {$row['capacity']}<br>";
    }
} else {
    echo "No rooms found<br>";
}

// Test 4: Test the exact query from get_all_rooms.php
echo "<h3>4. Test get_all_rooms.php query (participant counts):</h3>";
$sql = "
    SELECT 
        r.room_id, 
        r.room_name,
        r.capacity,
        r.manual_status,
        r.is_archived,
        r.special_access_code,
        COALESCE(
            (
                SELECT COUNT(DISTINCT sp.student_id)
                FROM schedule_participants sp
                INNER JOIN schedules s ON sp.schedule_id = s.id
                WHERE r.room_name = r.room_id 
                AND s.status != 'archived'
            ),
            0
        ) AS current_participants
        
    FROM rooms r
    ORDER BY r.room_name ASC
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Room: {$row['room_name']}, Capacity: {$row['capacity']}, Current Participants: {$row['current_participants']}<br>";
    }
} else {
    echo "No rooms or query error: " . $conn->error . "<br>";
}

// Test 5: Check API endpoint
echo "<h3>5. Test get_all_rooms.php API:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/SANROOM/public/backend/get_all_rooms.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "API Success! Data returned: <pre>";
        print_r($data['data']);
        echo "</pre>";
    } else {
        echo "API Error: " . $data['message'] . "<br>";
    }
} else {
    echo "HTTP Error: $httpCode<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
}

$conn->close();
