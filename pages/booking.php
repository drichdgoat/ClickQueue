<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Booking - SnapVenture</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/booking.css" />
    <link rel="stylesheet" href="../css/booking-form.css" />
    <link rel="stylesheet" href="../css/booking-modal.css" />
  </head>
  <body>
    <svg width="0" height="0" style="position: absolute" aria-hidden="true">
      <symbol id="i-user" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
      <symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></symbol>
      <symbol id="i-mail" viewBox="0 0 24 24"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></symbol>
      <symbol id="i-camera" viewBox="0 0 24 24"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></symbol>
      <symbol id="i-calendar" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
      <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></symbol>
      <symbol id="i-pin" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></symbol>
      <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol>
      <symbol id="i-left" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></symbol>
      <symbol id="i-right" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
      <symbol id="i-upload" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24"><path d="M21.8 10A10 10 0 1 1 17 3.3"/><path d="m9 11 3 3L22 4"/></symbol>
      <symbol id="i-doc" viewBox="0 0 24 24"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8"/></symbol>
    </svg>

    <header class="site-header">
      <div class="container navbar">
        <a href="../index.php"><img class="logo" src="../images/snapventure_logo.jpg" alt="SnapVenture" /></a>
        <nav class="nav-links">
          <a href="../index.php">Home</a><a href="../pages/services.php">Services</a
          ><a href="../pages/booking.php" class="active">Book Now</a
          ><a href="../pages/inquiry.php">Help &amp; Feedback</a>
        </nav>
        <button class="menu-btn">☰</button>
      </div>
      <nav class="mobile-nav">
        <a href="../index.php">Home</a><a href="../pages/services.php">Services</a
        ><a href="../pages/booking.php" class="active">Book Now</a
        ><a href="../pages/inquiry.php">Help &amp; Feedback</a>
      </nav>
    </header>

    <main class="bk-page">
      <section class="bk-banner">
        <h1>Book Your Service</h1>
        <p>Check availability and reserve your equipment in just a few steps</p>
      </section>

      <div class="bk-container">
        <div class="bk-layout" id="bkLayout">
          <div class="bk-left">

            <!-- 1. Customer Information -->
            <section class="bk-card" id="cardCustomer">
              <h2>Customer Information</h2>
              <div class="bk-field">
                <label for="fullName">Full Name</label>
                <div class="bk-input"><svg class="ic"><use href="#i-user"/></svg>
                  <input id="fullName" placeholder="Enter your full name" autocomplete="name" />
                </div>
              </div>
              <div class="bk-field">
                <label for="phone">Contact Number</label>
                <div class="bk-input"><svg class="ic"><use href="#i-phone"/></svg>
                  <input id="phone" type="tel" placeholder="+63 912 345 6789" autocomplete="tel" />
                </div>
              </div>
              <div class="bk-field">
                <label for="facebook">Facebook / Messenger Name</label>
                <div class="bk-input"><svg class="ic"><use href="#i-chat"/></svg>
                  <input id="facebook" placeholder="Enter your Facebook/Messenger name" />
                </div>
              </div>
              <div class="bk-field">
                <label for="email">Email Address</label>
                <div class="bk-input"><svg class="ic"><use href="#i-mail"/></svg>
                  <input id="email" type="email" placeholder="your.email@gmail.com" autocomplete="email" />
                </div>
              </div>
            </section>

            <!-- 2. Select Service & Package -->
            <section class="bk-card is-locked" id="cardService">
              <h2>Select Service</h2>
              <div class="bk-body">
                <div class="bk-field">
                  <label for="serviceType">Service Type</label>
                  <select id="serviceType" class="bk-select">
                    <option value="" disabled selected>Choose a service...</option>
                    <option value="camera_rental">Camera Rental</option>
                    <option value="photobooth">Photobooth</option>
                  </select>
                </div>
                <div id="packageGrid" class="pkg-pick-grid"></div>
              </div>
              <p class="bk-lock">Please complete your customer information first</p>
            </section>

            <!-- 3. Rental / Package Details (contextual) -->
            <section class="bk-card is-locked" id="cardDetails">
              <h2>Rental Details</h2>
              <div class="bk-body" id="detailsBody"></div>
              <p class="bk-lock">Please select a package first</p>
            </section>

            <!-- 4. Select Date -->
            <section class="bk-card is-locked" id="cardDate">
              <h2>Select Date</h2>
              <div class="bk-body">
                <div class="bk-cal-head">
                  <button type="button" class="bk-nav" id="calPrev" aria-label="Previous month"><svg class="ic"><use href="#i-left"/></svg></button>
                  <strong id="calTitle"></strong>
                  <button type="button" class="bk-nav" id="calNext" aria-label="Next month"><svg class="ic"><use href="#i-right"/></svg></button>
                </div>
                <div class="bk-cal-week"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                <div class="bk-cal-grid" id="calGrid"></div>
                <div class="bk-legend"><span><i class="bk-sw"></i>Selected</span><span><i class="bk-sw booked"></i>Booked</span></div>
              </div>
              <p class="bk-lock">Please select a service and package first</p>
            </section>

            <!-- 5. Event Details -->
            <section class="bk-card is-locked" id="cardEvent">
              <h2>Event Details</h2>
              <div class="bk-body">
                <div class="bk-field">
                  <label for="eventLocation">Event Location</label>
                  <div class="bk-input"><svg class="ic"><use href="#i-pin"/></svg>
                    <input id="eventLocation" placeholder="Enter event location" />
                  </div>
                </div>
                <div class="bk-row2">
                  <div class="bk-field">
                    <label for="startTime">Start Time</label>
                    <div class="bk-input"><svg class="ic"><use href="#i-clock"/></svg><input id="startTime" type="time" /></div>
                  </div>
                  <div class="bk-field">
                    <label for="endTime">End Time</label>
                    <div class="bk-input"><svg class="ic"><use href="#i-clock"/></svg><input id="endTime" type="time" /></div>
                  </div>
                </div>
                <p class="bk-error" id="timeError" hidden>End time must be later than start time.</p>
                <div class="bk-field">
                  <label for="notes">Additional Notes</label>
                  <textarea id="notes" placeholder="Any special requests or notes for your event"></textarea>
                </div>
              </div>
              <p class="bk-lock">Please select a date first</p>
            </section>

            <!-- 6. Payment Method & Proof -->
            <section class="bk-card is-locked" id="cardPayment">
              <h2>Payment Method</h2>
              <div class="bk-body">
                <div class="methods" id="methods"></div>
                <div class="instructions" id="instructions" hidden></div>

                <div class="bk-field" style="margin-top: 20px" id="proofField">
                  <label>Upload Proof of Payment</label>
                  <div class="dropzone" id="dropzone" role="button" tabindex="0"></div>
                  <input class="sr-only" type="file" id="proofInput" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" />
                  <p class="bk-error" id="proofError" hidden></p>
                </div>

                <label class="agree">
                  <input type="checkbox" id="agree" />
                  <span>I have read and agree to the rental terms and conditions. I understand I am responsible for the equipment/service during the booked period.</span>
                </label>
              </div>
              <p class="bk-lock">Please complete event details first</p>
            </section>
          </div>

          <!-- Reservation Summary -->
          <aside class="bk-card bk-summary">
            <h2>Reservation Summary</h2>
            <p class="bk-sum-empty" id="sumEmpty">Fill in your information to continue</p>
            <div id="sumRows"></div>
            <div id="sumPricing"></div>
            <div class="bk-notice">
              <svg class="ic"><use href="#i-alert"/></svg>
              <div>
                <b id="noticeTitle">Down Payment Required</b>
                <p id="noticeText">
                  A 50% down payment is required to confirm your camera rental booking.
                  After payment confirmation, a PDF copy of the rental agreement will be
                  automatically generated and sent to your email.
                </p>
              </div>
            </div>
            <p class="bk-error" id="submitError" hidden></p>
            <button type="button" class="bk-pay" id="proceedBtn" disabled>Submit Booking</button>
            <p class="confirm-hint" id="confirmHint"></p>
          </aside>
        </div>

        <!-- shown as a centered popup after successful submission -->
        <div class="bk-modal-overlay" id="bkSuccessOverlay" hidden>
        <section class="bk-card bk-success" id="bkSuccess" role="dialog" aria-modal="true" aria-labelledby="bkSuccessTitle" hidden>
          <button type="button" class="bk-modal-close" id="bkSuccessClose" aria-label="Close">&#10005;</button>
          <h2 id="bkSuccessTitle">Reservation Submitted</h2>
          <p>Reference No. <b id="okRef"></b></p>
          <div id="okRows"></div>
          <div class="bk-notice">
            <svg class="ic"><use href="#i-alert"/></svg>
            <div>
              <b>What's Next</b>
              <p>
                We'll verify your payment and confirm your booking. A PDF copy of the
                rental agreement will be emailed to you once confirmed.
              </p>
            </div>
          </div>
          <div class="bk-actions">
            <a class="bk-pay" href="booking.php">Book Another Service</a>
            <a class="bk-pay alt" href="../index.php">Back to Home</a>
          </div>
        </section>
        </div>
      </div>
    </main>

    <footer class="footer">
      <div class="container">
        <div class="footer-grid">
          <div class="footer-brand">
            <img class="logo" src="../images/snapventure_logo.jpg" alt="SnapVenture" />
            <p>Camera rentals and photobooth experiences for your special moments.</p>
          </div>
          <div>
            <h3>Explore</h3>
            <div class="footer-links">
              <a href="../pages/services.php">Services</a>
              <a href="../pages/booking.php">Book Now</a>
              <a href="../pages/inquiry.php">Help &amp; Feedback</a>
            </div>
          </div>
          <div>
            <h3>Get in Touch</h3>
            <div class="footer-links">
              <a href="mailto:jamelamendoza5@gmail.com">jamelamendoza5@gmail.com</a>
              <a href="tel:09684819660">0968 481 9660</a>
            </div>
          </div>
        </div>
        <div class="copyright">© 2026 SnapVenture Photography Services. All Rights Reserved.</div>
      </div>
    </footer>

    <script src="../js/nav.js"></script>
    <script src="../js/booking-form.js"></script>
  </body>
</html>