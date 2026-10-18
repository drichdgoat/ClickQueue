<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Home - SnapVenture</title>
    <link rel="stylesheet" href="./css/index.css" />
    <link rel="stylesheet" href="./css/testimonial-carousel.css" />
  </head>
  <body>
    <header class="site-header">
      <div class="container navbar">
        <a href="index.php"
          ><img
            class="logo"
            src="images/snapventure_logo.jpg"
            alt="SnapVenture"
        /></a>
        <nav class="nav-links">
          <a href="index.php" class="active">Home</a
          ><a href="pages/services.php" class="">Services</a
          ><a href="pages/booking.php" class="">Book Now</a
          ><a href="pages/inquiry.php" class="">Help & Feedback</a>
        </nav>
        <button class="menu-btn" type="button" aria-label="Toggle menu" aria-expanded="false">☰</button>
      </div>
      <nav class="mobile-nav">
        <a href="index.php" class="active">Home</a
        ><a href="pages/services.php" class="">Services</a
        ><a href="pages/booking.php" class="">Book Now</a
        ><a href="pages/inquiry.php" class="">Help & Feedback</a>
      </nav>
    </header>
    <main class="page">
      <section class="hero">
        <div class="container hero-grid">
          <div>
            <h1>Capture Every Moment with SnapVenture</h1>
            <p>
              Professional camera rental and photobooth services for weddings,
              events, and special occasions. Book easily through our 
              system.
            </p>
            <a class="btn btn-primary" href="pages/booking.php">Book Now</a>
            <a class="btn btn-outline" href="pages/services.php"
              >View Services</a
            >
          </div>
          <div class="hero-images">
            <img
              src="https://images.unsplash.com/photo-1502982720700-bfff97f2ecac?auto=format&fit=crop&w=700&q=80"
            /><img src="images/photobooth_index.png" />
          </div>
        </div>
      </section>
      <section class="section muted">
        <div class="container">
          <div class="section-title">
            <h2>Featured Services</h2>
            <p>Tailored packages for every occasion</p>
          </div>
          <div class="grid-2">
            <div class="card">
              <img
                src="https://images.unsplash.com/photo-1471341971476-ae15ff5dd4ea?auto=format&fit=crop&w=900&q=80"
              />
              <h3>Camera Rental</h3>
              <p>
                High-end cameras, lenses, and accessories for your photography
                needs.
              </p>
              <ul class="check-list">
                <li>Professional camera bodies</li>
                <li>Wide selection of lenses</li>
                <li>Lighting and accessories</li>
              </ul>
              <a class="btn btn-primary" href="pages/services.php"
                >View Packages</a
              >
            </div>
            <div class="card">
              <img src="images/photobooth_image.png" />
              <h3>Photobooth Services</h3>
              <p>
                Interactive photobooths that make your events memorable and fun.
              </p>
              <ul class="check-list">
                <li>Modern photobooth setups</li>
                <li>Custom props and backdrops</li>
                <li>Instant prints & digital copies</li>
              </ul>
              <a class="btn btn-primary" href="pages/services.php"
                >View Packages</a
              >
            </div>
          </div>
        </div>
      </section>

      <!-- ============ TESTIMONIALS (live, admin-curated) ============ -->
      <section class="section light">
        <div class="container">
          <div class="section-title">
            <h2>What Our Clients Say</h2>
            <p>Trusted by hundreds of happy customers</p>
          </div>
          <div id="testimonialCarousel" class="t-carousel" aria-live="polite">
            <p class="t-loading">Loading reviews…</p>
          </div>
        </div>
      </section>

      <section class="section cta">
        <div class="container">
          <h2>Ready to Book Your Event?</h2>
          <p>Check availability and reserve your equipment today</p>
          <div class="cta-actions">
            <a class="btn btn-outline" href="pages/booking.php"
              >Check Availability</a
            >
            <a class="btn btn-outline" href="pages/inquiry.php">Contact Us</a>
          </div>
        </div>
      </section>
    </main>
    <footer class="footer">
      <div class="container">
        <div class="footer-grid">
          <div class="footer-brand">
            <img class="logo" src="images/snapventure_logo.jpg" alt="SnapVenture" />
            <p>Camera rentals and photobooth experiences for your special moments.</p>
          </div>
          <div>
            <h3>Explore</h3>
            <div class="footer-links">
              <a href="pages/services.php">Services</a>
              <a href="pages/booking.php">Book Now</a>
              <a href="pages/inquiry.php">Help &amp; Feedback</a>
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
    <script src="js/nav.js"></script>
    <script src="js/testimonial-carousel.js"></script>
  </body>
</html>