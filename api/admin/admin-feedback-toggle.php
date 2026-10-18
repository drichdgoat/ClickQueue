<?php
/**
 * SnapVenture Admin - POST /api/admin-feedback-toggle.php
 * Body: { id: 5, is_featured: 0|1 }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (!isset($_SESSION['admin_id'])) fail(401, 'Not authenticated.');

require_once __DIR__ . '/../../includes/feedback-data.php';

$id = (int) ($_POST['id'] ?? 0);
$featured = ($_POST['is_featured'] ?? '0') === '1';

if (!$id) fail(422, 'Invalid feedback id.');

try {
    setFeedbackFeatured($pdo, $id, $featured);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('SnapVenture admin-feedback-toggle error: ' . $e->getMessage());
    fail(500, 'Could not update feedback status.');
}