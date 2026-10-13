
<?php
// Shared administrator authentication guard.
// Include this file before any database operation.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

// Require a logged-in account.
if (empty($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in first"
    ]);

    exit;
}

// Only administrators can use protected APIs.
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied. Administrator only."
    ]);

    exit;
}

// Protect state-changing requests from cross-origin access.
if (in_array(
    $_SERVER["REQUEST_METHOD"],
    ["POST", "PUT", "PATCH", "DELETE"],
    true
)) {
    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";

    $expectedOrigin =
        (!empty($_SERVER["HTTPS"]) &&
         $_SERVER["HTTPS"] !== "off"
            ? "https"
            : "http")
        . "://"
        . $_SERVER["HTTP_HOST"];

    if ($origin !== "" && $origin !== $expectedOrigin) {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Cross-origin request rejected"
        ]);

        exit;
    }

    if (
        ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") !==
        "XMLHttpRequest"
    ) {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Invalid request"
        ]);

        exit;
    }
}
?>
