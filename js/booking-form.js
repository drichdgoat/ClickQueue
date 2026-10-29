/* SnapVenture - booking.php logic (DB-backed via /api/*.php) */
(function () {
  "use strict";

  var $ = function (id) {
    return document.getElementById(id);
  };
  if (!$("bkLayout")) return;

  var MONTHS = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }
  function peso(n) {
    return (
      "\u20B1" +
      Number(n || 0).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }
  function pad(n) {
    return n < 10 ? "0" + n : "" + n;
  }
  function iso(y, m, d) {
    return y + "-" + pad(m + 1) + "-" + pad(d);
  }
  function formatDate(date) {
    var parts = date.split("-");
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
      .toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
  }
  function todayISO() {
    var t = new Date();
    return iso(t.getFullYear(), t.getMonth(), t.getDate());
  }
  function val(id) {
    return $(id).value.trim();
  }
  // image_url from the DB is relative to site root; this page lives in /pages/
  function resolveImg(path) {
    return path ? "../" + path : "";
  }

  /* ---------------- state ---------------- */
  var catalog = null;
  var serviceType = "";
  var selectedPkg = null; // the chosen camera or photobooth object
  var accessoryIds = []; // camera accessories selected
  var addonQty = {}; // photobooth add-ons: addon_id -> quantity
  var addonIds = [];
  var MAX_ADDON_QTY = 10;
  var lastSummary = null;
  var picked = ""; // selected date (yyyy-mm-dd)
  var bookedRanges = []; // occupied date ranges for the current package + visible month
  var now = new Date(),
    vy = now.getFullYear(),
    vm = now.getMonth();
  var paymentMethod = null;
  var proofFile = null;
  var TIMED_BASE_HOURS = 3; // Snap Lite / Snap Plus base service duration
  var autoEndOn = false; // End Time is currently auto-calculated (read-only)
  var bookingSubmitted = false; // set after a successful submission so the form can't be sent twice

  var METHODS = [
    {
      id: "Bank Transfer",
      desc: "Transfer to our bank account",
      proof: true,
      details: [
        ["Bank Name", "Philippine National Bank"],
        ["Account Name", "SnapVenture Services"],
        ["Account Number", "1234-5678-9012"],
      ],
    },
    {
      id: "GCash / PayMaya",
      desc: "Mobile wallet payment",
      proof: true,
      details: [
        ["Mobile Number", "0912 345 6789"],
        ["Account Name", "SnapVenture Services"],
      ],
    },
    {
      id: "Over-the-Counter",
      desc: "Pay at our office or partner locations",
      proof: false,
      details: [["Office Hours", "Mon-Sat, 9AM-6PM"]],
    },
  ];

  /* =========================================================
     PRICE DISPLAY HELPERS (mirrors services-carousel.js)
     ========================================================= */
  function cameraPriceHTML(cam) {
    if (cam.pricing_type === "per_day" && cam.rate_tiers) {
      return cam.rate_tiers
        .map(function (t) {
          var range = t.max_days
            ? t.min_days + "-" + t.max_days + " days"
            : t.min_days + "+ days";
          return (
            peso(t.rate_per_day).replace(".00", "") + "/day (" + range + ")"
          );
        })
        .join(" \u2022 ");
    }
    if (cam.pricing_type === "per_day_and_hour" && cam.flat_hourly) {
      var f = cam.flat_hourly;
      return (
        peso(f.flat_price).replace(".00", "") +
        " flat (1-" +
        f.flat_days +
        " days) + " +
        peso(f.extra_hour_rate).replace(".00", "") +
        "/extra hr"
      );
    }
    if (cam.pricing_type === "per_photo" && cam.photo_tiers) {
      return cam.photo_tiers
        .map(function (t) {
          var range = t.max_qty
            ? t.min_qty + "-" + t.max_qty + " pcs"
            : t.min_qty + "+ pcs";
          return (
            peso(t.price_per_photo).replace(".00", "") +
            "/photo (" +
            range +
            ")"
          );
        })
        .join(" \u2022 ");
    }
    return "";
  }

  /* =========================================================
     STEP 2: service type + package picker
     ========================================================= */
  function renderPackageGrid() {
    var grid = $("packageGrid");
    if (!serviceType) {
      grid.innerHTML = "";
      return;
    }
    var items =
      serviceType === "camera_rental" ? catalog.cameras : catalog.photobooth;

    grid.innerHTML = items
      .map(function (item) {
        var isCamera = serviceType === "camera_rental";
        var id = isCamera ? item.camera_id : item.photobooth_id;
        var img =
          isCamera && item.image_url
            ? '<img src="' +
              esc(resolveImg(item.image_url)) +
              '" alt="" onerror="this.style.display=\'none\'">'
            : "";
        var priceText = isCamera ? cameraPriceHTML(item) : peso(item.price);
        var isSel =
          selectedPkg &&
          (isCamera ? selectedPkg.camera_id : selectedPkg.photobooth_id) === id;
        return (
          '<div class="pkg-pick-card' +
          (isSel ? " is-selected" : "") +
          '" data-id="' +
          id +
          '">' +
          img +
          "<b>" +
          esc(item.name) +
          "</b>" +
          "<small>" +
          priceText +
          "</small>" +
          "</div>"
        );
      })
      .join("");
  }

  $("serviceType").addEventListener("change", function () {
    serviceType = this.value;
    selectedPkg = null;
    accessoryIds = [];
    addonIds = [];
    addonQty = {};
    picked = "";
    renderPackageGrid();
    renderDetails();
    loadAvailabilityThenDraw();
    refresh();
  });

  $("packageGrid").addEventListener("click", function (e) {
    var card = e.target.closest(".pkg-pick-card");
    if (!card) return;
    var id = parseInt(card.getAttribute("data-id"), 10);
    var items =
      serviceType === "camera_rental" ? catalog.cameras : catalog.photobooth;
    var prevPkg = selectedPkg;
    selectedPkg = items.find(function (i) {
      return (
        (serviceType === "camera_rental" ? i.camera_id : i.photobooth_id) === id
      );
    });
    if (prevPkg !== selectedPkg) addonQty = {}; // add-ons belong to one package only
    accessoryIds = [];
    addonIds = [];
    picked = "";
    renderPackageGrid();
    renderDetails();
    loadAvailabilityThenDraw();
    refresh();
  });

  /* =========================================================
     STEP 3: rental details (contextual per pricing_type)
     ========================================================= */
  function renderDetails() {
    var body = $("detailsBody");
    if (!selectedPkg) {
      body.innerHTML = "";
      return;
    }

    if (serviceType === "camera_rental") {
      var pt = selectedPkg.pricing_type;
      var html = "";

      if (pt === "per_day") {
        var minDays = Math.min.apply(
          null,
          selectedPkg.rate_tiers.map(function (t) {
            return t.min_days;
          }),
        );
        html +=
          '<div class="bk-field"><label for="rentalDays">Rental Duration (days)</label>' +
          '<div class="bk-input"><input id="rentalDays" type="number" min="' +
          minDays +
          '" value="' +
          minDays +
          '"></div></div>';
      } else if (pt === "per_day_and_hour") {
        var fd = selectedPkg.flat_hourly.flat_days;
        html +=
          '<div class="detail-row">' +
          '<div class="bk-field"><label for="rentalDays">Rental Days</label>' +
          '<div class="bk-input"><input id="rentalDays" type="number" min="1" value="' +
          fd +
          '"></div></div>' +
          '<div class="bk-field"><label for="extraHours">Additional Hours</label>' +
          '<div class="bk-input"><input id="extraHours" type="number" min="0" value="0"></div></div>' +
          "</div>";
      } else if (pt === "per_photo") {
        var minQty = Math.min.apply(
          null,
          selectedPkg.photo_tiers.map(function (t) {
            return t.min_qty;
          }),
        );
        html +=
          '<div class="bk-field"><label for="photoQty">Number of Photos</label>' +
          '<div class="bk-input"><input id="photoQty" type="number" min="' +
          minQty +
          '" value="' +
          minQty +
          '"></div></div>';
      }

      var accessories = catalog.camera_accessories || [];
      var filmOpts = accessories.filter(function (a) {
        return a.name.indexOf("Film") !== -1;
      });
      var otherOpts = accessories.filter(function (a) {
        return a.name.indexOf("Film") === -1;
      });

      if (filmOpts.length) {
        html += '<div class="accessory-group"><h4>Film</h4>';
        html +=
          '<label class="accessory-option"><input type="radio" name="filmChoice" value="" checked>None<span class="opt-price"></span></label>';
        filmOpts.forEach(function (a) {
          html +=
            '<label class="accessory-option"><input type="radio" name="filmChoice" value="' +
            a.accessory_id +
            '">' +
            esc(a.name) +
            '<span class="opt-price">' +
            peso(a.price) +
            "</span></label>";
        });
        html += "</div>";
      }
      if (otherOpts.length) {
        html += '<div class="accessory-group"><h4>Other Accessories</h4>';
        otherOpts.forEach(function (a) {
          html +=
            '<label class="accessory-option"><input type="checkbox" class="accCheck" value="' +
            a.accessory_id +
            '">' +
            esc(a.name) +
            '<span class="opt-price">' +
            peso(a.price) +
            "</span></label>";
        });
        html += "</div>";
      }

      body.innerHTML = html;
      bindDetailInputs();
    } else {
      var addons = (catalog.photobooth_addons || []).filter(function (a) {
        return !(isLuxe() && isHoursAddon(a)); // Snap Luxe has no Additional Hours
      });
      var html2 = '<p class="addon-hint">Optional add-ons for your event:</p>';
      addons.forEach(function (a) {
        var info = addonInfo(a.name);
        html2 +=
          '<div class="addon-row" data-id="' +
          a.addon_id +
          '">' +
          '<div class="addon-info"><b>' +
          esc(info.label) +
          "</b>" +
          "<small>" +
          peso(a.price) +
          " " +
          esc(info.per) +
          "</small></div>" +
          '<div class="addon-controls">' +
          '<button type="button" class="qty-btn" data-dir="-1" aria-label="Decrease">&minus;</button>' +
          '<span class="qty-val">0</span>' +
          '<button type="button" class="qty-btn" data-dir="1" aria-label="Increase">+</button>' +
          "</div>" +
          '<span class="addon-total"></span>' +
          "</div>";
      });
      body.innerHTML = html2;
      body.querySelectorAll(".addon-row").forEach(updateAddonRow);
    }
  }

  function bindDetailInputs() {
    ["rentalDays", "extraHours", "photoQty"].forEach(function (id) {
      var el = $(id);
      if (el) el.addEventListener("input", onDetailsChanged);
    });
    document.querySelectorAll('input[name="filmChoice"]').forEach(function (r) {
      r.addEventListener("change", onDetailsChanged);
    });
    document.querySelectorAll(".accCheck, .addonCheck").forEach(function (c) {
      c.addEventListener("change", onDetailsChanged);
    });
  }

  function onDetailsChanged() {
    if (serviceType === "camera_rental") {
      accessoryIds = [];
      var film = document.querySelector('input[name="filmChoice"]:checked');
      if (film && film.value) accessoryIds.push(parseInt(film.value, 10));
      document.querySelectorAll(".accCheck:checked").forEach(function (c) {
        accessoryIds.push(parseInt(c.value, 10));
      });
      picked = ""; // duration changed -> re-check date availability
      loadAvailabilityThenDraw();
    }
    // photobooth add-ons don't affect availability, so the chosen date stays
    refresh();
  }

  /* ---- photobooth time rules ----
     Snap Lite / Snap Plus: End Time = Start Time + 3 hours + Additional Hours (read-only).
     Snap Luxe and everything else: unchanged (both times are entered by the customer). */
  function isLuxe() {
    return (
      serviceType === "photobooth" &&
      !!selectedPkg &&
      /luxe/i.test(selectedPkg.name)
    );
  }
  function isTimedPhotobooth() {
    return (
      serviceType === "photobooth" &&
      !!selectedPkg &&
      !isLuxe() &&
      /\b(lite|plus)\b/i.test(selectedPkg.name)
    );
  }
  function isHoursAddon(a) {
    return /hour/i.test(a.name);
  }
  function extraHoursChosen() {
    var hrs = 0;
    (catalog.photobooth_addons || []).forEach(function (a) {
      if (isHoursAddon(a))
        hrs += (addonQty[a.addon_id] || 0) * addonInfo(a.name).size;
    });
    return hrs;
  }
  function pad2(n) {
    return (n < 10 ? "0" : "") + n;
  }
  function fmtTime(hhmm) {
    var p = hhmm.split(":"),
      h = +p[0];
    return (h % 12 || 12) + ":" + p[1] + (h < 12 ? " AM" : " PM");
  }
  // Snap Lite / Plus / Luxe all need a Start Time
  function needsStart() {
    return isLuxe() || isTimedPhotobooth();
  }
  function syncAutoEnd() {
    var endEl = $("endTime"),
      field = endEl.closest(".bk-field"),
      note = $("endAutoNote"),
      startEl = $("startTime");
    startEl.required = needsStart();
    document
      .querySelector('label[for="startTime"]')
      .classList.toggle("is-required", needsStart());

    // Snap Luxe is a whole-duration package: no End Time field at all
    field.hidden = isLuxe();

    if (!isTimedPhotobooth()) {
      if (autoEndOn || isLuxe()) endEl.value = ""; // never keep a stale or hidden end time
      endEl.readOnly = false;
      endEl.classList.remove("is-auto");
      note.hidden = true;
      autoEndOn = false;
      return;
    }
    autoEndOn = true;
    endEl.readOnly = true;
    endEl.classList.add("is-auto");
    note.hidden = false;
    var st = startEl.value;
    if (!st) {
      endEl.value = "";
      return;
    }
    var p = st.split(":");
    var mins =
      (+p[0] * 60 + +p[1] + (TIMED_BASE_HOURS + extraHoursChosen()) * 60) %
      1440;
    endEl.value = pad2(Math.floor(mins / 60)) + ":" + pad2(mins % 60);
  }

  function addonInfo(name) {
    var m = /\((\d+)\s*pcs\)/i.exec(name);
    if (m)
      return {
        label: name.replace(/\s*\(\d+\s*pcs\)/i, ""),
        size: +m[1],
        unit: "pcs",
        per: "per " + m[1] + " pcs",
      };
    if (/hour/i.test(name))
      return { label: name, size: 1, unit: "hr", per: "per hour" };
    return { label: name, size: 1, unit: "", per: "each" };
  }
  function qtyText(info, q) {
    if (!q) return "0";
    var n = q * info.size;
    if (info.unit === "hr") return n + (n === 1 ? " hr" : " hrs");
    return n + (info.unit ? " " + info.unit : "");
  }
  function updateAddonRow(row) {
    var id = row.getAttribute("data-id");
    var a = catalog.photobooth_addons.find(function (x) {
      return String(x.addon_id) === id;
    });
    var q = addonQty[id] || 0;
    row.querySelector(".qty-val").textContent = qtyText(addonInfo(a.name), q);
    row.querySelector(".addon-total").textContent = q ? peso(a.price * q) : "";
    row.classList.toggle("is-on", q > 0);
    row.querySelector('[data-dir="-1"]').disabled = q === 0;
    row.querySelector('[data-dir="1"]').disabled = q >= MAX_ADDON_QTY;
  }
  $("detailsBody").addEventListener("click", function (e) {
    var btn = e.target.closest(".qty-btn");
    if (!btn) return;
    var row = btn.closest(".addon-row");
    var id = row.getAttribute("data-id");
    var q = (addonQty[id] || 0) + parseInt(btn.getAttribute("data-dir"), 10);
    q = Math.max(0, Math.min(MAX_ADDON_QTY, q));
    if (q) addonQty[id] = q;
    else delete addonQty[id];
    updateAddonRow(row);
    onDetailsChanged();
  });

  function detailsComplete() {
    if (!selectedPkg) return false;
    if (serviceType === "photobooth") return true;
    var pt = selectedPkg.pricing_type;
    if (pt === "per_day") return $("rentalDays") && +$("rentalDays").value > 0;
    if (pt === "per_day_and_hour")
      return $("rentalDays") && +$("rentalDays").value > 0;
    if (pt === "per_photo") return $("photoQty") && +$("photoQty").value > 0;
    return false;
  }

  /* =========================================================
     STEP 4: calendar (real availability from the DB)
     ========================================================= */
  function currentPackageId() {
    if (!selectedPkg) return null;
    return serviceType === "camera_rental"
      ? selectedPkg.camera_id
      : selectedPkg.photobooth_id;
  }

  function rentalDurationDays() {
    if (serviceType !== "camera_rental" || !selectedPkg || selectedPkg.pricing_type === "per_photo") {
      return 1;
    }
    var days = $("rentalDays");
    return days ? Math.max(1, parseInt(days.value, 10) || 1) : 1;
  }

  function dateAfterDays(date, days) {
    var parts = date.split("-");
    var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    d.setDate(d.getDate() + days - 1);
    return iso(d.getFullYear(), d.getMonth(), d.getDate());
  }

  function updateTimeLabels() {
    var startLabel = $("startTimeLabel");
    var endLabel = $("endTimeLabel");
    var startDate = picked ? formatDate(picked) : "";
    var endDate = picked ? formatDate(dateAfterDays(picked, rentalDurationDays())) : "";
    startLabel.textContent = "Start Time" + (startDate ? " (" + startDate + ")" : "");
    endLabel.textContent = "End Time" + (endDate ? " (" + endDate + ")" : "");
  }

  function isDateRangeAvailable(startDate) {
    var endDate = dateAfterDays(startDate, rentalDurationDays());
    return !bookedRanges.some(function (range) {
      return range.start_date <= endDate && range.end_date >= startDate;
    });
  }

  function loadAvailabilityThenDraw() {
    var pid = currentPackageId();
    if (!pid) {
      bookedRanges = [];
      drawCal();
      return;
    }
    fetch(
      "../api/public/availability.php?service_type=" +
        serviceType +
        "&package_id=" +
        pid +
        "&days=" +
        rentalDurationDays() +
        "&year=" +
        vy +
        "&month=" +
        (vm + 1),
    )
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        bookedRanges = data.booked_ranges || [];
        drawCal();
      })
      .catch(function () {
        bookedRanges = [];
        drawCal();
      });
  }

  function drawCal() {
    var first = new Date(vy, vm, 1).getDay(),
      days = new Date(vy, vm + 1, 0).getDate();
    var t = todayISO(),
      h = "",
      i;
    $("calTitle").textContent = MONTHS[vm] + " " + vy;
    for (i = 0; i < first; i++) h += "<span></span>";
    for (i = 1; i <= days; i++) {
      var s = iso(vy, vm, i);
      var booked = !isDateRangeAvailable(s);
      var past = s < t;
      var c =
        "bk-day" +
        (booked ? " is-booked" : past ? "" : "") +
        (s === picked ? " is-selected" : "");
      h +=
        '<button type="button" class="' +
        c +
        '" data-date="' +
        s +
        '"' +
        (booked || past ? " disabled" : "") +
        ">" +
        i +
        "</button>";
    }
    $("calGrid").innerHTML = h;
  }

  $("calPrev").addEventListener("click", function () {
    vm--;
    if (vm < 0) {
      vm = 11;
      vy--;
    }
    loadAvailabilityThenDraw();
  });
  $("calNext").addEventListener("click", function () {
    vm++;
    if (vm > 11) {
      vm = 0;
      vy++;
    }
    loadAvailabilityThenDraw();
  });
  $("calGrid").addEventListener("click", function (e) {
    var b = e.target.closest(".bk-day");
    if (!b || b.disabled) return;
    picked = b.getAttribute("data-date");
    drawCal();
    refresh();
  });

  /* =========================================================
     LIVE PRICE SUMMARY (authoritative -- from api/calculate.php)
     ========================================================= */
  var calcTimer = null;
  function requestCalculation() {
    clearTimeout(calcTimer);
    calcTimer = setTimeout(function () {
      if (!selectedPkg || !detailsComplete()) {
        lastSummary = null;
        renderSummary();
        return;
      }

      var body = { service_type: serviceType };
      if (serviceType === "camera_rental") {
        body.camera_id = selectedPkg.camera_id;
        if ($("rentalDays")) body.days = +$("rentalDays").value;
        if ($("extraHours")) body.extra_hours = +$("extraHours").value;
        if ($("photoQty")) body.photo_qty = +$("photoQty").value;
        body.accessory_ids = accessoryIds;
      } else {
        body.photobooth_id = selectedPkg.photobooth_id;
        body.addon_qty = addonQty;
      }

      fetch("../api/public/calculate.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          lastSummary = data.success ? data.summary : null;
          renderSummary();
        })
        .catch(function () {
          lastSummary = null;
          renderSummary();
        });
    }, 250);
  }

  function row(icon, label, value) {
    return (
      '<div class="bk-row"><svg class="ic"><use href="#i-' +
      icon +
      '"/></svg><div><small>' +
      esc(label) +
      "</small><b>" +
      esc(value) +
      "</b></div></div>"
    );
  }

  function renderSummary() {
    var h = "",
      n = val("fullName");
    if (n) h += row("user", "Customer", n);
    if (serviceType)
      h += row(
        "camera",
        "Service",
        serviceType === "camera_rental" ? "Camera Rental" : "Photobooth",
      );
    if (selectedPkg) h += row("camera", "Package", selectedPkg.name);
        if (picked) {
      var duration = rentalDurationDays();
      var lastDate = dateAfterDays(picked, duration);
      h += row(
        "calendar",
        duration > 1 ? "Rental Dates" : "Event Date",
        duration > 1 ? formatDate(picked) + " – " + formatDate(lastDate) : formatDate(picked),
      );
      if (serviceType === "camera_rental") {
        var startTime = $("startTime").value;
        var endTime = $("endTime").value;
        if (startTime || endTime) {
          var timeLabel = duration > 1
            ? (startTime ? startTime + " (" + formatDate(picked) + ")" : "") +
              (startTime && endTime ? " – " : "") +
              (endTime ? endTime + " (" + formatDate(lastDate) + ")" : "")
            : [startTime, endTime].filter(Boolean).join(" – ");
          h += row("clock", "Time", timeLabel);
        }
      }
    }
    // photobooth: Snap Luxe shows the start time only, Lite/Plus show the range
    if (isLuxe() && $("startTime").value)
      h += row("clock", "Start Time", fmtTime($("startTime").value));
    if (isTimedPhotobooth() && $("startTime").value && $("endTime").value)
      h += row(
        "clock",
        "Time",
        fmtTime($("startTime").value) +
          " \u2013 " +
          fmtTime($("endTime").value),
      );
    $("sumRows").innerHTML = h;
    $("sumEmpty").hidden = !!h;

    var pr = "";
    if (lastSummary) {
      pr = '<hr class="bk-line">';
      pr +=
        '<div class="bk-price"><span>Package Subtotal</span><b>' +
        peso(lastSummary.package_price) +
        "</b></div>";
      if (lastSummary.addons_total > 0) {
        pr +=
          '<div class="bk-price"><span>Add-ons / Accessories</span><b>' +
          peso(lastSummary.addons_total) +
          "</b></div>";
      }
      pr +=
        '<div class="bk-total"><span>Grand Total</span><b>' +
        peso(lastSummary.grand_total) +
        "</b></div>";
      pr +=
        '<div class="bk-due"><span>Amount to Pay Now</span><b>' +
        peso(lastSummary.amount_due_now) +
        "</b></div>";
      pr +=
        '<div class="bk-remaining"><span>Remaining Balance</span><b>' +
        peso(lastSummary.remaining_balance) +
        "</b></div>";
    }
    $("sumPricing").innerHTML = pr;

    if (serviceType === "camera_rental") {
      $("noticeTitle").textContent = "50% Down Payment Required";
      $("noticeText").textContent =
        "A 50% down payment of the total rental cost is required to confirm your booking. The remaining 50% is due on pickup/event day.";
    } else if (serviceType === "photobooth") {
      $("noticeTitle").textContent = "\u20B1300 Reservation Fee";
      $("noticeText").textContent =
        "A flat \u20B1300 reservation fee secures your photobooth date. The remaining balance (package price + add-ons) is due on your event day.";
    }
  }

  /* =========================================================
     STEP 6: payment method + proof upload
     ========================================================= */
  function renderMethods() {
    $("methods").innerHTML = METHODS.map(function (m) {
      return (
        '<label class="method" data-id="' +
        esc(m.id) +
        '"><input type="radio" name="payMethod" value="' +
        esc(m.id) +
        '">' +
        '<span class="method-text"><b>' +
        esc(m.id) +
        "</b><small>" +
        esc(m.desc) +
        "</small></span></label>"
      );
    }).join("");
  }
  function renderInstructions() {
    var box = $("instructions");
    if (!paymentMethod) {
      box.hidden = true;
      return;
    }
    var h = "";
    paymentMethod.details.forEach(function (d) {
      h += "<p><b>" + esc(d[0]) + ":</b> " + esc(d[1]) + "</p>";
    });
    box.innerHTML = h;
    box.hidden = false;
    $("proofField").style.display = paymentMethod.proof ? "" : "none";
  }
  $("methods").addEventListener("change", function (e) {
    if (e.target.name !== "payMethod") return;
    paymentMethod =
      METHODS.find(function (m) {
        return m.id === e.target.value;
      }) || null;
    document.querySelectorAll(".method").forEach(function (el) {
      el.classList.toggle(
        "is-selected",
        paymentMethod && el.getAttribute("data-id") === paymentMethod.id,
      );
    });
    renderInstructions();
    refresh();
  });

  function drawDropzone() {
    $("dropzone").innerHTML = proofFile
      ? '<svg class="ic"><use href="#i-check"/></svg><b>File Uploaded</b><span>' +
        esc(proofFile.name) +
        "</span>"
      : '<svg class="ic"><use href="#i-upload"/></svg><b>Click to upload</b><span>PNG, JPG, or PDF (max. 5MB)</span>';
  }
  $("dropzone").addEventListener("click", function () {
    $("proofInput").click();
  });
  $("proofInput").addEventListener("change", function (e) {
    var f = e.target.files[0];
    var err = $("proofError");
    err.hidden = true;
    if (!f) return;
    if (!/\.(png|jpe?g|pdf)$/i.test(f.name)) {
      err.textContent = "Please upload a PNG, JPG, or PDF file.";
      err.hidden = false;
      return;
    }
    if (f.size > 5 * 1024 * 1024) {
      err.textContent = "File must be 5MB or smaller.";
      err.hidden = false;
      return;
    }
    proofFile = f;
    drawDropzone();
    refresh();
  });
  $("agree").addEventListener("change", refresh);

  /* =========================================================
     ORCHESTRATION: lock/unlock cards + enable submit
     ========================================================= */
  [
    "fullName",
    "phone",
    "facebook",
    "email",
    "eventLocation",
    "startTime",
    "endTime",
    "notes",
  ].forEach(function (id) {
    $(id).addEventListener("input", refresh);
  });

  function infoOk() {
    var ph = val("phone");
    return (
      !!val("fullName") &&
      /^[+()\d\s-]+$/.test(ph) &&
      ph.replace(/\D/g, "").length >= 7 &&
      !!val("facebook") &&
      /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val("email"))
    );
  }

  function refresh() {
    function refresh() {
    updateTimeLabels();
    syncAutoEnd();
    syncAutoEnd();
    var s1 = infoOk();
    var s2 = s1 && !!serviceType && !!selectedPkg;
    var s3 = s2 && detailsComplete();
    if (s3) requestCalculation();
    else {
      lastSummary = null;
      renderSummary();
    }

    var s4 = s3 && !!picked;
    var loc = val("eventLocation");
    var st = $("startTime").value,
      en = $("endTime").value;
    // the auto-calculated End Time (Snap Lite / Plus) may fall on the next day, so only check manual times
    var badTime = rentalDurationDays() === 1 && !autoEndOn && !!st && !!en && en <= st;
    $("timeError").hidden = !badTime;
    var s5 = s4 && !!loc && !badTime && (!needsStart() || !!st);

    var proofOk = !paymentMethod || !paymentMethod.proof || !!proofFile;
    var s6 = s5 && !!paymentMethod && proofOk && $("agree").checked;

    $("cardService").classList.toggle("is-locked", !s1);
    $("cardDetails").classList.toggle("is-locked", !s2);
    $("cardDate").classList.toggle("is-locked", !s3);
    $("cardEvent").classList.toggle("is-locked", !s4);
    $("cardPayment").classList.toggle("is-locked", !s5);

    if (s3 && !currentPackageId.__loaded) {
      /* no-op placeholder */
    }

    $("proceedBtn").disabled = bookingSubmitted || !(s6 && lastSummary);
    $("submitError").hidden = true;
  }

  /* =========================================================
     SUBMIT
     ========================================================= */
  $("proceedBtn").addEventListener("click", function () {
    if (this.disabled) return;
    var btn = this,
      err = $("submitError"),
      hint = $("confirmHint");
    btn.disabled = true;
    hint.textContent = "Submitting...";

    var fd = new FormData();
    fd.append("full_name", val("fullName"));
    fd.append("phone", val("phone"));
    fd.append("facebook_name", val("facebook"));
    fd.append("email", val("email"));
    fd.append("service_type", serviceType);
    fd.append("event_date", picked);
    fd.append("start_time", $("startTime").value);
    fd.append("end_time", $("endTime").value);
    fd.append("event_location", val("eventLocation"));
    fd.append("notes", val("notes"));
    fd.append("payment_method", paymentMethod.id);
    fd.append("agree_terms", $("agree").checked ? "1" : "0");
    if (proofFile) fd.append("proof_file", proofFile);

    if (serviceType === "camera_rental") {
      fd.append("camera_id", selectedPkg.camera_id);
      if ($("rentalDays")) fd.append("days", $("rentalDays").value);
      if ($("extraHours")) fd.append("extra_hours", $("extraHours").value);
      if ($("photoQty")) fd.append("photo_qty", $("photoQty").value);
      accessoryIds.forEach(function (id) {
        fd.append("accessory_ids[]", id);
      });
    } else {
      fd.append("photobooth_id", selectedPkg.photobooth_id);
      Object.keys(addonQty).forEach(function (id) {
        fd.append("addon_qty[" + id + "]", addonQty[id]);
      });
    }

    fetch("../api/public/book.php", { method: "POST", body: fd })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.success) {
          err.textContent =
            data.error ||
            "We couldn't save your reservation. Please try again.";
          err.hidden = false;
          hint.textContent = "";
          btn.disabled = false;
          if (data.error && data.error.indexOf("already booked") !== -1) {
            picked = "";
            loadAvailabilityThenDraw();
          }
          return;
        }
        showSuccess(data);
      })
      .catch(function () {
        err.textContent =
          "Network error. Please check your connection and try again.";
        err.hidden = false;
        hint.textContent = "";
        btn.disabled = false;
      });
  });

  function showSuccess(data) {
    $("okRef").textContent = data.reference_no;
    $("okRows").innerHTML =
      row(
        "camera",
        "Service",
        serviceType === "camera_rental" ? "Camera Rental" : "Photobooth",
      ) +
      row(
        "calendar",
        rentalDurationDays() > 1 ? "Rental Dates" : "Event Date",
        rentalDurationDays() > 1
          ? formatDate(data.event_date) + " – " + formatDate(dateAfterDays(data.event_date, rentalDurationDays()))
          : formatDate(data.event_date),
      ) +
      (($("startTime").value || $("endTime").value)
        ? row(
            "clock",
            "Time",
            rentalDurationDays() > 1
              ? ($("startTime").value ? $("startTime").value + " (" + formatDate(data.event_date) + ")" : "") +
                ($("startTime").value && $("endTime").value ? " – " : "") +
                ($("endTime").value ? $("endTime").value + " (" + formatDate(dateAfterDays(data.event_date, rentalDurationDays())) + ")" : "")
              : [$("startTime").value, $("endTime").value].filter(Boolean).join(" – "),
          )
        : "") +
      row("doc", "Amount Paid Now", peso(data.summary.amount_due_now)) +
      row("doc", "Remaining Balance", peso(data.summary.remaining_balance));
    // Show the summary as a centered popup; the booking page stays visible (dimmed) behind it
    bookingSubmitted = true;
    $("proceedBtn").disabled = true;
    $("confirmHint").textContent = "Reservation submitted.";
    $("bkSuccess").hidden = false;
    $("bkSuccessOverlay").hidden = false;
    document.body.classList.add("bk-modal-open");
    $("bkSuccessClose").focus();
  }

  function closeSuccess() {
    $("bkSuccessOverlay").hidden = true;
    document.body.classList.remove("bk-modal-open");
  }
  $("bkSuccessClose").addEventListener("click", closeSuccess);
  $("bkSuccessOverlay").addEventListener("mousedown", function (e) {
    if (e.target === this) closeSuccess();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && !$("bkSuccessOverlay").hidden) closeSuccess();
  });

  /* =========================================================
     BOOT
     ========================================================= */
  function applyUrlPreselect() {
    var p = new URLSearchParams(window.location.search);
    var svc = p.get("service");
    if (svc === "camera_rental" || svc === "photobooth") {
      serviceType = svc;
      $("serviceType").value = svc;
      renderPackageGrid();
      var camId = parseInt(p.get("camera_id"), 10);
      var pbId = parseInt(p.get("photobooth_id"), 10);
      if (svc === "camera_rental" && camId) {
        selectedPkg =
          catalog.cameras.find(function (c) {
            return c.camera_id === camId;
          }) || null;
      } else if (svc === "photobooth" && pbId) {
        selectedPkg =
          catalog.photobooth.find(function (c) {
            return c.photobooth_id === pbId;
          }) || null;
      }
      renderPackageGrid();
      renderDetails();
    }
  }

  renderMethods();
  drawDropzone();

  fetch("../api/public/packages.php")
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      catalog = data;
      applyUrlPreselect();
      loadAvailabilityThenDraw();
      refresh();
    })
    .catch(function () {
      $("submitError").textContent =
        "Could not load packages. Please refresh the page.";
      $("submitError").hidden = false;
    });
})();