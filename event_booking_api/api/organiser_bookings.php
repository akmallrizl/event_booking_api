
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Allow: GET');

require_once __DIR__ . '/../config/database.php';

function sendResponse(
    int $code,
    bool $success,
    string $message = '',
    $data = null
): void {
    http_response_code($code);

    $response = ['success' => $success];

    if ($message !== '') {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, false, 'Method not allowed');
}

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'organiser'
) {
    sendResponse(
        403,
        false,
        'Only logged-in organisers can access this page'
    );
}

$organiserId = (int) $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        sendResponse(500, false, 'Database connection failed');
    }

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $where = ['e.organiser_id = ?'];
    $params = [$organiserId];

    if (isset($_GET['id'])) {
        $bookingId = filter_var(
            $_GET['id'],
            FILTER_VALIDATE_INT
        );

        if ($bookingId === false || $bookingId <= 0) {
            sendResponse(400, false, 'Invalid booking ID');
        }

        $where[] = 'b.booking_id = ?';
        $params[] = $bookingId;
    }

    if (isset($_GET['event_id'])) {
        $eventId = filter_var(
            $_GET['event_id'],
            FILTER_VALIDATE_INT
        );

        if ($eventId === false || $eventId <= 0) {
            sendResponse(400, false, 'Invalid event ID');
        }

        $where[] = 'b.event_id = ?';
        $params[] = $eventId;
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
            b.quantity,
            b.total_amount,
            b.booking_status,
            b.booking_date,
            b.ticket_token,
            b.checked_in_quantity,
            b.checked_in_at
        FROM bookings b
        INNER JOIN events e
            ON b.event_id = e.event_id
        INNER JOIN users u
            ON b.customer_id = u.user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY b.booking_id DESC
    ';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as &$booking) {
        $booking['booking_id'] =
            (int) $booking['booking_id'];

        $booking['customer_id'] =
            (int) $booking['customer_id'];

        $booking['event_id'] =
            (int) $booking['event_id'];

        $booking['quantity'] =
            (int) $booking['quantity'];

        $booking['checked_in_quantity'] =
            (int) $booking['checked_in_quantity'];

        $booking['remaining_entries'] = max(
            0,
            $booking['quantity'] -
            $booking['checked_in_quantity']
        );

        $booking['ticket_available'] =
            $booking['booking_status'] === 'confirmed' &&
            !empty($booking['ticket_token']);

        if (!$booking['ticket_available']) {
            $booking['ticket_token'] = null;
        }
    }

    unset($booking);

    if (isset($_GET['id'])) {
        if (empty($bookings)) {
            sendResponse(404, false, 'Booking not found');
        }

        sendResponse(200, true, '', $bookings[0]);
    }

    sendResponse(200, true, '', $bookings);

} catch (Throwable $e) {
    error_log(
        'Organiser bookings API error: ' .
        $e->getMessage()
    );

    sendResponse(
        500,
        false,
        'Unable to retrieve organiser bookings'
    );
}
