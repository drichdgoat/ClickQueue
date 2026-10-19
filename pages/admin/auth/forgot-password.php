<?php

session_start();

require_once "../../../config/database.php";
require_once "../../../includes/auth-ui.php";

// config/mail.php holds SMTP credentials and is not committed (see config/mail.example.php)
$mailConfigured = is_file(__DIR__ . "/../../../config/mail.php");
if ($mailConfigured) {
    require_once __DIR__ . "/../../../config/mail.php";
}

require_once "../../../includes/PHPMailer/src/Exception.php";
require_once "../../../includes/PHPMailer/src/PHPMailer.php";
require_once "../../../includes/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$alert = "";   // Banner at the top of the card
$errors = [];  // Field errors, keyed by input id
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $errors["email"] = "Please enter your email.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors["email"] = "Please enter a valid email address.";

    } else {

        $stmt = $pdo->prepare("SELECT id, name FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && !$mailConfigured) {

            error_log("Password reset requested but config/mail.php is missing; no email sent.");

        } elseif ($admin) {

            $rawToken = bin2hex(random_bytes(32));
            $hashedToken = hash("sha256", $rawToken);
            $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $update = $pdo->prepare(
                "UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?"
            );
            $update->execute([$hashedToken, $expiresAt, $admin["id"]]);

            $resetLink = rtrim(APP_BASE_URL, "/") . "/pages/admin/auth/reset-password.php?token=" . $rawToken;

            $mail = new PHPMailer(true);

            try {

                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($email, $admin["name"]);

                $mail->isHTML(true);
                $mail->Subject = "SnapVenture Admin - Password Reset";
                $mail->Body = "
                    <p>Hi " . htmlspecialchars($admin["name"]) . ",</p>
                    <p>We received a request to reset your SnapVenture admin password.</p>
                    <p><a href=\"" . $resetLink . "\">Click here to reset your password</a></p>
                    <p>This link will expire in 1 hour. If you did not request this, you can ignore this email.</p>
                ";
                $mail->AltBody = "Reset your SnapVenture admin password: " . $resetLink . " (expires in 1 hour)";

                $mail->send();

            } catch (Exception $e) {
                // Fail silently to the user (avoid leaking mail errors)
                error_log("Password reset email failed: " . $mail->ErrorInfo);
            }
        }

        // Always show the same message, whether or not the email exists.
        // This avoids revealing which emails are registered admins.
        $alert = auth_alert("success", "Check your email", "If an account exists for that address, we've sent a reset link.");
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>Forgot Password - SnapVenture</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="../../../css/auth.css" />
</head>

<body>

    <div class="auth-shell">

        <a class="auth-brand" href="../../../index.php">
            <img src="../../../images/snapventure_logo.jpg" alt="SnapVenture Logo" />
            <span>
                <span class="auth-brand-name">SnapVenture</span>
                <span class="auth-brand-tag">Admin</span>
            </span>
        </a>

        <a class="auth-back" href="../../../index.php">&larr; Website</a>

        <main class="auth-main">

            <div class="auth-card">

                <h1>Forgot Password</h1>
                <p class="auth-sub">Enter your email and we'll send you a reset link</p>

                <?php echo $alert; ?>

                <form method="POST" action="forgot-password.php" novalidate>

                    <div class="field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            maxlength="150"
                            value="<?php echo htmlspecialchars($email); ?>"
                            <?php echo auth_field_attrs($errors, "email"); ?>
                            required
                        />
                        <?php echo auth_field_message($errors, "email"); ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Send Reset Link
                    </button>

                </form>

            </div>

            <p class="auth-foot">
                Remembered it? <a href="login.php">Back to sign in</a>
            </p>

        </main>

    </div>

    <script src="../../../js/auth.js"></script>

</body>

</html>
