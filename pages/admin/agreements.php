<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../../includes/admin-api.php';
require_once __DIR__ . '/../../includes/agreements.php';

$tab = $_GET['tab'] ?? 'sent';
if (!in_array($tab, ['sent', 'templates', 'representative'], true)) $tab = 'sent';

$setupError = null;
$rows = [];
$templates = [];
$settings = ['rep_name' => null, 'rep_position' => null, 'signature_path' => null];
$activeTpl = null;
$total = 0;
$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, ['generated', 'sent', 'failed'], true)) $status = '';

try {
    $settings = agr_settings($pdo);
    $templates = agr_templates($pdo);
    $activeTpl = agr_active_template($pdo);

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(a.agreement_ref LIKE ? OR b.reference_no LIKE ? OR COALESCE(b.contact_name, c.full_name) LIKE ? OR c.email LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($status !== '') {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    $from = 'FROM agreements a
             JOIN bookings b ON b.booking_id = a.booking_id
             JOIN customers c ON c.customer_id = b.customer_id
             JOIN agreement_templates t ON t.template_id = a.template_id
             LEFT JOIN booking_camera_details bcd ON bcd.booking_id = b.booking_id
             LEFT JOIN camera_packages cam ON cam.camera_id = bcd.camera_id
             LEFT JOIN booking_photobooth_details bpd ON bpd.booking_id = b.booking_id
             LEFT JOIN photobooth_packages pb ON pb.photobooth_id = bpd.photobooth_id'
          . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $cnt = $pdo->prepare('SELECT COUNT(*) ' . $from);
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $list = $pdo->prepare('SELECT a.agreement_id, a.agreement_ref, a.booking_id, a.revision, a.is_current, a.status, a.template_version,
                                  a.template_type, a.generated_at, a.sent_at, a.send_count,
                                  b.reference_no, b.event_date, b.service_type, COALESCE(b.contact_name, c.full_name) AS full_name, c.email,
                                  cam.name AS camera_name, pb.name AS photobooth_name, t.title AS template_title '
                         . $from . ' ORDER BY a.generated_at DESC, a.agreement_id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
    $list->execute($params);
    $rows = $list->fetchAll();
} catch (Throwable $e) {
    error_log('SnapVenture agreements page error: ' . $e->getMessage());
    $setupError = 'The agreement tables were not found. Apply database/migrations/001_booking_review_and_agreements.sql in phpMyAdmin first.';
    $pages = 1;
}

$repReady = agr_rep_ready($settings);
$tplJson = [];
foreach ($templates as $t) {
    $tplJson[$t['template_id']] = ['id' => (int) $t['template_id'], 'family_id' => (int) $t['family_id'], 'version' => (int) $t['version'],
        'title' => $t['title'], 'type' => $t['template_type'], 'content_html' => $t['content_html']];
}
$sigUrl = $settings['signature_path'] ? '../../api/admin/agreement-file.php?type=signature&v=' . urlencode($settings['signature_path']) : null;
$qs = fn(array $o) => http_build_query(array_merge(['tab' => 'sent', 'q' => $q, 'status' => $status], $o));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Agreement - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/bookings.css" />
    <link rel="stylesheet" href="../../css/admin-sidebar.css" />
    <link rel="stylesheet" href="../../css/booking-review.css" />
    <link rel="stylesheet" href="../../css/admin-responsive.css?v=<?= filemtime(__DIR__ . '/../../css/admin-responsive.css') ?>" />
  </head>
  <body data-api="../../api/admin/" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b
        ><button class="btn btn-outline" data-admin-menu>☰</button>
      </div>
      <?php $activePage = 'agreements'; include __DIR__ . '/partials/sidebar.php'; ?>
      <main class="admin-content">
        <div class="admin-heading">
          <h1>Agreement Management</h1>
          <p>Manage agreement templates and track agreements sent to customers.</p>
        </div>

        <?php if ($setupError): ?>
          <div class="notice bad"><?= htmlspecialchars($setupError) ?></div>
        <?php else: ?>
          <?php if (!$repReady || !$activeTpl): ?>
            <div class="notice warn">
              <b>Setup needed before agreements can be generated:</b>
              <?php if (!$repReady): ?><br>• Set the authorized representative name and position (<a href="?tab=representative">Representative</a>).<?php endif; ?>
              <?php if (!$activeTpl): ?><br>• Create or upload an agreement template and make it active (<a href="?tab=templates">Templates</a>).<?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="admin-card" style="margin-top: 20px">
          <div class="tabs" id="pageTabs">
            <button type="button" data-tab="sent" class="<?= $tab === 'sent' ? 'active' : '' ?>">Sent Agreements</button>
            <button type="button" data-tab="templates" class="<?= $tab === 'templates' ? 'active' : '' ?>">Templates</button>
            <button type="button" data-tab="representative" class="<?= $tab === 'representative' ? 'active' : '' ?>">Representative</button>
          </div>

          <!-- ============ Sent agreements ============ -->
          <div class="tab-panel <?= $tab === 'sent' ? 'active' : '' ?>" id="tab-sent">
            <form class="filters" method="get">
              <input type="hidden" name="tab" value="sent">
              <input class="sv-input" type="search" name="q" placeholder="Search agreement / booking ref, name, or email..." value="<?= htmlspecialchars($q) ?>">
              <select class="sv-select" name="status">
                <option value="">All statuses</option>
                <?php foreach (['generated' => 'Generated', 'sent' => 'Sent', 'failed' => 'Failed'] as $k => $v): ?>
                  <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary" type="submit">Filter</button>
              <?php if ($q !== '' || $status !== ''): ?><a class="btn btn-outline" href="?tab=sent">Clear</a><?php endif; ?>
            </form>
            <p class="small muted" style="margin:10px 0 0">“Sent” means the mail server accepted the email. It does not prove the customer received or opened it, or signed anything.</p>
            <div class="admin-table-wrap" style="margin-top: 12px">
              <table>
                <tr>
                  <th>Agreement</th><th>Booking</th><th>Customer</th><th>Service / Package</th><th>Event date</th>
                  <th>Template</th><th>Generated</th><th>Sent</th><th>Status</th><th></th>
                </tr>
                <?php if (!$rows): ?>
                  <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:24px">
                    <?= ($q !== '' || $status !== '') ? 'No agreements match your filters.' : 'No agreements yet. One is created when you confirm a booking.' ?>
                  </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['agreement_ref']) ?><?= $r['is_current'] ? '' : '<br><small style="color:var(--muted)">superseded</small>' ?></td>
                    <td><?= htmlspecialchars($r['reference_no']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($r['email']) ?></small></td>
                    <td><?= br_service_label($r['service_type']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($r['camera_name'] ?? $r['photobooth_name'] ?? '—') ?></small></td>
                    <td><?= htmlspecialchars($r['event_date']) ?></td>
                    <td><?= htmlspecialchars($r['template_title']) ?><br><small style="color:var(--muted)">v<?= (int) $r['template_version'] ?><?= $r['template_type'] === 'static_pdf' ? ' · static' : '' ?></small></td>
                    <td><?= htmlspecialchars($r['generated_at']) ?></td>
                    <td><?= $r['sent_at'] ? htmlspecialchars($r['sent_at']) . '<br><small style="color:var(--muted)">' . (int) $r['send_count'] . '×</small>' : '—' ?></td>
                    <td><span class="pill <?= $r['status'] === 'sent' ? 'ok' : ($r['status'] === 'failed' ? 'bad' : 'info') ?>"><?= htmlspecialchars(agr_status_label($r['status'])) ?></span></td>
                    <td style="white-space:nowrap">
                      <button type="button" class="btn btn-outline btn-sm" data-agr-open data-booking="<?= (int) $r['booking_id'] ?>" data-agreement="<?= (int) $r['agreement_id'] ?>">Preview / Send</button>
                      <a class="btn btn-outline btn-sm" href="../../api/admin/agreement-file.php?agreement_id=<?= (int) $r['agreement_id'] ?>&download=1">Download</a>
                      <a class="btn btn-outline btn-sm" href="bookings.php?open=<?= (int) $r['booking_id'] ?>">Booking</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
            <?php if (!$setupError): ?>
              <div class="pager">
                <span><?= $total ?> agreement<?= $total === 1 ? '' : 's' ?> · page <?= $page ?> of <?= $pages ?></span>
                <span>
                  <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="?<?= $qs(['page' => $page - 1]) ?>">Previous</a><?php endif; ?>
                  <?php if ($page < $pages): ?><a class="btn btn-outline btn-sm" href="?<?= $qs(['page' => $page + 1]) ?>">Next</a><?php endif; ?>
                </span>
              </div>
            <?php endif; ?>
          </div>

          <!-- ============ Templates ============ -->
          <div class="tab-panel <?= $tab === 'templates' ? 'active' : '' ?>" id="tab-templates">
            <div class="action-row" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px">
              <button type="button" class="btn btn-primary" id="newTplBtn">New editable template</button>
              <button type="button" class="btn btn-outline" id="uploadTplBtn">Upload PDF template</button>
            </div>
            <div class="notice">
              <b>Editable template</b>: dynamic placeholders are filled from each booking, producing a personalized PDF.<br>
              <b>Uploaded PDF</b>: static — it is attached exactly as uploaded and is <b>not</b> personalized (the system cannot write booking details into an arbitrary PDF).<br>
              Editing creates a <b>new version</b>; agreements already generated keep the exact text they were created with.
            </div>
            <div class="admin-table-wrap" style="margin-top: 12px">
              <table>
                <tr><th>Title</th><th>Version</th><th>Type</th><th>Status</th><th>Created</th><th></th></tr>
                <?php if (!$templates): ?>
                  <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">No templates yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($templates as $t): ?>
                  <tr>
                    <td><?= htmlspecialchars($t['title']) ?></td>
                    <td>v<?= (int) $t['version'] ?></td>
                    <td><?= $t['template_type'] === 'editable' ? 'Editable (personalized)' : 'Static PDF (not personalized)' ?></td>
                    <td><span class="pill <?= $t['is_active'] ? 'ok' : '' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= htmlspecialchars($t['created_at']) ?><?= $t['created_by_name'] ? '<br><small style="color:var(--muted)">' . htmlspecialchars($t['created_by_name']) . '</small>' : '' ?></td>
                    <td style="white-space:nowrap">
                      <?php if ($t['template_type'] === 'editable'): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-tpl="edit" data-id="<?= (int) $t['template_id'] ?>">Edit</button>
                        <button type="button" class="btn btn-outline btn-sm" data-tpl="preview" data-id="<?= (int) $t['template_id'] ?>">Preview</button>
                      <?php else: ?>
                        <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="../../api/admin/agreement-file.php?type=template&template_id=<?= (int) $t['template_id'] ?>">View</a>
                        <a class="btn btn-outline btn-sm" href="../../api/admin/agreement-file.php?type=template&template_id=<?= (int) $t['template_id'] ?>&download=1">Download</a>
                      <?php endif; ?>
                      <?php if ($t['is_active']): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-tpl="deactivate" data-id="<?= (int) $t['template_id'] ?>">Deactivate</button>
                      <?php else: ?>
                        <button type="button" class="btn btn-primary btn-sm" data-tpl="activate" data-id="<?= (int) $t['template_id'] ?>">Set active</button>
                        <button type="button" class="btn btn-outline btn-sm" data-tpl="archive" data-id="<?= (int) $t['template_id'] ?>">Archive</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
          </div>

          <!-- ============ Representative ============ -->
          <div class="tab-panel <?= $tab === 'representative' ? 'active' : '' ?>" id="tab-representative">
            <div class="notice">The authorized representative appears in the signature block of every generated agreement. Nothing is pre-filled or invented: until a name and position are saved, agreements cannot be generated. A signature image is optional (without one the PDF leaves a blank line to sign by hand).</div>
            <form id="repForm" style="max-width:520px;margin-top:14px" enctype="multipart/form-data">
              <div class="sv-field"><label for="repName">Representative name</label>
                <input class="sv-input" id="repName" name="rep_name" maxlength="150" value="<?= htmlspecialchars((string) $settings['rep_name']) ?>"></div>
              <div class="sv-field"><label for="repPos">Position</label>
                <input class="sv-input" id="repPos" name="rep_position" maxlength="150" value="<?= htmlspecialchars((string) $settings['rep_position']) ?>"></div>
              <div class="sv-field"><label for="repSig">Signature image (PNG or JPG, max 1MB, optional)</label>
                <?php if ($sigUrl): ?><img class="sig-preview" src="<?= htmlspecialchars($sigUrl) ?>" alt="Saved signature">
                  <label class="check"><input type="checkbox" name="remove_signature" value="1"> Remove saved signature</label><?php endif; ?>
                <input class="sv-input" id="repSig" name="signature" type="file" accept="image/png,image/jpeg"></div>
              <button type="submit" class="btn btn-primary" id="repSave">Save representative</button>
            </form>
          </div>
        </div>
      </main>
    </div>

    <!-- template editor -->
    <div class="modal-overlay" id="tplModal">
      <div class="modal modal-xl" role="dialog" aria-modal="true" aria-labelledby="tplTitle">
        <div class="modal-head"><h2 id="tplTitle">Template</h2><button type="button" class="icon-btn" data-close aria-label="Close">✕</button></div>
        <div class="agr-grid" style="grid-template-columns: 1fr 1fr">
          <div>
            <div class="sv-field"><label for="tplName">Agreement title</label><input class="sv-input" id="tplName" maxlength="200" placeholder="e.g. Terms of Agreement"></div>
            <label style="color:var(--brown);font-weight:500;font-size:14px">Agreement text (terms, policies, sections)</label>
            <div class="editor-toolbar">
              <button type="button" data-cmd="bold"><b>B</b></button>
              <button type="button" data-cmd="italic"><i>I</i></button>
              <button type="button" data-cmd="underline"><u>U</u></button>
              <button type="button" data-cmd="formatBlock" data-val="h3">Heading</button>
              <button type="button" data-cmd="formatBlock" data-val="p">Paragraph</button>
              <button type="button" data-cmd="insertUnorderedList">• List</button>
              <button type="button" data-cmd="insertOrderedList">1. List</button>
            </div>
            <div class="editor" id="tplEditor" contenteditable="true" spellcheck="true"></div>
            <div class="small muted" style="margin-top:8px">Click a placeholder to insert it where your cursor is. Empty values print as “N/A”.</div>
            <div class="ph-list" id="phList">
              <?php foreach (agr_known_placeholders() as $ph): ?><button type="button" data-ph="<?= $ph ?>">{{<?= $ph ?>}}</button><?php endforeach; ?>
            </div>
          </div>
          <div>
            <div class="agr-frame" id="tplPreviewWrap"><div class="viewer-empty">Click “Preview” to render the PDF with sample data.</div></div>
          </div>
        </div>
        <div class="notice" id="tplErr" hidden></div>
        <div class="modal-actions">
          <button type="button" class="btn btn-outline" data-close>Cancel</button>
          <button type="button" class="btn btn-outline" id="tplPreviewBtn">Preview</button>
          <button type="button" class="btn btn-outline" id="tplSaveBtn">Save as new version</button>
          <button type="button" class="btn btn-primary" id="tplSaveActiveBtn">Save &amp; set active</button>
        </div>
      </div>
    </div>

    <!-- PDF upload -->
    <div class="modal-overlay" id="pdfModal">
      <div class="modal modal-sm" role="dialog" aria-modal="true" aria-labelledby="pdfTitle">
        <div class="modal-head"><h2 id="pdfTitle">Upload PDF template</h2><button type="button" class="icon-btn" data-close aria-label="Close">✕</button></div>
        <div class="notice warn">An uploaded PDF is sent exactly as it is. It will <b>not</b> contain the customer’s name, dates or amounts.</div>
        <div class="sv-field"><label for="pdfName">Title</label><input class="sv-input" id="pdfName" maxlength="200"></div>
        <div class="sv-field"><label for="pdfFile">PDF file (max 5MB)</label><input class="sv-input" id="pdfFile" type="file" accept="application/pdf,.pdf"></div>
        <label class="check"><input type="checkbox" id="pdfActive"> Set as the active template</label>
        <div class="notice bad" id="pdfErr" hidden></div>
        <div class="modal-actions">
          <button type="button" class="btn btn-outline" data-close>Cancel</button>
          <button type="button" class="btn btn-primary" id="pdfSaveBtn">Upload</button>
        </div>
      </div>
    </div>

    <!-- small confirm -->
    <div class="modal-overlay" id="cfmModal">
      <div class="modal modal-sm" role="dialog" aria-modal="true" aria-labelledby="cfmTitle">
        <div class="modal-head"><h2 id="cfmTitle">Please confirm</h2></div>
        <p class="modal-text" id="cfmText"></p>
        <div class="modal-actions">
          <button type="button" class="btn btn-outline" data-close>Cancel</button>
          <button type="button" class="btn btn-primary" id="cfmOk">Confirm</button>
        </div>
      </div>
    </div>

    <?php include __DIR__ . '/partials/agreement-modal.php'; ?>
    <script type="application/json" id="tplData"><?= json_encode($tplJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <script src="../../js/admin-common.js"></script>
    <script src="../../js/admin-menu.js"></script>
    <script src="../../js/agreement-modal.js"></script>
    <script src="../../js/agreements-admin.js"></script>
  </body>
</html>