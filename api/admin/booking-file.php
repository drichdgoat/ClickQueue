<?php
/**
 * SnapVenture Admin - GET api/admin/booking-file.php?booking_id=ID&type=payment_proof[&download=1]
 * Streams the customer's payment receipt to a logged-in admin only. The file path comes from the booking
 * record (never from the request) and must resolve inside uploads/proofs. Read-only: nothing is modified.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
api_require_admin();
api_require_method('GET');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/booking-review.php';

$id = (int) ($_GET['booking_id'] ?? 0);
$type = $_GET['type'] ?? 'payment_proof';
if ($id <= 0 || $type !== 'payment_proof') api_fail(422, 'Invalid request.');

try {
    $stmt = $pdo->prepare('SELECT reference_no, proof_file_path FROM bookings WHERE booking_id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) api_fail(404, 'Booking not found.');

    $abs = br_safe_proof_abs($row['proof_file_path']);
    if ($abs === null) api_fail(404, 'No proof of payment uploaded yet.');

    $mime = br_detect_mime($abs);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'][$mime] ?? null;
    if ($ext === null) api_fail(415, 'This file type cannot be previewed or downloaded.');

    $download = ($_GET['download'] ?? '') === '1';
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $row['reference_no']) . '-proof-of-payment.' . $ext;

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($abs));
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($abs);
    exit;
} catch (Throwable $e) {
    error_log('SnapVenture booking-file error: ' . $e->getMessage());
    api_fail(500, 'Could not open the file.');
}
