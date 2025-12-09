<?php
header("Content-Type: application/json");
require_once("../../database.php");

$token = trim($_GET["token"] ?? "");

if (!$token) {
    echo json_encode(["success" => false, "message" => "Missing token"]);
    exit;
}

$stmt = $conn->prepare("SELECT email, activation_code, is_active FROM teachers WHERE qr_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid QR token"]);
    exit;
}

echo json_encode([
    "success" => true,
    "email" => $data["email"],
    "is_active" => (int)$data["is_active"],
    "activation_code" => $data["is_active"] ?
        null :
        "(cannot send hash, but JS will use token instead)"
]);
exit;
