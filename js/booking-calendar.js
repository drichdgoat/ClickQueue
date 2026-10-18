/* SnapVenture - booking date picker + live admin calendar
   Both pages share bookings through localStorage (key below).
   Load this file on booking.html and admin/calendar.html. */
(function () {
  "use strict";

  var KEY = "snapventure_bookings";
  // How many bookings of the SAME service can be taken on one date.
  // Set to 2 if you own two units of each package, etc.
  var MAX_PER_SERVICE_PER_DAY = 1;

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

  /* ---------- helpers ---------- */
  function $(id) {
    return document.getElementById(id);
  }
  function pad(n) {
    return n < 10 ? "0" + n : "" + n;
  }
  // m is 0-based (JavaScript months)
  function iso(y, m, d) {
    return y + "-" + pad(m + 1) + "-" + pad(d);
  }
  function todayISO() {
    var t = new Date();
    return iso(t.getFullYear(), t.getMonth(), t.getDate());
  }
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
  function prettyDate(s) {
    var p = s.split("-");
    return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString("en-US", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  }
  function prettyTime(t) {
    if (!t) return "";
    var p = t.split(":");
    var h = +p[0];
    return (h % 12 || 12) + ":" + p[1] + " " + (h >= 12 ? "PM" : "AM");
  }
  function cap(s) {
    return s.charAt(0).toUpperCase() + s.slice(1);
  }
  function setText(id, text) {
    var el = $(id);
    if (el) el.textContent = text;
  }

  /* ---------- storage ---------- */
  var Store = {
    all: function () {
      try {
        return JSON.parse(localStorage.getItem(KEY)) || [];
      } catch (e) {
        return [];
      }
    },
    save: function (list) {
      try {
        localStorage.setItem(KEY, JSON.stringify(list));
        return true;
      } catch (e) {
        return false;
      }
    },
    add: function (booking) {
      var list = Store.all();
      list.push(booking);
      return Store.save(list);
    },
    update: function (id, changes) {
      var list = Store.all().map(function (b) {
        if (b.id !== id) return b;
        for (var k in changes) b[k] = changes[k];
        return b;
      });
      Store.save(list);
    },
    remove: function (id) {
      Store.save(
        Store.all().filter(function (b) {
          return b.id !== id;
        }),
      );
    },
  };
  window.SnapStore = Store; // handy for your other admin pages (dashboard, bookings...)

  // Cancelled bookings free up the date again.
  function isFull(service, date) {
    var taken = Store.all().filter(function (b) {
      return (
        b.service === service && b.date === date && b.status !== "cancelled"
      );
    }).length;
    return taken >= MAX_PER_SERVICE_PER_DAY;
  }

  /* =========================================================
     CUSTOMER SIDE: booking.html
     ========================================================= */
  function initBookingPage() {
    var form = $("bookingForm");
    var picker = $("datePicker");
    if (!form || !picker) return;

    var service = $("service");
    var dateField = $("bookingDate");
    var timeField = $("bookingTime");
    var locationField = $("location");
    var msg = $("bookingMessage");

    var now = new Date();
    var vy = now.getFullYear();
    var vm = now.getMonth();
    var selected = "";

    function renderPicker() {
      var firstWeekday = new Date(vy, vm, 1).getDay();
      var daysInMonth = new Date(vy, vm + 1, 0).getDate();
      var today = todayISO();
      var atCurrentMonth = vy === now.getFullYear() && vm === now.getMonth();

      var h =
        '<div class="dp-head">' +
        '<button type="button" class="dp-nav" data-dir="-1" aria-label="Previous month"' +
        (atCurrentMonth ? " disabled" : "") +
        ">&#8249;</button>" +
        "<strong>" +
        MONTHS[vm] +
        " " +
        vy +
        "</strong>" +
        '<button type="button" class="dp-nav" data-dir="1" aria-label="Next month">&#8250;</button>' +
        "</div>";

      h += '<div class="dp-week">';
      ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"].forEach(function (d) {
        h += "<span>" + d + "</span>";
      });
      h += '</div><div class="dp-grid">';

      var i;
      for (i = 0; i < firstWeekday; i++) h += "<span></span>";

      for (var d = 1; d <= daysInMonth; d++) {
        var s = iso(vy, vm, d);
        var past = s < today;
        var full = !past && isFull(service.value, s);
        var cls = "dp-day";
        if (s === today) cls += " is-today";
        if (s === selected) cls += " is-selected";
        if (full) cls += " is-booked";
        h +=
          '<button type="button" class="' +
          cls +
          '" data-date="' +
          s +
          '"' +
          (past || full ? " disabled" : "") +
          (full ? ' title="Fully booked for this service"' : "") +
          ">" +
          d +
          "</button>";
      }

      h +=
        '</div><p class="dp-legend"><span class="dp-key dp-key-sel"></span>Selected' +
        '<span class="dp-key dp-key-full"></span>Fully booked</p>';
      picker.innerHTML = h;
    }

    function updateSummary() {
      setText("summaryService", service.value);
      setText("summaryDate", selected ? prettyDate(selected) : "Not selected");
      setText(
        "summaryTime",
        timeField.value ? prettyTime(timeField.value) : "Not selected",
      );
      setText("summaryLocation", locationField.value || "Not entered");
    }

    function showMsg(text, type) {
      msg.className = "form-msg " + type;
      msg.textContent = text;
    }

    picker.addEventListener("click", function (e) {
      var nav = e.target.closest(".dp-nav");
      if (nav && !nav.disabled) {
        vm += +nav.getAttribute("data-dir");
        if (vm < 0) {
          vm = 11;
          vy--;
        }
        if (vm > 11) {
          vm = 0;
          vy++;
        }
        renderPicker();
        return;
      }
      var day = e.target.closest(".dp-day");
      if (day && !day.disabled) {
        selected = day.getAttribute("data-date");
        dateField.value = prettyDate(selected);
        msg.className = "";
        msg.textContent = "";
        renderPicker();
        updateSummary();
      }
    });

    service.addEventListener("change", function () {
      // The new service might already be taken on the chosen date.
      if (selected && isFull(service.value, selected)) {
        selected = "";
        dateField.value = "";
        showMsg(
          "That date is already booked for this service. Please pick another date.",
          "error",
        );
      }
      renderPicker();
      updateSummary();
    });
    timeField.addEventListener("input", updateSummary);
    locationField.addEventListener("input", updateSummary);

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      // Stops any older submit handler in script.js from also running.
      e.stopImmediatePropagation();

      if (!selected) {
        showMsg("Please choose a date from the calendar.", "error");
        picker.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }
      // Re-check right before saving in case someone else just booked it.
      if (isFull(service.value, selected)) {
        showMsg(
          "Sorry, that date was just booked. Please pick another date.",
          "error",
        );
        renderPicker();
        return;
      }

      var booking = {
        id: "SV-" + Date.now().toString(36).toUpperCase(),
        firstName: $("firstName").value.trim(),
        lastName: $("lastName").value.trim(),
        email: $("email").value.trim(),
        phone: $("phone").value.trim(),
        service: service.value,
        date: selected,
        time: timeField.value,
        location: locationField.value.trim(),
        guests: $("guests").value,
        notes: $("notes").value.trim(),
        status: "pending",
        createdAt: new Date().toISOString(),
      };

      if (!Store.add(booking)) {
        showMsg(
          "We couldn't save your reservation. Please try again.",
          "error",
        );
        return;
      }

      form.reset();
      selected = "";
      dateField.value = "";
      renderPicker();
      updateSummary();
      showMsg(
        "Reservation submitted! Your reference is " +
          booking.id +
          ". We'll contact you to confirm.",
        "success",
      );
    });

    renderPicker();
    updateSummary();
  }

  /* =========================================================
     OWNER SIDE: admin/calendar.html
     ========================================================= */
  function initAdminCalendar() {
    var root = $("adminCalendar");
    if (!root) return;

    var titleEl = $("calTitle");
    var countsEl = $("calCounts");
    var gridEl = $("calGrid");
    var detailEl = $("calDetail");

    var now = new Date();
    var vy = now.getFullYear();
    var vm = now.getMonth();
    var selected = todayISO();

    function byDate() {
      var map = {};
      Store.all().forEach(function (b) {
        (map[b.date] = map[b.date] || []).push(b);
      });
      Object.keys(map).forEach(function (k) {
        map[k].sort(function (a, b) {
          return (a.time || "").localeCompare(b.time || "");
        });
      });
      return map;
    }

    function bookingCard(b) {
      var id = esc(b.id);
      var acts = "";
      if (b.status === "pending")
        acts +=
          '<button class="btn btn-primary btn-sm" data-act="confirmed" data-id="' +
          id +
          '">Confirm</button>';
      if (b.status === "confirmed")
        acts +=
          '<button class="btn btn-primary btn-sm" data-act="completed" data-id="' +
          id +
          '">Mark completed</button>';
      if (b.status === "pending" || b.status === "confirmed")
        acts +=
          '<button class="btn btn-outline btn-sm" data-act="cancelled" data-id="' +
          id +
          '">Cancel</button>';
      acts +=
        '<button class="btn btn-danger btn-sm" data-act="delete" data-id="' +
        id +
        '">Delete</button>';

      return (
        '<article class="booking-item">' +
        "<header><strong>" +
        esc((b.firstName + " " + b.lastName).trim()) +
        "</strong>" +
        '<span class="status ' +
        esc(b.status) +
        '">' +
        esc(cap(b.status)) +
        "</span></header>" +
        '<div class="booking-meta">' +
        "<div><b>Service:</b> " +
        esc(b.service) +
        "</div>" +
        "<div><b>Time:</b> " +
        esc(
          prettyTime(b.time) + (b.endTime ? " - " + prettyTime(b.endTime) : ""),
        ) +
        "</div>" +
        "<div><b>Location:</b> " +
        esc(b.location) +
        "</div>" +
        (b.facebook
          ? "<div><b>Messenger:</b> " + esc(b.facebook) + "</div>"
          : "") +
        (b.total
          ? "<div><b>Total:</b> \u20B1" +
            esc(Number(b.total).toLocaleString("en-US")) +
            "</div>"
          : "") +
        "<div><b>Phone:</b> " +
        esc(b.phone) +
        "</div>" +
        "<div><b>Email:</b> " +
        esc(b.email) +
        "</div>" +
        "<div><b>Reference:</b> " +
        id +
        "</div>" +
        (b.notes ? "<div><b>Notes:</b> " + esc(b.notes) + "</div>" : "") +
        '</div><div class="action-row">' +
        acts +
        "</div></article>"
      );
    }

    function renderDetail() {
      var list = byDate()[selected] || [];
      var h = "<h2>" + esc(prettyDate(selected)) + "</h2>";
      if (!list.length) {
        h += '<p class="cal-empty">No bookings on this date.</p>';
      } else {
        h += '<div class="day-list">';
        list.forEach(function (b) {
          h += bookingCard(b);
        });
        h += "</div>";
      }
      detailEl.innerHTML = h;
    }

    function render() {
      var map = byDate();
      var today = todayISO();
      var firstWeekday = new Date(vy, vm, 1).getDay();
      var daysInMonth = new Date(vy, vm + 1, 0).getDate();
      var total = 0,
        pending = 0;
      var h = "";
      var i;

      for (i = 0; i < firstWeekday; i++)
        h += '<div class="cal-cell is-out"></div>';

      for (var d = 1; d <= daysInMonth; d++) {
        var s = iso(vy, vm, d);
        var list = map[s] || [];
        list.forEach(function (b) {
          if (b.status !== "cancelled") total++;
          if (b.status === "pending") pending++;
        });

        h +=
          '<div class="cal-cell' +
          (s === today ? " is-today" : "") +
          (s === selected ? " is-selected" : "") +
          '" data-date="' +
          s +
          '" role="button" tabindex="0">' +
          '<span class="cal-num">' +
          d +
          "</span>";
        list.slice(0, 3).forEach(function (b) {
          h +=
            '<span class="status cal-chip ' +
            esc(b.status) +
            '">' +
            esc(prettyTime(b.time)) +
            " " +
            esc(b.lastName || b.firstName) +
            "</span>";
        });
        if (list.length > 3)
          h += '<span class="cal-more">+' + (list.length - 3) + " more</span>";
        h += "</div>";
      }

      titleEl.textContent = MONTHS[vm] + " " + vy;
      countsEl.textContent =
        total +
        (total === 1 ? " booking" : " bookings") +
        " this month, " +
        pending +
        " pending";
      gridEl.innerHTML = h;
      renderDetail();
    }

    function shiftMonth(delta) {
      vm += delta;
      if (vm < 0) {
        vm = 11;
        vy--;
      }
      if (vm > 11) {
        vm = 0;
        vy++;
      }
      render();
    }

    $("calPrev").addEventListener("click", function () {
      shiftMonth(-1);
    });
    $("calNext").addEventListener("click", function () {
      shiftMonth(1);
    });
    $("calToday").addEventListener("click", function () {
      var t = new Date();
      vy = t.getFullYear();
      vm = t.getMonth();
      selected = todayISO();
      render();
    });

    function pickCell(cell) {
      if (!cell || cell.classList.contains("is-out")) return;
      selected = cell.getAttribute("data-date");
      render();
    }
    gridEl.addEventListener("click", function (e) {
      pickCell(e.target.closest(".cal-cell"));
    });
    gridEl.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        pickCell(e.target.closest(".cal-cell"));
      }
    });

    detailEl.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-act]");
      if (!btn) return;
      var id = btn.getAttribute("data-id");
      var act = btn.getAttribute("data-act");
      if (act === "delete") {
        if (!confirm("Delete this booking permanently?")) return;
        Store.remove(id);
      } else {
        Store.update(id, { status: act });
      }
      render();
    });

    // Live updates: fires when a booking is saved from another tab/window
    // (e.g. the customer booking page open next to the admin panel).
    window.addEventListener("storage", function (e) {
      if (e.key === KEY || e.key === null) render();
    });
    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) render();
    });

    render();
  }

  initBookingPage();
  initAdminCalendar();
})();
