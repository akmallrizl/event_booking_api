
<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// Only administrators can modify venues.
// GET requests are available for reading venue information.
$method = $_SERVER["REQUEST_METHOD"];

if (in_array($method, ["POST", "PUT", "DELETE"], true)) {
    require_once __DIR__ . "/admin_auth.php";
}

require_once __DIR__ . "/../config/database.php";

function venueResponse($status, $success, $message, $extra = []) {
    http_response_code($status);

    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra), JSON_PRETTY_PRINT);

    exit;
}

function venueId() {
    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id === null || $id <= 0) {
        venueResponse(400, false, "Valid venue ID is required");
    }

    return $id;
}

function venueBody() {
    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {
        venueResponse(400, false, "Invalid JSON body");
    }

    $name = trim((string)($data["venue_name"] ?? ""));
    $location = trim((string)($data["location"] ?? ""));

    $capacity = filter_var(
        $data["capacity"] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($name === "" || $location === "") {
        venueResponse(
            400,
            false,
            "Venue name and location are required"
        );
    }

    if ($capacity === false || $capacity === null || $capacity <= 0) {
        venueResponse(
            400,
            false,
            "Capacity must be a positive integer"
        );
    }

    return [
        "venue_name" => $name,
        "location" => $location,
        "capacity" => $capacity
    ];
}

try {
    $db = (new Database())->getConnection();

    if (!$db) {
        venueResponse(500, false, "Database connection failed");
    }

    // GET: Retrieve venues
    // Customers, organisers and administrators can read venue details.
    if ($method === "GET") {
        if (isset($_GET["id"])) {
            $id = venueId();

            $stmt = $db->prepare("
                SELECT
                    venue_id,
                    venue_name,
                    location,
                    capacity,
                    created_at
                FROM venues
                WHERE venue_id = ?
            ");

            $stmt->execute([$id]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$venue) {
                venueResponse(404, false, "Venue not found");
            }

            venueResponse(200, true, "Venue retrieved", [
                "data" => $venue
            ]);
        }

        $stmt = $db->query("
            SELECT
                venue_id,
                venue_name,
                location,
                capacity,
                created_at
            FROM venues
            ORDER BY venue_id ASC
        ");

        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        venueResponse(200, true, "Venues retrieved", [
            "count" => count($venues),
            "data" => $venues
        ]);
    }

    // POST: Create venue
    // Administrator only.
    if ($method === "POST") {
        $venue = venueBody();

        $stmt = $db->prepare("
            INSERT INTO venues (
                venue_name,
                location,
                capacity
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $venue["venue_name"],
            $venue["location"],
            $venue["capacity"]
        ]);

        venueResponse(201, true, "Venue created successfully", [
            "venue_id" => (int)$db->lastInsertId()
        ]);
    }

    // PUT: Update venue
    // Administrator only.
    if ($method === "PUT") {
        $id = venueId();
        $venue = venueBody();

        $check = $db->prepare("
            SELECT venue_id
            FROM venues
            WHERE venue_id = ?
        ");

        $check->execute([$id]);

        if (!$check->fetch()) {
            venueResponse(404, false, "Venue not found");
        }

        $stmt = $db->prepare("
            UPDATE venues
            SET
                venue_name = ?,
                location = ?,
                capacity = ?
            WHERE venue_id = ?
        ");

        $stmt->execute([
            $venue["venue_name"],
            $venue["location"],
            $venue["capacity"],
            $id
        ]);

        venueResponse(200, true, "Venue updated successfully", [
            "venue_id" => $id
        ]);
    }

    // DELETE: Delete venue
    // Administrator only.
    if ($method === "DELETE") {
        $id = venueId();

        $check = $db->prepare("
            SELECT venue_id
            FROM venues
            WHERE venue_id = ?
        ");

        $check->execute([$id]);

        if (!$check->fetch()) {
            venueResponse(404, false, "Venue not found");
        }

        try {
            $stmt = $db->prepare("
                DELETE FROM venues
                WHERE venue_id = ?
            ");

            $stmt->execute([$id]);

            venueResponse(200, true, "Venue deleted successfully", [
                "venue_id" => $id
            ]);

        } catch (PDOException $e) {
            venueResponse(
                409,
                false,
                "Venue cannot be deleted because it is used by an event"
            );
        }
    }

    venueResponse(405, false, "Method not allowed");

} catch (PDOException $e) {
    error_log($e->getMessage());

    venueResponse(
        500,
        false,
        "Database operation failed"
    );
}
?>
