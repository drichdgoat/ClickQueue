<?php require_once __DIR__ . '/admin-guard.php'; ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Equipment Management - SnapVenture Admin</title>
    <link rel="stylesheet" href="../../css/equipment.css" />
      <link rel="stylesheet" href="../../css/admin-sidebar.css" />
  </head>
  <body>
    <div class="admin-shell">
      <div class="admin-mobile">
        <b style="color: var(--brown)">SnapVenture Admin</b>
        <button class="btn btn-outline" data-admin-menu>☰</button>
      </div>

        <?php $activePage = 'equipment'; include __DIR__ . '/partials/sidebar.php'; ?>
      <main class="admin-content">
        <div class="page-head">
          <div class="admin-heading">
            <h1>Equipment Management</h1>
            <p>Manage camera packages and accessories. Changes here show up on Services and Booking automatically.</p>
          </div>
          <button class="btn btn-primary btn-icon" type="button" id="addBtn">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14" /><path d="M12 5v14" /></svg>
            Add Equipment
          </button>
        </div>

        <div class="stats">
          <div class="stat-card"><div><div class="label">Total Cameras</div><div class="value" id="statCameras">0</div></div></div>
          <div class="stat-card"><div><div class="label">Total Accessories</div><div class="value" id="statAccessories">0</div></div></div>
          <div class="stat-card"><div><div class="label">Active</div><div class="value" id="statActive">0</div></div></div>
          <div class="stat-card"><div><div class="label">Inactive</div><div class="value" id="statInactive">0</div></div></div>
        </div>

        <div class="admin-card filter-card">
          <div class="search-box">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
            <input class="field-input" type="search" id="searchInput" placeholder="Search equipment..." autocomplete="off" />
          </div>
          <select class="field-select" id="categoryFilter">
            <option value="all">All Categories</option>
            <option value="Camera">Cameras</option>
            <option value="Accessories">Accessories</option>
          </select>
          <select class="field-select" id="statusFilter">
            <option value="all">All Status</option>
            <option value="active">Active only</option>
            <option value="inactive">Inactive only</option>
          </select>
        </div>

        <div class="equipment-grid" id="equipmentGrid"></div>
        <div class="empty-state" id="emptyState" hidden>
          <h3 id="emptyTitle">No equipment found</h3>
          <p id="emptyText">Try a different search or category.</p>
        </div>
      </main>
    </div>

    <!-- Add / Edit modal -->
    <div class="modal-overlay" id="equipmentModal">
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-head">
          <h2 id="modalTitle">Add New Equipment</h2>
          <button class="icon-btn" type="button" data-close aria-label="Close">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
          </button>
        </div>

        <form id="equipmentForm">
          <input type="hidden" id="editId" value="" />

          <div class="form-group" style="margin-bottom:16px">
            <label for="eqType">Equipment Type</label>
            <select class="field-select" id="eqType" required>
              <option value="camera">Camera (rental package)</option>
              <option value="accessory">Accessory (e.g. Film, Microphone)</option>
            </select>
          </div>

          <!-- ===== CAMERA FIELDS ===== -->
          <div id="cameraFields">
            <div class="form-grid">
              <div class="form-group">
                <label for="camName">Camera / Package Name</label>
                <input class="field-input" type="text" id="camName" placeholder="e.g. Osmo Pocket 3" />
              </div>
              <div class="form-group">
                <label for="camSubtitle">Subtitle (optional)</label>
                <input class="field-input" type="text" id="camSubtitle" placeholder="e.g. Standard/Creator Combo" />
              </div>
            </div>

            <div class="form-group" style="margin-top:14px">
              <label for="camImage">Photo <span id="camImageRequired" style="color:var(--red)">(required)</span></label>
              <input class="field-input" type="file" id="camImage" accept="image/png,image/jpeg,image/webp" />
              <img id="camImagePreview" alt="" hidden />
            </div>

            <div class="form-group" style="margin-top:14px">
              <label for="camPricingType">Pricing Type</label>
              <select class="field-select" id="camPricingType">
                <option value="per_day">Per Day (tiered rates, e.g. 2-4 days / 5+ days)</option>
                <option value="per_day_and_hour">Flat + Additional Hour (e.g. Instax Pal style)</option>
                <option value="per_photo">Per Photo (tiered, e.g. Instax Mini Evo style)</option>
              </select>
            </div>

            <div id="pricingFieldsWrap" style="margin-top:14px"></div>

            <div class="form-group" style="margin-top:14px">
              <label>Inclusions</label>
              <div id="inclusionsWrap"></div>
              <button class="btn btn-outline" type="button" id="addInclusionBtn" style="margin-top:6px">+ Add Inclusion</button>
            </div>
          </div>

          <!-- ===== ACCESSORY FIELDS ===== -->
          <div id="accessoryFields" hidden>
            <div class="form-grid">
              <div class="form-group">
                <label for="accName">Accessory Name</label>
                <input class="field-input" type="text" id="accName" placeholder="e.g. BOYA Microphone" />
              </div>
              <div class="form-group">
                <label for="accPrice">Price (₱)</label>
                <input class="field-input" type="number" id="accPrice" min="0" step="0.01" placeholder="250" />
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-top:16px">
            <label style="display:flex;align-items:center;gap:8px;font-weight:500">
              <input type="checkbox" id="eqActive" checked style="width:auto" />
              Active (visible on Services &amp; Booking)
            </label>
          </div>

          <p class="modal-text" id="formError" style="color:var(--red);display:none"></p>

          <div class="modal-actions">
            <button class="btn btn-outline" type="button" data-close>Cancel</button>
            <button class="btn btn-primary" type="submit" id="submitBtn">Add Equipment</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Deactivate/Activate confirmation modal -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal modal-sm" role="alertdialog" aria-modal="true" aria-labelledby="deleteTitle">
        <div class="modal-head">
          <h2 id="deleteTitle">Change status?</h2>
          <button class="icon-btn" type="button" data-close aria-label="Close">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
          </button>
        </div>
        <p class="modal-text" id="deleteText"></p>
        <div class="modal-actions">
          <button class="btn btn-outline" type="button" data-close>Cancel</button>
          <button class="btn btn-danger-solid" type="button" id="confirmDeleteBtn">Confirm</button>
        </div>
      </div>
    </div>

    <script src="../../script.js"></script>
    <script src="../../js/equipment.js"></script>
  </body>
</html>