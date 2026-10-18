<?php
/**
 * SnapVenture Admin - POST api/admin/agreement-template-preview.php
 * Body: title, content_html, csrf_token  ->  application/pdf rendered with clearly-marked SAMPLE data.
 * Nothing is saved. Uses the saved representative details (or shows them blank if not configured yet).
 */
require_once __DIR__ . '/../../includes/admin-api.php';
api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

try {
    $html = agr_sanitize_html((string) ($_POST['content_html'] ?? ''));
    $unknown = agr_unknown_placeholders($html);
    if ($unknown) api_fail(422, 'Unknown placeholder(s): {{' . implode('}}, {{', $unknown) . '}}.');
    $tpl = ['title' => trim((string) ($_POST['title'] ?? '')) ?: 'Terms of Agreement', 'content_html' => $html];
    $pdf = agr_html_to_pdf(agr_build_document_html(agr_sample_booking(), $tpl, agr_settings($pdo), 'AGR-SAMPLE-0', true));
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdf));
    header('Content-Disposition: inline; filename="agreement-preview.pdf"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo $pdf;
    exit;
} catch (AgreementException $e) {
    api_fail(422, $e->getMessage());
} catch (Throwable $e) {
    error_log('SnapVenture agreement-template-preview error: ' . $e->getMessage());
    api_fail(500, 'Could not render the preview.');
}
