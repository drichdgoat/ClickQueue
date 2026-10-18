<?php /* Agreement preview / send modal. Behaviour: js/agreement-modal.js */ ?>
<div class="modal-overlay" id="agrModal">
  <div class="modal modal-xl" role="dialog" aria-modal="true" aria-labelledby="agrTitle">
    <div class="modal-head">
      <div>
        <h2 id="agrTitle">Agreement</h2>
        <div class="sub" id="agrSub"></div>
      </div>
      <button type="button" class="icon-btn" data-close aria-label="Close">✕</button>
    </div>
    <div id="agrLoading" class="viewer-empty">Loading…</div>
    <div id="agrNotice"></div>
    <div class="agr-grid" id="agrBody" hidden>
      <div id="agrSummary"></div>
      <div>
        <div class="agr-frame" id="agrFrameWrap"></div>
        <div id="agrHistory"></div>
      </div>
    </div>
    <div class="modal-actions" id="agrActions"></div>
  </div>
</div>
<div class="toast-wrap" id="toastWrap" aria-live="polite"></div>
