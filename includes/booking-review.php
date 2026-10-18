<?php
/**
 * SnapVenture - booking review helpers (admin).
 * Reuses getBookingByReference() from includes/pricing.php; adds no new booking logic.
 */
require_once __DIR__ . '/pricing.php';

const BR_PROOF_DIR = 'uploads/proofs';   // where api/public/book.php stores receipts
const BR_PAYMENT_METHODS_WITHOUT_PROOF = ['Over-the-Counter'];

function br_peso($n): string {
    return '₱' . number_format((float) $n, 2);
}

function br_service_label(string $t): string {
    return $t === 'camera_rental' ? 'Camera Rental' : 'Photobooth';
}

function br_fmt_date(?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('F j, Y', $ts) : $d;
}

function br_fmt_time(?string $t): string {
    if (!$t) return '';
    $ts = strtotime($t);
    return $ts ? date('g:i A', $ts) : $t;
}

/** Full booking (customer + package + add-ons) by id, or null. */
function br_load_booking(PDO $pdo, int $bookingId): ?array {
    $stmt = $pdo->prepare('SELECT reference_no FROM bookings WHERE booking_id = ? LIMIT 1');
    $stmt->execute([$bookingId]);
    $ref = $stmt->fetchColumn();
    if ($ref === false) return null;
    return getBookingByReference($pdo, (string) $ref);
}

function br_package_name(array $b): string {
    $d = $b['detail'] ?? null;
    if (!$d) return '';
    return (string) ($b['service_type'] === 'camera_rental' ? ($d['camera_name'] ?? '') : ($d['photobooth_name'] ?? ''));
}

/** Equipment / package / add-on lines exactly as stored for this booking. */
function br_equipment_lines(array $b): array {
    $lines = [];
    $pkg = br_package_name($b);
    if ($pkg !== '') $lines[] = $pkg;
    foreach (($b['addons'] ?? []) as $a) {
        $qty = isset($a['qty']) ? (int) $a['qty'] : 1;
        $lines[] = $a['name'] . ($qty > 1 ? ' × ' . $qty : '');
    }
    return $lines;
}

function br_event_time(array $b): string {
    $s = br_fmt_time($b['start_time'] ?? null);
    $e = br_fmt_time($b['end_time'] ?? null);
    if ($s && $e) return $s . ' – ' . $e;
    return $s ?: ($e ?: '');
}

/** Duration only from data we actually store: rental days (camera) or start/end hours. */
function br_rental_duration(array $b): string {
    $d = $b['detail'] ?? null;
    if ($b['service_type'] === 'camera_rental' && $d && !empty($d['rental_days'])) {
        $n = (int) $d['rental_days'];
        return $n . ' day' . ($n === 1 ? '' : 's');
    }
    if (!empty($b['start_time']) && !empty($b['end_time'])) {
        $s = strtotime($b['start_time']);
        $e = strtotime($b['end_time']);
        if ($s !== false && $e !== false && $e > $s) {
            $mins = (int) round(($e - $s) / 60);
            $h = intdiv($mins, 60);
            $m = $mins % 60;
            return $h . ' hour' . ($h === 1 ? '' : 's') . ($m ? ' ' . $m . ' min' : '');
        }
    }
    return '';
}

function br_payment_label(string $status): string {
    switch ($status) {
        case 'verified':  return 'Verified by admin (manual review)';
        case 'submitted': return 'Submitted - awaiting manual review';
        default:          return 'Unpaid';
    }
}

function br_proof_required(?string $method): bool {
    return !in_array((string) $method, BR_PAYMENT_METHODS_WITHOUT_PROOF, true);
}

/**
 * Resolves proof_file_path to an absolute path ONLY if it is a regular file
 * directly inside uploads/proofs. Returns null otherwise (path traversal guard).
 */
function br_safe_proof_abs(?string $relative): ?string {
    if (!$relative) return null;
    $name = basename(str_replace('\\', '/', $relative));
    if ($name === '' || $name[0] === '.') return null;
    $dir = realpath(dirname(__DIR__) . '/' . BR_PROOF_DIR);
    if ($dir === false) return null;
    $abs = realpath($dir . DIRECTORY_SEPARATOR . $name);
    if ($abs === false || !is_file($abs)) return null;
    if (strpos($abs, $dir . DIRECTORY_SEPARATOR) !== 0) return null;
    return $abs;
}

function br_detect_mime(string $abs): string {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    $m = $f ? (string) finfo_file($f, $abs) : '';
    if ($f) finfo_close($f);
    return $m;
}

/** Receipt metadata for the viewer (no file content). */
function br_receipt_info(array $b): array {
    $info = ['exists' => false, 'recorded' => !empty($b['proof_file_path']), 'kind' => null,
             'mime' => null, 'filename' => null, 'size' => null, 'submitted_at' => $b['created_at'] ?? null];
    $abs = br_safe_proof_abs($b['proof_file_path'] ?? null);
    if ($abs === null) return $info;
    $mime = br_detect_mime($abs);
    $kinds = ['image/png' => 'image', 'image/jpeg' => 'image', 'application/pdf' => 'pdf'];
    $info['exists'] = true;
    $info['mime'] = $mime;
    $info['kind'] = $kinds[$mime] ?? 'unsupported';
    $info['filename'] = basename($abs);
    $info['size'] = filesize($abs);
    return $info;
}

/**
 * Same availability rule the public booking form uses (one booking per package per date,
 * cancelled bookings don't count), but excluding the booking being reviewed.
 */
function br_availability_conflicts(PDO $pdo, array $b): array {
    $d = $b['detail'] ?? null;
    if (!$d) return [];
    if ($b['service_type'] === 'camera_rental') {
        $sql = "SELECT b.booking_id, b.reference_no, b.status FROM bookings b
                JOIN booking_camera_details x ON x.booking_id = b.booking_id
                WHERE x.camera_id = ? AND b.event_date = ? AND b.status != 'cancelled' AND b.booking_id != ?";
        $pkgId = (int) $d['camera_id'];
    } else {
        $sql = "SELECT b.booking_id, b.reference_no, b.status FROM bookings b
                JOIN booking_photobooth_details x ON x.booking_id = b.booking_id
                WHERE x.photobooth_id = ? AND b.event_date = ? AND b.status != 'cancelled' AND b.booking_id != ?";
        $pkgId = (int) $d['photobooth_id'];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pkgId, $b['event_date'], (int) $b['booking_id']]);
    return $stmt->fetchAll();
}

function br_admin_name(PDO $pdo, $adminId): ?string {
    if (!$adminId) return null;
    $stmt = $pdo->prepare('SELECT name FROM admins WHERE id = ?');
    $stmt->execute([(int) $adminId]);
    $n = $stmt->fetchColumn();
    return $n === false ? null : (string) $n;
}

/**
 * The existing booking form + database collect exactly ONE customer file: the payment receipt.
 * There is no requirements-upload field, handler or column, so nothing is invented here.
 */
function br_requirements_info(): array {
    return [
        'collected' => false,
        'note' => 'The current booking form does not collect any requirement documents, and the database has no '
                . 'column for them. The only customer upload is the proof of payment shown in the Proof of Payment tab.',
        'items' => [],
    ];
}

/** Everything the admin booking-details view shows (only fields that exist). */
function br_details_payload(PDO $pdo, array $b): array {
    $d = $b['detail'] ?? [];
    $receipt = br_receipt_info($b);
    $items = [];
    foreach (($b['addons'] ?? []) as $a) {
        $items[] = ['name' => $a['name'], 'qty' => isset($a['qty']) ? (int) $a['qty'] : 1,
                    'price' => (float) $a['price_applied']];
    }
    return [
        'booking_id'    => (int) $b['booking_id'],
        'reference_no'  => $b['reference_no'],
        'booking_date'  => $b['created_at'],
        'status'        => $b['status'],
        'confirmed_at'  => $b['confirmed_at'] ?? null,
        'customer' => [
            'name' => $b['full_name'], 'email' => $b['email'],
            'phone' => $b['phone'], 'facebook' => $b['facebook_name'],
        ],
        'service_type'  => $b['service_type'],
        'service_label' => br_service_label($b['service_type']),
        'package_name'  => br_package_name($b),
        'camera_detail' => $b['service_type'] === 'camera_rental' ? [
            'rental_days' => $d['rental_days'] ?? null,
            'photo_qty'   => $d['photo_qty'] ?? null,
            'rate_applied' => isset($d['rate_applied']) ? (float) $d['rate_applied'] : null,
            'rental_subtotal' => isset($d['rental_subtotal']) ? (float) $d['rental_subtotal'] : null,
        ] : null,
        'addons'        => $items,
        'event' => [
            'date' => $b['event_date'], 'start_time' => $b['start_time'], 'end_time' => $b['end_time'],
            'time_label' => br_event_time($b), 'location' => $b['event_location'],
            'duration_label' => br_rental_duration($b),
        ],
        'notes'         => $b['notes'],
        'amounts' => [
            'package_price' => (float) $b['package_price'], 'addons_total' => (float) $b['addons_total'],
            'grand_total' => (float) $b['grand_total'], 'amount_due_now' => (float) $b['amount_due_now'],
            'remaining_balance' => (float) $b['remaining_balance'],
        ],
        'payment' => [
            'method' => $b['payment_method'],
            'status' => $b['payment_status'],
            'status_label' => br_payment_label($b['payment_status']),
            'proof_required' => br_proof_required($b['payment_method']),
            'reviewed_at' => $b['payment_reviewed_at'] ?? null,
            'reviewed_by' => br_admin_name($pdo, $b['payment_reviewed_by'] ?? null),
            'receipt' => $receipt,
        ],
        'requirements' => br_requirements_info(),
        'availability' => ['conflicts' => br_availability_conflicts($pdo, $b)],
    ];
}
