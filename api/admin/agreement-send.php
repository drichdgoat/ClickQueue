<?php
/**
 * SnapVenture Admin - POST api/admin/agreement-send.php
 * Body: agreement_id, resend = 0|1, csrf_token
 * Emails the stored PDF to the booking's customer email. "Sent" is recorded only after the SMTP server accepted it.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
$adminId = api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$id = (int) ($_POST['agreement_id'] ?? 0);
if ($id <= 0) api_fail(422, 'Invalid agreement id.');
$resend = ($_POST['resend'] ?? '0') === '1';

try {
    $agr = agr_send($pdo, $id, $adminId, $resend);
    api_json(['success' => true, 'agreement' => agr_summary($agr),
              'message' => 'The email was accepted by the mail server. (This does not confirm the customer received or opened it.)']);
} catch (AgreementException $e) {
    $code = ['not_found' => 404, 'already_sent' => 409, 'in_progress' => 409, 'superseded' => 409][$e->errorCode] ?? 422;
    $agr = agr_get($pdo, $id);
    api_fail($code, $e->getMessage(), ['code' => $e->errorCode, 'agreement' => $agr ? agr_summary($agr) : null]);
} catch (Throwable $e) {
    error_log('SnapVenture agreement-send error: ' . $e->getMessage());
    api_fail(500, 'Something went wrong while sending. See the server error log.');
}
