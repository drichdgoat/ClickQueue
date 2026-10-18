<?php
/**
 * SnapVenture Admin - POST api/admin/booking-confirm.php
 * Body: booking_id, ack_details=1, ack_receipt=1, csrf_token
 *
 * The ONLY way to set a booking to "confirmed" (the old dropdown form is blocked for that value).
 * Requires the admin's explicit acknowledgment, checks availability and receipt, sets status + audit columns,
 * and does NOT touch payment_status. Then it creates the agreement PDF (idempotent) but never emails it.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
$adminId = api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$id = (int) ($_POST['booking_id'] ?? 0);
if ($id <= 0) api_fail(422, 'Invalid booking id.');
if (($_POST['ack_details'] ?? '') !== '1' || ($_POST['ack_receipt'] ?? '') !== '1') {
    api_fail(422, 'Please confirm that you reviewed the booking details and the payment information first.');
}

try {
    $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT booking_id FROM bookings WHERE booking_id = ? FOR UPDATE');
    $s->execute([$id]);
    if (!$s->fetchColumn()) { $pdo->rollBack(); api_fail(404, 'Booking not found.'); }

    $b = br_load_booking($pdo, $id);
    $alreadyConfirmed = $b['status'] === 'confirmed';

    if (!$alreadyConfirmed) {
        if ($b['status'] !== 'pending') {
            $pdo->rollBack();
            api_fail(409, 'Only pending bookings can be confirmed. This booking is "' . $b['status'] . '"; set it back to Pending first if that is intended.');
        }
        if (br_proof_required($b['payment_method']) && br_safe_proof_abs($b['proof_file_path']) === null) {
            $pdo->rollBack();
            api_fail(422, 'No proof of payment is on file for this booking (' . $b['payment_method'] . '). It cannot be confirmed until a receipt is available.');
        }
        $conf = br_availability_conflicts($pdo, $b);
        if ($conf) {
            $pdo->rollBack();
            api_fail(409, 'Another active booking (' . $conf[0]['reference_no'] . ') already uses this package on ' . br_fmt_date($b['event_date']) . '. Resolve the conflict first.');
        }
        $pdo->prepare("UPDATE bookings SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()), confirmed_by = ? WHERE booking_id = ?")
            ->execute([$adminId, $id]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SnapVenture booking-confirm error: ' . $e->getMessage());
    api_fail(500, 'Could not confirm the booking. If this is a new install, apply database/migrations/001_booking_review_and_agreements.sql.');
}

/* Booking is confirmed and saved. Agreement generation is a separate step so a PDF/setup problem
   never undoes the confirmation; the admin can retry it from the popup. */
$out = ['success' => true, 'already_confirmed' => $alreadyConfirmed, 'agreement' => null, 'agreement_error' => null];
try {
    $agr = agr_generate($pdo, $id, $adminId, false);
    $out['agreement'] = agr_summary($agr);
} catch (AgreementException $e) {
    $out['agreement_error'] = ['code' => $e->errorCode, 'message' => $e->getMessage()];
} catch (Throwable $e) {
    error_log('SnapVenture agreement generation error: ' . $e->getMessage());
    $out['agreement_error'] = ['code' => 'unexpected', 'message' => 'The agreement could not be generated. See the server error log.'];
}
api_json($out);
