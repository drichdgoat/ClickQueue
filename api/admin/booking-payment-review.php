<?php
/**
 * SnapVenture Admin - POST api/admin/booking-payment-review.php
 * Body: booking_id, decision = 'verified' | 'submitted', csrf_token
 *
 * This ONLY records the administrator's manual review of the receipt (who + when).
 * It is independent of booking status: confirming a booking never calls this, and this never confirms a booking.
 * The system does not verify payments with any bank or gateway.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
$adminId = api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/booking-review.php';

$id = (int) ($_POST['booking_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
if ($id <= 0 || !in_array($decision, ['verified', 'submitted'], true)) api_fail(422, 'Invalid request.');

try {
    $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT booking_id, payment_method, payment_status, proof_file_path FROM bookings WHERE booking_id = ? FOR UPDATE');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) { $pdo->rollBack(); api_fail(404, 'Booking not found.'); }

    if ($decision === 'verified') {
        if (br_proof_required($row['payment_method']) && br_safe_proof_abs($row['proof_file_path']) === null) {
            $pdo->rollBack();
            api_fail(422, 'There is no receipt on file for this booking, so the payment cannot be marked as verified.');
        }
        $pdo->prepare("UPDATE bookings SET payment_status = 'verified', payment_reviewed_by = ?, payment_reviewed_at = NOW() WHERE booking_id = ?")
            ->execute([$adminId, $id]);
    } else {
        $pdo->prepare("UPDATE bookings SET payment_status = 'submitted', payment_reviewed_by = NULL, payment_reviewed_at = NULL WHERE booking_id = ?")
            ->execute([$id]);
    }
    $pdo->commit();
    api_json(['success' => true, 'payment_status' => $decision === 'verified' ? 'verified' : 'submitted',
              'status_label' => br_payment_label($decision === 'verified' ? 'verified' : 'submitted')]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SnapVenture booking-payment-review error: ' . $e->getMessage());
    api_fail(500, 'Could not update the payment review. If this is a new install, apply database/migrations/001_booking_review_and_agreements.sql.');
}
