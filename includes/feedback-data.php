<?php
/**
 * SnapVenture - Feedback data access.
 * Place at project root, next to database.php and pricing.php.
 */

require_once __DIR__ . '/../config/database.php'; // provides $pdo

/** Insert a new customer-submitted feedback row. is_featured always starts at 0. */
function insertFeedback(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare(
        'INSERT INTO feedback (customer_name, customer_email, phone, service, rating, message, is_featured)
         VALUES (?,?,?,?,?,?,0)'
    );
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'] ?: null,
        $data['service'] ?: null,
        $data['rating'],
        $data['message'],
    ]);
    return (int) $pdo->lastInsertId();
}

/** All feedback, newest first -- for the admin management page. */
function getAllFeedbackAdmin(PDO $pdo): array {
    return $pdo->query(
        'SELECT feedback_id, customer_name, customer_email, phone, service, rating, message, is_featured, created_at
         FROM feedback ORDER BY created_at DESC'
    )->fetchAll();
}

/** Only the feedback the admin chose to feature -- for the homepage carousel. */
function getFeaturedFeedback(PDO $pdo): array {
    return $pdo->query(
        "SELECT feedback_id, customer_name, service, rating, message, created_at
         FROM feedback WHERE is_featured = 1 ORDER BY created_at DESC"
    )->fetchAll();
}

function setFeedbackFeatured(PDO $pdo, int $id, bool $featured): void {
    $stmt = $pdo->prepare('UPDATE feedback SET is_featured = ? WHERE feedback_id = ?');
    $stmt->execute([$featured ? 1 : 0, $id]);
}

function getFeedbackStats(PDO $pdo): array {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
    $avg = (float) $pdo->query('SELECT COALESCE(AVG(rating),0) FROM feedback')->fetchColumn();
    $featured = (int) $pdo->query('SELECT COUNT(*) FROM feedback WHERE is_featured = 1')->fetchColumn();
    return [
        'total' => $total,
        'average_rating' => round($avg, 1),
        'featured' => $featured,
        'not_featured' => $total - $featured,
    ];
}