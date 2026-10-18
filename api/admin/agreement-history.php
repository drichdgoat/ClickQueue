<?php
/** SnapVenture Admin - GET api/admin/agreement-history.php?agreement_id=ID  (send/resend attempts) */
require_once __DIR__ . '/../../includes/admin-api.php';
api_require_admin();
api_require_method('GET');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$id = (int) ($_GET['agreement_id'] ?? 0);
if ($id <= 0) api_fail(422, 'Invalid agreement id.');
try {
    $a = agr_get($pdo, $id);
    if (!$a) api_fail(404, 'Agreement not found.');
    api_json(['success' => true, 'agreement' => agr_summary($a), 'history' => agr_history($pdo, $id)]);
} catch (Throwable $e) {
    error_log('SnapVenture agreement-history error: ' . $e->getMessage());
    api_fail(500, 'Could not load the send history.');
}
