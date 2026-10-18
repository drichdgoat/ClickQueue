/* SnapVenture - booking page: locked steps, live summary, big date picker.
   Needs booking-calendar.js loaded first (it provides window.SnapStore). */
(function () {
  "use strict";
  var Store = window.SnapStore;
  if (!Store || !document.getElementById("bkLayout")) return;

  /* ---- EDIT HERE: packages + prices (only Professional is from your mockup) ---- */
  var SERVICE_FEE = 1250;
  var DOWN_PAYMENT = 0.5;
  var MAX_PER_DAY = 1; // bookings of the same package allowed per date
  var SERVICES = {
    "Camera Rental": {
      unit: "day",
      packages: [
        {
          name: "Basic Package",
          price: 9500,
          desc: "Mirrorless camera with kit lens",
        },
        {
          name: "Professional Package",
          price: 17500,
          desc: "Full-frame DSLR with premium lenses",
        },
        {
          name: "Premium Package",
          price: 25000,
          desc: "Cinema-grade camera with full lens set and gimbal",
        },
      ],
    },
    Photobooth: {
      unit: "event",
      packages: [
        {
          name: "Classic Booth",
          price: 6500,
          desc: "Enclosed booth with instant prints",
        },
        {
          name: "Open-Air Booth",
          price: 8500,
          desc: "Open-air setup with props and backdrop",
        },
        {
          name: "Premium Experience",
          price: 12000,
          desc: "Premium booth with unlimited prints and an attendant",
        },
      ],
    },
  };

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
  var $ = function (id) {
    return document.getElementById(id);
  };
  var pad = function (n) {
    return n < 10 ? "0" + n : "" + n;
  };
  var iso = function (y, m, d) {
    return y + "-" + pad(m + 1) + "-" + pad(d);
  };
  var todayISO = function () {
    var t = new Date();
    return iso(t.getFullYear(), t.getMonth(), t.getDate());
  };
  var val = function (id) {
    return $(id).value.trim();
  };
  var peso = function (n) {
    return "\u20B1" + n.toLocaleString("en-US");
  };
  var icon = function (n) {
    return '<svg class="ic"><use href="#i-' + n + '"/></svg>';
  };
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
  function shortDate(s) {
    var p = s.split("-");
    return +p[1] + "/" + +p[2] + "/" + p[0];
  } // 9/24/2026
  function prettyTime(t) {
    var p = t.split(":"),
      h = +p[0];
    return (h % 12 || 12) + ":" + p[1] + " " + (h >= 12 ? "PM" : "AM");
  }

  var now = new Date(),
    vy = now.getFullYear(),
    vm = now.getMonth();
  var picked = "",
    dateOpen = false;

  /* ---------- helpers ---------- */
  function key() {
    return $("serviceType").value + " - " + $("package").value;
  }
  function isFull(k, d) {
    return (
      Store.all().filter(function (b) {
        return b.service === k && b.date === d && b.status !== "cancelled";
      }).length >= MAX_PER_DAY
    );
  }
  function pkg() {
    var t = SERVICES[$("serviceType").value],
      n = $("package").value;
    return t && n
      ? t.packages.filter(function (p) {
          return p.name === n;
        })[0]
      : null;
  }
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
  function fillPkgs() {
    var h = '<option value="" disabled selected>Choose a package...</option>';
    SERVICES[$("serviceType").value].packages.forEach(function (p) {
      h += "<option>" + esc(p.name) + "</option>";
    });
    $("package").innerHTML = h;
  }

  /* ---------- big calendar ---------- */
  function drawCal() {
    var first = new Date(vy, vm, 1).getDay(),
      days = new Date(vy, vm + 1, 0).getDate();
    var t = todayISO(),
      k = key(),
      h = "",
      i;
    $("calTitle").textContent = MONTHS[vm] + " " + vy;
    for (i = 0; i < first; i++) h += "<span></span>";
    for (i = 1; i <= days; i++) {
      var s = iso(vy, vm, i),
        booked = isFull(k, s),
        past = s < t;
      var c =
        "bk-day" +
        (booked ? " is-booked" : past ? " is-past" : "") +
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
  function go(d) {
    vm += d;
    if (vm < 0) {
      vm = 11;
      vy--;
    }
    if (vm > 11) {
      vm = 0;
      vy++;
    }
    drawCal();
  }

  /* ---------- live reservation summary ---------- */
  function row(ic, label, v) {
    return (
      '<div class="bk-row">' +
      icon(ic) +
      "<div><small>" +
      label +
      "</small><b>" +
      esc(v) +
      "</b></div></div>"
    );
  }
  function drawSummary(s1, s2, p) {
    var h = "",
      n = val("fullName"),
      pr = "";
    if (n) h += row("user", "Customer", n);
    if (s1 && $("serviceType").value)
      h += row("camera", "Service", $("serviceType").value);
    if (s2) h += row("calendar", "Package", p.name);
    if (s2 && picked) h += row("calendar", "Date", shortDate(picked));
    $("sumRows").innerHTML = h;
    $("sumEmpty").hidden = !!h;
    if (s2) {
      pr =
        '<hr class="bk-line"><div class="bk-price"><span>Subtotal</span><b>' +
        peso(p.price) +
        "/" +
        SERVICES[$("serviceType").value].unit +
        '</b></div><div class="bk-price fee"><span>Service fee</span><b>' +
        peso(SERVICE_FEE) +
        '</b></div><hr class="bk-line"><div class="bk-total"><span>Total</span><b>' +
        peso(p.price + SERVICE_FEE) +
        "</b></div>";
    }
    $("sumPricing").innerHTML = pr;
  }

  /* ---------- one refresh for everything ---------- */
  function refresh() {
    var s1 = infoOk(),
      p = pkg(),
      s2 = s1 && !!p;
    $("submitError").hidden = true;
    if (picked && p && isFull(key(), picked)) picked = "";
    var s3 = s2 && !!picked;
    var wasOpen = dateOpen;
    dateOpen = s2;
    $("cardService").classList.toggle("is-locked", !s1);
    $("cardDate").classList.toggle("is-locked", !s2);
    $("cardEvent").classList.toggle("is-locked", !s3);
    if (s2 && !wasOpen) drawCal();
    var st = $("startTime").value,
      en = $("endTime").value;
    var bad = !!st && !!en && en <= st;
    $("timeError").hidden = !bad;
    $("proceedBtn").disabled = !(
      s3 &&
      val("eventLocation") &&
      st &&
      en &&
      !bad
    );
    drawSummary(s1, s2, p);
  }

  /* ---------- events ---------- */
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
    $(id).addEventListener("change", refresh);
  });
  $("serviceType").addEventListener("change", function () {
    fillPkgs();
    refresh();
  });
  $("package").addEventListener("change", function () {
    refresh();
    drawCal();
  });
  $("calPrev").addEventListener("click", function () {
    go(-1);
  });
  $("calNext").addEventListener("click", function () {
    go(1);
  });
  $("calGrid").addEventListener("click", function (e) {
    var b = e.target.closest(".bk-day");
    if (!b || b.disabled) return;
    picked = b.getAttribute("data-date");
    drawCal();
    refresh();
  });
  // a booking saved in another tab changes which dates are taken
  window.addEventListener("storage", function () {
    if (dateOpen) {
      drawCal();
      refresh();
    }
  });

  $("proceedBtn").addEventListener("click", function () {
    if (this.disabled) return;
    var p = pkg(),
      t = $("serviceType").value,
      err = $("submitError");
    if (isFull(key(), picked)) {
      picked = "";
      drawCal();
      refresh();
      err.textContent =
        "Sorry, that date was just booked. Please choose another date.";
      err.hidden = false;
      return;
    }
    var total = p.price + SERVICE_FEE;
    var b = {
      id: "SV-" + Date.now().toString(36).toUpperCase(),
      fullName: val("fullName"),
      firstName: val("fullName"), // kept so the admin calendar can read it
      lastName: "",
      phone: val("phone"),
      facebook: val("facebook"),
      email: val("email"),
      serviceType: t,
      package: p.name,
      service: t + " - " + p.name,
      date: picked,
      startTime: $("startTime").value,
      endTime: $("endTime").value,
      time: $("startTime").value,
      location: val("eventLocation"),
      notes: val("notes"),
      subtotal: p.price,
      serviceFee: SERVICE_FEE,
      total: total,
      downPayment: Math.round(total * DOWN_PAYMENT),
      status: "pending",
      createdAt: new Date().toISOString(),
    };
    // Hand the reservation over to payment.html. It is only saved to the
    // booking list when the customer presses "Confirm Reservation" there.
    b.packageDesc = p.desc || "";
    try {
      sessionStorage.setItem("snapventure_draft", JSON.stringify(b));
    } catch (e) {
      err.textContent =
        "We couldn't continue to payment. Please allow browser storage and try again.";
      err.hidden = false;
      return;
    }
    window.location.href = "payment.html";
  });

  fillPkgs();
  refresh();
})();
