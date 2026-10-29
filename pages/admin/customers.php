<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../../includes/pricing.php';

$customers = $pdo->query(
    "SELECT c.customer_id, c.full_name, c.email, c.phone,
            COUNT(b.booking_id) AS total_bookings,
            COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN b.grand_total ELSE 0 END),0) AS lifetime_value
     FROM customers c
     LEFT JOIN bookings b ON b.customer_id = c.customer_id
     GROUP BY c.customer_id
     ORDER BY total_bookings DESC, c.full_name ASC"
)->fetchAll();

function peso($n) { return '₱' . number_format((float) $n, 2); }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Customer Management - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/customers.css" />
      <link rel="stylesheet" href="../../css/admin-sidebar.css" />
    <link rel="stylesheet" href="../../css/admin-responsive.css?v=<?= filemtime(__DIR__ . '/../../css/admin-responsive.css') ?>" />
  </head>
  <body>
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b
        ><button class="btn btn-outline" data-admin-menu>☰</button>
      </div>
        <?php $activePage = 'customers'; include __DIR__ . '/partials/sidebar.php'; ?>
      <main class="admin-content">
        <div class="admin-heading">
          <h1>Customer Management</h1>
          <p>Everyone who has booked through SnapVenture.</p>
        </div>
        <div class="admin-card" style="margin-top: 25px">
          <div class="admin-table-wrap">
            <table>
              <tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Lifetime Value</th></tr>
              <?php if (!$customers): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">No customers yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($customers as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['full_name']) ?></td>
                  <td><?= htmlspecialchars($c['email']) ?></td>
                  <td><?= htmlspecialchars($c['phone']) ?></td>
                  <td><?= (int) $c['total_bookings'] ?></td>
                  <td><?= peso($c['lifetime_value']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </main>
    </div>
    <script src="../../js/admin-menu.js"></script>
  </body>
</html>