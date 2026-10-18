<?php
/**
 * SnapVenture Admin - GET /api/admin-equipment-list.php
 * Returns ALL camera packages and accessories (including inactive ones)
 * for the Equipment management page. Requires an active admin session.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

require_once __DIR__ . '/../../includes/pricing.php';

try {
    echo json_encode([
        'cameras'    => getCameraCatalogAdmin($pdo),
        'accessories' => getCameraAccessoriesAdmin($pdo),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load equipment.']);
}