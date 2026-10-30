<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Services - SnapVenture</title>
    <link rel="stylesheet" href="../css/services.css" />
    <link rel="stylesheet" href="../css/services-carousel.css" />
  </head>
  <body>
    <header class="site-header">
      <div class="container navbar">
        <a href="../index.php"
          ><img
            class="logo"
            src="../images/snapventure_logo.jpg"
            alt="SnapVenture"
        /></a>
        <nav class="nav-links">
          <a href="../index.php" class="">Home</a
          ><a href="../pages/services.php" class="active">Services</a
          ><a href="../pages/booking.php" class="">Book Now</a
          ><a href="../pages/inquiry.php" class="">Help & Feedback</a>
        </nav>
        <button class="menu-btn">☰</button>
      </div>
      <nav class="mobile-nav">
        <a href="../index.php" class="">Home</a
        ><a href="../pages/services.php" class="active">Services</a
        ><a href="../pages/booking.php" class="">Book Now</a
        ><a href="../pages/inquiry.php" class="">Help & Feedback</a>
      </nav>
    </header>

    <main class="page">
      <section class="page-banner">
        <div class="container">
          <h1>Our Services</h1>
          <p>
            Professional photography equipment and photobooth packages
            tailored to your needs
          </p>
        </div>
      </section>

      <!-- ============ CAMERA RENTAL CAROUSEL ============ -->
      <section class="section light">
        <div class="container">
          <div class="section-title">
            <h2>Camera Rental Packages</h2>
            <p>Choose the perfect equipment for your photography needs</p>
          </div>

          <div id="cameraCarousel" class="pkg-carousel" aria-live="polite">
            <p class="pkg-loading">Loading camera packages…</p>
          </div>
        </div>
      </section>

      <!-- ============ PHOTOBOOTH CAROUSEL ============ -->
      <section class="section muted">
        <div class="container">
          <div class="section-title">
            <h2>Photobooth Packages</h2>
            <p>Make your events unforgettable</p>
          </div>

          <div id="photoboothCarousel" class="pkg-carousel" aria-live="polite">
            <p class="pkg-loading">Loading photobooth packages…</p>
          </div>
        </div>
      </section>

      <!-- ============ ACCESSORIES / ADD-ONS ============ -->
      <section class="section light">
        <div class="container">
          <div class="section-title">
            <h2>Add-Ons &amp; Extras</h2>
            <p>Enhance your booking with these optional extras</p>
          </div>

          <h3 class="addon-group-title">For Camera Rentals</h3>
          <div id="cameraAddons" class="grid-4">
            <p class="pkg-loading">Loading…</p>
          </div>

          <h3 class="addon-group-title">For Photobooth Events</h3>
          <div id="photoboothAddons" class="grid-4">
            <p class="pkg-loading">Loading…</p>
          </div>
        </div>
      </section>
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
    <script src="../js/services-carousel.js"></script>
  </body>
</html>