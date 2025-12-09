<?php
// Use the main database connection file
require_once __DIR__ . "/../../database.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data["id"]) || empty($data["status"])) {
    echo json_encode(["success" => false, "message" => "ID or status missing"]);
    exit;
}

$id = intval($data["id"]);
$status = $data["status"];

$allowed = ["active", "archived", "online", "suspended", "face-to-face"];
if (!in_array($status, $allowed)) {
    echo json_encode(["success" => false, "message" => "Invalid status"]);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE schedules SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();

    // Return updated record
    $stmt = $conn->prepare("SELECT * FROM schedules WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $newData = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "success" => true,
        "message" => "Status updated",
        "data" => $newData
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
