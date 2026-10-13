
<?php

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Allow: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . '/../config/database.php';

function respond(int $code, bool $success, string $message = '', $data = null): void
{
    http_response_code($code);

    $out = ['success' => $success];

    if ($message !== '') {
        $out['message'] = $message;
    }

    if ($data !== null) {
        $out['data'] = $data;
    }

    echo json_encode($out, JSON_PRETTY_PRINT);
    exit;
}

function positiveId($value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? (int)$id : 0;
}

function jsonBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body) || json_last_error() !== JSON_ERROR_NONE) {
        respond(400, false, 'Invalid JSON body');
    }

    return $body;
}

function requireCustomer(): int
{
    if (
        empty($_SESSION['user_id']) ||
        ($_SESSION['role'] ?? '') !== 'customer'
    ) {
        respond(401, false, 'Please log in as a customer');
    }

    return (int)$_SESSION['user_id'];
}

function checkCsrf(): void
{
    if (
        ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !==
        'XMLHttpRequest'
    ) {
        respond(403, false, 'Missing X-Requested-With header');
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = parse_url($origin);

        if (
            !$parts ||
            !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
            ($parts['host'] ?? '') !== explode(':', $host)[0] ||
            (
                isset($parts['port']) &&
                (string)$parts['port'] !==
                (string)($_SERVER['SERVER_PORT'] ?? '80')
            )
        ) {
            respond(403, false, 'Cross-origin requests are not allowed');
        }
    }
}

function generateTicketToken(): string
{
    return bin2hex(random_bytes(32));
}

function createUniqueTicketToken(PDO $db): string
{
    $check = $db->prepare(
        'SELECT booking_id FROM bookings WHERE ticket_token = ?'
    );

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = generateTicketToken();
        $check->execute([$token]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            return $token;
        }
    }

    throw new RuntimeException(
        'Unable to generate unique ticket token'
    );
}

function rollbackRespond(
    PDO $db,
    int $code,
    string $message
): void {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    respond($code, false, $message);
}

function normalizeSeatNumbers($value): array
{
    if (!is_array($value) || !array_is_list($value)) {
        respond(400, false, 'seat_numbers must be an array');
    }

    $numbers = [];

    foreach ($value as $seat) {
        if (!is_string($seat)) {
            respond(400, false, 'Invalid seat number');
        }

        $seat = strtoupper(trim($seat));

        if (!preg_match('/^[A-Z][1-9][0-9]{0,8}$/', $seat)) {
            respond(400, false, 'Invalid seat number');
        }

        $numbers[] = $seat;
    }

    if (count($numbers) !== count(array_unique($numbers))) {
        respond(400, false, 'Duplicate seats are not allowed');
    }

    sort($numbers, SORT_NATURAL);

    return $numbers;
}

function eventSeats(PDO $db, int $eventId): array
{
    $stmt = $db->prepare(
        'SELECT seat_id, seat_number, booking_id
         FROM event_seats
         WHERE event_id = ?
         ORDER BY seat_id
         FOR UPDATE'
    );

    $stmt->execute([$eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validateSeatInventory(
    PDO $db,
    array $event,
    array $seats
): void {
    $capacity = (int)$event['total_tickets'];
    $available = (int)$event['available_tickets'];

    if (count($seats) !== $capacity) {
        rollbackRespond(
            $db,
            409,
            'Seat layout does not match event capacity'
        );
    }

    $free = 0;

    foreach ($seats as $seat) {
        if ($seat['booking_id'] === null) {
            $free++;
        }
    }

    if ($free !== $available) {
        rollbackRespond(
            $db,
            409,
            'Seat inventory does not match ticket availability'
        );
    }
}

function validateRequestedSeats(
    PDO $db,
    array $seats,
    array $requested,
    ?int $ownBookingId = null
): void {
    $lookup = [];

    foreach ($seats as $seat) {
        $lookup[$seat['seat_number']] = $seat;
    }

    foreach ($requested as $number) {
        if (!isset($lookup[$number])) {
            rollbackRespond(
                $db,
                400,
                'Seat ' . $number . ' does not exist'
            );
        }

        $owner = $lookup[$number]['booking_id'];

        if (
            $owner !== null &&
            (int)$owner !== $ownBookingId
        ) {
            rollbackRespond(
                $db,
                409,
                'Seat ' . $number . ' is already booked'
            );
        }
    }
}

function bookingSeatNumbers(PDO $db, int $bookingId): array
{
    $stmt = $db->prepare(
        'SELECT seat_number
         FROM event_seats
         WHERE booking_id = ?
         ORDER BY seat_number'
    );

    $stmt->execute([$bookingId]);

    $numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($numbers, SORT_NATURAL);

    return $numbers;
}

function releaseBookingSeats(PDO $db, int $bookingId): void
{
    $stmt = $db->prepare(
        'UPDATE event_seats
         SET booking_id = NULL
         WHERE booking_id = ?'
    );

    $stmt->execute([$bookingId]);
}

function assignBookingSeats(
    PDO $db,
    int $eventId,
    int $bookingId,
    array $numbers
): void {
    $stmt = $db->prepare(
        'UPDATE event_seats
         SET booking_id = ?
         WHERE event_id = ?
           AND seat_number = ?
           AND booking_id IS NULL'
    );

    foreach ($numbers as $number) {
        $stmt->execute([$bookingId, $eventId, $number]);

        if ($stmt->rowCount() !== 1) {
            rollbackRespond(
                $db,
                409,
                'Seat ' . $number . ' is no longer available'
            );
        }
    }
}

function updateEventAvailability(
    PDO $db,
    int $eventId,
    int $change
): void {
    if ($change === 0) {
        return;
    }

    $stmt = $db->prepare(
        'UPDATE events
         SET available_tickets = available_tickets + ?
         WHERE event_id = ?'
    );

    $stmt->execute([$change, $eventId]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    respond(200, true, 'OK');
}

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    respond(405, false, 'Method not allowed');
}

$customerId = requireCustomer();

if ($method !== 'GET') {
    checkCsrf();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        respond(500, false, 'Database connection failed');
    }

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    /*
     * GET — Customer bookings and QR ticket details
     */
    if ($method === 'GET') {
        $where = ['b.customer_id = ?'];
        $params = [$customerId];

        if (isset($_GET['id'])) {
            $id = positiveId($_GET['id']);

            if (!$id) {
                respond(400, false, 'Invalid booking ID');
            }

            $where[] = 'b.booking_id = ?';
            $params[] = $id;
        }

        if (isset($_GET['event_id'])) {
            $eventId = positiveId($_GET['event_id']);

            if (!$eventId) {
                respond(400, false, 'Invalid event ID');
            }

            $where[] = 'b.event_id = ?';
            $params[] = $eventId;
        }

        if (
            isset($_GET['customer_id']) &&
            positiveId($_GET['customer_id']) !== $customerId
        ) {
            respond(
                403,
                false,
                'You can only view your own bookings'
            );
        }

        if (isset($_GET['status'])) {
            $status = strtolower(trim((string)$_GET['status']));

            if (
                !in_array(
                    $status,
                    ['pending', 'confirmed', 'cancelled'],
                    true
                )
            ) {
                respond(400, false, 'Invalid booking status');
            }

            $where[] = 'b.booking_status = ?';
            $params[] = $status;
        }

        $sql = '
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
                e.seating_type,
                b.quantity,
                b.total_amount,
                b.booking_status,
                b.booking_date,
                b.ticket_token,
                b.checked_in_at,
                b.checked_in_quantity
            FROM bookings b
            JOIN users u
                ON b.customer_id = u.user_id
            JOIN events e
                ON b.event_id = e.event_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY b.booking_id ASC
        ';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['ticket_token'] =
                $row['booking_status'] === 'confirmed'
                ? $row['ticket_token']
                : null;

            $row['ticket_available'] =
                $row['booking_status'] === 'confirmed' &&
                !empty($row['ticket_token']);

            $row['checked_in_quantity'] =
                (int)$row['checked_in_quantity'];

            $row['seat_numbers'] =
                $row['seating_type'] === 'assigned'
                ? bookingSeatNumbers(
                    $db,
                    (int)$row['booking_id']
                )
                : [];
        }

        unset($row);

        if (isset($_GET['id'])) {
            if (!$rows) {
                respond(404, false, 'Booking not found');
            }

            respond(200, true, '', $rows[0]);
        }

        http_response_code(200);

        echo json_encode([
            'success' => true,
            'count' => count($rows),
            'data' => $rows
        ], JSON_PRETTY_PRINT);

        exit;
    }

    /*
     * POST — Create booking
     */
    if ($method === 'POST') {
        $data = jsonBody();

        $eventId = positiveId($data['event_id'] ?? null);
        $quantity = positiveId($data['quantity'] ?? null);

        if (!$eventId || !$quantity) {
            respond(
                400,
                false,
                'Valid event_id and quantity are required'
            );
        }

        $hasSeatSelection = array_key_exists(
            'seat_numbers',
            $data
        );

        $requestedSeats = $hasSeatSelection
            ? normalizeSeatNumbers($data['seat_numbers'])
            : [];

        $db->beginTransaction();

        $stmt = $db->prepare(
            "SELECT user_id
             FROM users
             WHERE user_id = ?
               AND role = 'customer'"
        );

        $stmt->execute([$customerId]);

        if (!$stmt->fetch()) {
            rollbackRespond(
                $db,
                401,
                'Customer account not found'
            );
        }

        $stmt = $db->prepare(
            'SELECT
                event_id,
                event_name,
                event_date,
                event_time,
                ticket_price,
                total_tickets,
                available_tickets,
                seating_type,
                status
             FROM events
             WHERE event_id = ?
             FOR UPDATE'
        );

        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            rollbackRespond($db, 404, 'Event not found');
        }

        if ($event['status'] !== 'upcoming') {
            rollbackRespond(
                $db,
                400,
                'This event is not available for booking'
            );
        }

        $available = (int)$event['available_tickets'];
        $capacity = (int)$event['total_tickets'];

        if ($available < 0 || $available > $capacity) {
            rollbackRespond(
                $db,
                409,
                'Event ticket inventory needs correction'
            );
        }

        if ($quantity > $available) {
            rollbackRespond(
                $db,
                400,
                'Not enough tickets available'
            );
        }

        $assigned = $event['seating_type'] === 'assigned';

        if ($assigned) {
            if (!$hasSeatSelection) {
                rollbackRespond(
                    $db,
                    400,
                    'Please select your seats'
                );
            }

            if (count($requestedSeats) !== $quantity) {
                rollbackRespond(
                    $db,
                    400,
                    'Number of selected seats must match quantity'
                );
            }

            $seats = eventSeats($db, $eventId);

            validateSeatInventory($db, $event, $seats);

            validateRequestedSeats(
                $db,
                $seats,
                $requestedSeats
            );
        } elseif ($hasSeatSelection && count($requestedSeats) > 0) {
            rollbackRespond(
                $db,
                400,
                'This event uses general admission'
            );
        }

        $total = round(
            (float)$event['ticket_price'] * $quantity,
            2
        );

        $ticketToken = createUniqueTicketToken($db);

        $stmt = $db->prepare(
            "INSERT INTO bookings (
                customer_id,
                event_id,
                quantity,
                total_amount,
                booking_status,
                ticket_token,
                checked_in_quantity
            )
            VALUES (?, ?, ?, ?, 'confirmed', ?, 0)"
        );

        $stmt->execute([
            $customerId,
            $eventId,
            $quantity,
            $total,
            $ticketToken
        ]);

        $bookingId = (int)$db->lastInsertId();

        if ($assigned) {
            assignBookingSeats(
                $db,
                $eventId,
                $bookingId,
                $requestedSeats
            );
        }

        updateEventAvailability(
            $db,
            $eventId,
            -$quantity
        );

        $db->commit();

        respond(
            201,
            true,
            'Booking created successfully. Your QR ticket is ready.',
            [
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'event_id' => $eventId,
                'event_name' => $event['event_name'],
                'event_date' => $event['event_date'],
                'event_time' => $event['event_time'],
                'seating_type' => $event['seating_type'],
                'seat_numbers' => $assigned
                    ? $requestedSeats
                    : [],
                'quantity' => $quantity,
                'ticket_price' => number_format(
                    (float)$event['ticket_price'],
                    2,
                    '.',
                    ''
                ),
                'total_amount' => number_format(
                    $total,
                    2,
                    '.',
                    ''
                ),
                'booking_status' => 'confirmed',
                'ticket_token' => $ticketToken,
                'ticket_available' => true,
                'checked_in_quantity' => 0
            ]
        );
    }

    /*
     * PUT / DELETE — Modify or remove booking
     */
    $bookingId = positiveId($_GET['id'] ?? null);

    if (!$bookingId) {
        respond(400, false, 'Valid booking ID is required');
    }

    $data = $method === 'PUT' ? jsonBody() : [];

    $newQuantity = $method === 'PUT'
        ? positiveId($data['quantity'] ?? null)
        : 0;

    if ($method === 'PUT' && !$newQuantity) {
        respond(
            400,
            false,
            'Quantity must be greater than 0'
        );
    }

    $newStatus = $method === 'PUT'
        ? strtolower(
            trim((string)($data['booking_status'] ?? 'confirmed'))
        )
        : '';

    if (
        $method === 'PUT' &&
        !in_array(
            $newStatus,
            ['pending', 'confirmed', 'cancelled'],
            true
        )
    ) {
        respond(400, false, 'Invalid booking status');
    }

    $hasSeatSelection =
        $method === 'PUT' &&
        array_key_exists('seat_numbers', $data);

    $requestedSeats = $hasSeatSelection
        ? normalizeSeatNumbers($data['seat_numbers'])
        : [];

    /*
     * Read event ID before acquiring locks.
     * Event is locked first to serialize seat operations.
     */
    $stmt = $db->prepare(
        'SELECT event_id
         FROM bookings
         WHERE booking_id = ?
           AND customer_id = ?'
    );

    $stmt->execute([$bookingId, $customerId]);
    $bookingEventId = $stmt->fetchColumn();

    if ($bookingEventId === false) {
        respond(404, false, 'Booking not found');
    }

    $bookingEventId = (int)$bookingEventId;

    $db->beginTransaction();

    $stmt = $db->prepare(
        'SELECT
            event_id,
            event_name,
            ticket_price,
            total_tickets,
            available_tickets,
            seating_type,
            status
         FROM events
         WHERE event_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$bookingEventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        rollbackRespond($db, 404, 'Event not found');
    }

    $stmt = $db->prepare(
        'SELECT
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
         FOR UPDATE'
    );

    $stmt->execute([$bookingId, $customerId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        rollbackRespond($db, 404, 'Booking not found');
    }

    if ((int)$booking['event_id'] !== $bookingEventId) {
        rollbackRespond(
            $db,
            409,
            'Booking event changed. Please retry'
        );
    }

    $available = (int)$event['available_tickets'];
    $capacity = (int)$event['total_tickets'];

    if ($available < 0 || $available > $capacity) {
        rollbackRespond(
            $db,
            409,
            'Event ticket inventory needs correction'
        );
    }

    $oldQuantity = (int)$booking['quantity'];
    $wasActive = $booking['booking_status'] !== 'cancelled';
    $checkedIn = (int)$booking['checked_in_quantity'];
    $assigned = $event['seating_type'] === 'assigned';

    $currentSeats = [];

    if ($assigned) {
        $seats = eventSeats($db, $bookingEventId);

        validateSeatInventory($db, $event, $seats);

        $currentSeats = bookingSeatNumbers(
            $db,
            $bookingId
        );

        $expectedSeats = $wasActive ? $oldQuantity : 0;

        if (count($currentSeats) !== $expectedSeats) {
            rollbackRespond(
                $db,
                409,
                'Booking seat allocation needs correction'
            );
        }
    }

    /*
     * DELETE — Remove booking
     */
    if ($method === 'DELETE') {
        if ($checkedIn > 0) {
            rollbackRespond(
                $db,
                409,
                'A checked-in booking cannot be deleted'
            );
        }

        $change = $wasActive ? $oldQuantity : 0;

        if ($available + $change > $capacity) {
            rollbackRespond(
                $db,
                409,
                'Ticket restoration would exceed event capacity'
            );
        }

        if ($assigned) {
            releaseBookingSeats($db, $bookingId);
        }

        $stmt = $db->prepare(
            'DELETE FROM bookings
             WHERE booking_id = ?
               AND customer_id = ?'
        );

        $stmt->execute([$bookingId, $customerId]);

        updateEventAvailability(
            $db,
            $bookingEventId,
            $change
        );

        $db->commit();

        respond(
            200,
            true,
            'Booking deleted successfully',
            [
                'booking_id' => $bookingId,
                'event_id' => $bookingEventId
            ]
        );
    }

    /*
     * PUT — Update booking
     */
    $isActive = $newStatus !== 'cancelled';

    if ($checkedIn > 0) {
        if (!$isActive || $newQuantity < $checkedIn) {
            rollbackRespond(
                $db,
                409,
                'Cannot cancel or reduce below already checked-in tickets'
            );
        }
    }

    $change =
        ($wasActive ? $oldQuantity : 0) -
        ($isActive ? $newQuantity : 0);

    if ($change < 0 && -$change > $available) {
        rollbackRespond(
            $db,
            400,
            'Not enough tickets available'
        );
    }

    if ($available + $change > $capacity) {
        rollbackRespond(
            $db,
            409,
            'Ticket restoration would exceed event capacity'
        );
    }

    if (
        $isActive &&
        !$wasActive &&
        $event['status'] !== 'upcoming'
    ) {
        rollbackRespond(
            $db,
            400,
            'This event is not available for booking'
        );
    }

    if (
        $isActive &&
        $wasActive &&
        $newQuantity > $oldQuantity &&
        $event['status'] !== 'upcoming'
    ) {
        rollbackRespond(
            $db,
            400,
            'This event is not available for additional tickets'
        );
    }

    if ($assigned) {
        if (!$isActive) {
            $requestedSeats = [];
        } elseif (!$hasSeatSelection) {
            if ($wasActive && $newQuantity === $oldQuantity) {
                $requestedSeats = $currentSeats;
            } else {
                rollbackRespond(
                    $db,
                    400,
                    'Please select seats matching the new quantity'
                );
            }
        }

        if ($isActive && count($requestedSeats) !== $newQuantity) {
            rollbackRespond(
                $db,
                400,
                'Number of selected seats must match quantity'
            );
        }

        if ($isActive) {
            validateRequestedSeats(
                $db,
                $seats,
                $requestedSeats,
                $bookingId
            );
        }

        /*
         * Seat changes are blocked after any check-in.
         * Existing checked-in seats must remain unchanged.
         */
        if (
            $checkedIn > 0 &&
            $requestedSeats !== $currentSeats
        ) {
            rollbackRespond(
                $db,
                409,
                'Cannot change seats after ticket check-in'
            );
        }

        /*
         * Existing allocations are released and reassigned
         * within the same transaction.
         */
        releaseBookingSeats($db, $bookingId);

        if ($isActive) {
            assignBookingSeats(
                $db,
                $bookingEventId,
                $bookingId,
                $requestedSeats
            );
        }
    } elseif ($hasSeatSelection && count($requestedSeats) > 0) {
        rollbackRespond(
            $db,
            400,
            'This event uses general admission'
        );
    }

    $total = round(
        (float)$event['ticket_price'] * $newQuantity,
        2
    );

    $ticketToken = null;

    if ($newStatus === 'confirmed') {
        if (
            $booking['booking_status'] === 'confirmed' &&
            !empty($booking['ticket_token'])
        ) {
            $ticketToken = $booking['ticket_token'];
        } else {
            $ticketToken = createUniqueTicketToken($db);
        }
    }

    $keepCheckIn =
        $booking['booking_status'] === 'confirmed' &&
        $newStatus === 'confirmed';

    $stmt = $db->prepare(
        'UPDATE bookings
         SET
            quantity = ?,
            total_amount = ?,
            booking_status = ?,
            ticket_token = ?,
            checked_in_quantity = CASE
                WHEN ? = ? THEN checked_in_quantity
                ELSE 0
            END,
            checked_in_at = CASE
                WHEN ? = ? THEN checked_in_at
                ELSE NULL
            END
         WHERE booking_id = ?
           AND customer_id = ?'
    );

    $stmt->execute([
        $newQuantity,
        $total,
        $newStatus,
        $ticketToken,
        $keepCheckIn ? 1 : 0,
        1,
        $keepCheckIn ? 1 : 0,
        1,
        $bookingId,
        $customerId
    ]);

    updateEventAvailability(
        $db,
        $bookingEventId,
        $change
    );

    $db->commit();

    respond(
        200,
        true,
        'Booking updated successfully',
        [
            'booking_id' => $bookingId,
            'event_id' => $bookingEventId,
            'event_name' => $event['event_name'],
            'seating_type' => $event['seating_type'],
            'seat_numbers' => $assigned
                ? $requestedSeats
                : [],
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'booking_status' => $newStatus,
            'total_amount' => number_format(
                $total,
                2,
                '.',
                ''
            ),
            'ticket_token' => $ticketToken,
            'ticket_available' =>
                $newStatus === 'confirmed' &&
                !empty($ticketToken)
        ]
    );

} catch (PDOException $e) {
    if (
        isset($db) &&
        $db instanceof PDO &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log(
        'Booking API database error: ' . $e->getMessage()
    );

    respond(500, false, 'Booking operation failed');

} catch (Throwable $e) {
    if (
        isset($db) &&
        $db instanceof PDO &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log(
        'Booking API error: ' . $e->getMessage()
    );

    respond(500, false, 'Booking operation failed');
}
