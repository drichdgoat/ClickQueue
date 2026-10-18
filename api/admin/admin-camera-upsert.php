<?php
/**
 * SnapVenture Admin - POST /api/admin-camera-upsert.php  (multipart/form-data)
 * Creates a new camera package, or updates an existing one when camera_id
 * is provided. Because the Services page and Booking page both read from
 * camera_packages via api/packages.php, anything saved here becomes
 * visible there immediately -- no other file needs to change.
 *
 * Fields:
 *   camera_id       (optional -- present when editing)
 *   name            (required)
 *   subtitle        (optional)
 *   pricing_type    (required: per_day | per_day_and_hour | per_photo)
 *   inclusions_json (required JSON array of strings, e.g. ["SD Card","Tripod"])
 *   tiers_json      (required JSON, shape depends on pricing_type -- see below)
 *   is_active       ("1" or "0", default "1")
 *   image           (file -- REQUIRED when creating a new camera;
 *                    optional when editing, keeps the old photo if omitted)
 *
 * tiers_json shapes:
 *   per_day:          [{"min_days":2,"max_days":4,"rate_per_day":500}, {"min_days":5,"max_days":null,"rate_per_day":450}]
 *   per_day_and_hour: {"flat_days":2,"flat_price":220,"extra_hour_rate":60}
 *   per_photo:        [{"min_qty":1,"max_qty":2,"price_per_photo":75}, ...]
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (!isset($_SESSION['admin_id'])) fail(401, 'Not authenticated.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');

require_once __DIR__ . '/../../includes/pricing.php';

$cameraId = isset($_POST['camera_id']) && $_POST['camera_id'] !== '' ? (int) $_POST['camera_id'] : null;
$isEdit = $cameraId !== null;

$name = trim($_POST['name'] ?? '');
$subtitle = trim($_POST['subtitle'] ?? '');
$pricingType = $_POST['pricing_type'] ?? '';
$isActive = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

if ($name === '') fail(422, 'Please enter the camera/package name.');
if (!in_array($pricingType, ['per_day', 'per_day_and_hour', 'per_photo'], true)) {
    fail(422, 'Please choose a valid pricing type.');
}

$inclusions = json_decode($_POST['inclusions_json'] ?? '[]', true);
if (!is_array($inclusions)) $inclusions = [];
$inclusions = array_values(array_filter(array_map('trim', $inclusions), fn($v) => $v !== ''));

$tiers = json_decode($_POST['tiers_json'] ?? 'null', true);
if ($pricingType === 'per_day' || $pricingType === 'per_photo') {
    if (!is_array($tiers) || count($tiers) === 0) {
        fail(422, 'Please add at least one pricing tier.');
    }
} else { // per_day_and_hour
    if (!is_array($tiers) || !isset($tiers['flat_days'], $tiers['flat_price'], $tiers['extra_hour_rate'])) {
        fail(422, 'Please fill in the flat rate and hourly rate fields.');
    }
}

/* ---------------- image upload (required on create) ---------------- */
$newImagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) fail(422, 'There was a problem uploading the image.');
    if ($file['size'] > 8 * 1024 * 1024) fail(422, 'Image must be 8MB or smaller.');

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) fail(422, 'Image must be a JPG, PNG, or WEBP file.');

    $camerasDir = __DIR__ . '/../../images/cameras/';
    if (!is_dir($camerasDir) && !mkdir($camerasDir, 0755, true) && !is_dir($camerasDir)) {
        fail(500, 'Could not create images/cameras folder. Please create it manually and set permission to 755.');
    }
    if (!is_writable($camerasDir)) {
        fail(500, '"images/cameras" is not writable. Please set its permission to 755.');
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    $filename = $slug . '-' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $camerasDir . $filename)) {
        fail(500, 'Could not save the uploaded image.');
    }
    $newImagePath = 'images/cameras/' . $filename;
} elseif (!$isEdit) {
    fail(422, 'A photo is required when adding a new camera.');
}

/* ---------------- save ---------------- */
try {
    $pdo->beginTransaction();

    if ($isEdit) {
        $existing = $pdo->prepare('SELECT image_url FROM camera_packages WHERE camera_id = ?');
        $existing->execute([$cameraId]);
        $existingRow = $existing->fetch();
        if (!$existingRow) fail(404, 'Camera not found.');

        $imageToSave = $newImagePath ?? $existingRow['image_url'];
        $upd = $pdo->prepare(
            'UPDATE camera_packages SET name=?, subtitle=?, image_url=?, pricing_type=?, is_active=? WHERE camera_id=?'
        );
        $upd->execute([$name, $subtitle ?: null, $imageToSave, $pricingType, $isActive, $cameraId]);

        // clean slate for tiers/inclusions, then re-insert below
        $pdo->prepare('DELETE FROM camera_inclusions WHERE camera_id = ?')->execute([$cameraId]);
        $pdo->prepare('DELETE FROM camera_rate_tiers WHERE camera_id = ?')->execute([$cameraId]);
        $pdo->prepare('DELETE FROM camera_flat_hourly_rates WHERE camera_id = ?')->execute([$cameraId]);
        $pdo->prepare('DELETE FROM camera_per_photo_rates WHERE camera_id = ?')->execute([$cameraId]);

        // remove the old photo file only if we actually replaced it
        if ($newImagePath && $existingRow['image_url'] && $existingRow['image_url'] !== $newImagePath) {
            $oldFile = __DIR__ . '/../../' . $existingRow['image_url'];
            if (is_file($oldFile)) @unlink($oldFile);
        }
    } else {
        $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM camera_packages')->fetchColumn();
        $ins = $pdo->prepare(
            'INSERT INTO camera_packages (name, subtitle, image_url, pricing_type, is_active, sort_order) VALUES (?,?,?,?,?,?)'
        );
        $ins->execute([$name, $subtitle ?: null, $newImagePath, $pricingType, $isActive, $nextSort]);
        $cameraId = (int) $pdo->lastInsertId();
    }

    foreach ($inclusions as $i => $text) {
        $pdo->prepare('INSERT INTO camera_inclusions (camera_id, inclusion_text, sort_order) VALUES (?,?,?)')
            ->execute([$cameraId, $text, $i + 1]);
    }

    if ($pricingType === 'per_day') {
        foreach ($tiers as $t) {
            $minD = (int) ($t['min_days'] ?? 0);
            $maxD = isset($t['max_days']) && $t['max_days'] !== '' && $t['max_days'] !== null ? (int) $t['max_days'] : null;
            $rate = (float) ($t['rate_per_day'] ?? 0);
            if ($minD < 1 || $rate <= 0) continue;
            $pdo->prepare('INSERT INTO camera_rate_tiers (camera_id, min_days, max_days, rate_per_day) VALUES (?,?,?,?)')
                ->execute([$cameraId, $minD, $maxD, $rate]);
        }
    } elseif ($pricingType === 'per_day_and_hour') {
        $pdo->prepare('INSERT INTO camera_flat_hourly_rates (camera_id, flat_days, flat_price, extra_hour_rate) VALUES (?,?,?,?)')
            ->execute([$cameraId, (int) $tiers['flat_days'], (float) $tiers['flat_price'], (float) $tiers['extra_hour_rate']]);
    } elseif ($pricingType === 'per_photo') {
        foreach ($tiers as $t) {
            $minQ = (int) ($t['min_qty'] ?? 0);
            $maxQ = isset($t['max_qty']) && $t['max_qty'] !== '' && $t['max_qty'] !== null ? (int) $t['max_qty'] : null;
            $price = (float) ($t['price_per_photo'] ?? 0);
            if ($minQ < 1 || $price <= 0) continue;
            $pdo->prepare('INSERT INTO camera_per_photo_rates (camera_id, min_qty, max_qty, price_per_photo) VALUES (?,?,?,?)')
                ->execute([$cameraId, $minQ, $maxQ, $price]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'camera_id' => $cameraId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($newImagePath) {
        $f = __DIR__ . '/../../' . $newImagePath;
        if (is_file($f)) @unlink($f);
    }
    error_log('SnapVenture admin-camera-upsert error: ' . $e->getMessage());
    fail(500, 'Could not save the camera package. Please try again.');
}