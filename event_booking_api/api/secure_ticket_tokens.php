
<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';

// Only an authenticated admin can run this migration.
if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $db->beginTransaction();

    $stmt = $db->query(
        "SELECT booking_id
         FROM bookings
         WHERE booking_status = 'confirmed'
         FOR UPDATE"
    );

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $update = $db->prepare(
        "UPDATE bookings
         SET ticket_token = ?
         WHERE booking_id = ?"
    );

    foreach ($bookings as $booking) {
        $token = bin2hex(random_bytes(32));

        $update->execute([
            $token,
            $booking['booking_id']
        ]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Secure ticket tokens generated',
        'updated_bookings' => count($bookings)
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    if (
        isset($db) &&
        $db instanceof PDO &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log('Ticket migration error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update ticket tokens'
    ]);
}
