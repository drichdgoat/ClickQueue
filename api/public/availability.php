<?php
/**
 * SnapVenture - GET /api/availability.php
 * ?service_type=camera_rental|photobooth&package_id=3&year=2026&month=10
 * Returns: { "booked_dates": ["2026-10-05", "2026-10-14", ...] }
 * A package is considered "1 unit" -- once it has a non-cancelled booking
 * on a date, that date is fully booked for that specific package.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/pricing.php';

$serviceType = $_GET['service_type'] ?? '';
$packageId   = (int) ($_GET['package_id'] ?? 0);
$year        = (int) ($_GET['year'] ?? 0);
$month       = (int) ($_GET['month'] ?? 0);

if (!in_array($serviceType, ['camera_rental', 'photobooth'], true) || !$packageId || !$year || !$month) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid parameters.']);
    exit;
}

try {
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end   = date('Y-m-t', strtotime($start));

    if ($serviceType === 'camera_rental') {
        $sql = "SELECT DISTINCT b.event_date FROM bookings b
                JOIN booking_camera_details d ON d.booking_id = b.booking_id
                WHERE d.camera_id = ? AND b.status != 'cancelled'
                  AND b.event_date BETWEEN ? AND ?";
    } else {
        $sql = "SELECT DISTINCT b.event_date FROM bookings b
                JOIN booking_photobooth_details d ON d.booking_id = b.booking_id
                WHERE d.photobooth_id = ? AND b.status != 'cancelled'
                  AND b.event_date BETWEEN ? AND ?";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$packageId, $start, $end]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['booked_dates' => $dates]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load availability.']);
}