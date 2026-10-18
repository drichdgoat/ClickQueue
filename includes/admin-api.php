<?php
/**
 * SnapVenture Admin - shared helpers for admin JSON / file endpoints.
 * Same auth rule as the existing admin APIs: a logged-in admin session ($_SESSION['admin_id']).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function api_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_fail(int $code, string $message, array $extra = []): void {
    api_json(array_merge(['success' => false, 'error' => $message], $extra), $code);
}

/** Returns the admin id or ends the request with 401. */
function api_require_admin(): int {
    if (empty($_SESSION['admin_id'])) {
        api_fail(401, 'Not authenticated. Please log in again.');
    }
    return (int) $_SESSION['admin_id'];
}

function api_require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        api_fail(405, 'Method not allowed.');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** State-changing requests must send the session token (POST field or X-CSRF-Token header). */
function api_require_csrf(): void {
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        api_fail(403, 'Your session security token is invalid or expired. Please refresh the page and try again.');
    }
}

function app_root(): string {
    return dirname(__DIR__);
}
