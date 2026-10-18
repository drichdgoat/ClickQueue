<?php
/**
 * SnapVenture - POST /api/feedback-submit.php  (public, from inquiry.php)
 * Fields: name, email, phone (optional), service, rating (1-5), message
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/feedback-data.php';

function fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$rating = (int) ($_POST['rating'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($name === '') fail(422, 'Please enter your full name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Please enter a valid email address.');
if ($service === '') fail(422, 'Please select a service.');
if ($rating < 1 || $rating > 5) fail(422, 'Please rate your experience.');
if ($message === '') fail(422, 'Please write your feedback.');

try {
    $id = insertFeedback($pdo, [
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'service' => $service, 'rating' => $rating, 'message' => $message,
    ]);
    echo json_encode(['success' => true, 'feedback_id' => $id]);
} catch (Throwable $e) {
    error_log('SnapVenture feedback-submit error: ' . $e->getMessage());
    fail(500, 'We could not save your feedback. Please try again.');
}