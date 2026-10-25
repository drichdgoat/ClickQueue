/* SnapVenture Admin - Bookings page: category cards + status filter (client-side, uses the rows already on the page) */
(function () {
  "use strict";

  var cards = document.querySelectorAll("[data-category]");
  var statusSelect = document.getElementById("statusFilter");
  var tbody = document.getElementById("bookingsBody");
  var noMatch = document.getElementById("noMatchRow");
  var countEl = document.getElementById("resultCount");
  if (!cards.length || !statusSelect || !tbody) return;

  var rows = Array.prototype.slice.call(
    tbody.querySelectorAll("tr[data-booking-id]"),
  );
  var CATEGORIES = ["all", "camera_rental", "photobooth"];
  var STATUSES = ["", "pending", "confirmed", "completed", "cancelled"];
  var KEY = "sv_admin_bookings_filters";
  var state = { category: "all", status: "" };

  // Remember the filters so they survive the page reload after a status change / search
  try {
    var saved = JSON.parse(sessionStorage.getItem(KEY) || "null");
    if (saved && CATEGORIES.indexOf(saved.category) !== -1)
      state.category = saved.category;
    if (saved && STATUSES.indexOf(saved.status) !== -1)
      state.status = saved.status;
  } catch (e) {}

  // The row dropdown's data-current is kept up to date by booking-details.js after a confirm
  function rowStatus(tr) {
    var sel = tr.querySelector("select[name=new_status]");
    return (sel && sel.dataset.current) || tr.dataset.status || "";
  }

  function apply() {
    var shown = 0;
    rows.forEach(function (tr) {
      var okCategory =
        state.category === "all" || tr.dataset.service === state.category;
      var okStatus = state.status === "" || rowStatus(tr) === state.status;
      var visible = okCategory && okStatus;
      tr.hidden = !visible;
      if (visible) shown++;
    });

    cards.forEach(function (card) {
      var active = card.dataset.category === state.category;
      card.classList.toggle("is-active", active);
      card.setAttribute("aria-pressed", active ? "true" : "false");
    });
    statusSelect.value = state.status;

    if (noMatch) noMatch.hidden = rows.length === 0 || shown > 0;
    if (countEl && rows.length) {
      countEl.textContent =
        "Showing " +
        shown +
        " of " +
        rows.length +
        " booking" +
        (rows.length === 1 ? "" : "s");
    }

    try {
      sessionStorage.setItem(KEY, JSON.stringify(state));
    } catch (e) {}
  }

  cards.forEach(function (card) {
    card.addEventListener("click", function () {
      state.category = card.dataset.category;
      apply();
    });
  });
  statusSelect.addEventListener("change", function () {
    state.status = statusSelect.value;
    apply();
  });

  // Re-apply when a row's status changes without a reload (booking confirmed from the details dialog)
  if (window.MutationObserver) {
    var observer = new MutationObserver(apply);
    tbody.querySelectorAll(".status-cell").forEach(function (cell) {
      observer.observe(cell, { childList: true, subtree: true });
    });
  }

  apply();
})();
