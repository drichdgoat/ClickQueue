<?php
/**
 * SnapVenture Admin - GET api/admin/agreement-file.php
 *   ?agreement_id=ID[&download=1]            generated agreement PDF
 *   ?type=template&template_id=ID            an uploaded static template PDF
 *   ?type=signature                          the representative's signature image
 * Logged-in admins only. Paths come from the database and must resolve inside storage/agreements.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
api_require_admin();
api_require_method('GET');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$type = $_GET['type'] ?? 'agreement';
$download = ($_GET['download'] ?? '') === '1';

try {
    if ($type === 'template') {
        $t = agr_template($pdo, (int) ($_GET['template_id'] ?? 0));
        if (!$t || $t['template_type'] !== 'static_pdf') api_fail(404, 'Template not found.');
        $abs = agr_safe_abs($t['file_path']);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($t['original_filename'] ?: 'template.pdf'));
        $mime = 'application/pdf';
    } elseif ($type === 'signature') {
        $abs = agr_signature_abs(agr_settings($pdo));
        $mime = $abs ? br_detect_mime($abs) : '';
        $name = 'signature';
        if ($abs && !in_array($mime, ['image/png', 'image/jpeg'], true)) $abs = null;
    } else {
        $a = agr_get($pdo, (int) ($_GET['agreement_id'] ?? 0));
        if (!$a) api_fail(404, 'Agreement not found.');
        $abs = agr_safe_abs($a['pdf_path']);
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', $a['agreement_ref']) . '.pdf';
        $mime = 'application/pdf';
    }
    if (!$abs) api_fail(404, 'File not found.');
    if ($mime === 'application/pdf' && file_get_contents($abs, false, null, 0, 4) !== '%PDF') api_fail(415, 'Not a valid PDF file.');

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($abs));
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($abs);
    exit;
} catch (Throwable $e) {
    error_log('SnapVenture agreement-file error: ' . $e->getMessage());
    api_fail(500, 'Could not open the file.');
}
