/* SnapVenture Admin - Agreement Management page: tabs, template editor, PDF upload, representative settings */
(function () {
  "use strict";
  var SV = window.SV;
  if (!SV) return;
  var $ = function (id) { return document.getElementById(id); };
  var TPL = {};
  try { TPL = JSON.parse($("tplData").textContent || "{}"); } catch (e) { TPL = {}; }

  /* ---------- page tabs ---------- */
  $("pageTabs").addEventListener("click", function (e) {
    var b = e.target.closest("button[data-tab]");
    if (!b) return;
    document.querySelectorAll("#pageTabs button").forEach(function (x) { x.classList.toggle("active", x === b); });
    ["sent", "templates", "representative"].forEach(function (t) { $("tab-" + t).classList.toggle("active", t === b.dataset.tab); });
    history.replaceState(null, "", "?tab=" + b.dataset.tab);
  });

  /* ---------- sent agreements: open preview/send modal ---------- */
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-agr-open]");
    if (b) SV.agreement.open({ bookingId: b.dataset.booking, agreementId: b.dataset.agreement });
  });
  SV.onAgreementChange = function () { /* list refreshes on next page load */ };

  /* ---------- small confirm dialog ---------- */
  var cfm = $("cfmModal"), cfmAction = null;
  function ask(title, text, okLabel, fn) {
    $("cfmTitle").textContent = title; $("cfmText").textContent = text; $("cfmOk").textContent = okLabel || "Confirm";
    cfmAction = fn; SV.openModal(cfm);
  }
  $("cfmOk").addEventListener("click", function () {
    var fn = cfmAction; cfmAction = null; SV.closeModal(cfm);
    if (fn) fn();
  });

  function reloadTemplates() { location.href = "?tab=templates"; }
  function simple(action, id, okMsg) {
    SV.post("agreement-template-save.php", { action: action, template_id: id })
      .then(function () { SV.toast(okMsg, "ok"); setTimeout(reloadTemplates, 600); })
      .catch(function (e) { SV.toast(e.message, "bad"); });
  }

  /* ---------- template list actions ---------- */
  $("tab-templates").addEventListener("click", function (e) {
    var b = e.target.closest("button[data-tpl]");
    if (!b) return;
    var id = b.dataset.id, a = b.dataset.tpl;
    if (a === "edit") openEditor(TPL[id]);
    else if (a === "preview") { openEditor(TPL[id]); previewTemplate(); }
    else if (a === "activate") ask("Set as active template?", "Future confirmed bookings will use this template. Agreements already generated are not changed.", "Set active", function () { simple("activate", id, "Template activated."); });
    else if (a === "deactivate") ask("Deactivate this template?", "Until another template is active, confirming a booking will not be able to generate an agreement.", "Deactivate", function () { simple("deactivate", id, "Template deactivated."); });
    else if (a === "archive") ask("Archive this template?", "It is hidden from this list but kept in the database, because existing agreements may reference it. It is not deleted.", "Archive", function () { simple("archive", id, "Template archived."); });
  });

  /* ---------- editor ---------- */
  var tplModal = $("tplModal"), editing = null, previewUrl = null;
  function editorErr(msg) { var n = $("tplErr"); n.hidden = !msg; n.textContent = msg || ""; }

  function openEditor(t) {
    editing = t || null;
    $("tplTitle").textContent = t ? "Edit template (saves as v" + (t.version + 1) + ")" : "New editable template";
    $("tplName").value = t ? t.title : "";
    $("tplEditor").innerHTML = t ? t.content_html : "<h3>Agreement terms</h3><p><br></p>";
    $("tplPreviewWrap").innerHTML = '<div class="viewer-empty">Click “Preview” to render the PDF with sample data.</div>';
    editorErr("");
    SV.openModal(tplModal);
  }
  $("newTplBtn").addEventListener("click", function () { openEditor(null); });

  tplModal.querySelector(".editor-toolbar").addEventListener("mousedown", function (e) {
    var b = e.target.closest("button[data-cmd]");
    if (!b) return;
    e.preventDefault();                       // keep the selection in the editor
    $("tplEditor").focus();
    document.execCommand(b.dataset.cmd, false, b.dataset.val || null);
  });
  $("phList").addEventListener("mousedown", function (e) {
    var b = e.target.closest("button[data-ph]");
    if (!b) return;
    e.preventDefault();
    $("tplEditor").focus();
    document.execCommand("insertText", false, "{{" + b.dataset.ph + "}}");
  });

  function formData(activate) {
    var fd = new FormData();
    fd.append("action", "save_editable");
    fd.append("title", $("tplName").value);
    fd.append("content_html", $("tplEditor").innerHTML);
    if (editing) fd.append("family_id", editing.family_id);
    if (activate) fd.append("activate", "1");
    return fd;
  }

  function previewTemplate() {
    editorErr("");
    var btn = $("tplPreviewBtn"); btn.disabled = true;
    var fd = new FormData();
    fd.append("title", $("tplName").value);
    fd.append("content_html", $("tplEditor").innerHTML);
    fd.append("csrf_token", SV.csrf);
    fetch(SV.api + "agreement-template-preview.php", { method: "POST", credentials: "same-origin", body: fd })
      .then(function (res) {
        var ct = res.headers.get("Content-Type") || "";
        if (res.ok && ct.indexOf("application/pdf") === 0) return res.blob();
        return res.text().then(function (t) {
          var msg = "Preview failed (HTTP " + res.status + ").";
          try { msg = JSON.parse(t).error || msg; } catch (e) { /* keep default */ }
          throw new Error(msg);
        });
      })
      .then(function (blob) {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(blob);
        $("tplPreviewWrap").innerHTML = '<iframe title="Template preview" src="' + previewUrl + '#view=FitH"></iframe>';
      })
      .catch(function (e) { editorErr(e.message); })
      .then(function () { btn.disabled = false; });
  }
  $("tplPreviewBtn").addEventListener("click", previewTemplate);

  function saveTemplate(activate, btn) {
    editorErr("");
    btn.disabled = true;
    SV.post("agreement-template-save.php", formData(activate))
      .then(function () { SV.toast(activate ? "Saved and set active." : "Saved as a new version.", "ok"); setTimeout(reloadTemplates, 600); })
      .catch(function (e) { editorErr(e.message); btn.disabled = false; });
  }
  $("tplSaveBtn").addEventListener("click", function () { saveTemplate(false, this); });
  $("tplSaveActiveBtn").addEventListener("click", function () { saveTemplate(true, this); });
  tplModal.__onClose = function () { if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; } };

  /* ---------- PDF upload ---------- */
  var pdfModal = $("pdfModal");
  $("uploadTplBtn").addEventListener("click", function () {
    $("pdfName").value = ""; $("pdfFile").value = ""; $("pdfActive").checked = false; $("pdfErr").hidden = true;
    SV.openModal(pdfModal);
  });
  $("pdfSaveBtn").addEventListener("click", function () {
    var btn = this, err = $("pdfErr");
    err.hidden = true;
    var f = $("pdfFile").files[0];
    if (!f) { err.hidden = false; err.textContent = "Please choose a PDF file."; return; }
    var fd = new FormData();
    fd.append("action", "upload_pdf");
    fd.append("title", $("pdfName").value);
    fd.append("pdf", f);
    if ($("pdfActive").checked) fd.append("activate", "1");
    btn.disabled = true;
    SV.post("agreement-template-save.php", fd)
      .then(function () { SV.toast("PDF template uploaded.", "ok"); setTimeout(reloadTemplates, 600); })
      .catch(function (e) { err.hidden = false; err.textContent = e.message; btn.disabled = false; });
  });

  /* ---------- representative ---------- */
  $("repForm").addEventListener("submit", function (e) {
    e.preventDefault();
    var btn = $("repSave"); btn.disabled = true;
    SV.post("agreement-settings-save.php", new FormData(this))
      .then(function () { SV.toast("Representative saved.", "ok"); setTimeout(function () { location.href = "?tab=representative"; }, 600); })
      .catch(function (err) { SV.toast(err.message, "bad"); btn.disabled = false; });
  });

  /* open a tab requested in the URL when the editor modal etc. are not needed */
})();
