<?php
// Returns minimal info about the currently logged-in user,
// including optional assigned_room if that column exists.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../../database.php";

if (empty($_SESSION["user_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Not authenticated",
        "data" => null,
    ]);
    exit;
}

$userId = intval($_SESSION["user_id"]);

try {
    // Check if assigned_room column exists
    $hasAssignedRoom = false;
    $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'assigned_room'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasAssignedRoom = true;
    }

    if ($hasAssignedRoom) {
        $stmt = $conn->prepare(
            "SELECT id, email, assigned_room FROM users WHERE id = ? LIMIT 1"
        );
    } else {
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE id = ? LIMIT 1");
    }

    if (!$stmt) {
        throw new Exception("Unable to prepare user query");
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user) {
        echo json_encode([
            "success" => false,
            "message" => "User not found",
            "data" => null,
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "data" => $user,
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "data" => null,
    ]);
}


