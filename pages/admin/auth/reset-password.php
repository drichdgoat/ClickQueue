<?php

session_start();

require_once "../../../config/database.php";
require_once "../../../config/mail.php";

require_once "../../../includes/PHPMailer/src/Exception.php";
require_once "../../../includes/PHPMailer/src/PHPMailer.php";
require_once "../../../includes/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (empty($email)) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Please enter your email address.</div>';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Please enter a valid email address.</div>';

    } else {

        $stmt = $pdo->prepare("SELECT id, name FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {

            $rawToken = bin2hex(random_bytes(32));
            $hashedToken = hash("sha256", $rawToken);
            $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $update = $pdo->prepare(
                "UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?"
            );
            $update->execute([$hashedToken, $expiresAt, $admin["id"]]);

            $resetLink = APP_BASE_URL . "/admin/pages/reset-password.php?token=" . $rawToken;

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
                // Fail silently to the user (avoid leaking mail errors),
                // but you can log $mail->ErrorInfo somewhere for debugging.
            }
        }

        // Always show the same message, whether or not the email exists.
        // This avoids revealing which emails are registered admins.
        $message = '<div class="notice success" style="color:#166534;background:#dcfce7;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">If an account exists with that email, a password reset link has been sent.</div>';
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>Forgot Password - SnapVenture</title>

    <link rel="stylesheet" href="../../../css/login.css" />
</head>

<body>

    <div class="login-page">

        <div class="login-box">

            <img
                class="logo"
                src="../../../images/logo.png"
                alt="SnapVenture Logo"
            />

            <h1>Forgot Password</h1>

            <p>Enter your email and we'll send you a reset link</p>

            <form method="POST" action="forgot-password.php">

                <div class="field">

                    <label for="email">Email</label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        placeholder="Enter your email"
                        required
                    />

                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    style="width: 100%"
                >
                    Send Reset Link
                </button>

            </form>

            <?php echo $message; ?>

            <p style="margin-top: 20px">
                <a href="login.php">← Back to login</a>
            </p>

        </div>

    </div>

</body>

</html>