<?php
/**
 * SnapVenture Admin - POST api/admin/agreement-template-save.php  (multipart/form-data)
 * action = save_editable   (title, content_html, [family_id], [activate=1])  -> creates a NEW version row
 *        | upload_pdf      (title, pdf file, [activate=1])                    -> static, NOT personalized
 *        | activate | deactivate | archive   (template_id)
 * Templates are never deleted or edited in place, so existing agreements stay reproducible.
 */
require_once __DIR__ . '/../../includes/admin-api.php';
$adminId = api_require_admin();
api_require_method('POST');
api_require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/agreements.php';

$action = $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'save_editable':
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 200) api_fail(422, 'Enter an agreement title (up to 200 characters).');
            $html = agr_sanitize_html((string) ($_POST['content_html'] ?? ''));
            if (trim(strip_tags($html)) === '') api_fail(422, 'The agreement text is empty.');
            $unknown = agr_unknown_placeholders($html);
            if ($unknown) api_fail(422, 'Unknown placeholder(s): {{' . implode('}}, {{', $unknown) . '}}. Use only the placeholders listed in the editor.');
            $family = (int) ($_POST['family_id'] ?? 0) ?: null;
            if ($family) {
                $chk = $pdo->prepare("SELECT template_type FROM agreement_templates WHERE family_id = ? LIMIT 1");
                $chk->execute([$family]);
                if ($chk->fetchColumn() !== 'editable') api_fail(422, 'Only editable templates can be edited.');
            }
            $id = agr_template_create_editable($pdo, $adminId, $title, $html, $family, ($_POST['activate'] ?? '') === '1');
            api_json(['success' => true, 'template_id' => $id]);

        case 'upload_pdf':
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 200) api_fail(422, 'Enter a title for the uploaded PDF (up to 200 characters).');
            if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) api_fail(422, 'Please choose a PDF file to upload.');
            $f = $_FILES['pdf'];
            if ($f['size'] > 5 * 1024 * 1024) api_fail(422, 'The PDF must be 5MB or smaller.');
            if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf') api_fail(422, 'Only .pdf files are allowed.');
            if (br_detect_mime($f['tmp_name']) !== 'application/pdf' || file_get_contents($f['tmp_name'], false, null, 0, 4) !== '%PDF') {
                api_fail(422, 'That file is not a valid PDF.');
            }
            $dir = agr_storage_abs('templates');
            $stored = 'tpl_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) api_fail(500, 'Could not save the uploaded PDF.');
            @chmod($dir . '/' . $stored, 0644);
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO agreement_templates (family_id, version, title, template_type, file_path, original_filename, file_sha256, created_by)
                           VALUES (0, 1, ?, 'static_pdf', ?, ?, ?, ?)")
                ->execute([$title, AGR_STORAGE_REL . '/templates/' . $stored, mb_substr(basename($f['name']), 0, 250),
                           hash_file('sha256', $dir . '/' . $stored), $adminId]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE agreement_templates SET family_id = ? WHERE template_id = ?')->execute([$id, $id]);
            $pdo->commit();
            if (($_POST['activate'] ?? '') === '1') agr_template_activate($pdo, $id);
            api_json(['success' => true, 'template_id' => $id]);

        case 'activate':
            agr_template_activate($pdo, (int) ($_POST['template_id'] ?? 0));
            api_json(['success' => true]);

        case 'deactivate':
            $pdo->prepare('UPDATE agreement_templates SET is_active = 0 WHERE template_id = ?')->execute([(int) ($_POST['template_id'] ?? 0)]);
            api_json(['success' => true]);

        case 'archive':   // soft delete: kept in the database and on disk because agreements may reference it
            $pdo->prepare('UPDATE agreement_templates SET is_archived = 1, is_active = 0 WHERE template_id = ?')
                ->execute([(int) ($_POST['template_id'] ?? 0)]);
            api_json(['success' => true]);

        default:
            api_fail(422, 'Unknown action.');
    }
} catch (AgreementException $e) {
    api_fail(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SnapVenture agreement-template-save error: ' . $e->getMessage());
    api_fail(500, 'Could not save the template. If this is a new install, apply database/migrations/001_booking_review_and_agreements.sql.');
}