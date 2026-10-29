/* SnapVenture Admin - shared helpers (fetch + CSRF, formatting, modal, toast, mobile menu) */
(function () {
  "use strict";
  var SV = (window.SV = {});
  SV.api = document.body.dataset.api || "../../api/admin/";
  SV.csrf = document.body.dataset.csrf || "";

  SV.esc = function (v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  };
  SV.money = function (n) {
    return "₱" + Number(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };
  function toDate(s) {
    if (!s) return null;
    var d = new Date(String(s).replace(" ", "T"));
    return isNaN(d) ? null : d;
  }
  SV.date = function (s) {
    var d = toDate(s);
    return d ? d.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" }) : "";
  };
  SV.datetime = function (s) {
    var d = toDate(s);
    return d ? d.toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "";
  };
  SV.bytes = function (n) {
    if (!n) return "";
    return n > 1048576 ? (n / 1048576).toFixed(1) + " MB" : Math.max(1, Math.round(n / 1024)) + " KB";
  };

  function parse(res) {
    return res.text().then(function (t) {
      var data;
      try { data = JSON.parse(t); } catch (e) {
        var err = new Error("Unexpected server response (HTTP " + res.status + "). Check the PHP error log.");
        err.data = null;
        throw err;
      }
      if (!res.ok || !data.success) {
        var e2 = new Error(data.error || "Request failed (HTTP " + res.status + ").");
        e2.data = data;
        throw e2;
      }
      return data;
    });
  }
  SV.get = function (endpoint, params) {
    var qs = new URLSearchParams(params || {}).toString();
    return fetch(SV.api + endpoint + (qs ? "?" + qs : ""), {
      credentials: "same-origin", headers: { Accept: "application/json" },
    }).then(parse);
  };
  SV.post = function (endpoint, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    fd.append("csrf_token", SV.csrf);
    return fetch(SV.api + endpoint, { method: "POST", credentials: "same-origin", body: fd, headers: { Accept: "application/json" } }).then(parse);
  };
  SV.url = function (endpoint, params) {
    return SV.api + endpoint + "?" + new URLSearchParams(params || {}).toString();
  };

  SV.toast = function (msg, kind) {
    var wrap = document.getElementById("toastWrap");
    if (!wrap) return;
    var t = document.createElement("div");
    t.className = "toast " + (kind || "");
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, kind === "bad" ? 8000 : 4500);
  };

  SV.openModal = function (el) { el.classList.add("open"); document.body.classList.add("modal-open"); };
  SV.closeModal = function (el) {
    el.classList.remove("open");
    if (!document.querySelector(".modal-overlay.open")) document.body.classList.remove("modal-open");
    if (el.__onClose) el.__onClose();
  };
  document.querySelectorAll(".modal-overlay").forEach(function (ov) {
    ov.addEventListener("mousedown", function (e) {
      if (e.target === ov || (e.target.closest && e.target.closest("[data-close]") && ov.contains(e.target))) {
        if (e.target === ov || e.target.closest("[data-close]")) SV.closeModal(ov);
      }
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    var open = document.querySelectorAll(".modal-overlay.open");
    if (open.length) SV.closeModal(open[open.length - 1]);
  });

})();
