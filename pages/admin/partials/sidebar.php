<?php
// Usage: $activePage = 'dashboard' | 'bookings' | 'equipment' | 'customers';
$activePage = $activePage ?? '';
$cls = fn($p) => $activePage === $p ? ' class="active"' : '';
?>
<aside class="sidebar">
  <img class="logo" src="../../images/snapventure_logo.jpg" alt="SnapVenture" />
  <nav class="admin-nav">
    <a href="dashboard.php"<?= $cls('dashboard') ?>>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" /></svg>
      Dashboard
    </a>
    <a href="bookings.php"<?= $cls('bookings') ?>>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4" /><path d="M16 2v4" /><rect width="18" height="18" x="3" y="4" rx="2" /><path d="M3 10h18" /></svg>
      Bookings
    </a>
    <a href="equipment.php"<?= $cls('equipment') ?>>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /><circle cx="12" cy="13" r="3" /></svg>
      Equipment
    </a>
    <a href="customers.php"<?= $cls('customers') ?>>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
      Customers
    </a>
        <a href="feedback.php"<?= $cls('feedback') ?>>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
      Feedback
    </a>
        <a href="agreements.php"<?= $cls('agreements') ?>>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M8 13h8M8 17h5" /></svg>
      Agreement
    </a>
  </nav>
  <div class="sidebar-bottom">
    <a class="sidebar-link" href="../../index.php" target="_blank" rel="noopener">
      SnapVenture Website
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /></svg>
    </a>
    <a class="sidebar-link logout" href="auth/logout.php">
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" x2="9" y1="12" y2="12" /></svg>
      Logout
    </a>
  </div>
</aside>