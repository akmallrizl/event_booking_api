
<?php
// organiser_auth.php
// Protect event management operations.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Please log in first"
    ]);

    exit;
}

$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["organiser", "admin"], true)) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Organiser or administrator access required"
    ]);

    exit;
}

// Basic protection for browser-based modifications.
if (in_array($_SERVER["REQUEST_METHOD"], ["POST", "PUT", "PATCH", "DELETE"], true)) {

    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";

    if ($origin !== "") {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = explode(":", $_SERVER["HTTP_HOST"] ?? "")[0];

        if (strcasecmp($originHost ?? "", $requestHost) !== 0) {
            http_response_code(403);

            echo json_encode([
                "success" => false,
                "message" => "Invalid request origin"
            ]);

            exit;
        }
    }

    if (
        ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") !==
        "XMLHttpRequest"
    ) {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Missing required request header"
        ]);

        exit;
    }
}
?>
