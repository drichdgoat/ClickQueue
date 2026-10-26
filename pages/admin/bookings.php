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
               cam.name AS camera_name, pb.name AS photobooth_name
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
    <link rel="stylesheet" href="../../css/bookings.css" />
      <link rel="stylesheet" href="../../css/admin-sidebar.css" />
      <link rel="stylesheet" href="../../css/booking-review.css" />
      <link rel="stylesheet" href="../../css/bookings-table.css" />
  </head>
  <body data-api="../../api/admin/" data-csrf="<?= htmlspecialchars(csrf_token()) ?>" data-open="<?= (int) ($_GET['open'] ?? 0) ?>">
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b
        ><button class="btn btn-outline" data-admin-menu>☰</button>
      </div>
        <?php $activePage = 'bookings'; include __DIR__ . '/partials/sidebar.php'; ?>
      <main class="admin-content">
        <div class="admin-heading">
          <h1>Bookings Management</h1>
          <p>View and manage all customer bookings.</p>
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
                  <td class="col-date"><?= htmlspecialchars($b['event_date']) ?></td>
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
    <?php include __DIR__ . '/partials/booking-details-modal.php'; ?>
    <?php include __DIR__ . '/partials/agreement-modal.php'; ?>
    <script src="../../js/admin-common.js"></script>
    <script src="../../js/agreement-modal.js"></script>
    <script src="../../js/booking-details.js"></script>
    <script src="../../js/bookings.js"></script>
  </body>
</html>