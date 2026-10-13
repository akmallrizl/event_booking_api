
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");
header("Allow: GET, POST, PUT, DELETE, OPTIONS");

require_once __DIR__ . "/../config/database.php";

function respond($code, $success, $message = "", $data = null) {
    http_response_code($code);

    $result = ["success" => $success];

    if ($message !== "") {
        $result["message"] = $message;
    }

    if ($data !== null) {
        $result["data"] = $data;
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

function positiveId($value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? (int)$id : 0;
}

function jsonBody() {
    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        respond(400, false, "Invalid JSON body");
    }

    return $data;
}

function requireCustomer() {
    if (
        empty($_SESSION["user_id"]) ||
        ($_SESSION["role"] ?? "") !== "customer"
    ) {
        respond(401, false, "Please log in as a customer");
    }

    return (int)$_SESSION["user_id"];
}

function checkCsrf() {
    if (
        ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") !==
        "XMLHttpRequest"
    ) {
        respond(
            403,
            false,
            "Missing X-Requested-With header"
        );
    }

    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";

    if ($origin !== "") {
        $host = $_SERVER["HTTP_HOST"] ?? "";
        $parts = parse_url($origin);

        if (
            !$parts ||
            !in_array(
                $parts["scheme"] ?? "",
                ["http", "https"],
                true
            ) ||
            ($parts["host"] ?? "") !== explode(":", $host)[0] ||
            (
                isset($parts["port"]) &&
                (string)$parts["port"] !==
                (string)($_SERVER["SERVER_PORT"] ?? "80")
            )
        ) {
            respond(
                403,
                false,
                "Cross-origin requests are not allowed"
            );
        }
    }
}

function generateTicketToken() {
    return bin2hex(random_bytes(32));
}

function createUniqueTicketToken($db) {
    $stmt = $db->prepare(
        "SELECT booking_id
         FROM bookings
         WHERE ticket_token = ?"
    );

    for ($i = 0; $i < 5; $i++) {
        $token = generateTicketToken();

        $stmt->execute([$token]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $token;
        }
    }

    throw new RuntimeException(
        "Unable to generate unique ticket token"
    );
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

if ($method === "OPTIONS") {
    respond(200, true, "OK");
}

if (!in_array(
    $method,
    ["GET", "POST", "PUT", "DELETE"],
    true
)) {
    respond(405, false, "Method not allowed");
}

$customerId = requireCustomer();

if ($method !== "GET") {
    checkCsrf();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        respond(
            500,
            false,
            "Database connection failed"
        );
    }

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // ==========================================
    // GET - CUSTOMER BOOKINGS AND QR TICKETS
    // ==========================================

    if ($method === "GET") {
        $where = ["b.customer_id = ?"];
        $params = [$customerId];

        if (isset($_GET["id"])) {
            $bookingId = positiveId($_GET["id"]);

            if (!$bookingId) {
                respond(400, false, "Invalid booking ID");
            }

            $where[] = "b.booking_id = ?";
            $params[] = $bookingId;
        }

        if (isset($_GET["event_id"])) {
            $eventId = positiveId($_GET["event_id"]);

            if (!$eventId) {
                respond(400, false, "Invalid event ID");
            }

            $where[] = "b.event_id = ?";
            $params[] = $eventId;
        }

        if (
            isset($_GET["customer_id"]) &&
            positiveId($_GET["customer_id"]) !== $customerId
        ) {
            respond(
                403,
                false,
                "You can only view your own bookings"
            );
        }

        if (isset($_GET["status"])) {
            $status = strtolower(
                trim((string)$_GET["status"])
            );

            if (!in_array(
                $status,
                ["pending", "confirmed", "cancelled"],
                true
            )) {
                respond(
                    400,
                    false,
                    "Invalid booking status"
                );
            }

            $where[] = "b.booking_status = ?";
            $params[] = $status;
        }

        $sql = "
            SELECT
                b.booking_id,
                b.customer_id,
                u.name AS customer_name,
                u.email AS customer_email,
                b.event_id,
                e.event_name,
                e.event_date,
                e.event_time,
                e.ticket_price,
                b.quantity,
                b.total_amount,
                b.booking_status,
                b.booking_date,
                b.ticket_token,
                b.checked_in_at,
                b.checked_in_quantity
            FROM bookings b
            INNER JOIN users u
                ON b.customer_id = u.user_id
            INNER JOIN events e
                ON b.event_id = e.event_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY b.booking_id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bookings as &$booking) {
            $booking["ticket_token"] =
                $booking["booking_status"] === "confirmed"
                    ? $booking["ticket_token"]
                    : null;

            $booking["ticket_available"] =
                $booking["booking_status"] === "confirmed" &&
                !empty($booking["ticket_token"]);

            $booking["checked_in_quantity"] =
                (int)$booking["checked_in_quantity"];
        }

        unset($booking);

        if (isset($_GET["id"])) {
            if (!$bookings) {
                respond(
                    404,
                    false,
                    "Booking not found"
                );
            }

            respond(200, true, "", $bookings[0]);
        }

        echo json_encode([
            "success" => true,
            "count" => count($bookings),
            "data" => $bookings
        ], JSON_PRETTY_PRINT);

        exit;
    }

    // ==========================================
    // POST - CREATE GENERAL ADMISSION BOOKING
    // ==========================================

    if ($method === "POST") {
        $data = jsonBody();

        $eventId = positiveId(
            $data["event_id"] ?? null
        );

        $quantity = positiveId(
            $data["quantity"] ?? null
        );

        if (!$eventId || !$quantity) {
            respond(
                400,
                false,
                "Valid event_id and quantity are required"
            );
        }

        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = ?
              AND role = 'customer'
        ");

        $stmt->execute([$customerId]);

        if (!$stmt->fetch()) {
            $db->rollBack();

            respond(
                401,
                false,
                "Customer account not found"
            );
        }

        $stmt = $db->prepare("
            SELECT
                event_id,
                event_name,
                event_date,
                event_time,
                ticket_price,
                total_tickets,
                available_tickets,
                status
            FROM events
            WHERE event_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$eventId]);

        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            $db->rollBack();

            respond(
                404,
                false,
                "Event not found"
            );
        }

        if ($event["status"] !== "upcoming") {
            $db->rollBack();

            respond(
                400,
                false,
                "This event is not available for booking"
            );
        }

        $available = (int)$event["available_tickets"];
        $capacity = (int)$event["total_tickets"];

        if (
            $available < 0 ||
            $available > $capacity
        ) {
            $db->rollBack();

            respond(
                409,
                false,
                "Event ticket inventory needs correction"
            );
        }

        if ($quantity > $available) {
            $db->rollBack();

            respond(
                400,
                false,
                "Not enough tickets available"
            );
        }

        $total = round(
            (float)$event["ticket_price"] * $quantity,
            2
        );

        $ticketToken = createUniqueTicketToken($db);

        // Normal ticket booking.
        // No seat selection or seat assignment.

        $stmt = $db->prepare("
            INSERT INTO bookings (
                customer_id,
                event_id,
                quantity,
                total_amount,
                booking_status,
                ticket_token,
                checked_in_quantity
            )
            VALUES (?, ?, ?, ?, 'confirmed', ?, 0)
        ");

        $stmt->execute([
            $customerId,
            $eventId,
            $quantity,
            $total,
            $ticketToken
        ]);

        $bookingId = (int)$db->lastInsertId();

        $stmt = $db->prepare("
            UPDATE events
            SET available_tickets =
                available_tickets - ?
            WHERE event_id = ?
        ");

        $stmt->execute([
            $quantity,
            $eventId
        ]);

        $db->commit();

        respond(
            201,
            true,
            "Booking created successfully. Your QR ticket is ready.",
            [
                "booking_id" => $bookingId,
                "customer_id" => $customerId,
                "event_id" => $eventId,
                "event_name" => $event["event_name"],
                "event_date" => $event["event_date"],
                "event_time" => $event["event_time"],
                "quantity" => $quantity,
                "ticket_price" => number_format(
                    (float)$event["ticket_price"],
                    2,
                    ".",
                    ""
                ),
                "total_amount" => number_format(
                    $total,
                    2,
                    ".",
                    ""
                ),
                "booking_status" => "confirmed",
                "ticket_token" => $ticketToken,
                "ticket_available" => true,
                "checked_in_quantity" => 0
            ]
        );
    }

    // ==========================================
    // PUT / DELETE - MANAGE EXISTING BOOKING
    // ==========================================

    $bookingId = positiveId(
        $_GET["id"] ?? null
    );

    if (!$bookingId) {
        respond(
            400,
            false,
            "Valid booking ID is required"
        );
    }

    $data = $method === "PUT"
        ? jsonBody()
        : [];

    $newQuantity = $method === "PUT"
        ? positiveId($data["quantity"] ?? null)
        : 0;

    if ($method === "PUT" && !$newQuantity) {
        respond(
            400,
            false,
            "Quantity must be greater than 0"
        );
    }

    $newStatus = $method === "PUT"
        ? strtolower(
            trim(
                (string)(
                    $data["booking_status"] ??
                    "confirmed"
                )
            )
        )
        : "";

    if (
        $method === "PUT" &&
        !in_array(
            $newStatus,
            ["pending", "confirmed", "cancelled"],
            true
        )
    ) {
        respond(
            400,
            false,
            "Invalid booking status"
        );
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT
            booking_id,
            customer_id,
            event_id,
            quantity,
            booking_status,
            ticket_token,
            checked_in_quantity
        FROM bookings
        WHERE booking_id = ?
          AND customer_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $bookingId,
        $customerId
    ]);

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $db->rollBack();

        respond(
            404,
            false,
            "Booking not found"
        );
    }

    $stmt = $db->prepare("
        SELECT
            event_id,
            event_name,
            ticket_price,
            total_tickets,
            available_tickets,
            status
        FROM events
        WHERE event_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $booking["event_id"]
    ]);

    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $db->rollBack();

        respond(
            404,
            false,
            "Event not found"
        );
    }

    $available = (int)$event["available_tickets"];
    $capacity = (int)$event["total_tickets"];

    if (
        $available < 0 ||
        $available > $capacity
    ) {
        $db->rollBack();

        respond(
            409,
            false,
            "Event ticket inventory needs correction"
        );
    }

    $oldQuantity = (int)$booking["quantity"];

    $wasActive =
        $booking["booking_status"] !== "cancelled";

    $checkedIn =
        (int)$booking["checked_in_quantity"];

    // ==========================================
    // DELETE BOOKING
    // ==========================================

    if ($method === "DELETE") {
        if ($checkedIn > 0) {
            $db->rollBack();

            respond(
                409,
                false,
                "A checked-in booking cannot be deleted"
            );
        }

        $change = $wasActive
            ? $oldQuantity
            : 0;

        if ($available + $change > $capacity) {
            $db->rollBack();

            respond(
                409,
                false,
                "Ticket restoration would exceed event capacity"
            );
        }

        $stmt = $db->prepare("
            DELETE FROM bookings
            WHERE booking_id = ?
              AND customer_id = ?
        ");

        $stmt->execute([
            $bookingId,
            $customerId
        ]);

        if ($change > 0) {
            $stmt = $db->prepare("
                UPDATE events
                SET available_tickets =
                    available_tickets + ?
                WHERE event_id = ?
            ");

            $stmt->execute([
                $change,
                $booking["event_id"]
            ]);
        }

        $db->commit();

        respond(
            200,
            true,
            "Booking deleted successfully",
            [
                "booking_id" => $bookingId,
                "event_id" =>
                    (int)$booking["event_id"]
            ]
        );
    }

    // ==========================================
    // PUT - UPDATE BOOKING
    // ==========================================

    $isActive = $newStatus !== "cancelled";

    if ($checkedIn > 0) {
        if (
            !$isActive ||
            $newQuantity < $checkedIn
        ) {
            $db->rollBack();

            respond(
                409,
                false,
                "Cannot cancel or reduce below already checked-in tickets"
            );
        }
    }

    $change =
        ($wasActive ? $oldQuantity : 0) -
        ($isActive ? $newQuantity : 0);

    if (
        $change < 0 &&
        -$change > $available
    ) {
        $db->rollBack();

        respond(
            400,
            false,
            "Not enough tickets available"
        );
    }

    if (
        $available + $change > $capacity
    ) {
        $db->rollBack();

        respond(
            409,
            false,
            "Ticket restoration would exceed event capacity"
        );
    }

    if (
        $isActive &&
        !$wasActive &&
        $event["status"] !== "upcoming"
    ) {
        $db->rollBack();

        respond(
            400,
            false,
            "This event is not available for booking"
        );
    }

    if (
        $isActive &&
        $wasActive &&
        $newQuantity > $oldQuantity &&
        $event["status"] !== "upcoming"
    ) {
        $db->rollBack();

        respond(
            400,
            false,
            "This event is not available for additional tickets"
        );
    }

    $total = round(
        (float)$event["ticket_price"] * $newQuantity,
        2
    );

    // Keep QR token for existing confirmed bookings.
    // Generate a new token when confirming a booking.

    $ticketToken = null;

    if ($newStatus === "confirmed") {
        if (
            $booking["booking_status"] === "confirmed" &&
            !empty($booking["ticket_token"])
        ) {
            $ticketToken =
                $booking["ticket_token"];
        } else {
            $ticketToken =
                createUniqueTicketToken($db);
        }
    }

    $keepCheckIn =
        $booking["booking_status"] === "confirmed" &&
        $newStatus === "confirmed";

    $stmt = $db->prepare("
        UPDATE bookings
        SET
            quantity = ?,
            total_amount = ?,
            booking_status = ?,
            ticket_token = ?,
            checked_in_quantity = CASE
                WHEN ? = 1
                THEN checked_in_quantity
                ELSE 0
            END,
            checked_in_at = CASE
                WHEN ? = 1
                THEN checked_in_at
                ELSE NULL
            END
        WHERE booking_id = ?
          AND customer_id = ?
    ");

    $stmt->execute([
        $newQuantity,
        $total,
        $newStatus,
        $ticketToken,
        $keepCheckIn ? 1 : 0,
        $keepCheckIn ? 1 : 0,
        $bookingId,
        $customerId
    ]);

    if ($change !== 0) {
        $stmt = $db->prepare("
            UPDATE events
            SET available_tickets =
                available_tickets + ?
            WHERE event_id = ?
        ");

        $stmt->execute([
            $change,
            $booking["event_id"]
        ]);
    }

    $db->commit();

    respond(
        200,
        true,
        "Booking updated successfully",
        [
            "booking_id" => $bookingId,
            "event_id" =>
                (int)$booking["event_id"],
            "event_name" =>
                $event["event_name"],
            "old_quantity" =>
                $oldQuantity,
            "new_quantity" =>
                $newQuantity,
            "booking_status" =>
                $newStatus,
            "total_amount" =>
                number_format(
                    $total,
                    2,
                    ".",
                    ""
                ),
            "ticket_token" =>
                $ticketToken,
            "ticket_available" =>
                $newStatus === "confirmed" &&
                !empty($ticketToken)
        ]
    );

} catch (Throwable $e) {
    if (
        isset($db) &&
        $db instanceof PDO &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log(
        "Booking API error: " .
        $e->getMessage()
    );

    respond(
        500,
        false,
        "Booking operation failed"
    );
}
?>
