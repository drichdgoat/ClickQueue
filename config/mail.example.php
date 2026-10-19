<?php
/**
 * Copy this file to config/mail.php and fill in your SMTP details.
 * config/mail.php is git-ignored so credentials are never committed.
 */

// Base URL of the site, no trailing slash (used to build password-reset links)
define("APP_BASE_URL", "http://localhost/GitHub/ClickQueue");

define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_USERNAME", "you@example.com");
define("SMTP_PASSWORD", "your-app-password");
define("SMTP_PORT", 587);
define("SMTP_FROM_EMAIL", "you@example.com");
define("SMTP_FROM_NAME", "SnapVenture");
