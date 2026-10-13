
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Allow: POST');

require_once __DIR__ . '/../config/database.php';

function respond(int $code, bool $success, string $message, $data = null): void {
    http_response_code($code);

    $result = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $result['data'] = $data;
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Only POST requests are allowed');
}

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'organiser'
) {
    respond(403, false, 'Organiser login required');
}

if (
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
) {
    respond(403, false, 'Missing security header');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '') {
    $parts = parse_url($origin);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '80');

    if (
        !$parts ||
        !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
        ($parts['host'] ?? '') !== explode(':', $host)[0] ||
        (
            isset($parts['port']) &&
            (string)$parts['port'] !== $serverPort
        )
    ) {
        respond(403, false, 'Cross-origin request rejected');
    }
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    respond(400, false, 'Invalid JSON request');
}

$token = strtolower(trim((string)($input['ticket_token'] ?? '')));
$action = strtolower(trim((string)($input['action'] ?? 'verify')));

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    respond(400, false, 'Invalid QR ticket token');
}

if (!in_array($action, ['verify', 'checkin'], true)) {
    respond(400, false, 'Invalid action');
}

$organiserId = (int)$_SESSION['user_id'];

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

    $db->beginTransaction();

    $sql = '
        SELECT
            b.booking_id,
            b.customer_id,
            b.event_id,
            b.quantity,
            b.booking_status,
            b.checked_in_quantity,
            b.checked_in_at,
            u.name AS customer_name,
            e.event_name,
            e.event_date,
            e.event_time,
            e.status AS event_status
        FROM bookings b
        INNER JOIN events e ON b.event_id = e.event_id
        INNER JOIN users u ON b.customer_id = u.user_id
        WHERE b.ticket_token = ?
          AND e.organiser_id = ?
        FOR UPDATE
    ';

    $stmt = $db->prepare($sql);
    $stmt->execute([$token, $organiserId]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $db->rollBack();
        respond(
            404,
            false,
            'Ticket not found or does not belong to your event'
        );
    }

    if ($ticket['booking_status'] !== 'confirmed') {
        $db->rollBack();
        respond(403, false, 'Ticket is not confirmed');
    }

    if ($ticket['event_status'] === 'cancelled') {
        $db->rollBack();
        respond(403, false, 'This event has been cancelled');
    }

    $quantity = (int)$ticket['quantity'];
    $checkedIn = (int)$ticket['checked_in_quantity'];

    if ($quantity < 1 || $checkedIn < 0 || $checkedIn > $quantity) {
        $db->rollBack();
        respond(409, false, 'Ticket check-in data is inconsistent');
    }

    if ($action === 'checkin') {
        if ($checkedIn >= $quantity) {
            $db->rollBack();
            respond(409, false, 'All tickets have already been used');
        }

        $update = $db->prepare(
            'UPDATE bookings
             SET
                checked_in_quantity = checked_in_quantity + 1,
                checked_in_at = NOW()
             WHERE booking_id = ?'
        );

        $update->execute([(int)$ticket['booking_id']]);

        $checkedIn++;

        $db->commit();

        $message = 'Attendee checked in successfully';
    } else {
        $db->commit();

        $message = 'QR ticket verified successfully';
    }

    respond(200, true, $message, [
        'booking_id' => (int)$ticket['booking_id'],
        'event_id' => (int)$ticket['event_id'],
        'event_name' => $ticket['event_name'],
        'event_date' => $ticket['event_date'],
        'event_time' => $ticket['event_time'],
        'customer_name' => $ticket['customer_name'],
        'quantity' => $quantity,
        'checked_in_quantity' => $checkedIn,
        'remaining_entries' => $quantity - $checkedIn,
        'booking_status' => $ticket['booking_status']
    ]);

} catch (Throwable $e) {
    if (
        isset($db) &&
        $db instanceof PDO &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log('QR check-in error: ' . $e->getMessage());

    respond(500, false, 'Unable to verify QR ticket');
}
