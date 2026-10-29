<?php
/**
 * SnapVenture - GET /api/availability.php
 * ?service_type=camera_rental|photobooth&package_id=3&year=2026&month=10
 * Returns both occupied start dates and occupied date ranges overlapping
 * the requested calendar month and rental length.
 * A package is considered "1 unit" -- once it has a non-cancelled booking
 * on a date, that date is fully booked for that specific package.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/pricing.php';

$serviceType = $_GET['service_type'] ?? '';
$packageId   = (int) ($_GET['package_id'] ?? 0);
$year        = (int) ($_GET['year'] ?? 0);
$month       = (int) ($_GET['month'] ?? 0);
$days        = max(1, (int) ($_GET['days'] ?? 1));

if (!in_array($serviceType, ['camera_rental', 'photobooth'], true) || !$packageId || !$year || !$month) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid parameters.']);
    exit;
}

try {
    $start = sprintf('%04d-%02d-01', $year, $month);
    $calendarEnd = date('Y-m-t', strtotime($start));
    $coverageEnd = (new DateTimeImmutable($calendarEnd))->modify('+' . ($days - 1) . ' days')->format('Y-m-d');

    if ($serviceType === 'camera_rental') {
        $sql = "SELECT b.event_date, GREATEST(COALESCE(d.rental_days, 1), 1) AS rental_days FROM bookings b
                JOIN booking_camera_details d ON d.booking_id = b.booking_id
                WHERE d.camera_id = ?
                  AND b.status != 'cancelled'
                  AND b.event_date <= ?
                  AND DATE_ADD(b.event_date, INTERVAL GREATEST(COALESCE(d.rental_days, 1), 1) DAY) > ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$packageId, $coverageEnd, $start]);
        $rows = $stmt->fetchAll();
        $ranges = [];
        foreach ($rows as $row) {
            $rangeStart = new DateTimeImmutable($row['event_date']);
            $rangeEnd = $rangeStart->modify('+' . ((int) $row['rental_days'] - 1) . ' days');
            $ranges[] = [
                'start_date' => $rangeStart->format('Y-m-d'),
                'end_date' => $rangeEnd->format('Y-m-d'),
            ];
        }
    } else {
        $sql = "SELECT DISTINCT b.event_date FROM bookings b
                JOIN booking_photobooth_details d ON d.booking_id = b.booking_id
                WHERE d.photobooth_id = ? AND b.status != 'cancelled'
                  AND b.event_date BETWEEN ? AND ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$packageId, $start, $coverageEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ranges = array_map(static function ($date) {
            return ['start_date' => $date, 'end_date' => $date];
        }, $rows);
    }

    echo json_encode([
        'booked_dates' => array_column($ranges, 'start_date'),
        'booked_ranges' => $ranges,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load availability.']);
}