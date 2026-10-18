<?php
/**
 * SnapVenture Admin - POST /api/admin-equipment-toggle.php
 * Body (JSON or form): { type: "camera"|"accessory", id: 5, is_active: 0|1 }
 * Soft-delete only -- we never hard-DELETE a camera/accessory that might
 * already be referenced by past bookings (would break foreign keys and
 * destroy booking history). Deactivating just hides it from Services and
 * Booking (api/packages.php only selects is_active = 1).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (!isset($_SESSION['admin_id'])) fail(401, 'Not authenticated.');

require_once __DIR__ . '/../../includes/pricing.php';

$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

$type = $input['type'] ?? '';
$id = (int) ($input['id'] ?? 0);
$isActive = isset($input['is_active']) && (string) $input['is_active'] === '1' ? 1 : 0;

if (!in_array($type, ['camera', 'accessory'], true) || !$id) {
    fail(422, 'Invalid request.');
}

try {
    if ($type === 'camera') {
        $pdo->prepare('UPDATE camera_packages SET is_active = ? WHERE camera_id = ?')->execute([$isActive, $id]);
    } else {
        $pdo->prepare('UPDATE camera_accessories SET is_active = ? WHERE accessory_id = ?')->execute([$isActive, $id]);
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('SnapVenture admin-equipment-toggle error: ' . $e->getMessage());
    fail(500, 'Could not update status.');
}