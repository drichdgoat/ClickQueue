/* SnapVenture Admin - Equipment Management (DB-backed via /api/admin-*.php) */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const grid = $("equipmentGrid");
  if (!grid) return; // not on this page

  const emptyState = $("emptyState");
  const searchInput = $("searchInput");
  const categoryFilter = $("categoryFilter");
  const statusFilter = $("statusFilter");

  const equipmentModal = $("equipmentModal");
  const deleteModal = $("deleteModal");
  const form = $("equipmentForm");
  const modalTitle = $("modalTitle");
  const submitBtn = $("submitBtn");
  const formError = $("formError");

  let cameras = [];
  let accessories = [];
  let editType = null; // 'camera' | 'accessory' | null (adding)
  let editId = null;
  let existingImageUrl = null;
  let toggleTarget = null; // {type, id, name, nextActive}

  function esc(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
  function peso(n) {
    return "₱" + Number(n || 0).toLocaleString("en-US");
  }
  function resolveImg(path) {
    return path ? "../../" + path : "";
  } // pages/admin/ is two levels deep

  /* =========================================================
     LOAD + RENDER
     ========================================================= */
  function loadEquipment() {
    fetch("../../api/admin/admin-equipment-list.php")
      .then((r) => r.json())
      .then((data) => {
        if (data.error) {
          formError.textContent = data.error;
          formError.style.display = "block";
          return;
        }
        cameras = data.cameras || [];
        accessories = data.accessories || [];
        render();
      })
      .catch(() => {
        grid.innerHTML =
          '<p style="color:var(--muted)">Could not load equipment. Please refresh.</p>';
      });
  }

  function cameraPriceSummary(cam) {
    if (
      cam.pricing_type === "per_day" &&
      cam.rate_tiers &&
      cam.rate_tiers.length
    ) {
      return cam.rate_tiers
        .map(
          (t) =>
            peso(t.rate_per_day) +
            "/day (" +
            t.min_days +
            (t.max_days ? "-" + t.max_days : "+") +
            " days)",
        )
        .join(", ");
    }
    if (cam.pricing_type === "per_day_and_hour" && cam.flat_hourly) {
      return (
        peso(cam.flat_hourly.flat_price) +
        " flat (1-" +
        cam.flat_hourly.flat_days +
        "d) + " +
        peso(cam.flat_hourly.extra_hour_rate) +
        "/extra hr"
      );
    }
    if (
      cam.pricing_type === "per_photo" &&
      cam.photo_tiers &&
      cam.photo_tiers.length
    ) {
      return cam.photo_tiers
        .map(
          (t) =>
            peso(t.price_per_photo) +
            "/photo (" +
            t.min_qty +
            (t.max_qty ? "-" + t.max_qty : "+") +
            " pcs)",
        )
        .join(", ");
    }
    return "No pricing set";
  }

  function cameraCardHTML(cam) {
    const img = cam.image_url
      ? '<img src="' +
        esc(resolveImg(cam.image_url)) +
        '" alt="" style="width:100%;height:160px;object-fit:cover;border-radius:10px;margin-bottom:14px">'
      : "";
    return (
      '<article class="eq-card">' +
      img +
      '<div class="eq-top"><h3>' +
      esc(cam.name) +
      '</h3><span class="pill">Camera</span></div>' +
      '<p class="eq-id">' +
      esc(cam.subtitle || "") +
      "</p>" +
      '<div class="eq-status-row">' +
      '<span class="badge ' +
      (cam.is_active == 1 ? "badge-available" : "badge-maintenance") +
      '">' +
      (cam.is_active == 1 ? "Active" : "Inactive") +
      "</span>" +
      '<span class="condition">' +
      (cam.booking_count || 0) +
      " booking(s)</span>" +
      "</div>" +
      '<div class="eq-meta"><div class="eq-row"><span>Pricing:</span></div><div style="font-size:13px;color:var(--muted)">' +
      esc(cameraPriceSummary(cam)) +
      "</div></div>" +
      '<p class="eq-specs">' +
      (cam.inclusions && cam.inclusions.length
        ? esc(cam.inclusions.join(", "))
        : "No inclusions listed") +
      "</p>" +
      '<div class="eq-actions">' +
      '<button type="button" class="btn btn-outline btn-icon btn-edit" data-action="edit" data-type="camera" data-id="' +
      cam.camera_id +
      '">Edit</button>' +
      '<button type="button" class="btn btn-toggle" data-action="toggle" data-type="camera" data-id="' +
      cam.camera_id +
      '" data-active="' +
      cam.is_active +
      '" data-name="' +
      esc(cam.name) +
      '">' +
      (cam.is_active == 1 ? "Deactivate" : "Activate") +
      "</button>" +
      "</div></article>"
    );
  }

  function accessoryCardHTML(acc) {
    return (
      '<article class="eq-card">' +
      '<div class="eq-top"><h3>' +
      esc(acc.name) +
      '</h3><span class="pill">Accessories</span></div>' +
      '<p class="eq-id">' +
      peso(acc.price) +
      "</p>" +
      '<div class="eq-status-row">' +
      '<span class="badge ' +
      (acc.is_active == 1 ? "badge-available" : "badge-maintenance") +
      '">' +
      (acc.is_active == 1 ? "Active" : "Inactive") +
      "</span>" +
      '<span class="condition">' +
      (acc.booking_count || 0) +
      " time(s) selected</span>" +
      "</div>" +
      '<div class="eq-actions">' +
      '<button type="button" class="btn btn-outline btn-icon btn-edit" data-action="edit" data-type="accessory" data-id="' +
      acc.accessory_id +
      '">Edit</button>' +
      '<button type="button" class="btn btn-outline btn-icon btn-delete" data-action="toggle" data-type="accessory" data-id="' +
      acc.accessory_id +
      '" data-active="' +
      acc.is_active +
      '" data-name="' +
      esc(acc.name) +
      '">' +
      (acc.is_active == 1 ? "Deactivate" : "Activate") +
      "</button>" +
      "</div></article>"
    );
  }

  function render() {
    const term = (searchInput.value || "").trim().toLowerCase();
    const cat = categoryFilter.value;
    const stat = statusFilter.value;

    function passFilters(name, isActive, category) {
      if (cat !== "all" && cat !== category) return false;
      if (stat === "active" && isActive != 1) return false;
      if (stat === "inactive" && isActive == 1) return false;
      if (term && name.toLowerCase().indexOf(term) === -1) return false;
      return true;
    }

    const camCards = cameras
      .filter((c) => passFilters(c.name, c.is_active, "Camera"))
      .map(cameraCardHTML);
    const accCards = accessories
      .filter((a) => passFilters(a.name, a.is_active, "Accessories"))
      .map(accessoryCardHTML);
    const all = camCards.concat(accCards);

    grid.innerHTML = all.join("");
    emptyState.hidden = all.length > 0;
    if (all.length === 0) {
      $("emptyTitle").textContent =
        cameras.length + accessories.length === 0
          ? "No equipment yet"
          : "No equipment found";
      $("emptyText").textContent =
        cameras.length + accessories.length === 0
          ? "Click Add Equipment to add your first camera or accessory."
          : "Try a different search or filter.";
    }

    $("statCameras").textContent = cameras.length;
    $("statAccessories").textContent = accessories.length;
    $("statActive").textContent =
      cameras.filter((c) => c.is_active == 1).length +
      accessories.filter((a) => a.is_active == 1).length;
    $("statInactive").textContent =
      cameras.filter((c) => c.is_active != 1).length +
      accessories.filter((a) => a.is_active != 1).length;
  }

  searchInput.addEventListener("input", render);
  categoryFilter.addEventListener("change", render);
  statusFilter.addEventListener("change", render);

  /* =========================================================
     DYNAMIC PRICING FIELDS
     ========================================================= */
  function tierRowHTML(kind, values) {
    values = values || {};
    if (kind === "per_day") {
      return (
        '<div class="tier-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center">' +
        '<input class="field-input tier-min" type="number" min="1" placeholder="Min days" value="' +
        (values.min_days ?? "") +
        '" style="width:100px">' +
        '<input class="field-input tier-max" type="number" min="1" placeholder="Max (blank=and up)" value="' +
        (values.max_days ?? "") +
        '" style="width:150px">' +
        '<input class="field-input tier-rate" type="number" min="0" step="0.01" placeholder="₱/day" value="' +
        (values.rate_per_day ?? "") +
        '" style="width:110px">' +
        '<button type="button" class="btn btn-outline btn-remove-tier" style="padding:8px">✕</button>' +
        "</div>"
      );
    }
    // per_photo
    return (
      '<div class="tier-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center">' +
      '<input class="field-input tier-min" type="number" min="1" placeholder="Min qty" value="' +
      (values.min_qty ?? "") +
      '" style="width:100px">' +
      '<input class="field-input tier-max" type="number" min="1" placeholder="Max (blank=and up)" value="' +
      (values.max_qty ?? "") +
      '" style="width:150px">' +
      '<input class="field-input tier-rate" type="number" min="0" step="0.01" placeholder="₱/photo" value="' +
      (values.price_per_photo ?? "") +
      '" style="width:110px">' +
      '<button type="button" class="btn btn-outline btn-remove-tier" style="padding:8px">✕</button>' +
      "</div>"
    );
  }

  function renderPricingFields(pricingType, tiers) {
    const wrap = $("pricingFieldsWrap");
    if (pricingType === "per_day_and_hour") {
      const v = tiers && !Array.isArray(tiers) ? tiers : {};
      wrap.innerHTML =
        '<div style="display:flex;gap:8px;align-items:center">' +
        '<div class="form-group"><label>Flat covers (days)</label><input class="field-input" id="flatDays" type="number" min="1" value="' +
        (v.flat_days ?? 2) +
        '" style="width:100px"></div>' +
        '<div class="form-group"><label>Flat Price (₱)</label><input class="field-input" id="flatPrice" type="number" min="0" step="0.01" value="' +
        (v.flat_price ?? "") +
        '" style="width:120px"></div>' +
        '<div class="form-group"><label>Extra Hour Rate (₱)</label><input class="field-input" id="extraHourRate" type="number" min="0" step="0.01" value="' +
        (v.extra_hour_rate ?? "") +
        '" style="width:130px"></div>' +
        "</div>";
      return;
    }
    const kind = pricingType === "per_photo" ? "per_photo" : "per_day";
    const rows = Array.isArray(tiers) && tiers.length ? tiers : [{}];
    wrap.innerHTML =
      '<div id="tierRows">' +
      rows
        .map((t) =>
          tierRowHTML(
            kind,
            kind === "per_day"
              ? t
              : {
                  min_qty: t.min_qty,
                  max_qty: t.max_qty,
                  price_per_photo: t.price_per_photo,
                },
          ),
        )
        .join("") +
      "</div>" +
      '<button type="button" class="btn btn-outline" id="addTierBtn" style="margin-top:4px">+ Add Tier</button>';
    $("addTierBtn").addEventListener("click", function () {
      $("tierRows").insertAdjacentHTML("beforeend", tierRowHTML(kind, {}));
    });
    wrap.addEventListener("click", function (e) {
      if (e.target.classList.contains("btn-remove-tier")) {
        const rows = $("tierRows");
        if (rows.children.length > 1) e.target.closest(".tier-row").remove();
      }
    });
  }

  function collectPricing() {
    const pricingType = $("camPricingType").value;
    if (pricingType === "per_day_and_hour") {
      return {
        flat_days: +$("flatDays").value,
        flat_price: +$("flatPrice").value,
        extra_hour_rate: +$("extraHourRate").value,
      };
    }
    const rows = Array.from(document.querySelectorAll("#tierRows .tier-row"));
    if (pricingType === "per_photo") {
      return rows.map((r) => ({
        min_qty: +r.querySelector(".tier-min").value,
        max_qty:
          r.querySelector(".tier-max").value === ""
            ? null
            : +r.querySelector(".tier-max").value,
        price_per_photo: +r.querySelector(".tier-rate").value,
      }));
    }
    return rows.map((r) => ({
      min_days: +r.querySelector(".tier-min").value,
      max_days:
        r.querySelector(".tier-max").value === ""
          ? null
          : +r.querySelector(".tier-max").value,
      rate_per_day: +r.querySelector(".tier-rate").value,
    }));
  }

  $("camPricingType").addEventListener("change", function () {
    renderPricingFields(this.value, null);
  });

  /* =========================================================
     INCLUSIONS
     ========================================================= */
  function inclusionRowHTML(text) {
    return (
      '<div class="inclusion-row" style="display:flex;gap:8px;margin-bottom:6px">' +
      '<input class="field-input inclusion-text" type="text" value="' +
      esc(text || "") +
      '" placeholder="e.g. SD Card">' +
      '<button type="button" class="btn btn-outline btn-remove-inclusion" style="padding:8px">✕</button>' +
      "</div>"
    );
  }
  function renderInclusions(list) {
    const wrap = $("inclusionsWrap");
    const items = list && list.length ? list : [""];
    wrap.innerHTML = items.map(inclusionRowHTML).join("");
  }
  $("addInclusionBtn").addEventListener("click", function () {
    $("inclusionsWrap").insertAdjacentHTML("beforeend", inclusionRowHTML(""));
  });
  $("inclusionsWrap").addEventListener("click", function (e) {
    if (e.target.classList.contains("btn-remove-inclusion")) {
      e.target.closest(".inclusion-row").remove();
    }
  });
  function collectInclusions() {
    return Array.from(
      document.querySelectorAll("#inclusionsWrap .inclusion-text"),
    )
      .map((i) => i.value.trim())
      .filter((v) => v !== "");
  }

  /* =========================================================
     MODAL: open/close, type switch, image preview
     ========================================================= */
  function openOverlay(overlay) {
    overlay.classList.add("open");
    document.body.classList.add("modal-open");
  }
  function closeOverlays() {
    equipmentModal.classList.remove("open");
    deleteModal.classList.remove("open");
    document.body.classList.remove("modal-open");
    editType = null;
    editId = null;
    toggleTarget = null;
    formError.style.display = "none";
  }

  function toggleTypeFields() {
    const isCamera = $("eqType").value === "camera";
    $("cameraFields").hidden = !isCamera;
    $("accessoryFields").hidden = isCamera;
  }
  $("eqType").addEventListener("change", toggleTypeFields);

  $("camImage").addEventListener("change", function () {
    const f = this.files[0];
    const prev = $("camImagePreview");
    if (f) {
      prev.src = URL.createObjectURL(f);
      prev.hidden = false;
      prev.style.cssText =
        "max-width:200px;max-height:140px;border-radius:8px;margin-top:8px;display:block";
    }
  });

  function openAddModal() {
    editType = null;
    editId = null;
    existingImageUrl = null;
    form.reset();
    $("eqType").value = "camera";
    $("eqType").disabled = false;
    toggleTypeFields();
    $("camImageRequired").style.display = "inline";
    $("camImagePreview").hidden = true;
    $("camPricingType").value = "per_day";
    renderPricingFields("per_day", null);
    renderInclusions([]);
    $("eqActive").checked = true;
    modalTitle.textContent = "Add New Equipment";
    submitBtn.textContent = "Add Equipment";
    openOverlay(equipmentModal);
  }

  function openEditModal(type, id) {
    editType = type;
    editId = id;
    form.reset();
    $("eqType").value = type;
    $("eqType").disabled = true; // type can't change on edit
    toggleTypeFields();

    if (type === "camera") {
      const cam = cameras.find((c) => c.camera_id == id);
      if (!cam) return;
      existingImageUrl = cam.image_url;
      $("camName").value = cam.name;
      $("camSubtitle").value = cam.subtitle || "";
      $("camImageRequired").style.display = "none";
      const prev = $("camImagePreview");
      if (cam.image_url) {
        prev.src = resolveImg(cam.image_url);
        prev.hidden = false;
        prev.style.cssText =
          "max-width:200px;max-height:140px;border-radius:8px;margin-top:8px;display:block";
      } else {
        prev.hidden = true;
      }
      $("camPricingType").value = cam.pricing_type;
      renderPricingFields(
        cam.pricing_type,
        cam.pricing_type === "per_day_and_hour"
          ? cam.flat_hourly
          : cam.pricing_type === "per_photo"
            ? cam.photo_tiers
            : cam.rate_tiers,
      );
      renderInclusions(cam.inclusions || []);
      $("eqActive").checked = cam.is_active == 1;
    } else {
      const acc = accessories.find((a) => a.accessory_id == id);
      if (!acc) return;
      $("accName").value = acc.name;
      $("accPrice").value = acc.price;
      $("eqActive").checked = acc.is_active == 1;
    }

    modalTitle.textContent =
      "Edit " + (type === "camera" ? "Camera" : "Accessory");
    submitBtn.textContent = "Save Changes";
    openOverlay(equipmentModal);
  }

  $("addBtn").addEventListener("click", openAddModal);

  grid.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-action]");
    if (!btn) return;
    const type = btn.dataset.type,
      id = btn.dataset.id;
    if (btn.dataset.action === "edit") openEditModal(type, id);
    if (btn.dataset.action === "toggle") {
      toggleTarget = {
        type,
        id,
        name: btn.dataset.name,
        nextActive: btn.dataset.active == "1" ? 0 : 1,
      };
      $("deleteTitle").textContent =
        (toggleTarget.nextActive ? "Activate" : "Deactivate") + " this item?";
      $("deleteText").innerHTML = toggleTarget.nextActive
        ? "<strong>" +
          esc(toggleTarget.name) +
          "</strong> will become visible again on Services and Booking."
        : "<strong>" +
          esc(toggleTarget.name) +
          "</strong> will be hidden from Services and Booking. Past bookings that used it are not affected.";
      $("confirmDeleteBtn").textContent = toggleTarget.nextActive
        ? "Activate"
        : "Deactivate";
      $("confirmDeleteBtn").className =
        "btn " + (toggleTarget.nextActive ? "btn-primary" : "btn-danger-solid");
      openOverlay(deleteModal);
    }
  });

  document.querySelectorAll(".modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay || e.target.closest("[data-close]"))
        closeOverlays();
    });
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeOverlays();
  });

  $("confirmDeleteBtn").addEventListener("click", function () {
    if (!toggleTarget) return;
    const fd = new FormData();
    fd.append("type", toggleTarget.type);
    fd.append("id", toggleTarget.id);
    fd.append("is_active", toggleTarget.nextActive);
    fetch("../../api/admin/admin-equipment-toggle.php", { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        closeOverlays();
        if (data.success) loadEquipment();
      });
  });

  /* =========================================================
     SUBMIT (create/update)
     ========================================================= */
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    formError.style.display = "none";
    const type = $("eqType").value;
    const fd = new FormData();
    fd.append("is_active", $("eqActive").checked ? "1" : "0");

    let url;
    if (type === "camera") {
      const name = $("camName").value.trim();
      if (!name) {
        formError.textContent = "Please enter the camera name.";
        formError.style.display = "block";
        return;
      }
      if (!editId && !$("camImage").files[0]) {
        formError.textContent = "A photo is required when adding a new camera.";
        formError.style.display = "block";
        return;
      }
      fd.append("name", name);
      fd.append("subtitle", $("camSubtitle").value.trim());
      fd.append("pricing_type", $("camPricingType").value);
      fd.append("tiers_json", JSON.stringify(collectPricing()));
      fd.append("inclusions_json", JSON.stringify(collectInclusions()));
      if ($("camImage").files[0]) fd.append("image", $("camImage").files[0]);
      if (editId) fd.append("camera_id", editId);
      url = "../../api/admin/admin-camera-upsert.php";
    } else {
      const name = $("accName").value.trim();
      if (!name) {
        formError.textContent = "Please enter the accessory name.";
        formError.style.display = "block";
        return;
      }
      fd.append("name", name);
      fd.append("price", $("accPrice").value);
      if (editId) fd.append("accessory_id", editId);
      url = "../../api/admin/admin-accessory-upsert.php";
    }

    submitBtn.disabled = true;
    fetch(url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        submitBtn.disabled = false;
        if (!data.success) {
          formError.textContent =
            data.error || "Could not save. Please try again.";
          formError.style.display = "block";
          return;
        }
        closeOverlays();
        loadEquipment();
      })
      .catch(() => {
        submitBtn.disabled = false;
        formError.textContent = "Network error. Please try again.";
        formError.style.display = "block";
      });
  });

  loadEquipment();
})();
