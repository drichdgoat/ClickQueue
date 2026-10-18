/* SnapVenture Admin - Feedback Management (DB-backed via /api/admin-feedback-*.php) */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const list = $("feedbackList");
  if (!list) return;

  const emptyState = $("emptyState");
  const searchInput = $("searchInput");
  const ratingFilter = $("ratingFilter");
  const featuredFilter = $("featuredFilter");

  let feedback = [];

  function esc(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
  function initials(name) {
    return name
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((w) => w[0].toUpperCase())
      .join("");
  }
  function stars(n) {
    return "★★★★★".slice(0, n) + "☆☆☆☆☆".slice(0, 5 - n);
  }
  function formatDate(s) {
    const d = new Date(s.replace(" ", "T"));
    return isNaN(d)
      ? s
      : d.toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        });
  }

  function load() {
    fetch("../../api/admin/admin-feedback-list.php")
      .then((r) => r.json())
      .then((data) => {
        if (data.error) {
          list.innerHTML =
            '<p style="color:var(--red)">' + esc(data.error) + "</p>";
          return;
        }
        feedback = data.feedback || [];
        updateStats(data.stats);
        render();
      })
      .catch(() => {
        list.innerHTML =
          '<p style="color:var(--muted)">Could not load feedback. Please refresh.</p>';
      });
  }

  function updateStats(stats) {
    if (!stats) return;
    $("statTotal").textContent = stats.total;
    $("statAvg").textContent = stats.average_rating;
    $("statFeatured").textContent = stats.featured;
    $("statNotFeatured").textContent = stats.not_featured;
  }

  function cardHTML(f) {
    return (
      '<div class="fb-card">' +
      '<div class="fb-avatar">' +
      esc(initials(f.customer_name)) +
      "</div>" +
      '<div class="fb-body">' +
      '<div class="fb-head"><span class="fb-name">' +
      esc(f.customer_name) +
      "</span>" +
      (f.service ? '<span class="fb-tag">' + esc(f.service) + "</span>" : "") +
      '<span class="fb-tag' +
      (f.is_featured == 1 ? " is-featured" : "") +
      '">' +
      (f.is_featured == 1 ? "Featured" : "Not Featured") +
      "</span>" +
      "</div>" +
      '<div class="fb-meta">' +
      esc(f.customer_email) +
      (f.phone ? " • " + esc(f.phone) : "") +
      "</div>" +
      '<p class="fb-message">' +
      esc(f.message) +
      "</p>" +
      '<div class="fb-actions">' +
      '<button type="button" class="btn btn-outline btn-icon" data-action="toggle" data-id="' +
      f.feedback_id +
      '" data-featured="' +
      f.is_featured +
      '">' +
      (f.is_featured == 1 ? "Remove from Homepage" : "Feature on Homepage") +
      "</button>" +
      "</div>" +
      "</div>" +
      '<div class="fb-right"><span class="fb-stars">' +
      stars(f.rating) +
      '</span><span class="fb-date">' +
      formatDate(f.created_at) +
      "</span></div>" +
      "</div>"
    );
  }

  function render() {
    const term = (searchInput.value || "").trim().toLowerCase();
    const ratingVal = ratingFilter.value;
    const featuredVal = featuredFilter.value;

    const filtered = feedback.filter((f) => {
      if (ratingVal !== "all" && String(f.rating) !== ratingVal) return false;
      if (featuredVal === "featured" && f.is_featured != 1) return false;
      if (featuredVal === "hidden" && f.is_featured == 1) return false;
      if (term) {
        const hay = (
          f.customer_name +
          " " +
          f.customer_email +
          " " +
          f.message +
          " " +
          (f.service || "")
        ).toLowerCase();
        if (hay.indexOf(term) === -1) return false;
      }
      return true;
    });

    list.innerHTML = filtered.map(cardHTML).join("");
    emptyState.hidden = filtered.length > 0;
  }

  searchInput.addEventListener("input", render);
  ratingFilter.addEventListener("change", render);
  featuredFilter.addEventListener("change", render);

  list.addEventListener("click", function (e) {
    const btn = e.target.closest('[data-action="toggle"]');
    if (!btn) return;
    const id = btn.dataset.id;
    const nextFeatured = btn.dataset.featured == "1" ? 0 : 1;
    btn.disabled = true;
    const fd = new FormData();
    fd.append("id", id);
    fd.append("is_featured", nextFeatured);
    fetch("../../api/admin/admin-feedback-toggle.php", { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) load();
        else btn.disabled = false;
      })
      .catch(() => {
        btn.disabled = false;
      });
  });

  load();
})();
