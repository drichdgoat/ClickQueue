<?php
/**
 * SnapVenture - GET /api/packages.php
 * Returns the full active catalog (cameras, accessories, photobooth
 * packages, photobooth add-ons) as JSON, straight from the database.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/pricing.php'; // also pulls in database.php -> $pdo

try {
    $data = [
        'cameras'            => getCameraCatalog($pdo),
        'camera_accessories' => getCameraAccessories($pdo),
        'photobooth'         => getPhotoboothCatalog($pdo),
        'photobooth_addons'  => getPhotoboothAddons($pdo),
        'rules'              => [
            'camera_downpayment_rate'    => CAMERA_DOWNPAYMENT_RATE,
            'photobooth_reservation_fee' => PHOTOBOOTH_RESERVATION_FEE,
        ],
    ];
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load packages.']);
}