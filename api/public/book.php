<?php
/**
 * SnapVenture - POST /api/book.php  (multipart/form-data)
 * Final booking submission. Recomputes price from scratch using
 * pricing.php so nothing the browser sent is trusted for money math.
 *
 * Expected fields:
 *  full_name, phone, facebook_name, email
 *  service_type = camera_rental | photobooth
 *  -- camera_rental --
 *  camera_id, days, extra_hours, photo_qty, accessory_ids[] (repeatable)
 *  -- photobooth --
 *  photobooth_id, addon_ids[] (repeatable)
 *  -- common --
 *  event_date, start_time, end_time, event_location, notes
 *  payment_method, agree_terms ("1")
 *  proof_file (uploaded file, optional depending on payment_method)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/pricing.php';

function fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Method not allowed.');
}

/* ---------------- customer info ---------------- */
$fullName = trim($_POST['full_name'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$facebook = trim($_POST['facebook_name'] ?? '');
$email    = trim($_POST['email'] ?? '');

if ($fullName === '' || $phone === '' || $facebook === '' || $email === '') {
    fail(422, 'Please complete all customer information fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail(422, 'Please enter a valid email address.');
}

/* ---------------- service selection ---------------- */
$serviceType = $_POST['service_type'] ?? '';
if (!in_array($serviceType, ['camera_rental', 'photobooth'], true)) {
    fail(422, 'Please select a service type.');
}

/* ---------------- event/schedule ---------------- */
$eventDate = $_POST['event_date'] ?? '';
$startTime = $_POST['start_time'] ?? null;
$endTime   = $_POST['end_time'] ?? null;
$location  = trim($_POST['event_location'] ?? '');
$notes     = trim($_POST['notes'] ?? '');

if (!$eventDate || !DateTime::createFromFormat('Y-m-d', $eventDate)) {
    fail(422, 'Please select a valid date.');
}
if ($eventDate < date('Y-m-d')) {
    fail(422, 'The selected date has already passed.');
}
if ($location === '') {
    fail(422, 'Please enter the event location.');
}

/* ---------------- payment method / terms ---------------- */
$paymentMethod = $_POST['payment_method'] ?? '';
$agreeTerms = ($_POST['agree_terms'] ?? '') === '1';
$validMethods = ['Bank Transfer', 'GCash / PayMaya', 'Over-the-Counter'];
if (!in_array($paymentMethod, $validMethods, true)) {
    fail(422, 'Please choose a payment method.');
}
if (!$agreeTerms) {
    fail(422, 'Please agree to the rental terms and conditions.');
}
$needsProof = $paymentMethod !== 'Over-the-Counter';

/* ---------------- proof of payment upload ---------------- */
$proofPath = null;
if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['proof_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        fail(422, 'There was a problem uploading your file.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        fail(422, 'Proof of payment must be 5MB or smaller.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
    if (!isset($allowed[$mime])) {
        fail(422, 'Proof of payment must be a PNG, JPG, or PDF file.');
    }

    $uploadDir = __DIR__ . '/../../uploads/proofs/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            error_log('SnapVenture: could not create upload dir ' . $uploadDir);
            fail(500, 'Server could not create the uploads folder. Please ask the site admin to create "uploads/proofs" and give it write permission (755).');
        }
    }
    if (!is_writable($uploadDir)) {
        error_log('SnapVenture: upload dir not writable: ' . $uploadDir);
        fail(500, 'Server cannot write to the uploads folder. Please ask the site admin to set "uploads/proofs" permission to 755.');
    }

    $filename = 'proof_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $lastErr = error_get_last();
        error_log('SnapVenture: move_uploaded_file failed: ' . ($lastErr['message'] ?? 'unknown error'));
        fail(500, 'Could not save the uploaded file. Please try again, or ask the site admin to check folder permissions on "uploads/proofs".');
    }
    $proofPath = 'uploads/proofs/' . $filename;
} elseif ($needsProof) {
    fail(422, 'Please upload your proof of payment for this payment method.');
}

/* ---------------- authoritative price calculation ---------------- */
try {
    if ($serviceType === 'camera_rental') {
        $cameraId = (int) ($_POST['camera_id'] ?? 0);
        if (!$cameraId) fail(422, 'Please select a camera package.');

        $days = isset($_POST['days']) && $_POST['days'] !== '' ? (int) $_POST['days'] : null;
        $extraHours = isset($_POST['extra_hours']) && $_POST['extra_hours'] !== '' ? (int) $_POST['extra_hours'] : null;
        $photoQty = isset($_POST['photo_qty']) && $_POST['photo_qty'] !== '' ? (int) $_POST['photo_qty'] : null;
        $accessoryIds = array_map('intval', $_POST['accessory_ids'] ?? []);

        if (isDateTaken($pdo, 'camera_rental', $cameraId, $eventDate)) {
            fail(409, 'Sorry, that camera is already booked on this date. Please choose another date.');
        }

        $summary = buildCameraBookingSummary($pdo, $cameraId, $days, $extraHours, $photoQty, $accessoryIds);
        $packageId = $cameraId;

    } else {
        $photoboothId = (int) ($_POST['photobooth_id'] ?? 0);
        if (!$photoboothId) fail(422, 'Please select a photobooth package.');

        $addonQtyMap = sanitizeAddonQty($_POST['addon_qty'] ?? []);

        if (isDateTaken($pdo, 'photobooth', $photoboothId, $eventDate)) {
            fail(409, 'Sorry, that package is already booked on this date. Please choose another date.');
        }

        $summary = buildPhotoboothBookingSummary($pdo, $photoboothId, $addonQtyMap);
        $packageId = $photoboothId;
    }
} catch (InvalidArgumentException $e) {
    fail(422, $e->getMessage());
}

/* ---------------- save everything in a transaction ---------------- */
try {
    $pdo->beginTransaction();

    // find or create the customer
    $find = $pdo->prepare('SELECT customer_id FROM customers WHERE email = ?');
    $find->execute([$email]);
    $customerId = $find->fetchColumn();
    if (!$customerId) {
        $ins = $pdo->prepare(
            'INSERT INTO customers (full_name, phone, facebook_name, email) VALUES (?,?,?,?)'
        );
        $ins->execute([$fullName, $phone, $facebook, $email]);
        $customerId = $pdo->lastInsertId();
    } else {
        // keep contact info current
        $upd = $pdo->prepare(
            'UPDATE customers SET full_name=?, phone=?, facebook_name=? WHERE customer_id=?'
        );
        $upd->execute([$fullName, $phone, $facebook, $customerId]);
    }

    $referenceNo = 'SV-' . strtoupper(base_convert((string) time(), 10, 36)) . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));

    $insBooking = $pdo->prepare(
        'INSERT INTO bookings
            (reference_no, customer_id, service_type, event_date, start_time, end_time,
             event_location, notes, package_price, addons_total, grand_total,
             amount_due_now, remaining_balance, payment_method, payment_status, proof_file_path, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insBooking->execute([
        $referenceNo,
        $customerId,
        $serviceType,
        $eventDate,
        $startTime ?: null,
        $endTime ?: null,
        $location,
        $notes ?: null,
        $summary['package_price'],
        $summary['addons_total'],
        $summary['grand_total'],
        $summary['amount_due_now'],
        $summary['remaining_balance'],
        $paymentMethod,
        'submitted',
        $proofPath,
        'pending',
    ]);
    $bookingId = $pdo->lastInsertId();

    if ($serviceType === 'camera_rental') {
        $insDetail = $pdo->prepare(
            'INSERT INTO booking_camera_details
                (booking_id, camera_id, rental_days, photo_qty, rate_applied, rental_subtotal)
             VALUES (?,?,?,?,?,?)'
        );
        $insDetail->execute([
            $bookingId,
            $packageId,
            $days,
            $photoQty,
            $summary['rental']['rate_applied'],
            $summary['rental']['subtotal'],
        ]);

        if (!empty($summary['accessories']['items'])) {
            $insAcc = $pdo->prepare(
                'INSERT INTO booking_camera_accessories (booking_id, accessory_id, price_applied) VALUES (?,?,?)'
            );
            foreach ($summary['accessories']['items'] as $acc) {
                $insAcc->execute([$bookingId, $acc['accessory_id'], $acc['price']]);
            }
        }
    } else {
        $insDetail = $pdo->prepare(
            'INSERT INTO booking_photobooth_details (booking_id, photobooth_id, package_price_applied) VALUES (?,?,?)'
        );
        $insDetail->execute([$bookingId, $packageId, $summary['package_price']]);

        if (!empty($summary['addons']['items'])) {
            $insAddon = $pdo->prepare(
                'INSERT INTO booking_photobooth_addons (booking_id, addon_id, qty, price_applied) VALUES (?,?,?,?)'
            );
            foreach ($summary['addons']['items'] as $addon) {
                $insAddon->execute([$bookingId, $addon['addon_id'], $addon['qty'], $addon['line_total']]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'reference_no'  => $referenceNo,
        'summary'       => $summary,
        'email'         => $email,
        'event_date'    => $eventDate,
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($proofPath && file_exists(__DIR__ . '/../../' . $proofPath)) {
        unlink(__DIR__ . '/../../' . $proofPath); // clean up orphaned upload
    }
    fail(500, 'We could not save your reservation. Please try again.');
}