<?php
/**
 * SnapVenture - GET /api/featured-feedback.php  (public, for index.php)
 * Returns only the feedback the admin selected to display on the homepage.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/feedback-data.php';

try {
    echo json_encode(['feedback' => getFeaturedFeedback($pdo)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load feedback.']);
}