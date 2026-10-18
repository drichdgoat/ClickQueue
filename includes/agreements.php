<?php
/**
 * SnapVenture - Agreement Management core (admin only).
 *
 * - Editable templates (placeholders -> personalized PDF via Dompdf)
 * - Static PDF templates (sent exactly as uploaded; NOT personalized)
 * - Persistent generated PDFs in storage/agreements/ (blocked from direct web access)
 * - Send / resend via the project's existing PHPMailer + config/mail.php constants
 */
require_once __DIR__ . '/booking-review.php';

const AGR_STORAGE_REL = 'storage/agreements';

class AgreementException extends RuntimeException {
    public string $errorCode;
    public function __construct(string $errorCode, string $message) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}

/* ===================== storage ===================== */

function agr_storage_abs(string $sub = ''): string {
    $base = dirname(__DIR__) . '/' . AGR_STORAGE_REL;
    $dir = $sub === '' ? $base : $base . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new AgreementException('storage', 'Could not create the folder "' . AGR_STORAGE_REL . '/' . $sub . '". Check folder permissions.');
    }
    // Defence in depth: deny direct web access even if the folder is inside the web root.
    $ht = dirname(__DIR__) . '/storage/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
    return $dir;
}

/** Resolve a DB-stored relative path ("storage/agreements/...") to an absolute file inside storage/agreements only. */
function agr_safe_abs(?string $relative): ?string {
    if (!$relative) return null;
    $base = realpath(dirname(__DIR__) . '/' . AGR_STORAGE_REL);
    if ($base === false) return null;
    $abs = realpath(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relative), '/'));
    if ($abs === false || !is_file($abs)) return null;
    if (strpos($abs, $base . DIRECTORY_SEPARATOR) !== 0) return null;
    return $abs;
}

function agr_status_label(string $s): string {
    $map = ['generated' => 'Generated', 'sent' => 'Sent', 'failed' => 'Failed'];
    return $map[$s] ?? ucfirst($s);
}

/* ===================== settings (authorized representative) ===================== */

function agr_settings(PDO $pdo): array {
    $row = $pdo->query('SELECT * FROM agreement_settings WHERE id = 1')->fetch();
    return $row ?: ['id' => 1, 'rep_name' => null, 'rep_position' => null, 'signature_path' => null];
}

/** An agreement may only be generated once a real representative name AND position are configured. */
function agr_rep_ready(array $s): bool {
    return trim((string) ($s['rep_name'] ?? '')) !== '' && trim((string) ($s['rep_position'] ?? '')) !== '';
}

/* ===================== placeholders ===================== */

function agr_known_placeholders(): array {
    return [
        'customer_name', 'customer_email', 'customer_phone', 'customer_facebook',
        'booking_reference', 'booking_date', 'service_name', 'package_name', 'equipment_list',
        'event_date', 'event_time', 'event_location', 'rental_duration',
        'total_amount', 'down_payment_amount', 'remaining_balance', 'payment_method',
    ];
}

/** Placeholders whose value is already-escaped HTML. */
function agr_raw_placeholders(): array {
    return ['equipment_list'];
}

function agr_h(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function agr_placeholder_values(array $b): array {
    $lines = br_equipment_lines($b);
    $list = '';
    if ($lines) {
        $list = '<ul>';
        foreach ($lines as $l) $list .= '<li>' . agr_h($l) . '</li>';
        $list .= '</ul>';
    }
    return [
        'customer_name'       => (string) $b['full_name'],
        'customer_email'      => (string) $b['email'],
        'customer_phone'      => (string) $b['phone'],
        'customer_facebook'   => (string) $b['facebook_name'],
        'booking_reference'   => (string) $b['reference_no'],
        'booking_date'        => br_fmt_date($b['created_at'] ?? null),
        'service_name'        => br_service_label((string) $b['service_type']),
        'package_name'        => br_package_name($b),
        'equipment_list'      => $list !== '' ? $list : 'N/A',
        'event_date'          => br_fmt_date($b['event_date'] ?? null),
        'event_time'          => br_event_time($b),
        'event_location'      => (string) ($b['event_location'] ?? ''),
        'rental_duration'     => br_rental_duration($b),
        'total_amount'        => br_peso($b['grand_total']),
        'down_payment_amount' => br_peso($b['amount_due_now']),
        'remaining_balance'   => br_peso($b['remaining_balance']),
        'payment_method'      => (string) ($b['payment_method'] ?? ''),
    ];
}

/** Missing / inapplicable values become "N/A" (never "undefined" or a raw {{placeholder}}). */
function agr_render_placeholders(string $html, array $vals): string {
    return preg_replace_callback('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', function ($m) use ($vals) {
        $k = strtolower($m[1]);
        if (!array_key_exists($k, $vals)) return '';
        $v = (string) $vals[$k];
        if (in_array($k, agr_raw_placeholders(), true)) return $v !== '' ? $v : 'N/A';
        return agr_h($v !== '' ? $v : 'N/A');
    }, $html);
}

/** Returns placeholder names used in $html that are not in the known list (typos). */
function agr_unknown_placeholders(string $html): array {
    $unknown = [];
    if (preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/', $html, $m)) {
        foreach ($m[1] as $name) {
            if (!in_array(strtolower($name), agr_known_placeholders(), true)) $unknown[$name] = true;
        }
    }
    return array_keys($unknown);
}

/* ===================== HTML sanitising for the editor ===================== */

function agr_sanitize_html(string $html): string {
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
                'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'div', 'span', 'blockquote'];
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input',
             'button', 'textarea', 'select', 'svg', 'img', 'video', 'audio', 'base'];
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return '';
    agr_clean_node($root, $allowed, $drop);
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function agr_clean_node(DOMNode $node, array $allowed, array $drop): void {
    foreach (iterator_to_array($node->childNodes) as $c) {
        if ($c->nodeType === XML_COMMENT_NODE || $c->nodeType === XML_PI_NODE) {
            $node->removeChild($c);
            continue;
        }
        if ($c->nodeType !== XML_ELEMENT_NODE) continue;
        $tag = strtolower($c->nodeName);
        if (in_array($tag, $drop, true)) {
            $node->removeChild($c);
            continue;
        }
        agr_clean_node($c, $allowed, $drop);
        if (!in_array($tag, $allowed, true)) {
            while ($c->firstChild) $node->insertBefore($c->firstChild, $c);
            $node->removeChild($c);
            continue;
        }
        for ($i = $c->attributes->length - 1; $i >= 0; $i--) {
            $c->removeAttributeNode($c->attributes->item($i));
        }
    }
}

/* ===================== document HTML + PDF ===================== */

function agr_data_uri(string $abs): ?string {
    if (!is_file($abs)) return null;
    $mime = br_detect_mime($abs);
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) return null;
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
}

function agr_signature_abs(array $settings): ?string {
    $p = $settings['signature_path'] ?? null;
    if (!$p) return null;
    $dir = realpath(dirname(__DIR__) . '/' . AGR_STORAGE_REL . '/signatures');
    if ($dir === false) return null;
    $abs = realpath($dir . DIRECTORY_SEPARATOR . basename($p));
    if ($abs === false || strpos($abs, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($abs)) return null;
    return $abs;
}

function agr_row(string $label, string $value): string {
    return '<tr><th>' . agr_h($label) . '</th><td>' . agr_h($value !== '' ? $value : 'N/A') . '</td></tr>';
}

function agr_build_document_html(array $b, array $tpl, array $settings, string $ref, bool $sample = false): string {
    $vals = agr_placeholder_values($b);
    $body = agr_render_placeholders((string) $tpl['content_html'], $vals);

    $logo = agr_data_uri(dirname(__DIR__) . '/images/snapventure_logo.jpg');
    $logoHtml = $logo ? '<img src="' . $logo . '" style="width:90px;height:auto">' : '<b style="font-size:18pt">SnapVenture</b>';

    $sigImg = '';
    $sigAbs = agr_signature_abs($settings);
    if ($sigAbs && ($uri = agr_data_uri($sigAbs))) {
        $sigImg = '<img src="' . $uri . '" style="max-height:60px;max-width:200px">';
    }

    $equip = $vals['equipment_list'];
    $pay = '';
    $pay .= agr_row('Package price', br_peso($b['package_price']));
    if ((float) $b['addons_total'] > 0) $pay .= agr_row('Add-ons total', br_peso($b['addons_total']));
    $pay .= agr_row('Total amount', br_peso($b['grand_total']));
    $pay .= agr_row('Down payment due at booking', br_peso($b['amount_due_now']));
    $pay .= agr_row('Remaining balance', br_peso($b['remaining_balance']));

    $css = 'html,body{background:#ffffff}body{font-family:"DejaVu Sans",sans-serif;font-size:9.5pt;color:#000000;line-height:1.45}'
         . 'h1{font-size:15pt;text-align:center;margin:10px 0 2px;color:#000000;letter-spacing:1px}'
         . 'h2{font-size:10.5pt;color:#000000;border-bottom:1px solid #999999;padding-bottom:3px;margin:16px 0 6px}'
         . '.sub{text-align:center;color:#000000;font-size:8.5pt;margin-bottom:6px}'
         . 'table{width:100%;border-collapse:collapse}'
         . '.kv th{width:32%;text-align:left;color:#000000;font-weight:normal;padding:3px 6px 3px 0;vertical-align:top}'
         . '.kv td{padding:3px 0;vertical-align:top}'
         . '.terms{font-size:9pt}.terms h2,.terms h3,.terms h4{color:#000000;margin:10px 0 4px}'
         . '.terms ul,.terms ol{margin:4px 0 4px 18px;padding:0}'
         . '.terms table td,.terms table th{border:1px solid #999999;padding:3px 6px}'
         . '.sig{margin-top:26px}.sigblock{width:55%;margin-bottom:24px;page-break-inside:avoid}'
         . '.line{border-bottom:1px solid #000000;height:44px}.muted{color:#000000;font-size:8.5pt}'
         . '.notice{border:1px dashed #b91c1c;color:#b91c1c;text-align:center;padding:4px;margin-bottom:8px;font-size:8.5pt}';

    $html = '<!doctype html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';
    if ($sample) $html .= '<div class="notice">PREVIEW - SAMPLE DATA ONLY. Not a real agreement.</div>';
    $html .= '<table><tr><td>' . $logoHtml . '</td><td style="text-align:right" class="muted">'
          . 'Agreement Ref: <b>' . agr_h($ref) . '</b><br>Generated: ' . agr_h(date('F j, Y')) . '</td></tr></table>';
    $html .= '<h1>' . agr_h(strtoupper((string) $tpl['title'])) . '</h1>';
    $html .= '<div class="sub">SnapVenture Photography Services</div>';

    $html .= '<h2>Customer Information</h2><table class="kv">'
          . agr_row('Full name', $vals['customer_name']) . agr_row('Email', $vals['customer_email'])
          . agr_row('Contact number', $vals['customer_phone']) . agr_row('Facebook / Messenger', $vals['customer_facebook'])
          . '</table>';
    $html .= '<h2>Booking Details</h2><table class="kv">'
          . agr_row('Booking reference', $vals['booking_reference']) . agr_row('Booking date', $vals['booking_date'])
          . agr_row('Service', $vals['service_name']) . agr_row('Package', $vals['package_name'])
          . agr_row('Event date', $vals['event_date']) . agr_row('Event time', $vals['event_time'])
          . agr_row('Event location', $vals['event_location']) . agr_row('Rental duration', $vals['rental_duration'])
          . '</table>';
    $html .= '<h2>Package, Equipment and Add-ons</h2>' . ($equip !== 'N/A' ? $equip : '<p>N/A</p>');
    $html .= '<h2>Payment Summary</h2><table class="kv">' . $pay . '</table>';
    $html .= '<h2>Terms and Conditions</h2><div class="terms">' . $body . '</div>';

    // Signature area: the customer signs first (blank line, name in CAPS), the representative signs below.
    // The representative's date is filled automatically (the day the agreement is generated); only the customer's date stays blank.
    $custName = function_exists('mb_strtoupper') ? mb_strtoupper($vals['customer_name'], 'UTF-8') : strtoupper($vals['customer_name']);
    $signedDate = date('F j, Y');
    $repLine = $sigImg !== ''
        ? $sigImg . '<div style="border-top:1px solid #000000;margin-top:2px"></div>'
        : '<div class="line"></div>';
    $html .= '<div class="sig">'
          . '<div class="sigblock"><b>Customer</b><div class="line"></div>' . agr_h($custName)
          . '<br><span class="muted">Customer signature</span><br><br>Date: ______________________</div>'
          . '<div class="sigblock"><b>SnapVenture - Authorized Representative</b><br>' . $repLine
          . agr_h((string) ($settings['rep_name'] ?? '')) . '<br><span class="muted">' . agr_h((string) ($settings['rep_position'] ?? ''))
          . '</span><br><br>Date: ' . agr_h($signedDate) . '</div></div>';
    $html .= '</body></html>';
    return $html;
}

function agr_html_to_pdf(string $html): string {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new AgreementException('pdf_library_missing',
            'The PDF library is not installed. Run "composer require dompdf/dompdf" in the project root (see docs/AGREEMENT_SETUP_AND_TESTING.md).');
    }
    require_once $autoload;
    if (!class_exists('\Dompdf\Dompdf')) {
        throw new AgreementException('pdf_library_missing', 'Dompdf could not be loaded from vendor/. Re-run "composer install".');
    }
    // Dompdf writes temporary files (e.g. for embedded images). The system temp folder is often NOT
    // writable by Apache (XAMPP on macOS), so use a folder inside storage/ that we know we can write to.
    $tmp = agr_storage_abs('tmp');
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('chroot', dirname(__DIR__));
    $options->set('tempDir', $tmp);
    try {
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
    } catch (Throwable $e) {
        error_log('SnapVenture Dompdf failure: ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        throw new AgreementException('pdf_failed', 'The PDF could not be generated: ' . $e->getMessage());
    }
    if (!is_string($pdf) || strncmp($pdf, '%PDF', 4) !== 0) {
        throw new AgreementException('pdf_failed', 'The PDF could not be generated.');
    }
    return $pdf;
}

/** Sample booking used ONLY for the template preview (clearly marked on the page). */
function agr_sample_booking(): array {
    return [
        'booking_id' => 0, 'reference_no' => 'SV-SAMPLE', 'full_name' => 'Sample Customer',
        'email' => 'sample@example.com', 'phone' => '0900 000 0000', 'facebook_name' => 'Sample Facebook Name',
        'service_type' => 'camera_rental', 'event_date' => date('Y-m-d', strtotime('+30 days')),
        'start_time' => '09:00:00', 'end_time' => '17:00:00', 'event_location' => 'Sample event location',
        'package_price' => 1000, 'addons_total' => 200, 'grand_total' => 1200, 'amount_due_now' => 600,
        'remaining_balance' => 600, 'payment_method' => 'GCash / PayMaya', 'created_at' => date('Y-m-d H:i:s'),
        'detail' => ['camera_name' => 'Sample Camera Package', 'rental_days' => 1],
        'addons' => [['name' => 'Sample Accessory', 'price_applied' => 200]],
    ];
}

/* ===================== templates ===================== */

function agr_templates(PDO $pdo, bool $withArchived = false): array {
    $sql = 'SELECT t.*, a.name AS created_by_name FROM agreement_templates t LEFT JOIN admins a ON a.id = t.created_by'
         . ($withArchived ? '' : ' WHERE t.is_archived = 0') . ' ORDER BY t.is_active DESC, t.family_id DESC, t.version DESC';
    return $pdo->query($sql)->fetchAll();
}

function agr_template(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare('SELECT * FROM agreement_templates WHERE template_id = ?');
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ?: null;
}

function agr_active_template(PDO $pdo): ?array {
    $r = $pdo->query('SELECT * FROM agreement_templates WHERE is_active = 1 AND is_archived = 0 ORDER BY template_id DESC LIMIT 1')->fetch();
    return $r ?: null;
}

function agr_template_activate(PDO $pdo, int $id): void {
    $pdo->beginTransaction();
    try {
        $t = $pdo->prepare('SELECT template_id FROM agreement_templates WHERE template_id = ? AND is_archived = 0 FOR UPDATE');
        $t->execute([$id]);
        if (!$t->fetchColumn()) throw new AgreementException('not_found', 'Template not found or archived.');
        $pdo->exec('UPDATE agreement_templates SET is_active = 0 WHERE is_active = 1');
        $u = $pdo->prepare('UPDATE agreement_templates SET is_active = 1 WHERE template_id = ?');
        $u->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Every edit is a NEW row (version+1) so agreements already generated keep pointing at the exact text used. */
function agr_template_create_editable(PDO $pdo, int $adminId, string $title, string $html, ?int $familyId, bool $activate): int {
    $pdo->beginTransaction();
    try {
        $version = 1;
        if ($familyId) {
            $v = $pdo->prepare('SELECT COALESCE(MAX(version),0) + 1 FROM agreement_templates WHERE family_id = ?');
            $v->execute([$familyId]);
            $version = (int) $v->fetchColumn();
        }
        $ins = $pdo->prepare("INSERT INTO agreement_templates (family_id, version, title, template_type, content_html, created_by)
                              VALUES (?, ?, ?, 'editable', ?, ?)");
        $ins->execute([$familyId ?: 0, $version, $title, $html, $adminId]);
        $id = (int) $pdo->lastInsertId();
        if (!$familyId) {
            $pdo->prepare('UPDATE agreement_templates SET family_id = ? WHERE template_id = ?')->execute([$id, $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    if ($activate) agr_template_activate($pdo, $id);
    return $id;
}

/* ===================== generated agreements ===================== */

function agr_get(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare('SELECT a.*, b.reference_no, b.status AS booking_status FROM agreements a
                        JOIN bookings b ON b.booking_id = a.booking_id WHERE a.agreement_id = ?');
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ?: null;
}

function agr_current_for_booking(PDO $pdo, int $bookingId): ?array {
    $s = $pdo->prepare('SELECT a.*, b.reference_no, b.status AS booking_status FROM agreements a
                        JOIN bookings b ON b.booking_id = a.booking_id
                        WHERE a.booking_id = ? AND a.is_current = 1 ORDER BY a.revision DESC LIMIT 1');
    $s->execute([$bookingId]);
    $r = $s->fetch();
    return $r ?: null;
}

function agr_summary(array $a): array {
    return [
        'agreement_id' => (int) $a['agreement_id'], 'agreement_ref' => $a['agreement_ref'],
        'booking_id' => (int) $a['booking_id'], 'revision' => (int) $a['revision'],
        'is_current' => (bool) $a['is_current'], 'status' => $a['status'], 'status_label' => agr_status_label($a['status']),
        'template_version' => (int) $a['template_version'], 'template_type' => $a['template_type'],
        'personalized' => $a['template_type'] === 'editable',
        'generated_at' => $a['generated_at'], 'sent_at' => $a['sent_at'],
        'send_count' => (int) $a['send_count'], 'last_error' => $a['last_error'],
    ];
}

/**
 * Creates (or, with $regenerate, intentionally re-creates) the agreement PDF for a CONFIRMED booking.
 * Idempotent: if a current agreement already exists it is returned unchanged - repeated confirms,
 * page reloads or double requests never create duplicates. The booking row is locked for the duration.
 */
function agr_generate(PDO $pdo, int $bookingId, int $adminId, bool $regenerate = false): array {
    $abs = null;
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT booking_id, status, reference_no FROM bookings WHERE booking_id = ? FOR UPDATE');
        $lock->execute([$bookingId]);
        $bk = $lock->fetch();
        if (!$bk) throw new AgreementException('not_found', 'Booking not found.');
        if ($bk['status'] !== 'confirmed') {
            throw new AgreementException('not_confirmed', 'An agreement can only be generated for a confirmed booking.');
        }

        $cur = agr_current_for_booking($pdo, $bookingId);
        if ($cur && !$regenerate) {
            $pdo->commit();
            return $cur;
        }

        $settings = agr_settings($pdo);
        if (!agr_rep_ready($settings)) {
            throw new AgreementException('rep_not_configured',
                'Set the authorized representative name and position first (Agreement > Representative).');
        }
        $tpl = agr_active_template($pdo);
        if (!$tpl) {
            throw new AgreementException('no_template', 'There is no active agreement template. Create or activate one in Agreement > Templates.');
        }

        $rs = $pdo->prepare('SELECT COALESCE(MAX(revision),0) + 1 FROM agreements WHERE booking_id = ?');
        $rs->execute([$bookingId]);
        $revision = (int) $rs->fetchColumn();
        $ref = 'AGR-' . $bk['reference_no'] . '-' . $revision;
        $fileName = preg_replace('/[^A-Za-z0-9_-]/', '', $ref) . '.pdf';
        $dir = agr_storage_abs('generated');
        $abs = $dir . '/' . $fileName;
        if (file_exists($abs)) throw new AgreementException('storage', 'A file for this agreement already exists; refusing to overwrite it.');

        if ($tpl['template_type'] === 'static_pdf') {
            $src = agr_safe_abs($tpl['file_path']);
            if (!$src) throw new AgreementException('template_file_missing', 'The uploaded template PDF file is missing from storage.');
            if (!copy($src, $abs)) throw new AgreementException('storage', 'Could not store the agreement PDF.');
        } else {
            $booking = br_load_booking($pdo, $bookingId);
            $pdf = agr_html_to_pdf(agr_build_document_html($booking, $tpl, $settings, $ref));
            if (file_put_contents($abs, $pdf) === false) throw new AgreementException('storage', 'Could not store the agreement PDF.');
        }
        @chmod($abs, 0644);
        $sha = hash_file('sha256', $abs);

        if ($regenerate) {
            $pdo->prepare('UPDATE agreements SET is_current = 0 WHERE booking_id = ?')->execute([$bookingId]);
        }
        $ins = $pdo->prepare('INSERT INTO agreements
            (agreement_ref, booking_id, revision, is_current, template_id, template_version, template_type, pdf_path, pdf_sha256, status, generated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$ref, $bookingId, $revision, 1, $tpl['template_id'], $tpl['version'], $tpl['template_type'],
                       AGR_STORAGE_REL . '/generated/' . $fileName, $sha, 'generated', $adminId]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->commit();
        return agr_get($pdo, $newId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($abs && is_file($abs) && !isset($newId)) @unlink($abs);   // never leave an orphan file without a DB row
        throw $e;
    }
}

function agr_history(PDO $pdo, int $agreementId): array {
    $s = $pdo->prepare('SELECT l.log_id, l.attempt_type, l.recipient, l.attempted_at, l.result, l.error_message, l.completed_at,
                               a.name AS admin_name
                        FROM agreement_email_log l LEFT JOIN admins a ON a.id = l.attempted_by
                        WHERE l.agreement_id = ? ORDER BY l.log_id DESC');
    $s->execute([$agreementId]);
    return $s->fetchAll();
}

/* ===================== sending ===================== */

function agr_send_mail(string $to, string $toName, string $subject, string $html, string $alt, string $attachAbs, string $attachName): void {
    $cfg = dirname(__DIR__) . '/config/mail.php';
    if (!is_file($cfg)) {
        throw new AgreementException('mail_not_configured', 'config/mail.php was not found, so email cannot be sent. Create it with the SMTP_* settings.');
    }
    require_once $cfg;
    foreach (['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_PORT', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME'] as $c) {
        if (!defined($c)) {
            throw new AgreementException('mail_not_configured', 'config/mail.php is missing the setting ' . $c . '.');
        }
    }
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;   // same as the existing reset-password mailer
    $mail->Port = SMTP_PORT;
    $mail->Timeout = 25;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($to, $toName);
    $mail->addAttachment($attachAbs, $attachName, 'base64', 'application/pdf');
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $alt;
    $mail->send();   // throws on failure
}

/**
 * Sends the agreement PDF. A DB "sent" status is written ONLY after PHPMailer reports the SMTP server
 * accepted the message. Every attempt (success or failure) is logged. Concurrent / repeated clicks are
 * blocked by an atomic claim on agreements.send_started_at.
 */
function agr_send(PDO $pdo, int $agreementId, int $adminId, bool $resend): array {
    $agr = agr_get($pdo, $agreementId);
    if (!$agr) throw new AgreementException('not_found', 'Agreement not found.');
    if (!(int) $agr['is_current']) {
        throw new AgreementException('superseded', 'This agreement was replaced by a newer revision and cannot be sent.');
    }
    $pdf = agr_safe_abs($agr['pdf_path']);
    if (!$pdf) throw new AgreementException('file_missing', 'The agreement PDF file is missing from storage. Regenerate it first.');

    $claim = $pdo->prepare('UPDATE agreements SET send_started_at = NOW()
                            WHERE agreement_id = ? AND (send_started_at IS NULL OR send_started_at < (NOW() - INTERVAL 2 MINUTE))');
    $claim->execute([$agreementId]);
    if ($claim->rowCount() !== 1) {
        throw new AgreementException('in_progress', 'This agreement is already being sent. Please wait a moment.');
    }

    $logId = 0;
    try {
        $fresh = agr_get($pdo, $agreementId);    // re-check AFTER claiming (another request may just have finished)
        if ($fresh['status'] === 'sent' && !$resend) {
            throw new AgreementException('already_sent', 'This agreement was already sent. Use "Resend Agreement" to send it again.');
        }
        $b = br_load_booking($pdo, (int) $agr['booking_id']);
        if (!$b) throw new AgreementException('not_found', 'Booking not found.');
        $to = trim((string) $b['email']);

        $isResend = $fresh['status'] === 'sent' || $resend;
        $pdo->prepare('INSERT INTO agreement_email_log (agreement_id, attempt_type, recipient, attempted_by, result)
                       VALUES (?,?,?,?,?)')->execute([$agreementId, $isResend ? 'resend' : 'send', $to, $adminId, 'pending']);
        $logId = (int) $pdo->lastInsertId();

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new AgreementException('invalid_email', 'The customer\'s email address is missing or invalid ("' . $to . '"). Fix it before sending.');
        }

        $name = (string) $b['full_name'];
        $ref = $agr['agreement_ref'];
        $html = '<p>Hello ' . agr_h($name) . ',</p>'
              . '<p>Thank you for booking with SnapVenture. Your booking <b>' . agr_h($b['reference_no']) . '</b> has been confirmed.</p>'
              . '<p>Attached is your Terms of Agreement (<b>' . agr_h($ref) . '</b>). Please read it, print it, sign it, '
              . 'and send a scanned or clear photo of the signed copy back by replying to this email.</p>'
              . '<p><b>Event:</b> ' . agr_h(br_service_label($b['service_type'])) . ' - ' . agr_h(br_package_name($b)) . '<br>'
              . '<b>Date:</b> ' . agr_h(br_fmt_date($b['event_date'])) . '</p>'
              . '<p>- SnapVenture</p>';
        $alt = "Hello $name,\n\nYour SnapVenture booking {$b['reference_no']} is confirmed. Your Terms of Agreement ($ref) is attached. "
             . "Please print, sign, and reply with a scanned copy.\n\n- SnapVenture";

        agr_send_mail($to, $name, 'Your SnapVenture Terms of Agreement - ' . $b['reference_no'], $html, $alt, $pdf,
                      'SnapVenture-Agreement-' . $b['reference_no'] . '.pdf');

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE agreements SET status = 'sent', sent_at = NOW(), send_count = send_count + 1,
                       last_error = NULL, send_started_at = NULL WHERE agreement_id = ?")->execute([$agreementId]);
        $pdo->prepare("UPDATE agreement_email_log SET result = 'accepted', completed_at = NOW() WHERE log_id = ?")->execute([$logId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = mb_substr($e instanceof AgreementException ? $e->getMessage() : 'Email could not be sent: ' . $e->getMessage(), 0, 480);
        if ($logId) {
            $pdo->prepare("UPDATE agreement_email_log SET result = 'failed', error_message = ?, completed_at = NOW() WHERE log_id = ?")
                ->execute([$msg, $logId]);
        }
        // A failed attempt never turns an already-sent agreement into "failed".
        $isPreflight = $e instanceof AgreementException && in_array($e->errorCode, ['already_sent'], true);
        if (!$isPreflight) {
            $pdo->prepare("UPDATE agreements SET status = IF(sent_at IS NULL, 'failed', status), last_error = ?, send_started_at = NULL
                           WHERE agreement_id = ?")->execute([$msg, $agreementId]);
        } else {
            $pdo->prepare('UPDATE agreements SET send_started_at = NULL WHERE agreement_id = ?')->execute([$agreementId]);
        }
        if ($e instanceof AgreementException) throw $e;
        throw new AgreementException('send_failed', $msg);
    }
    return agr_get($pdo, $agreementId);
}