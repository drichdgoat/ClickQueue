<?php
/**
 * SnapVenture Admin - POST api/admin/agreement-generate.php
 * Body: booking_id, regenerate = 0|1, csrf_token
 * Explicit generation (retry after a setup problem, or intentional regeneration with the CURRENT active template).
 * Without regenerate=1 an existing current agreement is simply returned - no duplicate is ever created.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
$adminId = api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$id = (int) ($_POST['booking_id'] ?? 0);
if ($id <= 0) api_fail(422, 'Invalid booking id.');
$regen = ($_POST['regenerate'] ?? '0') === '1';

try {
    $agr = agr_generate($pdo, $id, $adminId, $regen);
    api_json(['success' => true, 'agreement' => agr_summary($agr)]);
} catch (AgreementException $e) {
    api_fail($e->errorCode === 'not_found' ? 404 : 422, $e->getMessage(), ['code' => $e->errorCode]);
} catch (Throwable $e) {
    error_log('SnapVenture agreement-generate error: ' . $e->getMessage());
    api_fail(500, 'The agreement could not be generated. See the server error log.');
}
