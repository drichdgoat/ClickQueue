<?php
/**
 * SnapVenture Admin - POST /api/admin-accessory-upsert.php
 * Creates a new camera accessory, or updates an existing one when
 * accessory_id is provided.
 * Fields: accessory_id (optional), name, price, is_active ("1"/"0")
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

$accessoryId = isset($_POST['accessory_id']) && $_POST['accessory_id'] !== '' ? (int) $_POST['accessory_id'] : null;
$name = trim($_POST['name'] ?? '');
$price = isset($_POST['price']) ? (float) $_POST['price'] : null;
$isActive = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;

if ($name === '') fail(422, 'Please enter the accessory name.');
if ($price === null || $price < 0) fail(422, 'Please enter a valid price.');

try {
    if ($accessoryId) {
        $stmt = $pdo->prepare('UPDATE camera_accessories SET name=?, price=?, is_active=? WHERE accessory_id=?');
        $stmt->execute([$name, $price, $isActive, $accessoryId]);
    } else {
        $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM camera_accessories')->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO camera_accessories (name, price, is_active, sort_order) VALUES (?,?,?,?)');
        $stmt->execute([$name, $price, $isActive, $nextSort]);
        $accessoryId = (int) $pdo->lastInsertId();
    }
    echo json_encode(['success' => true, 'accessory_id' => $accessoryId]);
} catch (Throwable $e) {
    error_log('SnapVenture admin-accessory-upsert error: ' . $e->getMessage());
    fail(500, 'Could not save the accessory. Please try again.');
}