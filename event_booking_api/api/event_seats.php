
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/database.php";

function sendResponse($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");

    sendResponse(405, [
        "success" => false,
        "message" => "Method not allowed"
    ]);
}

$eventId = filter_input(
    INPUT_GET,
    "event_id",
    FILTER_VALIDATE_INT
);

if (!$eventId || $eventId <= 0) {
    sendResponse(400, [
        "success" => false,
        "message" => "Valid event_id is required"
    ]);
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        sendResponse(500, [
            "success" => false,
            "message" => "Database connection failed"
        ]);
    }

    $eventStmt = $conn->prepare("
        SELECT
            event_id,
            event_name,
            seating_type,
            total_tickets,
            available_tickets
        FROM events
        WHERE event_id = :event_id
        LIMIT 1
    ");

    $eventStmt->execute([
        ":event_id" => $eventId
    ]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        sendResponse(404, [
            "success" => false,
            "message" => "Event not found"
        ]);
    }

    if ($event["seating_type"] !== "assigned") {
        sendResponse(400, [
            "success" => false,
            "message" => "This event uses general admission"
        ]);
    }

    $seatStmt = $conn->prepare("
        SELECT
            seat_id,
            seat_number,
            CASE
                WHEN booking_id IS NULL THEN 'available'
                ELSE 'booked'
            END AS status
        FROM event_seats
        WHERE event_id = :event_id
        ORDER BY
            LEFT(seat_number, 1),
            CAST(SUBSTRING(seat_number, 2) AS UNSIGNED)
    ");

    $seatStmt->execute([
        ":event_id" => $eventId
    ]);

    $seats = $seatStmt->fetchAll(PDO::FETCH_ASSOC);

    $availableSeats = 0;

    foreach ($seats as $seat) {
        if ($seat["status"] === "available") {
            $availableSeats++;
        }
    }

    sendResponse(200, [
        "success" => true,
        "event" => $event,
        "total_seats" => count($seats),
        "available_seats" => $availableSeats,
        "seats" => $seats
    ]);

} catch (PDOException $exception) {
    error_log(
        "Seat availability API error: " .
        $exception->getMessage()
    );

    sendResponse(500, [
        "success" => false,
        "message" => "Unable to retrieve seats"
    ]);
}
?>
