/* SnapVenture Admin - agreement preview / send modal.
   Opening this modal NEVER generates or emails anything by itself:
   - generation happens only on booking confirmation (server) or an explicit "Generate / Regenerate" click
   - emailing happens only when the admin clicks Send Agreement / Resend Agreement */
(function () {
  "use strict";
  var SV = window.SV;
  var modal = document.getElementById("agrModal");
  if (!SV || !modal) return;

  var $ = function (id) {
    return document.getElementById(id);
  };
  var esc = SV.esc;
  var st = null; // { booking, agr, history, error, busy, armed }

  function statusPill(a) {
    var cls =
      a.status === "sent" ? "ok" : a.status === "failed" ? "bad" : "info";
    return '<span class="pill ' + cls + '">' + esc(a.status_label) + "</span>";
  }
  function kv(k, v) {
    return (
      '<div class="full"><span class="k">' +
      esc(k) +
      '</span><span class="v">' +
      (v || "<span class=muted>N/A</span>") +
      "</span></div>"
    );
  }
  function notice(kind, html) {
    return '<div class="notice ' + kind + '">' + html + "</div>";
  }

  function setupLink(code) {
    if (code === "rep_not_configured")
      return ' <a href="agreements.php?tab=representative">Open representative settings</a>';
    if (code === "no_template")
      return ' <a href="agreements.php?tab=templates">Open templates</a>';
    return "";
  }

  function render() {
    var b = st.booking,
      a = st.agr;
    $("agrLoading").hidden = true;
    $("agrBody").hidden = false;
    $("agrTitle").textContent = a
      ? "Agreement " + a.agreement_ref
      : "Agreement";
    $("agrSub").textContent = b.reference_no + " · " + b.customer.name;

    var equip = (b.package_name ? [b.package_name] : []).concat(
      b.addons.map(function (x) {
        return x.name + (x.qty > 1 ? " × " + x.qty : "");
      }),
    );
    var h = '<div class="kv">';
    h += kv("Booking reference", esc(b.reference_no));
    h += kv("Customer", esc(b.customer.name));
    h += kv("Customer email", esc(b.customer.email));
    h += kv(
      "Service / package",
      esc(b.service_label) +
        (b.package_name ? " — " + esc(b.package_name) : ""),
    );
    h += kv(
      "Equipment / add-ons",
      equip.length ? equip.map(esc).join("<br>") : "",
    );
    h += kv(
      "Event",
      esc(SV.date(b.event.date)) +
        (b.event.time_label ? "<br>" + esc(b.event.time_label) : "") +
        (b.event.location ? "<br>" + esc(b.event.location) : ""),
    );
    h += kv("Booking total", esc(SV.money(b.amounts.grand_total)));
    if (a) {
      h += kv(
        "Agreement status",
        statusPill(a) +
          (a.sent_at
            ? '<br><span class="small muted">Accepted by mail server ' +
              esc(SV.datetime(a.sent_at)) +
              " (sent " +
              a.send_count +
              "×)</span>"
            : ""),
      );
      h += kv(
        "Template",
        "Version " +
          a.template_version +
          (a.personalized
            ? " (personalized)"
            : " (static PDF — not personalized)"),
      );
      h += kv("Generated", esc(SV.datetime(a.generated_at)));
      if (!a.is_current)
        h += kv(
          "Note",
          "<span class='pill warn'>Older revision — superseded</span>",
        );
    }
    h += "</div>";
    $("agrSummary").innerHTML = h;

    var wrap = $("agrFrameWrap");
    if (a) {
      var src = SV.url("agreement-file.php", { agreement_id: a.agreement_id });
      wrap.innerHTML =
        '<iframe title="Agreement PDF preview" src="' +
        esc(src) +
        '#toolbar=0&navpanes=0&view=FitH"></iframe>' +
        '<div class="viewer-empty small">If the preview does not appear, use “Open in new tab”.</div>';
    } else {
      wrap.innerHTML =
        '<div class="viewer-empty">No agreement has been generated for this booking yet.</div>';
    }

    var n = "";
    if (st.error)
      n += notice(
        "bad",
        "<b>Agreement could not be generated.</b> " +
          esc(st.error.message) +
          setupLink(st.error.code),
      );
    if (a && !a.personalized)
      n += notice(
        "warn",
        "This agreement comes from an uploaded static PDF. It is sent exactly as uploaded and does <b>not</b> contain this customer’s booking details. Use an editable template for personalized agreements.",
      );
    if (a && a.status === "failed" && a.last_error)
      n += notice(
        "bad",
        "<b>Last send attempt failed:</b> " +
          esc(a.last_error) +
          " You can retry below; the PDF was kept.",
      );
    if (b.status !== "confirmed")
      n += notice(
        "warn",
        "This booking is not confirmed (status: " +
          esc(b.status) +
          "), so a new agreement cannot be generated.",
      );
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(b.customer.email || ""))
      n += notice(
        "warn",
        "The customer’s email address looks invalid. Sending will fail until it is corrected.",
      );
    $("agrNotice").innerHTML = n;

    renderActions();
  }

  function renderActions() {
    var a = st.agr,
      b = st.booking,
      h = "";
    if (a) {
      h +=
        '<button type="button" class="btn btn-outline" id="agrHistBtn">Send history</button>';
      if (b.status === "confirmed" && a.is_current) {
        h +=
          '<button type="button" class="btn btn-outline" id="agrRegenBtn">' +
          (st.armedRegen ? "Click again to confirm" : "Regenerate") +
          "</button>";
      }
      h +=
        '<a class="btn btn-outline" target="_blank" rel="noopener" href="' +
        esc(SV.url("agreement-file.php", { agreement_id: a.agreement_id })) +
        '">Open in new tab</a>';
      h +=
        '<a class="btn btn-outline" href="' +
        esc(
          SV.url("agreement-file.php", {
            agreement_id: a.agreement_id,
            download: 1,
          }),
        ) +
        '">Download PDF</a>';
    } else if (b.status === "confirmed") {
      h +=
        '<button type="button" class="btn btn-primary" id="agrGenBtn"' +
        (st.busy ? " disabled" : "") +
        ">" +
        (st.error ? "Try again" : "Generate agreement") +
        "</button>";
    }
    h +=
      '<button type="button" class="btn btn-outline" data-close>Close</button>';
    if (a && a.is_current) {
      var resend = a.status === "sent";
      var label = st.busy
        ? "Sending…"
        : resend
          ? st.armedResend
            ? "Click again to resend"
            : "Resend Agreement"
          : a.status === "failed"
            ? "Retry Send"
            : "Send Agreement";
      h +=
        '<button type="button" class="btn btn-primary" id="agrSendBtn"' +
        (st.busy ? " disabled" : "") +
        ">" +
        label +
        "</button>";
    }
    $("agrActions").innerHTML = h;
  }

  function loadHistory() {
    if (!st.agr) return;
    SV.get("agreement-history.php", { agreement_id: st.agr.agreement_id })
      .then(function (r) {
        var rows = r.history || [];
        var h = '<div class="history"><b>Send history</b>';
        if (!rows.length) h += '<div class="muted">No send attempts yet.</div>';
        rows.forEach(function (x) {
          var res =
            x.result === "accepted"
              ? "Accepted by mail server"
              : x.result === "failed"
                ? "Failed"
                : "Pending / unknown";
          h +=
            "<div>" +
            esc(SV.datetime(x.attempted_at)) +
            " · " +
            esc(x.attempt_type) +
            " to " +
            esc(x.recipient) +
            " · <b>" +
            res +
            "</b>" +
            (x.admin_name ? " · by " + esc(x.admin_name) : "") +
            (x.error_message
              ? '<br><span class="muted">' + esc(x.error_message) + "</span>"
              : "") +
            "</div>";
        });
        $("agrHistory").innerHTML = h + "</div>";
      })
      .catch(function (e) {
        SV.toast(e.message, "bad");
      });
  }

  function changed() {
    if (typeof SV.onAgreementChange === "function")
      SV.onAgreementChange(st.agr, st.booking);
  }

  function generate(regen) {
    st.busy = true;
    renderActions();
    SV.post("agreement-generate.php", {
      booking_id: st.booking.booking_id,
      regenerate: regen ? "1" : "0",
    })
      .then(function (r) {
        st.agr = r.agreement;
        st.error = null;
        st.armedRegen = false;
        SV.toast("Agreement ready to review.", "ok");
        changed();
      })
      .catch(function (e) {
        st.error = { code: (e.data && e.data.code) || "", message: e.message };
        SV.toast(e.message, "bad");
      })
      .then(function () {
        st.busy = false;
        render();
        $("agrHistory").innerHTML = "";
      });
  }

  function send() {
    if (st.busy) return;
    if (st.agr.status === "sent" && !st.armedResend) {
      st.armedResend = true;
      renderActions();
      setTimeout(function () {
        if (st) {
          st.armedResend = false;
          renderActions();
        }
      }, 5000);
      return;
    }
    st.armedResend = false;
    st.busy = true;
    renderActions(); // button disabled while the request runs
    var resend = st.agr.status === "sent";
    SV.post("agreement-send.php", {
      agreement_id: st.agr.agreement_id,
      resend: resend ? "1" : "0",
    })
      .then(function (r) {
        st.agr = r.agreement;
        SV.toast(r.message, "ok");
        changed();
      })
      .catch(function (e) {
        if (e.data && e.data.agreement) st.agr = e.data.agreement;
        SV.toast(e.message, "bad");
        changed();
      })
      .then(function () {
        st.busy = false;
        render();
        if ($("agrHistory").innerHTML) loadHistory();
      });
  }

  $("agrActions").addEventListener("click", function (e) {
    var t = e.target.closest("button");
    if (!t) return;
    if (t.id === "agrSendBtn") send();
    else if (t.id === "agrGenBtn") generate(false);
    else if (t.id === "agrHistBtn") loadHistory();
    else if (t.id === "agrRegenBtn") {
      if (!st.armedRegen) {
        st.armedRegen = true;
        renderActions();
        SV.toast(
          "Regenerating creates a NEW revision from the current active template. The previous PDF is kept.",
          "",
        );
        setTimeout(function () {
          if (st) {
            st.armedRegen = false;
            renderActions();
          }
        }, 6000);
      } else generate(true);
    }
  });

  /* opts: { bookingId, agreementId?, error? } */
  SV.agreement = {
    open: function (opts) {
      st = { booking: null, agr: null, error: opts.error || null, busy: false };
      $("agrLoading").hidden = false;
      $("agrBody").hidden = true;
      $("agrNotice").innerHTML = "";
      $("agrHistory").innerHTML = "";
      $("agrActions").innerHTML = "";
      $("agrTitle").textContent = "Agreement";
      $("agrSub").textContent = "";
      SV.openModal(modal);
      var calls = [
        SV.get("booking-details.php", { booking_id: opts.bookingId }),
      ];
      if (opts.agreementId)
        calls.push(
          SV.get("agreement-history.php", { agreement_id: opts.agreementId }),
        );
      Promise.all(calls)
        .then(function (r) {
          st.booking = r[0].booking;
          st.agr = opts.agreementId ? r[1].agreement : st.booking.agreement;
          render();
        })
        .catch(function (e) {
          $("agrLoading").hidden = true;
          $("agrNotice").innerHTML = notice("bad", esc(e.message));
          $("agrActions").innerHTML =
            '<button type="button" class="btn btn-outline" data-close>Close</button>';
        });
    },
  };
  modal.__onClose = function () {
    st = null;
    $("agrFrameWrap").innerHTML = "";
  };
})();
