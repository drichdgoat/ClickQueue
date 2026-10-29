<?php
/**
 * SnapVenture - Pricing Engine
 * -----------------------------------------------------------------------
 * Single source of truth for camera / photobooth pricing.
 * Both services.php (display) and the booking flow (calculation +
 * final DB insert) call these functions, so the Services page and the
 * Booking page can never show mismatched numbers.
 *
 * Place this file at the project root, next to database.php.
 */

require_once __DIR__ . '/../config/database.php'; // provides $pdo

const CAMERA_DOWNPAYMENT_RATE = 0.5;    // 50% downpayment on camera rentals
const PHOTOBOOTH_RESERVATION_FEE = 300.00; // flat reservation fee, photobooth
const MAX_ADDON_QTY = 10;

/** Turns {addon_id: qty} input into a clean, capped map. */
function sanitizeAddonQty($raw): array {
    $map = [];
    if (!is_array($raw)) return $map;
    foreach ($raw as $id => $qty) {
        $id = (int) $id;
        $qty = (int) $qty;
        if ($id > 0 && $qty > 0) $map[$id] = min($qty, MAX_ADDON_QTY);
    }
    return $map;
}

/* =========================================================
   CATALOG READS (for display: Services page + Booking page)
   ========================================================= */

/**
 * All active camera packages, each with its inclusions and pricing rules
 * attached, ready to be json_encode()'d straight to the front end.
 */
function getCameraCatalog(PDO $pdo): array {
    $cameras = $pdo->query(
        "SELECT camera_id, name, subtitle, image_url, pricing_type
         FROM camera_packages
         WHERE is_active = 1
         ORDER BY sort_order ASC"
    )->fetchAll();

    foreach ($cameras as &$cam) {
        $id = $cam['camera_id'];

        $inc = $pdo->prepare(
            "SELECT inclusion_text FROM camera_inclusions
             WHERE camera_id = ? ORDER BY sort_order ASC"
        );
        $inc->execute([$id]);
        $cam['inclusions'] = $inc->fetchAll(PDO::FETCH_COLUMN);

        switch ($cam['pricing_type']) {
            case 'per_day':
                $t = $pdo->prepare(
                    "SELECT min_days, max_days, rate_per_day FROM camera_rate_tiers
                     WHERE camera_id = ? ORDER BY min_days ASC"
                );
                $t->execute([$id]);
                $cam['rate_tiers'] = $t->fetchAll();
                break;

            case 'per_day_and_hour':
                $t = $pdo->prepare(
                    "SELECT flat_days, flat_price, extra_hour_rate
                     FROM camera_flat_hourly_rates WHERE camera_id = ? LIMIT 1"
                );
                $t->execute([$id]);
                $cam['flat_hourly'] = $t->fetch();
                break;

            case 'per_photo':
                $t = $pdo->prepare(
                    "SELECT min_qty, max_qty, price_per_photo
                     FROM camera_per_photo_rates WHERE camera_id = ? ORDER BY min_qty ASC"
                );
                $t->execute([$id]);
                $cam['photo_tiers'] = $t->fetchAll();
                break;
        }
    }
    unset($cam);

    return $cameras;
}

function getCameraAccessories(PDO $pdo): array {
    return $pdo->query(
        "SELECT accessory_id, name, price FROM camera_accessories
         WHERE is_active = 1 ORDER BY sort_order ASC"
    )->fetchAll();
}

function getPhotoboothCatalog(PDO $pdo): array {
    $packages = $pdo->query(
        "SELECT photobooth_id, name, price, pax_min, pax_max,
                duration_note, print_sizes_note, standee_note
         FROM photobooth_packages
         WHERE is_active = 1 ORDER BY sort_order ASC"
    )->fetchAll();

    $shared = $pdo->query(
        "SELECT inclusion_text FROM photobooth_shared_inclusions ORDER BY sort_order ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($packages as &$pkg) {
        $extra = $pdo->prepare(
            "SELECT inclusion_text FROM photobooth_package_inclusions
             WHERE photobooth_id = ? ORDER BY sort_order ASC"
        );
        $extra->execute([$pkg['photobooth_id']]);
        $pkg['extra_inclusions'] = $extra->fetchAll(PDO::FETCH_COLUMN);
        $pkg['shared_inclusions'] = $shared;
    }
    unset($pkg);

    return $packages;
}

function getPhotoboothAddons(PDO $pdo): array {
    return $pdo->query(
        "SELECT addon_id, name, price FROM photobooth_addons
         WHERE is_active = 1 ORDER BY sort_order ASC"
    )->fetchAll();
}

/* =========================================================
   ADMIN CATALOG READS (includes inactive items, for the
   Equipment management page)
   ========================================================= */

function getCameraCatalogAdmin(PDO $pdo): array {
    $cameras = $pdo->query(
        "SELECT camera_id, name, subtitle, image_url, pricing_type, is_active, sort_order
         FROM camera_packages
         ORDER BY sort_order ASC, camera_id ASC"
    )->fetchAll();

    foreach ($cameras as &$cam) {
        $id = $cam['camera_id'];
        $inc = $pdo->prepare("SELECT inclusion_text FROM camera_inclusions WHERE camera_id = ? ORDER BY sort_order ASC");
        $inc->execute([$id]);
        $cam['inclusions'] = $inc->fetchAll(PDO::FETCH_COLUMN);

        switch ($cam['pricing_type']) {
            case 'per_day':
                $t = $pdo->prepare("SELECT min_days, max_days, rate_per_day FROM camera_rate_tiers WHERE camera_id = ? ORDER BY min_days ASC");                $t->execute([$id]);
                $cam['rate_tiers'] = $t->fetchAll();
                break;
            case 'per_day_and_hour':
                $t = $pdo->prepare("SELECT flat_days, flat_price, extra_hour_rate FROM camera_flat_hourly_rates WHERE camera_id = ? LIMIT 1");
                $t->execute([$id]);
                $cam['flat_hourly'] = $t->fetch();
                break;
            case 'per_photo':
                $t = $pdo->prepare("SELECT min_qty, max_qty, price_per_photo FROM camera_per_photo_rates WHERE camera_id = ? ORDER BY min_qty ASC");
                $t->execute([$id]);
                $cam['photo_tiers'] = $t->fetchAll();
                break;
        }

        $bc = $pdo->prepare("SELECT COUNT(*) FROM booking_camera_details WHERE camera_id = ?");
        $bc->execute([$id]);
        $cam['booking_count'] = (int) $bc->fetchColumn();
    }
    unset($cam);

    return $cameras;
}

function getCameraAccessoriesAdmin(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT accessory_id, name, price, is_active, sort_order FROM camera_accessories
         ORDER BY sort_order ASC, accessory_id ASC"
    )->fetchAll();
    foreach ($rows as &$r) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_camera_accessories WHERE accessory_id = ?");
        $stmt->execute([$r['accessory_id']]);
        $r['booking_count'] = (int) $stmt->fetchColumn();
    }
    unset($r);
    return $rows;
}

/**
 * Computes the correct camera-rental total.
 *
 * @param int      $cameraId
 * @param int|null $days      required for per_day and per_day_and_hour cameras
 * @param int|null $extraHours  only used for per_day_and_hour cameras (e.g. Instax Pal)
 * @param int|null $photoQty  only used for per_photo cameras (e.g. Instax Mini Evo)
 * @return array{rate_applied: float, basis: string, subtotal: float}
 * @throws InvalidArgumentException if inputs don't match a defined pricing rule
 */
function calculateCameraRental(PDO $pdo, int $cameraId, ?int $days, ?int $extraHours, ?int $photoQty): array {
    $cam = $pdo->prepare("SELECT pricing_type, name FROM camera_packages WHERE camera_id = ?");
    $cam->execute([$cameraId]);
    $cam = $cam->fetch();
    if (!$cam) {
        throw new InvalidArgumentException('Unknown camera package.');
    }

    switch ($cam['pricing_type']) {
        case 'per_day':
            if (!$days || $days < 1) {
                throw new InvalidArgumentException('Rental days is required for ' . $cam['name']);
            }
            $t = $pdo->prepare(
                "SELECT rate_per_day FROM camera_rate_tiers
                 WHERE camera_id = ?
                   AND min_days <= ?
                   AND (max_days IS NULL OR max_days >= ?)
                 ORDER BY min_days DESC LIMIT 1"
            );
            $t->execute([$cameraId, $days, $days]);
            $row = $t->fetch();
            if (!$row) {
                throw new InvalidArgumentException('No rate defined for ' . $days . ' day(s) on ' . $cam['name']);
            }
            $rate = (float) $row['rate_per_day'];
            return [
                'rate_applied' => $rate,
                'basis'        => $rate . '/day x ' . $days . ' day(s)',
                'subtotal'     => round($rate * $days, 2),
            ];

        case 'per_day_and_hour':
            $t = $pdo->prepare(
                "SELECT flat_days, flat_price, extra_hour_rate
                 FROM camera_flat_hourly_rates WHERE camera_id = ? LIMIT 1"
            );
            $t->execute([$cameraId]);
            $row = $t->fetch();
            if (!$row) {
                throw new InvalidArgumentException('No flat/hourly rate defined for ' . $cam['name']);
            }
            $days = max(1, (int) ($days ?? 1));
            $extraHours = max(0, (int) ($extraHours ?? 0));
            $flatPrice = (float) $row['flat_price'];
            $hourRate  = (float) $row['extra_hour_rate'];
            // Flat price covers up to flat_days; extra days beyond that are
            // charged as if each extra day = 24 extra hours, kept explicit
            // rather than assumed, since the source image only defines
            // the flat block + an hourly rate.
            $extraDays = max(0, $days - (int) $row['flat_days']);
            $subtotal = $flatPrice + ($extraHours * $hourRate) + ($extraDays * 24 * $hourRate);
            return [
                'rate_applied' => $flatPrice,
                'basis'        => 'Flat \u20B1' . $flatPrice . ' (' . $row['flat_days'] . ' day/s)'
                                   . ($extraHours > 0 ? ' + ' . $extraHours . 'hr x \u20B1' . $hourRate : ''),
                'subtotal'     => round($subtotal, 2),
            ];

        case 'per_photo':
            if (!$photoQty || $photoQty < 1) {
                throw new InvalidArgumentException('Photo quantity is required for ' . $cam['name']);
            }
            $t = $pdo->prepare(
                "SELECT price_per_photo FROM camera_per_photo_rates
                 WHERE camera_id = ?
                   AND min_qty <= ?
                   AND (max_qty IS NULL OR max_qty >= ?)
                 ORDER BY min_qty DESC LIMIT 1"
            );
            $t->execute([$cameraId, $photoQty, $photoQty]);
            $row = $t->fetch();
            if (!$row) {
                throw new InvalidArgumentException('No per-photo rate defined for qty ' . $photoQty);
            }
            $rate = (float) $row['price_per_photo'];
            return [
                'rate_applied' => $rate,
                'basis'        => $rate . '/photo x ' . $photoQty,
                'subtotal'     => round($rate * $photoQty, 2),
            ];

        default:
            throw new InvalidArgumentException('Unsupported pricing type.');
    }
}

/**
 * Sums the price of the selected accessory IDs (Film / BOYA Mic).
 * @param int[] $accessoryIds
 */
function calculateAccessoriesTotal(PDO $pdo, array $accessoryIds): array {
    if (empty($accessoryIds)) {
        return ['items' => [], 'total' => 0.00];
    }
    $placeholders = implode(',', array_fill(0, count($accessoryIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT accessory_id, name, price FROM camera_accessories
         WHERE accessory_id IN ($placeholders) AND is_active = 1"
    );
    $stmt->execute($accessoryIds);
    $items = $stmt->fetchAll();
    $total = array_sum(array_column($items, 'price'));
    return ['items' => $items, 'total' => round((float) $total, 2)];
}

/**
 * Full camera-rental booking summary: rental + accessories + 50% downpayment.
 */
function buildCameraBookingSummary(PDO $pdo, int $cameraId, ?int $days, ?int $extraHours, ?int $photoQty, array $accessoryIds): array {
    $rental = calculateCameraRental($pdo, $cameraId, $days, $extraHours, $photoQty);
    $accessories = calculateAccessoriesTotal($pdo, $accessoryIds);

    $grandTotal = round($rental['subtotal'] + $accessories['total'], 2);
    $downpayment = round($grandTotal * CAMERA_DOWNPAYMENT_RATE, 2);
    $remaining = round($grandTotal - $downpayment, 2);

    return [
        'rental'            => $rental,
        'accessories'       => $accessories,
        'package_price'     => $rental['subtotal'],
        'addons_total'      => $accessories['total'],
        'grand_total'       => $grandTotal,
        'amount_due_now'    => $downpayment,
        'remaining_balance' => $remaining,
    ];
}

/**
 * Sums the price of the selected photobooth add-on IDs, honoring quantity
 * (only really relevant for "Additional Hours" if booked more than once).
 * @param array<int,int> $addonQtyMap addon_id => qty
 */
function calculatePhotoboothAddonsTotal(PDO $pdo, array $addonQtyMap): array {
    if (empty($addonQtyMap)) {
        return ['items' => [], 'total' => 0.00];
    }
    $ids = array_keys($addonQtyMap);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT addon_id, name, price FROM photobooth_addons
         WHERE addon_id IN ($placeholders) AND is_active = 1"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $items = [];
    $total = 0.0;
    foreach ($rows as $row) {
        $qty = max(1, (int) ($addonQtyMap[$row['addon_id']] ?? 1));
        $lineTotal = round((float) $row['price'] * $qty, 2);
        $items[] = [
            'addon_id'  => $row['addon_id'],
            'name'      => $row['name'],
            'price'     => (float) $row['price'],
            'qty'       => $qty,
            'line_total'=> $lineTotal,
        ];
        $total += $lineTotal;
    }
    return ['items' => $items, 'total' => round($total, 2)];
}

/**
 * Full photobooth booking summary: package price + add-ons, then the
 * flat ₱300 reservation fee is due now -- the rest is due on event day.
 */
function buildPhotoboothBookingSummary(PDO $pdo, int $photoboothId, array $addonQtyMap): array {
    $pkg = $pdo->prepare("SELECT name, price FROM photobooth_packages WHERE photobooth_id = ? AND is_active = 1");
    $pkg->execute([$photoboothId]);
    $pkg = $pkg->fetch();
    if (!$pkg) {
        throw new InvalidArgumentException('Unknown photobooth package.');
    }

    $addons = calculatePhotoboothAddonsTotal($pdo, $addonQtyMap);
    $packagePrice = (float) $pkg['price'];
    $grandTotal = round($packagePrice + $addons['total'], 2);
    $dueNow = PHOTOBOOTH_RESERVATION_FEE;
    $remaining = round($grandTotal - $dueNow, 2);

    return [
        'package_name'      => $pkg['name'],
        'addons'            => $addons,
        'package_price'     => $packagePrice,
        'addons_total'      => $addons['total'],
        'grand_total'       => $grandTotal,
        'amount_due_now'    => $dueNow,
        'remaining_balance' => $remaining,
    ];
}

/* =========================================================
   AVAILABILITY (1 unit per package per date -- same rule as
   your existing booking-calendar.js MAX_PER_SERVICE_PER_DAY)
   ========================================================= */

/**
 * Fetches one booking (with customer info and camera/photobooth package
 * name) by its public reference number. Returns null if not found.
 */
function getBookingByReference(PDO $pdo, string $referenceNo): ?array {
    $stmt = $pdo->prepare(
        "SELECT b.*,
                COALESCE(b.contact_name, c.full_name)         AS full_name,
                COALESCE(b.contact_phone, c.phone)            AS phone,
                COALESCE(b.contact_facebook, c.facebook_name) AS facebook_name,
                c.email
         FROM bookings b
         JOIN customers c ON c.customer_id = b.customer_id
         WHERE b.reference_no = ? LIMIT 1"
    );
    $stmt->execute([$referenceNo]);
    $booking = $stmt->fetch();
    if (!$booking) return null;

    if ($booking['service_type'] === 'camera_rental') {
        $d = $pdo->prepare(
            "SELECT d.*, cp.name AS camera_name FROM booking_camera_details d
             JOIN camera_packages cp ON cp.camera_id = d.camera_id
             WHERE d.booking_id = ? LIMIT 1"
        );
        $d->execute([$booking['booking_id']]);
        $booking['detail'] = $d->fetch();

        $a = $pdo->prepare(
            "SELECT bca.price_applied, ca.name FROM booking_camera_accessories bca
             JOIN camera_accessories ca ON ca.accessory_id = bca.accessory_id
             WHERE bca.booking_id = ?"
        );
        $a->execute([$booking['booking_id']]);
        $booking['addons'] = $a->fetchAll();
    } else {
        $d = $pdo->prepare(
            "SELECT d.*, pp.name AS photobooth_name FROM booking_photobooth_details d
             JOIN photobooth_packages pp ON pp.photobooth_id = d.photobooth_id
             WHERE d.booking_id = ? LIMIT 1"
        );
        $d->execute([$booking['booking_id']]);
        $booking['detail'] = $d->fetch();

        $a = $pdo->prepare(
            "SELECT bpa.price_applied, bpa.qty, pa.name FROM booking_photobooth_addons bpa
             JOIN photobooth_addons pa ON pa.addon_id = bpa.addon_id
             WHERE bpa.booking_id = ?"
        );
        $a->execute([$booking['booking_id']]);
        $booking['addons'] = $a->fetchAll();
    }

    return $booking;
}

function isDateTaken(PDO $pdo, string $serviceType, int $packageId, string $date, int $days = 1): bool {
    $days = max(1, $days);
    $endDate = (new DateTimeImmutable($date))->modify('+' . $days . ' days')->format('Y-m-d');
    if ($serviceType === 'camera_rental') {
        $sql = "SELECT COUNT(*) FROM bookings b
                JOIN booking_camera_details d ON d.booking_id = b.booking_id
                WHERE d.camera_id = ?
                  AND b.event_date < ?
                  AND DATE_ADD(b.event_date, INTERVAL GREATEST(COALESCE(d.rental_days, 1), 1) DAY) > ?
                  AND b.status != 'cancelled'";
    } else {
        $sql = "SELECT COUNT(*) FROM bookings b
                JOIN booking_photobooth_details d ON d.booking_id = b.booking_id
                WHERE d.photobooth_id = ? AND b.event_date = ? AND b.status != 'cancelled'";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($serviceType === 'camera_rental'
        ? [$packageId, $endDate, $date]
        : [$packageId, $date]);
    return ((int) $stmt->fetchColumn()) >= 1;
}