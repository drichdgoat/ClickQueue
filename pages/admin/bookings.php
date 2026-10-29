<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../../includes/pricing.php';
require_once __DIR__ . '/../../includes/admin-api.php'; // csrf_token()

/* ---------------- handle status update ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['new_status'])) {
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    $csrfOk = hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''));
    // 'confirmed' can ONLY be set through the review dialog (api/admin/booking-confirm.php),
    // which requires the admin's acknowledgment, checks availability/receipt and creates the agreement.
    if ($csrfOk && $_POST['new_status'] !== 'confirmed' && in_array($_POST['new_status'], $validStatuses, true)) {
        $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?');
        $stmt->execute([$_POST['new_status'], (int) $_POST['booking_id']]);
    }
    header('Location: bookings.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

/* ---------------- search ---------------- */
$q = trim($_GET['q'] ?? '');
$sql = "SELECT b.booking_id, b.reference_no, c.full_name, c.email, b.service_type,
               b.event_date, b.grand_total, b.amount_due_now, b.remaining_balance,
               b.payment_method, b.payment_status, b.status,
               cam.name AS camera_name, pb.name AS photobooth_name,
               bcd.rental_days
        FROM bookings b
        JOIN customers c ON c.customer_id = b.customer_id
        LEFT JOIN booking_camera_details bcd ON bcd.booking_id = b.booking_id
        LEFT JOIN camera_packages cam ON cam.camera_id = bcd.camera_id
        LEFT JOIN booking_photobooth_details bpd ON bpd.booking_id = b.booking_id
        LEFT JOIN photobooth_packages pb ON pb.photobooth_id = bpd.photobooth_id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE b.reference_no LIKE ? OR c.full_name LIKE ? OR c.email LIKE ?";
    $like = "%$q%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

function peso($n) { return '₱' . number_format((float) $n, 2); }
function serviceLabel($t) { return $t === 'camera_rental' ? 'Camera Rental' : 'Photobooth'; }

/* Display only: a completed booking is treated as fully paid, so it shows no outstanding balance.
   The stored remaining_balance value is not changed. */
function displayBalance(array $b) { return $b['status'] === 'completed' ? 0 : $b['remaining_balance']; }
function bookingDateRange(array $b): string {
    $start = new DateTimeImmutable($b['event_date']);
    $days = $b['service_type'] === 'camera_rental' ? max(1, (int) ($b['rental_days'] ?? 1)) : 1;
    $end = $start->modify('+' . ($days - 1) . ' days');
    return $start->format('M j, Y') . ($days > 1 ? ' – ' . $end->format('M j, Y') : '');
}

/* Card counts, taken from the bookings already loaded above (respects the search box) */
$counts = ['all' => count($bookings), 'camera_rental' => 0, 'photobooth' => 0];
foreach ($bookings as $row) {
    $counts[$row['service_type'] === 'camera_rental' ? 'camera_rental' : 'photobooth']++;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Bookings Management - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/bookings.css?v=<?= filemtime(__DIR__ . '/../../css/bookings.css') ?>" />
      <link rel="stylesheet" href="../../css/admin-sidebar.css" />
      <link rel="stylesheet" href="../../css/booking-review.css" />
      <link rel="stylesheet" href="../../css/bookings-table.css?v=<?= filemtime(__DIR__ . '/../../css/bookings-table.css') ?>" />
      <link rel="stylesheet" href="../../css/admin-booking-create.css?v=<?= filemtime(__DIR__ . '/../../css/admin-booking-create.css') ?>" />
      <link rel="stylesheet" href="../../css/admin-responsive.css?v=<?= filemtime(__DIR__ . '/../../css/admin-responsive.css') ?>" />
  </head>
  <body data-api="../../api/admin/" data-csrf="<?= htmlspecialchars(csrf_token()) ?>" data-open="<?= (int) ($_GET['open'] ?? 0) ?>">
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b
        ><button class="btn btn-outline" data-admin-menu>☰</button>
      </div>
        <?php $activePage = 'bookings'; include __DIR__ . '/partials/sidebar.php'; ?>
      <main class="admin-content">
        <div class="booking-page-head">
          <div class="admin-heading">
            <h1>Bookings Management</h1>
            <p>View and manage all customer bookings.</p>
          </div>
          <button class="btn btn-primary btn-icon" type="button" id="addBookingBtn">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14" /><path d="M12 5v14" /></svg>
            Add Booking
          </button>
        </div>

        <div class="category-cards" role="group" aria-label="Filter bookings by service">
          <button type="button" class="category-card is-active" data-category="all" aria-pressed="true">
            <span class="cc-text"><span class="cc-label">All Bookings</span><span class="cc-hint">Every service</span></span>
            <span class="cc-count"><?= (int) $counts['all'] ?></span>
          </button>
          <button type="button" class="category-card" data-category="camera_rental" aria-pressed="false">
            <span class="cc-text"><span class="cc-label">Camera Rental</span><span class="cc-hint">Rentals only</span></span>
            <span class="cc-count"><?= (int) $counts['camera_rental'] ?></span>
          </button>
          <button type="button" class="category-card" data-category="photobooth" aria-pressed="false">
            <span class="cc-text"><span class="cc-label">Photobooth Packages</span><span class="cc-hint">Photobooth only</span></span>
            <span class="cc-count"><?= (int) $counts['photobooth'] ?></span>
          </button>
        </div>

        <div class="admin-card" style="margin-top: 20px">
          <form class="action-row" method="get">
            <input
              class="btn btn-outline"
              style="flex: 1; min-width: 220px; text-align: left; background: white"
              type="search" name="q" placeholder="Search by reference no, name, or email..."
              value="<?= htmlspecialchars($q) ?>"
            />
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline" href="bookings.php">Clear</a><?php endif; ?>
          </form>

          <div class="table-toolbar">
            <div class="filter-group">
              <label for="statusFilter">Status</label>
              <select id="statusFilter" class="status-select status-filter">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <span class="result-count" id="resultCount" aria-live="polite"></span>
          </div>

          <div class="admin-table-wrap bookings-wrap">
            <table class="bookings-table">
              <thead>
                <tr>
                  <th class="col-ref">Ref No.</th><th class="col-customer">Customer</th><th class="col-service">Service</th><th class="col-package">Package</th>
                  <th class="col-date">Date</th><th class="col-money">Total</th><th class="col-money">Balance</th>
                  <th class="col-payment">Payment</th><th class="col-status">Status</th><th class="col-update">Update Status</th><th class="col-action">Actions</th>
                </tr>
              </thead>
              <tbody id="bookingsBody">
              <?php if (!$bookings): ?>
                <tr class="empty-row"><td colspan="11">No bookings found.</td></tr>
              <?php endif; ?>
              <?php foreach ($bookings as $b): ?>
                <tr class="row-clickable" data-booking-id="<?= (int) $b['booking_id'] ?>" data-service="<?= $b['service_type'] === 'camera_rental' ? 'camera_rental' : 'photobooth' ?>" data-status="<?= htmlspecialchars($b['status']) ?>">
                  <td class="col-ref"><?= htmlspecialchars($b['reference_no']) ?></td>
                  <td class="col-customer"><?= htmlspecialchars($b['full_name']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($b['email']) ?></small></td>
                  <td class="col-service"><?= serviceLabel($b['service_type']) ?></td>
                  <td class="col-package"><?= htmlspecialchars($b['camera_name'] ?? $b['photobooth_name'] ?? '—') ?></td>
                  <td class="col-date"><?= htmlspecialchars(bookingDateRange($b)) ?></td>
                  <td class="col-money"><?= peso($b['grand_total']) ?></td>
                  <td class="col-money"><?= peso(displayBalance($b)) ?></td>
                  <td class="payment-cell col-payment"><?= htmlspecialchars($b['payment_method']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($b['payment_status']) ?></small></td>
                  <td class="status-cell col-status"><span class="status <?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                  <td class="col-update">
                    <form method="post" class="status-form">
                      <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <select name="new_status" class="field-select status-select" aria-label="Update booking status" data-current="<?= htmlspecialchars($b['status']) ?>"
                        onchange="if (this.value === 'confirmed') { var c = this.dataset.current; this.value = c; if (window.svOpenBooking) svOpenBooking(this.form.booking_id.value, true); } else { this.form.submit(); }">
                        <option value="pending" <?= $b['status']==='pending'?'selected':'' ?>>Pending</option>
                        <option value="confirmed" <?= $b['status']==='confirmed'?'selected':'' ?>>Confirmed</option>
                        <option value="completed" <?= $b['status']==='completed'?'selected':'' ?>>Completed</option>
                        <option value="cancelled" <?= $b['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                      </select>
                    </form>
                  </td>
                  <td class="col-action">
                    <button type="button" class="btn btn-outline btn-sm view-btn" data-view-booking="<?= (int) $b['booking_id'] ?>">View Details</button>
                  </td>
                </tr>
              <?php endforeach; ?>
                <tr class="empty-row" id="noMatchRow" hidden><td colspan="11">No bookings match the selected filters.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
    <div class="booking-modal-overlay" id="createBookingModal" hidden>
      <section class="booking-modal" role="dialog" aria-modal="true" aria-labelledby="createBookingTitle">
        <div class="booking-modal-head">
          <div>
            <h2 id="createBookingTitle">Add Booking</h2>
            <p>Enter the customer's information and reservation details.</p>
          </div>
          <button class="booking-modal-close" type="button" data-booking-close aria-label="Close">×</button>
        </div>

        <form id="createBookingForm">
          <div class="booking-form-section">
            <h3>Customer Information</h3>
            <div class="booking-form-grid">
              <div class="booking-field">
                <label for="createFullName">Full Name</label>
                <input id="createFullName" name="full_name" type="text" autocomplete="name" required />
              </div>
              <div class="booking-field">
                <label for="createPhone">Contact Number</label>
                <input id="createPhone" name="phone" type="tel" autocomplete="tel" required />
              </div>
              <div class="booking-field">
                <label for="createFacebook">Facebook / Messenger Name</label>
                <input id="createFacebook" name="facebook_name" type="text" required />
              </div>
              <div class="booking-field">
                <label for="createEmail">Email Address</label>
                <input id="createEmail" name="email" type="email" autocomplete="email" required />
              </div>
            </div>
          </div>

          <div class="booking-form-section">
            <h3>Service &amp; Package</h3>
            <div class="booking-form-grid">
              <div class="booking-field">
                <label for="createServiceType">Service Type</label>
                <select id="createServiceType" name="service_type" required>
                  <option value="">Choose a service...</option>
                  <option value="camera_rental">Camera Rental</option>
                  <option value="photobooth">Photobooth</option>
                </select>
              </div>
              <div class="booking-field">
                <label for="createPackage">Package</label>
                <select id="createPackage" required disabled>
                  <option value="">Choose a service first...</option>
                </select>
              </div>
            </div>
            <div id="createPackageDetails" class="booking-package-details"></div>
            <div id="createPriceSummary" class="booking-price-summary" aria-live="polite">
              Select a package to see the booking total.
            </div>
          </div>

          <div class="booking-form-section">
            <h3>Event Details</h3>
            <div class="booking-form-grid">
              <div class="booking-field">
                <label for="createEventDate">Event Date</label>
                <input id="createEventDateDisplay" type="text" placeholder="Select an available date" readonly required aria-describedby="createAvailabilityMessage" />
                <input id="createEventDate" name="event_date" type="hidden" />
              </div>
              <div class="booking-field booking-field-full">
                <div class="booking-availability-calendar" aria-label="Booking date availability">
                  <div class="booking-calendar-head">
                    <button class="booking-calendar-nav" id="createCalendarPrev" type="button" aria-label="Previous month" disabled>&lsaquo;</button>
                    <strong id="createCalendarTitle"></strong>
                    <button class="booking-calendar-nav" id="createCalendarNext" type="button" aria-label="Next month" disabled>&rsaquo;</button>
                  </div>
                  <div class="booking-calendar-week" aria-hidden="true">
                    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                  </div>
                  <div class="booking-calendar-grid" id="createCalendarGrid"></div>
                  <p class="booking-availability-message" id="createAvailabilityMessage" role="status">Choose a service and package to view availability.</p>
                  <div class="booking-calendar-legend" aria-label="Calendar legend">
                    <span><i class="is-available"></i>Available</span>
                    <span><i class="is-booked"></i>Booked</span>
                    <span><i class="is-selected"></i>Selected</span>
                  </div>
                </div>
              </div>
              <div class="booking-field">
                <label for="createEventLocation">Event Location</label>
                <input id="createEventLocation" name="event_location" type="text" required />
              </div>
              <div class="booking-field">
                <label id="createStartTimeLabel" for="createStartTime">Start Time</label>
                <input id="createStartTime" name="start_time" type="time" />
              </div>
              <div class="booking-field">
                <label id="createEndTimeLabel" for="createEndTime">End Time</label>
                <input id="createEndTime" name="end_time" type="time" />
              </div>
              <div class="booking-field booking-field-full">
                <label for="createNotes">Additional Notes</label>
                <textarea id="createNotes" name="notes" rows="3"></textarea>
              </div>
            </div>
          </div>

          <div class="booking-form-section">
            <h3>Payment Method</h3>
            <div class="booking-form-grid">
              <div class="booking-field">
                <label for="createPaymentMethod">Payment Method</label>
                <select id="createPaymentMethod" name="payment_method" required>
                  <option value="">Choose a payment method...</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                  <option value="GCash / PayMaya">GCash / PayMaya</option>
                  <option value="Over-the-Counter">Over-the-Counter</option>
                </select>
              </div>
              <div class="booking-field" id="createProofField">
                <label for="createProof">Proof of Payment</label>
                <input id="createProof" name="proof_file" type="file" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" />
                <small>PNG, JPG, or PDF; maximum 5MB. Required except for Over-the-Counter.</small>
              </div>
            </div>
            <label class="booking-terms">
              <input id="createAgreeTerms" type="checkbox" required />
              <span>I have read and agree to the rental terms and conditions.</span>
            </label>
          </div>

          <p class="booking-form-message" id="createBookingMessage" role="alert" hidden></p>
          <div class="booking-modal-actions">
            <button class="btn btn-outline" type="button" data-booking-close>Cancel</button>
            <button class="btn btn-primary" type="submit" id="createBookingSubmit" disabled>Add Booking</button>
          </div>
        </form>
      </section>
    </div>
    <?php include __DIR__ . '/partials/booking-details-modal.php'; ?>
    <?php include __DIR__ . '/partials/agreement-modal.php'; ?>
    <script src="../../js/admin-common.js"></script>
    <script src="../../js/admin-menu.js"></script>
    <script src="../../js/agreement-modal.js"></script>
    <script src="../../js/booking-details.js?v=<?= filemtime(__DIR__ . '/../../js/booking-details.js') ?>"></script>
    <script src="../../js/bookings.js"></script>
    <script src="../../js/admin-booking-create.js?v=<?= filemtime(__DIR__ . '/../../js/admin-booking-create.js') ?>"></script>
  </body>
</html>