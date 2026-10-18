<?php
/**
 * SnapVenture Admin - GET api/admin/booking-details.php?booking_id=ID
 * Read-only. Returns every stored field for the booking review view. It never generates or sends anything.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
api_require_admin();
api_require_method('GET');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$id = (int) ($_GET['booking_id'] ?? 0);
if ($id <= 0) api_fail(422, 'Invalid booking id.');

try {
    $b = br_load_booking($pdo, $id);
    if (!$b) api_fail(404, 'Booking not found.');
    $payload = br_details_payload($pdo, $b);
    $agr = agr_current_for_booking($pdo, $id);
    $payload['agreement'] = $agr ? agr_summary($agr) : null;
    $payload['agreement_setup'] = [
        'rep_ready'    => agr_rep_ready(agr_settings($pdo)),
        'has_template' => agr_active_template($pdo) !== null,
    ];
    api_json(['success' => true, 'booking' => $payload]);
} catch (Throwable $e) {
    error_log('SnapVenture booking-details error: ' . $e->getMessage());
    api_fail(500, 'Could not load the booking details.');
}
