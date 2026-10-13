<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

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

$method = $_SERVER["REQUEST_METHOD"];


// ======================================================
// BASE SELECT SQL
// ======================================================

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


// ======================================================
// GET
// ======================================================

if ($method === "GET") {

    // --------------------------------------------------
    // GET SINGLE EVENT
    // Example: events.php?id=1
    // --------------------------------------------------

    if (isset($_GET["id"])) {

        $id = filter_var(
            $_GET["id"],
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id <= 0) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Invalid event ID"
            ], JSON_PRETTY_PRINT);

            exit;
        }

        try {

            $sql = $selectSql . "
                WHERE e.event_id = :id
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);

            $stmt->bindValue(
                ":id",
                $id,
                PDO::PARAM_INT
            );

            $stmt->execute();

            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($event) {

                http_response_code(200);

                echo json_encode([
                    "success" => true,
                    "data" => $event
                ], JSON_PRETTY_PRINT);

            } else {

                http_response_code(404);

                echo json_encode([
                    "success" => false,
                    "message" => "Event not found"
                ], JSON_PRETTY_PRINT);
            }

        } catch (PDOException $e) {

            http_response_code(500);

            echo json_encode([
                "success" => false,
                "message" => "Failed to retrieve event",
                "error" => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }

        exit;
    }


    // --------------------------------------------------
    // GET ALL EVENTS + FILTERING
    //
    // Examples:
    // events.php
    // events.php?status=upcoming
    // events.php?venue_id=1
    // events.php?organiser_id=2
    // events.php?status=upcoming&venue_id=1
    // --------------------------------------------------

    try {

        $conditions = [];
        $params = [];

        // FILTER BY STATUS
        if (isset($_GET["status"]) && $_GET["status"] !== "") {

            $status = strtolower(trim($_GET["status"]));

            $allowedStatus = [
                "upcoming",
                "ongoing",
                "completed",
                "cancelled"
            ];

            if (!in_array($status, $allowedStatus, true)) {

                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "Invalid event status"
                ], JSON_PRETTY_PRINT);

                exit;
            }

            $conditions[] = "e.status = :status";
            $params[":status"] = $status;
        }


        // FILTER BY VENUE
        if (isset($_GET["venue_id"]) && $_GET["venue_id"] !== "") {

            $venue_id = filter_var(
                $_GET["venue_id"],
                FILTER_VALIDATE_INT
            );

            if ($venue_id === false || $venue_id <= 0) {

                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "Invalid venue ID"
                ], JSON_PRETTY_PRINT);

                exit;
            }

            $conditions[] = "e.venue_id = :venue_id";
            $params[":venue_id"] = $venue_id;
        }


        // FILTER BY ORGANISER
        if (
            isset($_GET["organiser_id"]) &&
            $_GET["organiser_id"] !== ""
        ) {

            $organiser_id = filter_var(
                $_GET["organiser_id"],
                FILTER_VALIDATE_INT
            );

            if ($organiser_id === false || $organiser_id <= 0) {

                http_response_code(400);

                echo json_encode([
                    "success" => false,
                    "message" => "Invalid organiser ID"
                ], JSON_PRETTY_PRINT);

                exit;
            }

            $conditions[] = "e.organiser_id = :organiser_id";
            $params[":organiser_id"] = $organiser_id;
        }


        $sql = $selectSql;

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= "
            ORDER BY
                e.event_date ASC,
                e.event_time ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);

        echo json_encode([
            "success" => true,
            "count" => count($events),
            "data" => $events
        ], JSON_PRETTY_PRINT);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Failed to retrieve events",
            "error" => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }

    exit;
}


// ======================================================
// POST - CREATE NEW EVENT
// ======================================================

if ($method === "POST") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!$data) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON data"
        ]);

        exit;
    }


    $requiredFields = [
        "organiser_id",
        "venue_id",
        "event_name",
        "description",
        "event_date",
        "event_time",
        "ticket_price",
        "total_tickets"
    ];


    foreach ($requiredFields as $field) {

        if (!isset($data[$field]) || $data[$field] === "") {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Missing required field: " . $field
            ]);

            exit;
        }
    }


    $organiser_id = (int)$data["organiser_id"];
    $venue_id = (int)$data["venue_id"];

    $event_name = trim($data["event_name"]);
    $description = trim($data["description"]);

    $event_date = $data["event_date"];
    $event_time = $data["event_time"];

    $ticket_price = (float)$data["ticket_price"];
    $total_tickets = (int)$data["total_tickets"];

    $available_tickets = $total_tickets;

    $status = strtolower(
        trim($data["status"] ?? "upcoming")
    );


    if ($organiser_id <= 0 || $venue_id <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid organiser_id or venue_id"
        ]);

        exit;
    }


    if ($event_name === "" || $description === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Event name and description cannot be empty"
        ]);

        exit;
    }


    if ($ticket_price < 0 || $total_tickets <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid ticket price or total tickets"
        ]);

        exit;
    }


    $allowedStatus = [
        "upcoming",
        "ongoing",
        "completed",
        "cancelled"
    ];


    if (!in_array($status, $allowedStatus, true)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid event status"
        ]);

        exit;
    }


    try {

        // Check organiser exists and has valid role
        $organiserCheck = $db->prepare("
            SELECT user_id, role
            FROM users
            WHERE user_id = :organiser_id
            LIMIT 1
        ");

        $organiserCheck->execute([
            ":organiser_id" => $organiser_id
        ]);

        $organiser = $organiserCheck->fetch(PDO::FETCH_ASSOC);

        if (!$organiser) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Organiser not found"
            ]);

            exit;
        }

        if (!in_array($organiser["role"], ["admin", "organiser"], true)) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Selected user is not an organiser"
            ]);

            exit;
        }


        // Check venue exists
        $venueCheck = $db->prepare("
            SELECT venue_id, capacity
            FROM venues
            WHERE venue_id = :venue_id
            LIMIT 1
        ");

        $venueCheck->execute([
            ":venue_id" => $venue_id
        ]);

        $venue = $venueCheck->fetch(PDO::FETCH_ASSOC);

        if (!$venue) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Venue not found"
            ]);

            exit;
        }


        // Business rule:
        // total tickets cannot exceed venue capacity
        if ($total_tickets > (int)$venue["capacity"]) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Total tickets cannot exceed venue capacity"
            ]);

            exit;
        }


        $sql = "
            INSERT INTO events
            (
                organiser_id,
                venue_id,
                event_name,
                description,
                event_date,
                event_time,
                ticket_price,
                total_tickets,
                available_tickets,
                status
            )

            VALUES
            (
                :organiser_id,
                :venue_id,
                :event_name,
                :description,
                :event_date,
                :event_time,
                :ticket_price,
                :total_tickets,
                :available_tickets,
                :status
            )
        ";


        $stmt = $db->prepare($sql);

        $stmt->execute([
            ":organiser_id" => $organiser_id,
            ":venue_id" => $venue_id,
            ":event_name" => $event_name,
            ":description" => $description,
            ":event_date" => $event_date,
            ":event_time" => $event_time,
            ":ticket_price" => $ticket_price,
            ":total_tickets" => $total_tickets,
            ":available_tickets" => $available_tickets,
            ":status" => $status
        ]);


        $newEventId = $db->lastInsertId();


        http_response_code(201);

        echo json_encode([
            "success" => true,
            "message" => "Event created successfully",
            "event_id" => (int)$newEventId
        ], JSON_PRETTY_PRINT);


    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Failed to create event",
            "error" => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }

    exit;
}


// ======================================================
// PUT - UPDATE EVENT
// ======================================================

if ($method === "PUT") {

    if (!isset($_GET["id"])) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Event ID is required"
        ]);

        exit;
    }


    $id = filter_var(
        $_GET["id"],
        FILTER_VALIDATE_INT
    );


    if ($id === false || $id <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid event ID"
        ]);

        exit;
    }


    $data = json_decode(
        file_get_contents("php://input"),
        true
    );


    if (!$data) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON data"
        ]);

        exit;
    }


    $requiredFields = [
        "organiser_id",
        "venue_id",
        "event_name",
        "description",
        "event_date",
        "event_time",
        "ticket_price",
        "total_tickets",
        "available_tickets",
        "status"
    ];


    foreach ($requiredFields as $field) {

        if (!isset($data[$field]) || $data[$field] === "") {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Missing required field: " . $field
            ]);

            exit;
        }
    }


    $organiser_id = (int)$data["organiser_id"];
    $venue_id = (int)$data["venue_id"];
    $event_name = trim($data["event_name"]);
    $description = trim($data["description"]);
    $ticket_price = (float)$data["ticket_price"];
    $total_tickets = (int)$data["total_tickets"];
    $available_tickets = (int)$data["available_tickets"];
    $status = strtolower(trim($data["status"]));


    if ($organiser_id <= 0 || $venue_id <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid organiser_id or venue_id"
        ]);

        exit;
    }


    if (
        $ticket_price < 0 ||
        $total_tickets <= 0 ||
        $available_tickets < 0 ||
        $available_tickets > $total_tickets
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid ticket values"
        ]);

        exit;
    }


    $allowedStatus = [
        "upcoming",
        "ongoing",
        "completed",
        "cancelled"
    ];


    if (!in_array($status, $allowedStatus, true)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid event status"
        ]);

        exit;
    }


    try {

        // Check event exists
        $checkSql = "
            SELECT event_id
            FROM events
            WHERE event_id = :id
        ";

        $checkStmt = $db->prepare($checkSql);

        $checkStmt->execute([
            ":id" => $id
        ]);


        if (!$checkStmt->fetch()) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Event not found"
            ]);

            exit;
        }


        // Check organiser
        $organiserCheck = $db->prepare("
            SELECT user_id, role
            FROM users
            WHERE user_id = :organiser_id
            LIMIT 1
        ");

        $organiserCheck->execute([
            ":organiser_id" => $organiser_id
        ]);

        $organiser = $organiserCheck->fetch(PDO::FETCH_ASSOC);


        if (!$organiser) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Organiser not found"
            ]);

            exit;
        }


        if (!in_array($organiser["role"], ["admin", "organiser"], true)) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Selected user is not an organiser"
            ]);

            exit;
        }


        // Check venue
        $venueCheck = $db->prepare("
            SELECT venue_id, capacity
            FROM venues
            WHERE venue_id = :venue_id
            LIMIT 1
        ");

        $venueCheck->execute([
            ":venue_id" => $venue_id
        ]);

        $venue = $venueCheck->fetch(PDO::FETCH_ASSOC);


        if (!$venue) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Venue not found"
            ]);

            exit;
        }


        if ($total_tickets > (int)$venue["capacity"]) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Total tickets cannot exceed venue capacity"
            ]);

            exit;
        }


        $sql = "
            UPDATE events

            SET
                organiser_id = :organiser_id,
                venue_id = :venue_id,
                event_name = :event_name,
                description = :description,
                event_date = :event_date,
                event_time = :event_time,
                ticket_price = :ticket_price,
                total_tickets = :total_tickets,
                available_tickets = :available_tickets,
                status = :status

            WHERE event_id = :id
        ";


        $stmt = $db->prepare($sql);

        $stmt->execute([
            ":organiser_id" => $organiser_id,
            ":venue_id" => $venue_id,
            ":event_name" => $event_name,
            ":description" => $description,
            ":event_date" => $data["event_date"],
            ":event_time" => $data["event_time"],
            ":ticket_price" => $ticket_price,
            ":total_tickets" => $total_tickets,
            ":available_tickets" => $available_tickets,
            ":status" => $status,
            ":id" => $id
        ]);


        http_response_code(200);

        echo json_encode([
            "success" => true,
            "message" => "Event updated successfully",
            "event_id" => $id
        ], JSON_PRETTY_PRINT);


    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Failed to update event",
            "error" => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }

    exit;
}


// ======================================================
// DELETE - DELETE EVENT
// ======================================================

if ($method === "DELETE") {

    if (!isset($_GET["id"])) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Event ID is required"
        ]);

        exit;
    }


    $id = filter_var(
        $_GET["id"],
        FILTER_VALIDATE_INT
    );


    if ($id === false || $id <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid event ID"
        ]);

        exit;
    }


    try {

        // Check event exists
        $checkSql = "
            SELECT event_id
            FROM events
            WHERE event_id = :id
        ";


        $checkStmt = $db->prepare($checkSql);

        $checkStmt->execute([
            ":id" => $id
        ]);


        if (!$checkStmt->fetch()) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Event not found"
            ]);

            exit;
        }


        // Delete event
        $sql = "
            DELETE FROM events
            WHERE event_id = :id
        ";


        $stmt = $db->prepare($sql);

        $stmt->execute([
            ":id" => $id
        ]);


        http_response_code(200);

        echo json_encode([
            "success" => true,
            "message" => "Event deleted successfully",
            "event_id" => $id
        ], JSON_PRETTY_PRINT);


    } catch (PDOException $e) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Event cannot be deleted because related records exist"
        ], JSON_PRETTY_PRINT);
    }

    exit;
}


// ======================================================
// METHOD NOT ALLOWED
// ======================================================

http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Method not allowed"
], JSON_PRETTY_PRINT);

?>