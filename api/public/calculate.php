<?php
/**
 * SnapVenture - POST /api/calculate.php
 * Body (JSON): {
 *   service_type: "camera_rental" | "photobooth",
 *   camera_id, days, extra_hours, photo_qty, accessory_ids: [...]
 *   photobooth_id, addon_ids: [...]
 * }
 * Returns the authoritative price breakdown for live display on the
 * booking form. This is display-only -- api/book.php recalculates again
 * at submit time and is the real source of truth.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/pricing.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

try {
    $serviceType = $input['service_type'] ?? '';

    if ($serviceType === 'camera_rental') {
        $cameraId    = (int) ($input['camera_id'] ?? 0);
        $days        = isset($input['days']) ? (int) $input['days'] : null;
        $extraHours  = isset($input['extra_hours']) ? (int) $input['extra_hours'] : null;
        $photoQty    = isset($input['photo_qty']) ? (int) $input['photo_qty'] : null;
        $accessoryIds = array_map('intval', $input['accessory_ids'] ?? []);

        $summary = buildCameraBookingSummary($pdo, $cameraId, $days, $extraHours, $photoQty, $accessoryIds);
        echo json_encode(['success' => true, 'service_type' => 'camera_rental', 'summary' => $summary]);

    } elseif ($serviceType === 'photobooth') {
        $photoboothId = (int) ($input['photobooth_id'] ?? 0);
       $addonQtyMap = sanitizeAddonQty($input['addon_qty'] ?? []);

        $summary = buildPhotoboothBookingSummary($pdo, $photoboothId, $addonQtyMap);
        echo json_encode(['success' => true, 'service_type' => 'photobooth', 'summary' => $summary]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown service_type.']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not calculate price.']);
}