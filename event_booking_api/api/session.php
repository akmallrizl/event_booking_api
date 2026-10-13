
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);

    exit;
}

if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Not logged in",
        "logged_in" => false
    ]);

    exit;
}

echo json_encode([
    "success" => true,
    "logged_in" => true,
    "data" => [
        "user_id" => (int)$_SESSION["user_id"],
        "name" => $_SESSION["name"] ?? "",
        "role" => $_SESSION["role"] ?? ""
    ]
], JSON_PRETTY_PRINT);
?>
