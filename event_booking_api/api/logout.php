
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}

// Only accept requests from the same-origin frontend.
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

// Reject requests from a different origin.
$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

if ($origin !== "") {
    $expectedOrigin =
        (!empty($_SERVER["HTTPS"]) &&
         $_SERVER["HTTPS"] !== "off"
            ? "https"
            : "http")
        . "://"
        . $_SERVER["HTTP_HOST"];

    if ($origin !== $expectedOrigin) {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Cross-origin request rejected"
        ]);
        exit;
    }
}

// Clear all session data.
$_SESSION = [];

// Delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 42000,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax"
        ]
    );
}

// Destroy the server-side session.
session_destroy();

http_response_code(200);

echo json_encode([
    "success" => true,
    "message" => "Logout successful"
], JSON_PRETTY_PRINT);
?>
