<?php /* Booking details / review modal. Filled by js/booking-details.js. */ ?>
<div class="modal-overlay" id="bkModal">
  <div class="modal modal-lg" role="dialog" aria-modal="true" aria-labelledby="bkTitle">
    <div class="modal-head">
      <div>
        <h2 id="bkTitle">Booking Details</h2>
        <div class="sub" id="bkSub"></div>
      </div>
      <button type="button" class="icon-btn" data-close aria-label="Close">✕</button>
    </div>
    <div class="tabs" role="tablist">
      <button type="button" class="active" data-tab="info">Booking Information</button>
      <button type="button" data-tab="payment">Proof of Payment</button>
      <button type="button" data-tab="req">Proof of Requirements</button>
      <button type="button" data-tab="agreement">Agreement</button>
    </div>
    <div id="bkLoading" class="viewer-empty">Loading…</div>
    <div id="bkError" class="notice bad" hidden></div>
    <div class="tab-panel active" id="tab-info"></div>
    <div class="tab-panel" id="tab-payment"></div>
    <div class="tab-panel" id="tab-req"></div>
    <div class="tab-panel" id="tab-agreement"></div>
    <div id="bkConfirmBox"></div>
    <div class="modal-actions">
      <span id="bkRejectArea"></span>
      <button type="button" class="btn btn-outline" data-close>Close</button>
    </div>
  </div>
</div>
