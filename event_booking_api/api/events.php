
<?php
// EventHub Events API
// General Admission Version
// GET: Public
// POST, PUT, DELETE: Organiser / Admin

header("Content-Type: application/json; charset=UTF-8");

$method = $_SERVER["REQUEST_METHOD"];

if (in_array($method, ["POST", "PUT", "DELETE"], true)) {
    require_once __DIR__ . "/organiser_auth.php";
}

require_once __DIR__ . "/../config/database.php";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

function respond($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function positiveId($value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : null;
}

function bodyData() {
    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data)) {
        respond(400, [
            "success" => false,
            "message" => "Invalid JSON data"
        ]);
    }

    return $data;
}

function validEventInput($data) {
    $fields = [
        "venue_id",
        "event_name",
        "description",
        "event_date",
        "event_time",
        "ticket_price",
        "total_tickets"
    ];

    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === "") {
            respond(400, [
                "success" => false,
                "message" => "Missing required field: " . $field
            ]);
        }
    }

    $venueId = positiveId($data["venue_id"]);
    $name = trim((string)$data["event_name"]);
    $description = trim((string)$data["description"]);

    $price = filter_var(
        $data["ticket_price"],
        FILTER_VALIDATE_FLOAT
    );

    $tickets = filter_var(
        $data["total_tickets"],
        FILTER_VALIDATE_INT
    );

    $status = strtolower(trim(
        (string)($data["status"] ?? "upcoming")
    ));

    $date = (string)$data["event_date"];
    $time = (string)$data["event_time"];

    $dt = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $date
    );

    $validDate = $dt && $dt->format("Y-m-d") === $date;

    $validTime = preg_match(
        '/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
        $time
    );

    $allowedStatuses = [
        "upcoming",
        "ongoing",
        "completed",
        "cancelled"
    ];

    if (
        !$venueId ||
        $name === "" ||
        $description === "" ||
        $price === false ||
        $price < 0 ||
        $tickets === false ||
        $tickets <= 0 ||
        !$validDate ||
        !$validTime ||
        !in_array($status, $allowedStatuses, true)
    ) {
        respond(400, [
            "success" => false,
            "message" => "Invalid event details"
        ]);
    }

    return [
        "venue_id" => $venueId,
        "event_name" => $name,
        "description" => $description,
        "event_date" => $date,
        "event_time" => $time,
        "ticket_price" => $price,
        "total_tickets" => $tickets,
        "status" => $status
    ];
}

function ensureVenue($db, $venueId, $tickets) {
    $stmt = $db->prepare(
        "SELECT capacity FROM venues WHERE venue_id = ?"
    );

    $stmt->execute([$venueId]);
    $venue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        respond(404, [
            "success" => false,
            "message" => "Venue not found"
        ]);
    }

    if ($tickets > (int)$venue["capacity"]) {
        respond(400, [
            "success" => false,
            "message" => "Total tickets cannot exceed venue capacity"
        ]);
    }
}

function actorCanManage($event) {
    return (
        ($_SESSION["role"] ?? "") === "admin" ||
        (int)$event["organiser_id"] ===
        (int)$_SESSION["user_id"]
    );
}

// Keep seating_type in GET for compatibility
// with existing database and customer pages.
$selectSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.description,
        e.event_date,
        e.event_time,
        e.ticket_price,
        e.total_tickets,
        e.available_tickets,
        e.status,
        e.seating_type,
        u.user_id AS organiser_id,
        u.name AS organiser_name,
        v.venue_id,
        v.venue_name,
        v.location,
        v.capacity
    FROM events e
    INNER JOIN users u
        ON e.organiser_id = u.user_id
    INNER JOIN venues v
        ON e.venue_id = v.venue_id
";

try {

    // =========================
    // GET EVENTS
    // =========================

    if ($method === "GET") {

        if (isset($_GET["id"])) {
            $id = positiveId($_GET["id"]);

            if (!$id) {
                respond(400, [
                    "success" => false,
                    "message" => "Invalid event ID"
                ]);
            }

            $stmt = $db->prepare(
                $selectSql . " WHERE e.event_id = ? LIMIT 1"
            );

            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                respond(404, [
                    "success" => false,
                    "message" => "Event not found"
                ]);
            }

            respond(200, [
                "success" => true,
                "data" => $event
            ]);
        }

        $where = [];
        $params = [];

        if (
            isset($_GET["status"]) &&
            $_GET["status"] !== ""
        ) {
            $status = strtolower(
                trim((string)$_GET["status"])
            );

            if (!in_array($status, [
                "upcoming",
                "ongoing",
                "completed",
                "cancelled"
            ], true)) {
                respond(400, [
                    "success" => false,
                    "message" => "Invalid event status"
                ]);
            }

            $where[] = "e.status = ?";
            $params[] = $status;
        }

        foreach (["venue_id", "organiser_id"] as $field) {
            if (
                isset($_GET[$field]) &&
                $_GET[$field] !== ""
            ) {
                $id = positiveId($_GET[$field]);

                if (!$id) {
                    respond(400, [
                        "success" => false,
                        "message" => "Invalid " . $field
                    ]);
                }

                $where[] = "e." . $field . " = ?";
                $params[] = $id;
            }
        }

        $sql = $selectSql;

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= "
            ORDER BY
                e.event_date ASC,
                e.event_time ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        respond(200, [
            "success" => true,
            "count" => count($events),
            "data" => $events
        ]);
    }

    // =========================
    // CREATE EVENT
    // =========================

    if ($method === "POST") {
        $data = bodyData();
        $input = validEventInput($data);

        $organiserId = (int)$_SESSION["user_id"];

        if (
            ($_SESSION["role"] ?? "") === "admin" &&
            isset($data["organiser_id"])
        ) {
            $organiserId = positiveId(
                $data["organiser_id"]
            );

            if (!$organiserId) {
                respond(400, [
                    "success" => false,
                    "message" => "Invalid organiser_id"
                ]);
            }
        }

        $stmt = $db->prepare(
            "SELECT role FROM users WHERE user_id = ?"
        );

        $stmt->execute([$organiserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$user ||
            !in_array(
                $user["role"],
                ["organiser", "admin"],
                true
            )
        ) {
            respond(400, [
                "success" => false,
                "message" => "Selected user is not an organiser"
            ]);
        }

        ensureVenue(
            $db,
            $input["venue_id"],
            $input["total_tickets"]
        );

        // New events are General Admission.
        // No event_seats records are generated.

        $stmt = $db->prepare("
            INSERT INTO events (
                organiser_id,
                venue_id,
                event_name,
                description,
                event_date,
                event_time,
                ticket_price,
                total_tickets,
                available_tickets,
                status,
                seating_type
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $organiserId,
            $input["venue_id"],
            $input["event_name"],
            $input["description"],
            $input["event_date"],
            $input["event_time"],
            $input["ticket_price"],
            $input["total_tickets"],
            $input["total_tickets"],
            $input["status"],
            "general"
        ]);

        respond(201, [
            "success" => true,
            "message" => "Event created successfully",
            "event_id" => (int)$db->lastInsertId()
        ]);
    }

    // =========================
    // UPDATE EVENT
    // =========================

    if ($method === "PUT") {
        $id = positiveId($_GET["id"] ?? null);

        if (!$id) {
            respond(400, [
                "success" => false,
                "message" => "Valid event ID is required"
            ]);
        }

        $data = bodyData();
        $input = validEventInput($data);

        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT
                organiser_id,
                total_tickets,
                available_tickets,
                seating_type
            FROM events
            WHERE event_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            $db->rollBack();

            respond(404, [
                "success" => false,
                "message" => "Event not found"
            ]);
        }

        if (!actorCanManage($event)) {
            $db->rollBack();

            respond(403, [
                "success" => false,
                "message" => "You can only edit your own events"
            ]);
        }

        // Protect old assigned-seating events.
        // They must be migrated separately to avoid
        // damaging existing bookings.
        if ($event["seating_type"] === "assigned") {
            $db->rollBack();

            respond(409, [
                "success" => false,
                "message" =>
                    "This event uses the old assigned-seating system. " .
                    "It must be migrated before editing."
            ]);
        }

        $sold =
            (int)$event["total_tickets"] -
            (int)$event["available_tickets"];

        if ($input["total_tickets"] < $sold) {
            $db->rollBack();

            respond(400, [
                "success" => false,
                "message" =>
                    "Total tickets cannot be less than tickets already booked"
            ]);
        }

        ensureVenue(
            $db,
            $input["venue_id"],
            $input["total_tickets"]
        );

        $available = $input["total_tickets"] - $sold;

        $stmt = $db->prepare("
            UPDATE events
            SET
                venue_id = ?,
                event_name = ?,
                description = ?,
                event_date = ?,
                event_time = ?,
                ticket_price = ?,
                total_tickets = ?,
                available_tickets = ?,
                status = ?
            WHERE event_id = ?
        ");

        $stmt->execute([
            $input["venue_id"],
            $input["event_name"],
            $input["description"],
            $input["event_date"],
            $input["event_time"],
            $input["ticket_price"],
            $input["total_tickets"],
            $available,
            $input["status"],
            $id
        ]);

        $db->commit();

        respond(200, [
            "success" => true,
            "message" => "Event updated successfully",
            "event_id" => $id
        ]);
    }

    // =========================
    // DELETE EVENT
    // =========================

    if ($method === "DELETE") {
        $id = positiveId($_GET["id"] ?? null);

        if (!$id) {
            respond(400, [
                "success" => false,
                "message" => "Valid event ID is required"
            ]);
        }

        $stmt = $db->prepare("
            SELECT organiser_id
            FROM events
            WHERE event_id = ?
        ");

        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            respond(404, [
                "success" => false,
                "message" => "Event not found"
            ]);
        }

        if (!actorCanManage($event)) {
            respond(403, [
                "success" => false,
                "message" => "You can only delete your own events"
            ]);
        }

        $stmt = $db->prepare(
            "DELETE FROM events WHERE event_id = ?"
        );

        $stmt->execute([$id]);

        respond(200, [
            "success" => true,
            "message" => "Event deleted successfully",
            "event_id" => $id
        ]);
    }

    respond(405, [
        "success" => false,
        "message" => "Method not allowed"
    ]);

} catch (PDOException $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    $code = (
        $method === "DELETE" &&
        $e->getCode() === "23000"
    ) ? 409 : 500;

    respond($code, [
        "success" => false,
        "message" => $code === 409
            ? "Event cannot be deleted because related records exist"
            : "Database operation failed"
    ]);
}
?>
