<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Help & Feedback - SnapVenture</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/inquiry.css" />
    <link rel="stylesheet" href="../css/inquiry-page.css" />
  </head>
  <body>
    <svg width="0" height="0" style="position: absolute" aria-hidden="true">
      <symbol id="i-chat" viewBox="0 0 24 24">
        <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
      </symbol>
      <symbol id="i-help" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" />
        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01" />
      </symbol>
      <symbol id="i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></symbol>
      <symbol id="i-x" viewBox="0 0 24 24">
        <path d="M18 6 6 18M6 6l12 12" />
      </symbol>
      <symbol id="i-mail" viewBox="0 0 24 24">
        <rect width="20" height="16" x="2" y="4" rx="2" />
        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
      </symbol>
      <symbol id="i-phone" viewBox="0 0 24 24">
        <path
          d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"
        />
      </symbol>
      <symbol id="i-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 6v6l4 2" />
      </symbol>
      <symbol id="i-pin" viewBox="0 0 24 24">
        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
        <circle cx="12" cy="10" r="3" />
      </symbol>
      <symbol id="i-camera" viewBox="0 0 24 24">
        <path
          d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"
        />
        <circle cx="12" cy="13" r="3" />
      </symbol>
      <symbol id="i-box" viewBox="0 0 24 24">
        <path
          d="m7.5 4.27 9 5.15M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16ZM3.3 7 12 12l8.7-5M12 22V12"
        />
      </symbol>
      <symbol id="i-card" viewBox="0 0 24 24">
        <rect width="20" height="14" x="2" y="5" rx="2" />
        <path d="M2 10h20" />
      </symbol>
      <symbol id="i-check" viewBox="0 0 24 24">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <path d="m9 11 3 3L22 4" />
      </symbol>
      <symbol id="i-star" viewBox="0 0 24 24">
        <polygon
          points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
        />
      </symbol>
    </svg>

    <header class="site-header">
      <div class="container navbar">
        <a href="../index.php"
          ><img class="logo" src="../images/snapventure_logo.jpg" alt="SnapVenture"
        /></a>
        <nav class="nav-links">
          <a href="../index.php" class="">Home</a
          ><a href="../pages/services.php" class="">Services</a
          ><a href="../pages/booking.php" class="">Book Now</a
          ><a href="../pages/inquiry.php" class="active">Help & Feedback</a>
        </nav>
        <button class="menu-btn">☰</button>
      </div>
      <nav class="mobile-nav">
        <a href="../index.php" class="">Home</a
        ><a href="../pages/services.php" class="">Services</a
        ><a href="../pages/booking.php" class="">Book Now</a
        ><a href="../pages/inquiry.php" class="active">Help & Feedback</a>
      </nav>
    </header>

    <main class="hp-page">
      <section class="hp-banner">
        <h1>Help & Feedback</h1>
        <p>
          Get instant answers to your questions or share your experience with us
        </p>
      </section>

      <div class="hp-container">
        <!-- Quick Information (cards + details are built from QUICK_INFO in inquiry-page.js) -->
        <section class="hp-quick">
          <h2>Quick Information</h2>
          <p class="hp-sub">Click on any topic below for instant answers</p>
          <div class="hp-topics" id="topics"></div>
          <div class="hp-detail" id="detail" hidden>
            <button
              type="button"
              class="hp-x"
              id="detailClose"
              aria-label="Close"
            >
              <svg class="ic"><use href="#i-x" /></svg>
            </button>
            <h3 id="detailTitle"></h3>
            <div class="hp-detail-body" id="detailBody"></div>
          </div>
        </section>

        <div class="hp-layout">
          <div class="hp-left">
            <!-- FAQ (built from FAQS in inquiry-page.js) -->
            <section class="hp-card hp-faq">
              <div class="hp-card-head">
                <h2>Frequently Asked Questions</h2>
                <p>Find quick answers to common questions</p>
              </div>
              <div id="faqList"></div>
            </section>

            <!-- Feedback -->
            <section class="hp-card" id="feedback">
              <div class="hp-fb-head">
                <span class="hp-badge"
                  ><svg class="ic"><use href="#i-chat" /></svg
                ></span>
                <div>
                  <h2>Send Us Your Feedback</h2>
                  <p>We value your thoughts and suggestions</p>
                </div>
              </div>
              <form id="fbForm" novalidate>
                <div class="hp-row2">
                  <div class="hp-f">
                    <label for="fbName">Full Name</label>
                    <input
                      id="fbName"
                      placeholder="Enter your full name"
                      autocomplete="name"
                    />
                  </div>
                  <div class="hp-f">
                    <label for="fbEmail">Email Address</label>
                    <input
                      id="fbEmail"
                      type="email"
                      placeholder="your.email@gmail.com"
                      autocomplete="email"
                    />
                  </div>
                </div>
                <div class="hp-f">
                  <label for="fbPhone">Phone Number</label>
                  <input
                    id="fbPhone"
                    type="tel"
                    placeholder="+63 912 345 6789"
                    autocomplete="tel"
                  />
                </div>
                <div class="hp-f">
                  <label for="fbService">Service Used</label>
                  <select id="fbService">
                    <option value="">Select a service...</option>
                    <option>Camera Rental</option>
                    <option>Photobooth Service</option>
                    <option>Both Services</option>
                    <option>General Feedback</option>
                  </select>
                </div>
                <div class="hp-f">
                  <label>Rate Your Experience</label>
                  <div class="hp-rating" id="rating"></div>
                </div>
                <div class="hp-f">
                  <label for="fbMessage">Your Feedback</label>
                  <textarea
                    id="fbMessage"
                    placeholder="Tell us about your experience..."
                  ></textarea>
                </div>
                <p class="hp-error" id="fbError" hidden></p>
                <button type="submit" class="hp-submit">Submit Feedback</button>
                <p class="hp-ok" id="fbOk" hidden>
                  Thank you! Your feedback has been submitted.
                </p>
              </form>
            </section>
          </div>

          <aside class="hp-right">
            <!-- Messenger: replace YOUR_PAGE_NAME with your Facebook page username -->
            <section class="hp-chat">
              <span class="hp-badge"
                ><svg class="ic"><use href="#i-chat" /></svg
              ></span>
              <h3>Prefer Instant Chat?</h3>
              <p>
                Connect with us on Facebook Messenger for real-time responses to
                your questions.
              </p>
              <a
               <a href="https://m.me/648990524958020" target="_blank" rel="noopener">Open Messenger</a>
              <small>Available Mon-Sat, 9AM-6PM</small>
            </section>

            <section class="hp-card hp-contact">
              <h2>Contact Information</h2>
              <div class="hp-ci">
                <span class="hp-badge"
                  ><svg class="ic"><use href="#i-mail" /></svg
                ></span>
                <div>
                  <small>Email</small><b>jamelamendoza5@gmail.com</b
                  >
                </div>
              </div>
              <div class="hp-ci">
                <span class="hp-badge"
                  ><svg class="ic"><use href="#i-phone" /></svg
                ></span>
                <div>
                  <small>Phone</small><b>0968 481 9660</b
                  >
                </div>
              </div>
              <div class="hp-ci">
                <span class="hp-badge"
                  ><svg class="ic"><use href="#i-clock" /></svg
                ></span>
                <div>
                  <small>Business Hours</small><b>Mon - Sat</b
                  ><span>9:00 AM - 6:00 PM</span>
                </div>
              </div>
              <div class="hp-ci">
                <span class="hp-badge"
                  ><svg class="ic"><use href="#i-pin" /></svg
                ></span>
                <div>
                  <small>Office Location</small><b>123 Photography St.</b
                  ><span>Makati City</span>
                </div>
              </div>
            </section>
          </aside>
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

    <script src="../js/inquiry-page.js"></script>
    <script src="../js/nav.js"></script>
  </body>
</html>