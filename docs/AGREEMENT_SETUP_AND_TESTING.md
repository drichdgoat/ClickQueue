# SnapVenture – Booking Review, Payment Review and Agreement Management

## 1. One-time setup (in this order)

1. **Back up the database** (phpMyAdmin → `snapventure` → Export → Quick → Go).
2. **Apply the migration**: phpMyAdmin → `snapventure` → SQL tab → paste `database/migrations/001_booking_review_and_agreements.sql` → Go.
   It only adds things (3 nullable columns on `bookings`, 4 new tables) and is safe to run twice. Nothing is dropped, truncated or rewritten.
3. **Install the PDF library** (Dompdf) – from the project root:
   `composer install`  (or `composer require dompdf/dompdf`). Needs the PHP `gd` and `mbstring` extensions (both are on by default in XAMPP; check `php.ini` if images do not show).
   If you cannot run Composer, copy a ready `vendor/` folder (with `autoload.php`) into the project root.
4. **Make sure `config/mail.php` exists** with the same constants the password-reset page already uses:
   `SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD, SMTP_PORT, SMTP_FROM_EMAIL, SMTP_FROM_NAME` (STARTTLS, as in the existing mailer). It was not in the archive, so email cannot work until it exists.
5. **Folders**: `storage/agreements/` is created automatically (needs write permission). `storage/.htaccess` and `uploads/proofs/.htaccess` block direct web access (Apache). If you ever deploy on Nginx, add an equivalent `deny` rule or move `storage/` outside the web root.
6. Log in as admin → **Agreement → Representative**: enter the authorized representative's name and position (and optionally a signature image). → **Agreement → Templates**: create/upload a template and set it active. Agreements cannot be generated until both exist; nothing is pre-filled or invented.

## 2. Workflow

1. Customer books and uploads a receipt (unchanged). Booking = `pending`, payment = `submitted`.
2. Admin opens **Bookings → View Details** (or clicks the row). Tabs: Booking Information · Proof of Payment · Proof of Requirements · Agreement. Opening a booking never generates or sends anything.
3. **Proof of Payment** shows the receipt (image / PDF preview, open in new tab, download). The admin may press **Mark payment as verified (manual review)** – this records who and when. It is separate from booking status; the system does not check payments with any bank or e-wallet.
4. To confirm, choose **Confirmed** in the dropdown (or use the dialog). A review box appears: the admin must tick both acknowledgments; Confirm stays disabled if the receipt is missing (Bank Transfer / GCash) or the date conflicts with another active booking. Server-side checks repeat all of this. Confirming does **not** change payment status.
5. On success the booking is saved as confirmed (`confirmed_at`, `confirmed_by`) and the agreement PDF is generated and stored (idempotent: re-confirming or reloading reuses the existing one). The Agreement popup opens with the booking summary and PDF preview.
6. Admin clicks **Send Agreement**. Only after the SMTP server accepts the message is the agreement marked **Sent** (with timestamp). Failures are recorded and can be retried without regenerating. A sent agreement needs an explicit **Resend Agreement** (two clicks). Every attempt is in the send history.
7. **Reject** = the existing Cancelled status (button in the details view, with a confirm step).

## 3. Database changes (migration 001)

| Object | Purpose |
|---|---|
| `bookings.payment_reviewed_by / payment_reviewed_at / confirmed_by` | who/when manually reviewed payment; who confirmed (existing `confirmed_at` is now filled in) |
| `agreement_templates` | every edit = new version row; archived, never deleted |
| `agreement_settings` | authorized representative name/position/signature (single row) |
| `agreements` | one persistent PDF per booking revision; `UNIQUE(booking_id, revision)`; FK to bookings/templates |
| `agreement_email_log` | every send/resend attempt (`accepted` = accepted by SMTP server, not "delivered") |

Duplicate protection: booking row is locked (`FOR UPDATE`) during confirm/generate; unique revision key; atomic `send_started_at` claim blocks double-sends; button disabled while sending.

## 4. Templates – what each type can do

* **Editable template**: rich-text editor with placeholders (`{{customer_name}}` …). Produces a personalized PDF (branding, customer, booking, equipment, amounts, terms, representative signature block, customer signature/date lines). Missing values print "N/A". Unknown placeholders are rejected when saving.
* **Uploaded PDF**: static. It is attached exactly as uploaded and is **not** personalized (the app cannot write fields into an arbitrary PDF). The UI says so everywhere it is used.

## 5. Payment-related changes (summary)

* Receipt files are not moved/renamed/deleted. They are now served only through `api/admin/booking-file.php` (admin session required, path pinned to `uploads/proofs/`), and direct URLs are blocked by `uploads/proofs/.htaccess`.
* The old status dropdown can no longer set `confirmed` by itself (server-side), and now carries a CSRF token. Pending / Completed / Cancelled behave as before.
* No payment gateway or automatic verification was added.
* **Proof of Requirements**: the existing booking form and database collect no requirement documents (only the payment receipt), so this tab states that instead of inventing files.

## 6. Manual test script (run on your XAMPP – I could not run PHP/MySQL)

1. Submit a booking with a receipt from the public site → appears in Bookings as pending/submitted.
2. Open it → all tabs load; receipt previews (try a JPG and a PDF); Open/Download work; logged out → the receipt URL returns 401.
3. Try `http://localhost/…/uploads/proofs/<file>` directly → 403.
4. Set status dropdown to Confirmed → dialog opens, row status unchanged; Confirm disabled until both boxes ticked.
5. Confirm → agreement popup with PDF preview; check `storage/agreements/generated/` has the PDF and `agreements` has 1 row; payment status is still `submitted`.
6. Re-open the booking / re-select Confirmed / double-click Confirm → still 1 agreement row.
7. Send Agreement (use a real inbox) → toast, status Sent, attachment received. Click Resend → needs second click; log shows 2 rows.
8. Break SMTP password → Send fails, status Failed + error in history; fix → Retry Send works, no new PDF.
9. Customer with invalid email → clear error, logged as failed.
10. Edit the active template → saved as v2; previously generated PDFs unchanged; new confirmations use v2.
11. Upload a static PDF template, activate, confirm another booking → popup warns "not personalized".
12. Remove representative name → confirm shows the setup error with a link; booking stays confirmed; "Try again" works after setup.
13. Log out and open `api/admin/agreement-file.php?agreement_id=1` → 401.
14. Walk through Dashboard, Customers, Equipment, Feedback, public booking → unchanged.

## 7. Known limitations

* Not run in the development environment (no PHP/MySQL/Dompdf/SMTP available): see the testing report in the chat for exactly what was and was not checked.
* "Sent" = accepted by the SMTP server only. Customer signature/return is manual (print, sign, email back); signed-copy upload was not built.
* Single admin role (the existing system has no roles): any logged-in admin can manage templates.
* Admin pages have no toast/modal component of their own; small equivalents were added in `css/booking-review.css`.
* The deleted `script.js` also powered the admin mobile menu; `js/admin-common.js` now provides that toggle on the pages that load it (Bookings, Agreement). Other admin pages still reference the missing `script.js`.
