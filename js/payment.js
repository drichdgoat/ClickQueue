/* SnapVenture - payment page.
   Reads the reservation the customer entered on booking.html (saved by
   booking-page.js when they press "Proceed to Payment"), shows it, and only
   saves the booking when they press "Confirm Reservation".
   Needs booking-calendar.js loaded first (it provides window.SnapStore). */
(function () {
  "use strict";
  var Store = window.SnapStore;
  var $ = function (id) {
    return document.getElementById(id);
  };
  if (!Store || !$("payLayout")) return;

  /* ---- EDIT HERE: payment methods + account details ---- */
  var METHODS = [
    {
      id: "bank",
      title: "Bank Transfer",
      desc: "Transfer to our bank account",
      proof: true, // customer must upload a receipt
      details: [
        ["Bank Name", "Philippine National Bank"],
        ["Account Name", "SnapSync Services"],
        ["Account Number", "1234-5678-9012"],
      ],
      note: "After transferring, please upload your payment receipt below.",
    },
    {
      id: "ewallet",
      title: "GCash / PayMaya",
      desc: "Mobile wallet payment",
      proof: true,
      details: [
        ["Mobile Number", "0912 345 6789"],
        ["Account Name", "SnapSync Services"],
      ],
      note: "After sending, please upload a screenshot of your payment below.",
    },
    {
      id: "otc",
      title: "Over-the-Counter",
      desc: "Pay at our office or partner locations",
      proof: false, // nothing to upload before paying in person
      details: [
        ["Office Hours", "Mon-Sat, 9AM-6PM"],
        ["Reference No.", "{ref}"], // {ref} becomes the booking reference
      ],
      note: "Show your reference number at the counter. Uploading a receipt is optional for this method.",
    },
  ];

  var DRAFT_KEY = "snapventure_draft"; // must match booking-page.js
  var MAX_PER_DAY = 1; // must match booking-page.js
  var MAX_FILE_BYTES = 5 * 1024 * 1024;
  var OK_TYPES = ["image/png", "image/jpeg", "application/pdf"];

  /* ---------- helpers ---------- */
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
  function peso(n, decimals) {
    return (
      "\u20B1" +
      Number(n || 0).toLocaleString("en-US", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      })
    );
  }
  function longDate(s) {
    var p = s.split("-");
    return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  }
  function prettyTime(t) {
    if (!t) return "";
    var p = t.split(":"),
      h = +p[0];
    return (h % 12 || 12) + ":" + p[1] + " " + (h >= 12 ? "PM" : "AM");
  }
  function icon(name, cls) {
    return (
      '<svg class="ic' +
      (cls ? " " + cls : "") +
      '"><use href="#i-' +
      name +
      '"/></svg>'
    );
  }
  function row(ic, label, value, sub, price) {
    return (
      '<div class="res-row">' +
      icon(ic) +
      '<div class="res-text"><small>' +
      esc(label) +
      "</small><b>" +
      esc(value) +
      "</b>" +
      (sub ? "<span>" + esc(sub) + "</span>" : "") +
      "</div>" +
      (price ? '<b class="res-price">' + esc(price) + "</b>" : "") +
      "</div>"
    );
  }
  function readDraft() {
    try {
      var d = JSON.parse(sessionStorage.getItem(DRAFT_KEY));
      if (d && d.id && d.service && d.date && d.total) return d;
    } catch (e) {
      /* no draft */
    }
    return null;
  }

  /* ---------- state ---------- */
  var draft = readDraft();
  var method = null;
  var proof = null;
  var agreed = false;

  if (!draft) {
    $("payLayout").hidden = true;
    $("payEmpty").hidden = false;
    return;
  }

  // Is this date already taken by another (non-cancelled) booking?
  function isTaken() {
    return (
      Store.all().filter(function (b) {
        return (
          b.service === draft.service &&
          b.date === draft.date &&
          b.status !== "cancelled" &&
          b.id !== draft.id
        );
      }).length >= MAX_PER_DAY
    );
  }

  /* ---------- reservation + payment summary ---------- */
  function renderReservation() {
    var contact = [draft.phone, draft.email].filter(Boolean).join(", ");
    var h =
      row(
        "camera",
        "Service",
        draft.service,
        draft.packageDesc,
        peso(draft.subtotal, 0),
      ) +
      row(
        "calendar",
        "Date & Time",
        longDate(draft.date),
        prettyTime(draft.startTime) + " - " + prettyTime(draft.endTime),
      ) +
      row("pin", "Event Location", draft.location) +
      row("user", "Customer", draft.fullName, contact);
    if (draft.notes) h += row("doc", "Additional Notes", draft.notes);
    $("resRows").innerHTML = h;

    $("sumRental").textContent = peso(draft.subtotal, 2);
    $("sumFee").textContent = peso(draft.serviceFee, 2);
    $("sumTotal").textContent = peso(draft.total, 2);
    $("sumDown").textContent = peso(draft.downPayment, 2);
    $("sumRemain").textContent = peso(draft.total - draft.downPayment, 2);
  }

  /* ---------- payment method ---------- */
  function renderMethods() {
    $("methods").innerHTML = METHODS.map(function (m) {
      return (
        '<label class="method" data-id="' +
        m.id +
        '"><input type="radio" name="payMethod" value="' +
        m.id +
        '"><span class="radio"></span><span class="method-text"><b>' +
        esc(m.title) +
        "</b><small>" +
        esc(m.desc) +
        "</small></span></label>"
      );
    }).join("");
  }
  function renderInstructions() {
    var box = $("instructions");
    if (!method) {
      box.hidden = true;
      return;
    }
    var h = "<h3>Payment Instructions</h3>";
    method.details.forEach(function (d) {
      h +=
        "<p><b>" +
        esc(d[0]) +
        ":</b> " +
        esc(d[1].replace("{ref}", draft.id)) +
        "</p>";
    });
    h += '<p class="note">' + esc(method.note) + "</p>";
    box.innerHTML = h;
    box.hidden = false;
  }

  /* ---------- proof of payment ---------- */
  function drawDropzone() {
    $("dropzone").innerHTML = proof
      ? icon("check") +
        "<b>File Uploaded</b><span>" +
        esc(proof.name) +
        "</span>"
      : icon("upload") +
        "<b>Click to upload</b><span>PNG, JPG, or PDF (max. 5MB)</span>";
  }
  function setProof(file) {
    var err = $("proofError");
    err.hidden = true;
    if (!file) return;
    var okType =
      OK_TYPES.indexOf(file.type) !== -1 ||
      /\.(png|jpe?g|pdf)$/i.test(file.name);
    if (!okType) {
      err.textContent = "Please upload a PNG, JPG, or PDF file.";
      err.hidden = false;
      return;
    }
    if (file.size > MAX_FILE_BYTES) {
      err.textContent =
        "That file is larger than 5MB. Please upload a smaller file.";
      err.hidden = false;
      return;
    }
    proof = { name: file.name, size: file.size, type: file.type };
    drawDropzone();
    update();
  }

  /* ---------- confirm button gate ---------- */
  function update() {
    var missing = [];
    if (!method) missing.push("choose a payment method");
    else if (method.proof && !proof)
      missing.push("upload your proof of payment");
    if (!agreed) missing.push("agree to the rental terms");

    var taken = isTaken();
    var hint = $("confirmHint");
    $("confirmBtn").disabled = missing.length > 0 || taken;

    if (taken) {
      hint.textContent =
        "Sorry, this date was just booked by someone else. Please go back and choose another date.";
      hint.className = "confirm-hint is-error";
    } else if (missing.length) {
      var list =
        missing.length === 1
          ? missing[0]
          : missing.slice(0, -1).join(", ") +
            " and " +
            missing[missing.length - 1];
      hint.textContent = "To confirm, please " + list + ".";
      hint.className = "confirm-hint";
    } else {
      hint.textContent = "";
    }
  }

  /* ---------- events ---------- */
  $("methods").addEventListener("change", function (e) {
    if (e.target.name !== "payMethod") return;
    method =
      METHODS.filter(function (m) {
        return m.id === e.target.value;
      })[0] || null;
    Array.prototype.forEach.call(
      $("methods").querySelectorAll(".method"),
      function (el) {
        el.classList.toggle(
          "is-selected",
          !!method && el.dataset.id === method.id,
        );
      },
    );
    renderInstructions();
    update();
  });

  var dz = $("dropzone");
  dz.addEventListener("click", function () {
    $("proofInput").click();
  });
  dz.addEventListener("keydown", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      $("proofInput").click();
    }
  });
  ["dragenter", "dragover"].forEach(function (ev) {
    dz.addEventListener(ev, function (e) {
      e.preventDefault();
      dz.classList.add("is-over");
    });
  });
  ["dragleave", "drop"].forEach(function (ev) {
    dz.addEventListener(ev, function () {
      dz.classList.remove("is-over");
    });
  });
  dz.addEventListener("drop", function (e) {
    e.preventDefault();
    if (e.dataTransfer && e.dataTransfer.files.length)
      setProof(e.dataTransfer.files[0]);
  });
  $("proofInput").addEventListener("change", function (e) {
    setProof(e.target.files[0]);
    e.target.value = ""; // lets the same file be picked again
  });

  $("agree").addEventListener("change", function (e) {
    agreed = e.target.checked;
    update();
  });
  // another tab may have taken this date while the customer was paying
  window.addEventListener("storage", update);

  $("confirmBtn").addEventListener("click", function () {
    var btn = this;
    if (btn.disabled) return;
    var hint = $("confirmHint");

    // Re-check everything right before saving.
    if (!method || !agreed || (method.proof && !proof)) {
      update();
      return;
    }
    if (isTaken()) {
      update();
      return;
    }

    var now = new Date().toISOString();
    var booking = {};
    for (var k in draft) booking[k] = draft[k];
    booking.status = "pending"; // the owner confirms it after checking the payment
    booking.paymentMethod = method.title;
    booking.paymentStatus = "submitted";
    booking.proofFileName = proof ? proof.name : "";
    booking.agreedToTerms = true;
    booking.agreedAt = now;
    booking.createdAt = now;

    var already = Store.all().some(function (b) {
      return b.id === booking.id;
    });
    if (!already && !Store.add(booking)) {
      hint.textContent = "We couldn't save your reservation. Please try again.";
      hint.className = "confirm-hint is-error";
      return;
    }
    btn.disabled = true;
    try {
      sessionStorage.removeItem(DRAFT_KEY);
    } catch (e) {
      /* ignore */
    }
    showSuccess(booking);
  });

  function showSuccess(b) {
    $("okRef").textContent = b.id;
    $("okEmail").textContent = b.email;
    $("okRows").innerHTML =
      row("camera", "Service", b.service, b.packageDesc) +
      row(
        "calendar",
        "Date & Time",
        longDate(b.date),
        prettyTime(b.startTime) + " - " + prettyTime(b.endTime),
      ) +
      row("pin", "Event Location", b.location) +
      row("doc", "Down Payment (50%)", peso(b.downPayment, 2), b.paymentMethod);
    $("payLayout").hidden = true;
    $("paySuccess").hidden = false;
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  /* ---------- start ---------- */
  renderReservation();
  renderMethods();
  drawDropzone();
  update();
})();
