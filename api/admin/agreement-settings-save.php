<?php
/**
 * SnapVenture Admin - POST api/admin/agreement-settings-save.php (multipart)
 * Body: rep_name, rep_position, [signature image PNG/JPG], [remove_signature=1], csrf_token
 * The signature is only used if an admin uploads one. Nothing is ever invented or pre-filled.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
$adminId = api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$name = trim((string) ($_POST['rep_name'] ?? ''));
$pos = trim((string) ($_POST['rep_position'] ?? ''));
if (mb_strlen($name) > 150 || mb_strlen($pos) > 150) api_fail(422, 'Name and position must be 150 characters or fewer.');

try {
    $settings = agr_settings($pdo);
    $sigPath = $settings['signature_path'];

    if (($_POST['remove_signature'] ?? '') === '1') $sigPath = null;

    if (isset($_FILES['signature']) && $_FILES['signature']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['signature'];
        if ($f['error'] !== UPLOAD_ERR_OK) api_fail(422, 'There was a problem uploading the signature image.');
        if ($f['size'] > 1024 * 1024) api_fail(422, 'The signature image must be 1MB or smaller.');
        $mime = br_detect_mime($f['tmp_name']);
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg'][$mime] ?? null;
        if ($ext === null || @getimagesize($f['tmp_name']) === false) api_fail(422, 'The signature must be a PNG or JPG image.');
        $dir = agr_storage_abs('signatures');
        $stored = 'sig_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) api_fail(500, 'Could not save the signature image.');
        @chmod($dir . '/' . $stored, 0644);
        $sigPath = $stored;
    }

    $pdo->prepare('UPDATE agreement_settings SET rep_name = ?, rep_position = ?, signature_path = ?, updated_by = ?, updated_at = NOW() WHERE id = 1')
        ->execute([$name !== '' ? $name : null, $pos !== '' ? $pos : null, $sigPath, $adminId]);
    api_json(['success' => true]);
} catch (AgreementException $e) {
    api_fail(422, $e->getMessage());
} catch (Throwable $e) {
    error_log('SnapVenture agreement-settings-save error: ' . $e->getMessage());
    api_fail(500, 'Could not save the settings. If this is a new install, apply database/migrations/001_booking_review_and_agreements.sql.');
}