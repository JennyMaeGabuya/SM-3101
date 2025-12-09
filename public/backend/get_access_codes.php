<?php
// Returns (and lazily generates) current teacher & student access codes.
// Codes are now permanent (non-expiring).

header("Content-Type: application/json");

require_once __DIR__ . "/../../database.php";

$roles = ["teacher", "student"];
$result = [];

function generate_code($length = 8)
{
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $code = "";
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

try {
    foreach ($roles as $role) {
        // Try to get the existing permanent code for this role
        $stmt = $conn->prepare(
            "SELECT code 
             FROM login_access_codes 
             WHERE role = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if ($row) {
            $result[$role] = ["code" => $row["code"]];
            continue;
        }

        // No code exists, generate a new permanent one
        $code = generate_code();

        // Database insert query simplified to only use role and code
        $insert = $conn->prepare(
            "INSERT INTO login_access_codes (role, code) VALUES (?, ?)"
        );
        $insert->bind_param("ss", $role, $code);
        $insert->execute();

        $result[$role] = ["code" => $code];
    }

    echo json_encode([
        "success" => true,
        "data" => $result,
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
    ]);
}
