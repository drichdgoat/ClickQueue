<?php require_once __DIR__ . '/admin-guard.php'; ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Feedback Management - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/equipment.css" />
    <link rel="stylesheet" href="../../css/feedback.css" />
    <link rel="stylesheet" href="../../css/admin-sidebar.css" />
    <link rel="stylesheet" href="../../css/admin-responsive.css?v=<?= filemtime(__DIR__ . '/../../css/admin-responsive.css') ?>" />
  </head>
  <body>
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b>
        <button class="btn btn-outline" data-admin-menu>☰</button>
      </div>

          <?php $activePage = 'feedback'; include __DIR__ . '/partials/sidebar.php'; ?>

      <main class="admin-content">
        <div class="admin-heading">
          <h1>Feedback Management</h1>
          <p>Manage customer reviews. Choose which ones appear on the homepage.</p>
        </div>

        <div class="stats">
          <div class="stat-card"><div><div class="label">Total Feedback</div><div class="value" id="statTotal">0</div></div></div>
          <div class="stat-card"><div><div class="label">Average Rating</div><div class="value" id="statAvg">0</div></div></div>
          <div class="stat-card"><div><div class="label">Featured</div><div class="value" id="statFeatured">0</div></div></div>
          <div class="stat-card"><div><div class="label">Not Featured</div><div class="value" id="statNotFeatured">0</div></div></div>
        </div>

        <div class="admin-card filter-card">
          <div class="search-box">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
            <input class="field-input" type="search" id="searchInput" placeholder="Search feedback..." autocomplete="off" />
          </div>
          <select class="field-select" id="ratingFilter">
            <option value="all">All Ratings</option>
            <option value="5">5 stars</option>
            <option value="4">4 stars</option>
            <option value="3">3 stars</option>
            <option value="2">2 stars</option>
            <option value="1">1 star</option>
          </select>
          <select class="field-select" id="featuredFilter">
            <option value="all">All Status</option>
            <option value="featured">Featured only</option>
            <option value="hidden">Not Featured</option>
          </select>
        </div>

        <div id="feedbackList"></div>
        <div class="empty-state" id="emptyState" hidden>
          <h3>No feedback found</h3>
          <p>Try a different search or filter.</p>
        </div>
      </main>
    </div>

    <script src="../../js/admin-menu.js"></script>
    <script src="../../js/feedback-admin.js"></script>
  </body>
</html>