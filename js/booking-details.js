/* SnapVenture Admin - booking details / review view (Bookings Management page).
   Read-only until the admin clicks an explicit action:
   - "Mark payment as verified" records the admin's manual review (separate from booking status)
   - "Confirm Booking" requires both acknowledgments, then the server creates the agreement (never emails it) */
(function () {
  "use strict";
  var SV = window.SV;
  var modal = document.getElementById("bkModal");
  if (!SV || !modal) return;

  var $ = function (id) {
    return document.getElementById(id);
  };
  var esc = SV.esc;
  var cur = null; // currently loaded booking payload
  var activeTab = "info";

  function statusBadge(s) {
    return '<span class="status ' + esc(s) + '">' + esc(s) + "</span>";
  }
  function item(k, v, full) {
    var has = v !== null && v !== undefined && String(v).trim() !== "";
    return (
      "<div" +
      (full ? ' class="full"' : "") +
      '><span class="k">' +
      esc(k) +
      '</span><span class="v">' +
      (has ? v : '<span class="muted">—</span>') +
      "</span></div>"
    );
  }
  function section(title, inner) {
    return (
      '<div class="kv-section"><h3>' + esc(title) + "</h3>" + inner + "</div>"
    );
  }
  function notice(kind, html) {
    return '<div class="notice ' + kind + '">' + html + "</div>";
  }

  /* ---------------- tabs ---------------- */
  function setTab(name) {
    activeTab = name;
    modal.querySelectorAll(".tabs button").forEach(function (b) {
      b.classList.toggle("active", b.dataset.tab === name);
    });
    modal.querySelectorAll(".tab-panel").forEach(function (p) {
      p.classList.toggle("active", p.id === "tab-" + name);
    });
  }
  modal.querySelector(".tabs").addEventListener("click", function (e) {
    var b = e.target.closest("button[data-tab]");
    if (b) setTab(b.dataset.tab);
  });

  /* ---------------- Booking Information ---------------- */
  function renderInfo(b) {
    var h = "";
    h += section(
      "Booking",
      '<div class="kv">' +
        item("Reference no.", esc(b.reference_no)) +
        item("Booking date", esc(SV.datetime(b.booking_date))) +
        item("Booking status", statusBadge(b.status)) +
        item(
          "Confirmed on",
          b.confirmed_at ? esc(SV.datetime(b.confirmed_at)) : "",
        ) +
        "</div>",
    );
    h += section(
      "Customer",
      '<div class="kv">' +
        item("Full name", esc(b.customer.name)) +
        item("Email", esc(b.customer.email)) +
        item("Contact number", esc(b.customer.phone)) +
        item("Facebook / Messenger", esc(b.customer.facebook)) +
        "</div>",
    );

    var svc =
      '<div class="kv">' +
      item("Service", esc(b.service_label)) +
      item("Package / equipment", esc(b.package_name));
    if (b.camera_detail) {
      svc += item("Rental days", b.camera_detail.rental_days);
      if (b.camera_detail.photo_qty)
        svc += item("Photo quantity", b.camera_detail.photo_qty);
    }
    svc += "</div>";
    if (b.addons.length) {
      svc +=
        '<table class="mini-table" style="margin-top:8px"><tr><th>Add-ons</th><th>Price</th></tr>';
      b.addons.forEach(function (a) {
        svc +=
          "<tr><td>" +
          esc(a.name) +
          (a.qty > 1 ? " × " + a.qty : "") +
          "</td><td>" +
          esc(SV.money(a.price)) +
          "</td></tr>";
      });
      svc += "</table>";
    }
    h += section("Service and add-ons", svc);

    h += section(
      "Event",
      '<div class="kv">' +
        item("Event date", esc(SV.date(b.event.date))) +
        item("Time", esc(b.event.time_label)) +
        item("Location", esc(b.event.location)) +
        item("Rental duration", esc(b.event.duration_label)) +
        "</div>",
    );

    var a = b.amounts;
    h += section(
      "Amounts",
      '<table class="mini-table">' +
        "<tr><td>Package price</td><td>" +
        esc(SV.money(a.package_price)) +
        "</td></tr>" +
        (a.addons_total > 0
          ? "<tr><td>Add-ons total</td><td>" +
            esc(SV.money(a.addons_total)) +
            "</td></tr>"
          : "") +
        "<tr><td><b>Total amount</b></td><td><b>" +
        esc(SV.money(a.grand_total)) +
        "</b></td></tr>" +
        "<tr><td>Required down payment (due now)</td><td>" +
        esc(SV.money(a.amount_due_now)) +
        "</td></tr>" +
        "<tr><td>Remaining balance</td><td>" +
        esc(SV.money(a.remaining_balance)) +
        "</td></tr></table>",
    );

    h += section(
      "Payment",
      '<div class="kv">' +
        item("Payment method", esc(b.payment.method)) +
        item("Payment status", esc(b.payment.status_label)) +
        "</div>",
    );
    if (b.notes && String(b.notes).trim() !== "") {
      h += section(
        "Customer notes / special requests",
        '<div class="v" style="white-space:pre-wrap">' +
          esc(b.notes) +
          "</div>",
      );
    }
    $("tab-info").innerHTML = h;
  }

  /* ---------------- Proof of Payment ---------------- */
  function receiptHtml(b) {
    var r = b.payment.receipt;
    var params = { booking_id: b.booking_id, type: "payment_proof" };
    var openUrl = SV.url("booking-file.php", params);
    var dlUrl = SV.url("booking-file.php", {
      booking_id: b.booking_id,
      type: "payment_proof",
      download: 1,
    });
    if (!r.exists) {
      if (r.recorded)
        return notice(
          "bad",
          "A receipt is recorded for this booking but the file could not be found on the server.",
        );
      return (
        '<div class="viewer"><div class="viewer-empty">No proof of payment uploaded yet.</div></div>' +
        (b.payment.proof_required
          ? ""
          : notice(
              "",
              "This booking uses <b>" +
                esc(b.payment.method) +
                "</b>, which does not require a receipt upload.",
            ))
      );
    }
    var viewer;
    if (r.kind === "image") {
      viewer =
        '<img src="' +
        esc(openUrl) +
        '" alt="Proof of payment" onerror="this.parentNode.innerHTML=\'<div class=viewer-empty>The preview failed to load. Use Open or Download.</div>\'">';
    } else if (r.kind === "pdf") {
      viewer =
        '<iframe title="Proof of payment (PDF)" src="' +
        esc(openUrl) +
        '#toolbar=0&navpanes=0&view=FitH"></iframe>';
    } else {
      viewer =
        '<div class="viewer-empty">This file type cannot be previewed.</div>';
    }
    return (
      '<div class="viewer">' +
      viewer +
      "</div>" +
      '<div class="viewer-actions"><a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="' +
      esc(openUrl) +
      '">Open in new tab</a>' +
      '<a class="btn btn-outline btn-sm" href="' +
      esc(dlUrl) +
      '">Download</a></div>' +
      '<div class="small muted">File: ' +
      esc(r.filename) +
      (r.size ? " · " + esc(SV.bytes(r.size)) : "") +
      "</div>"
    );
  }

  function renderPayment(b) {
    var p = b.payment,
      a = b.amounts,
      h = "";
    var cls =
      p.status === "verified"
        ? "ok"
        : p.status === "submitted"
          ? "warn"
          : "bad";
    h +=
      '<div class="kv kv-section">' +
      item("Payment method", esc(p.method)) +
      item(
        "Payment status",
        '<span class="pill ' + cls + '">' + esc(p.status_label) + "</span>",
      ) +
      item("Required down payment", esc(SV.money(a.amount_due_now))) +
      item("Remaining balance", esc(SV.money(a.remaining_balance))) +
      item("Total", esc(SV.money(a.grand_total))) +
      item(
        "Receipt submitted",
        esc(SV.datetime(p.receipt.submitted_at)) +
          ' <span class="small muted">(with the booking)</span>',
      ) +
      "</div>";
    if (p.status === "verified") {
      h += notice(
        "ok",
        "Marked as verified by <b>" +
          esc(p.reviewed_by || "an administrator") +
          "</b>" +
          (p.reviewed_at ? " on " + esc(SV.datetime(p.reviewed_at)) : "") +
          " after manual review.",
      );
    }
    h += receiptHtml(b);
    h += notice(
      "",
      "Inspect the receipt, then record your decision. <b>Marking a payment as verified only records your manual review</b> — " +
        "the system does not check it with any bank or e-wallet, and confirming the booking does not change the payment status.",
    );
    h +=
      p.status === "verified"
        ? '<button type="button" class="btn btn-outline btn-sm" data-action="pay-undo">Undo verification</button>'
        : '<button type="button" class="btn btn-primary btn-sm" data-action="pay-verify">Mark payment as verified (manual review)</button>';
    $("tab-payment").innerHTML = h;
  }

  /* ---------------- Proof of Requirements ---------------- */
  function renderReq(b) {
    $("tab-req").innerHTML =
      notice("", esc(b.requirements.note)) +
      '<div class="viewer"><div class="viewer-empty">No requirement documents are collected for bookings.</div></div>';
  }

  /* ---------------- Agreement tab ---------------- */
  function renderAgreement(b) {
    var a = b.agreement,
      h = "";
    if (a) {
      var cls =
        a.status === "sent" ? "ok" : a.status === "failed" ? "bad" : "info";
      h +=
        '<div class="kv kv-section">' +
        item("Agreement", esc(a.agreement_ref)) +
        item(
          "Status",
          '<span class="pill ' + cls + '">' + esc(a.status_label) + "</span>",
        ) +
        item("Generated", esc(SV.datetime(a.generated_at))) +
        item(
          "Accepted by mail server",
          a.sent_at ? esc(SV.datetime(a.sent_at)) : "",
        ) +
        "</div>";
      h +=
        '<button type="button" class="btn btn-primary btn-sm" data-action="agr-open">Open agreement (preview / send)</button>';
    } else if (b.status === "confirmed") {
      h += notice(
        "warn",
        "This booking is confirmed but has no agreement yet.",
      );
      if (!b.agreement_setup.rep_ready)
        h += notice(
          "bad",
          'Set the authorized representative first: <a href="agreements.php?tab=representative">Agreement &gt; Representative</a>.',
        );
      if (!b.agreement_setup.has_template)
        h += notice(
          "bad",
          'No active template: <a href="agreements.php?tab=templates">Agreement &gt; Templates</a>.',
        );
      h +=
        '<button type="button" class="btn btn-primary btn-sm" data-action="agr-generate">Generate agreement</button>';
    } else {
      h += notice(
        "",
        "The agreement is created when you confirm this booking. Opening a booking never generates or sends one.",
      );
    }
    $("tab-agreement").innerHTML = h;
  }

  /* ---------------- confirm box + reject ---------------- */
  function renderConfirm(b) {
    var box = $("bkConfirmBox");
    if (b.status !== "pending") {
      box.innerHTML =
        b.status === "cancelled"
          ? notice(
              "",
              "This booking is cancelled. Set it back to Pending in the status dropdown first if you want to confirm it.",
            )
          : "";
      return;
    }
    var blockers = [];
    if (b.availability.conflicts.length) {
      blockers.push(
        "Another active booking (" +
          esc(b.availability.conflicts[0].reference_no) +
          ") already uses this package on this date.",
      );
    }
    if (b.payment.proof_required && !b.payment.receipt.exists) {
      blockers.push(
        "No proof of payment is on file for " + esc(b.payment.method) + ".",
      );
    }
    var h = '<div class="confirm-box"><h3>Review and confirm</h3>';
    blockers.forEach(function (t) {
      h += notice("bad", t);
    });
    if (b.payment.status !== "verified") {
      h += notice(
        "warn",
        "Payment is not marked as verified. You can still confirm — payment review is a separate step in the Proof of Payment tab.",
      );
    }
    h +=
      '<label class="check"><input type="checkbox" id="ackDetails"> I have reviewed the booking details and the date/availability.</label>' +
      '<label class="check"><input type="checkbox" id="ackReceipt"> I have reviewed the payment ' +
      (b.payment.proof_required ? "receipt" : "arrangement") +
      ".</label>" +
      '<div class="modal-actions" style="margin-top:12px"><button type="button" class="btn btn-primary" id="bkConfirmBtn" data-blocked="' +
      (blockers.length ? 1 : 0) +
      '" disabled>Confirm Booking</button></div>' +
      '<div class="small muted">Confirming creates the agreement PDF for you to review. Nothing is emailed until you click Send Agreement.</div></div>';
    box.innerHTML = h;
  }

  function renderReject(b) {
    var area = $("bkRejectArea");
    if (b.status === "pending" || b.status === "confirmed") {
      area.innerHTML =
        '<button type="button" class="btn btn-outline" data-action="reject-ask" style="color:#991b1b">Reject / Cancel booking</button>';
    } else area.innerHTML = "";
  }

  function syncConfirmBtn() {
    var btn = $("bkConfirmBtn");
    if (!btn) return;
    var ok =
      $("ackDetails").checked &&
      $("ackReceipt").checked &&
      btn.dataset.blocked !== "1";
    btn.disabled = !ok;
  }

  /* ---------------- load ---------------- */
  function renderAll(b) {
    cur = b;
    $("bkTitle").textContent = b.reference_no;
    $("bkSub").innerHTML = esc(b.customer.name) + " · " + statusBadge(b.status);
    renderInfo(b);
    renderPayment(b);
    renderReq(b);
    renderAgreement(b);
    renderConfirm(b);
    renderReject(b);
    setTab(activeTab);
  }

  function load(id, opts) {
    opts = opts || {};
    $("bkLoading").hidden = false;
    $("bkError").hidden = true;
    ["info", "payment", "req", "agreement"].forEach(function (t) {
      $("tab-" + t).innerHTML = "";
    });
    $("bkConfirmBox").innerHTML = "";
    $("bkRejectArea").innerHTML = "";
    if (!opts.keepTab) activeTab = "info";
    SV.openModal(modal);
    SV.get("booking-details.php", { booking_id: id })
      .then(function (r) {
        $("bkLoading").hidden = true;
        renderAll(r.booking);
        if (opts.focusConfirm) {
          var box = $("bkConfirmBox");
          if (box && box.firstChild) box.scrollIntoView({ block: "center" });
        }
      })
      .catch(function (e) {
        $("bkLoading").hidden = true;
        $("bkError").hidden = false;
        $("bkError").textContent = e.message;
      });
  }

  /* ---------------- table row sync ---------------- */
  function updateRow(id, patch) {
    var tr = document.querySelector('tr[data-booking-id="' + id + '"]');
    if (!tr) return;
    if (patch.status) {
      var cell = tr.querySelector(".status-cell");
      if (cell) cell.innerHTML = statusBadge(patch.status);
      var sel = tr.querySelector("select[name=new_status]");
      if (sel) {
        sel.value = patch.status;
        sel.dataset.current = patch.status;
      }
    }
    if (patch.payment_status) {
      var small = tr.cells[8] && tr.cells[8].querySelector("small");
      if (small) small.textContent = patch.payment_status;
    }
  }

  /* ---------------- actions ---------------- */
  modal.addEventListener("change", function (e) {
    if (e.target.id === "ackDetails" || e.target.id === "ackReceipt")
      syncConfirmBtn();
  });

  modal.addEventListener("click", function (e) {
    var btn = e.target.closest("button");
    if (!btn || !cur) return;
    var action = btn.dataset.action;

    if (action === "pay-verify" || action === "pay-undo") {
      btn.disabled = true;
      SV.post("booking-payment-review.php", {
        booking_id: cur.booking_id,
        decision: action === "pay-verify" ? "verified" : "submitted",
      })
        .then(function (r) {
          SV.toast(
            action === "pay-verify"
              ? "Payment marked as verified (manual review recorded)."
              : "Verification removed.",
            "ok",
          );
          updateRow(cur.booking_id, { payment_status: r.payment_status });
          load(cur.booking_id, { keepTab: true });
        })
        .catch(function (err) {
          SV.toast(err.message, "bad");
          btn.disabled = false;
        });
    } else if (action === "agr-open") {
      SV.agreement.open({ bookingId: cur.booking_id });
    } else if (action === "agr-generate") {
      btn.disabled = true;
      SV.post("agreement-generate.php", {
        booking_id: cur.booking_id,
        regenerate: "0",
      })
        .then(function () {
          SV.agreement.open({ bookingId: cur.booking_id });
          load(cur.booking_id, { keepTab: true });
        })
        .catch(function (err) {
          SV.toast(err.message, "bad");
          btn.disabled = false;
        });
    } else if (btn.id === "bkConfirmBtn") {
      confirmBooking(btn);
    } else if (action === "reject-ask") {
      $("bkRejectArea").innerHTML =
        '<span class="small">Cancel this booking? </span>' +
        '<button type="button" class="btn btn-outline btn-sm" data-action="reject-yes" style="color:#991b1b">Yes, cancel it</button> ' +
        '<button type="button" class="btn btn-outline btn-sm" data-action="reject-no">No</button>';
    } else if (action === "reject-no") {
      renderReject(cur);
    } else if (action === "reject-yes") {
      // reuse the page's existing status-update handler (booking status only; payment status untouched)
      var f = document.createElement("form");
      f.method = "post";
      f.action = location.pathname + location.search;
      [
        ["booking_id", cur.booking_id],
        ["new_status", "cancelled"],
        ["csrf_token", SV.csrf],
      ].forEach(function (p) {
        var i = document.createElement("input");
        i.type = "hidden";
        i.name = p[0];
        i.value = p[1];
        f.appendChild(i);
      });
      document.body.appendChild(f);
      f.submit();
    }
  });

  function confirmBooking(btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = "Confirming…";
    var id = cur.booking_id;
    SV.post("booking-confirm.php", {
      booking_id: id,
      ack_details: "1",
      ack_receipt: "1",
    })
      .then(function (r) {
        updateRow(id, { status: "confirmed" });
        SV.closeModal(modal);
        SV.toast(
          "Booking confirmed. Review the agreement, then click Send Agreement.",
          "ok",
        );
        SV.agreement.open({ bookingId: id, error: r.agreement_error || null });
      })
      .catch(function (err) {
        SV.toast(err.message, "bad");
        btn.textContent = "Confirm Booking";
        syncConfirmBtn();
      });
  }

  /* ---------------- entry points ---------------- */
  window.svOpenBooking = function (id, focusConfirm) {
    load(id, { focusConfirm: !!focusConfirm });
  };

  document.addEventListener("click", function (e) {
    var vb = e.target.closest("[data-view-booking]");
    if (vb) {
      load(vb.dataset.viewBooking);
      return;
    }
    var tr = e.target.closest("tr.row-clickable");
    if (tr && !e.target.closest("button, a, select, input, form, label"))
      load(tr.dataset.bookingId);
  });

  var auto = parseInt(document.body.dataset.open || "0", 10);
  if (auto > 0) load(auto);
})();
