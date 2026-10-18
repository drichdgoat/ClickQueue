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
        <div class="admin-card" style="margin-top: 25px">
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

          <div class="admin-table-wrap" style="margin-top: 15px">
            <table>
              <tr>
                <th>Ref No.</th><th>Customer</th><th>Service</th><th>Package</th>
                <th>Date</th><th>Total</th><th>Due Now</th><th>Balance</th>
                <th>Payment</th><th>Status</th><th></th>
              </tr>
              <?php if (!$bookings): ?>
                <tr><td colspan="11" style="text-align:center;color:var(--muted);padding:24px">No bookings found.</td></tr>
              <?php endif; ?>
              <?php foreach ($bookings as $b): ?>
                <tr class="row-clickable" data-booking-id="<?= (int) $b['booking_id'] ?>">
                  <td><?= htmlspecialchars($b['reference_no']) ?></td>
                  <td><?= htmlspecialchars($b['full_name']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($b['email']) ?></small></td>
                  <td><?= serviceLabel($b['service_type']) ?></td>
                  <td><?= htmlspecialchars($b['camera_name'] ?? $b['photobooth_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($b['event_date']) ?></td>
                  <td><?= peso($b['grand_total']) ?></td>
                  <td><?= peso($b['amount_due_now']) ?></td>
                  <td><?= peso($b['remaining_balance']) ?></td>
                  <td><?= htmlspecialchars($b['payment_method']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($b['payment_status']) ?></small></td>
                  <td class="status-cell"><span class="status <?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                  <td>
                    <form method="post" style="display:flex;gap:4px">
                      <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <select name="new_status" class="field-select" style="height:34px;padding:0 6px;font-size:12px" data-current="<?= htmlspecialchars($b['status']) ?>"
                        onchange="if (this.value === 'confirmed') { var c = this.dataset.current; this.value = c; if (window.svOpenBooking) svOpenBooking(this.form.booking_id.value, true); } else { this.form.submit(); }">
                        <option value="pending" <?= $b['status']==='pending'?'selected':'' ?>>Pending</option>
                        <option value="confirmed" <?= $b['status']==='confirmed'?'selected':'' ?>>Confirmed</option>
                        <option value="completed" <?= $b['status']==='completed'?'selected':'' ?>>Completed</option>
                        <option value="cancelled" <?= $b['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                      </select>
                    </form>
                    <button type="button" class="btn btn-outline btn-sm" style="margin-top:6px" data-view-booking="<?= (int) $b['booking_id'] ?>">View Details</button>
                  </td>
                </tr>
              <?php endforeach; ?>
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
  </body>
</html>