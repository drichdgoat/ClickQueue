<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../../includes/pricing.php'; // also gives us $pdo

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');

/* ---------------- stats ---------------- */
$totalBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled'")->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

// Only money the admin has actually verified counts as "collected":
// bookings marked confirmed/completed, counted in the month they were
// confirmed (older rows without confirmed_at fall back to created_at).
$monthRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount_due_now),0) FROM bookings
     WHERE status IN ('confirmed','completed')
       AND DATE_FORMAT(COALESCE(confirmed_at, created_at), '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')"
)->fetchColumn();

$cameraBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE service_type='camera_rental' AND status != 'cancelled'")->fetchColumn();
$photoboothBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE service_type='photobooth' AND status != 'cancelled'")->fetchColumn();

/* ---------------- recent bookings ---------------- */
$recent = $pdo->query(
    "SELECT b.reference_no, c.full_name, b.service_type, b.event_date, b.grand_total, b.amount_due_now, b.status
     FROM bookings b JOIN customers c ON c.customer_id = b.customer_id
     ORDER BY b.created_at DESC LIMIT 8"
)->fetchAll();

function peso($n) { return '₱' . number_format((float) $n, 2); }
function serviceLabel($t) { return $t === 'camera_rental' ? 'Camera Rental' : 'Photobooth'; }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Dashboard - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/dashboard.css" />
      <link rel="stylesheet" href="../../css/admin-sidebar.css" />
  </head>
  <body>
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b
        ><button class="btn btn-outline" data-admin-menu>☰</button>
      </div>
       <?php $activePage = 'dashboard'; include __DIR__ . '/partials/sidebar.php'; ?>
      <main class="admin-content">
        <div class="admin-heading">
          <h1>Dashboard</h1>
          <p>Welcome back, <?php echo $adminName; ?>! Here's what's happening today.</p>
        </div>
        <div class="stats">
          <div class="stat-card">
            <div class="label">Total Bookings</div>
            <div class="value"><?= $totalBookings ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Camera Rentals</div>
            <div class="value"><?= $cameraBookings ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Photobooth Bookings</div>
            <div class="value"><?= $photoboothBookings ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Total Customers</div>
            <div class="value"><?= $totalCustomers ?></div>
          </div>
        </div>
        <div class="admin-card">
          <h2>Amount Collected This Month</h2>
          <p style="font-size: 30px; font-weight: bold; color: var(--brown); margin: 0">
            <?= peso($monthRevenue) ?>
          </p>
          <p style="color: var(--muted); margin: 4px 0 0">
            Down payments / reservation fees from bookings you confirmed this month
          </p>
        </div>
        <div class="admin-card">
          <h2>Recent Bookings</h2>
          <div class="admin-table-wrap">
            <table>
              <tr><th>Ref No.</th><th>Customer</th><th>Service</th><th>Date</th><th>Total</th><th>Amount Due Now</th><th>Status</th></tr>
              <?php if (!$recent): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--muted)">No bookings yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($recent as $b): ?>
                <tr>
                  <td><?= htmlspecialchars($b['reference_no']) ?></td>
                  <td><?= htmlspecialchars($b['full_name']) ?></td>
                  <td><?= serviceLabel($b['service_type']) ?></td>
                  <td><?= htmlspecialchars($b['event_date']) ?></td>
                  <td><?= peso($b['grand_total']) ?></td>
                  <td><?= peso($b['amount_due_now']) ?></td>
                  <td><span class="status <?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </main>
    </div>
    <script src="../../script.js"></script>
  </body>
</html>