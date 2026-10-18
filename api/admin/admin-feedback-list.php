<?php
/**
 * SnapVenture Admin - GET /api/admin-feedback-list.php
 * Returns all feedback (any status) plus summary stats.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

require_once __DIR__ . '/../../includes/feedback-data.php';

try {
    echo json_encode([
        'feedback' => getAllFeedbackAdmin($pdo),
        'stats'    => getFeedbackStats($pdo),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load feedback.']);
}