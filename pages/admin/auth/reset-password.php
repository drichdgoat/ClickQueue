<?php

session_start();

require_once "../../../config/database.php";
require_once "../../../includes/auth-ui.php";

$errors = [];  // Field errors, keyed by input id

// Token comes from the emailed link (GET) and is carried through the form (POST)
$token = trim($_GET["token"] ?? $_POST["token"] ?? "");
$admin = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {

    // Only the SHA-256 hash of the token is stored (see forgot-password.php)
    $stmt = $pdo->prepare(
        "SELECT id, reset_expires FROM admins WHERE reset_token = ?"
    );
    $stmt->execute([hash("sha256", $token)]);
    $admin = $stmt->fetch();

    // Expiry was written with PHP's date(), so compare in PHP to avoid
    // MySQL/PHP timezone mismatches
    if ($admin && strtotime($admin["reset_expires"]) < time()) {
        $admin = null;
    }
}

if ($admin && $_SERVER["REQUEST_METHOD"] === "POST") {

    $errors = auth_validate_new_password(
        $_POST["password"] ?? "",
        $_POST["confirm_password"] ?? ""
    );

    if (!$errors) {

        // Save the new password and invalidate the token so the link can't be reused
        $update = $pdo->prepare(
            "UPDATE admins
             SET password = ?, reset_token = NULL, reset_expires = NULL
             WHERE id = ?"
        );
        $update->execute([
            password_hash($_POST["password"], PASSWORD_DEFAULT),
            $admin["id"]
        ]);

        // Lets login.php show the "Password updated" banner
        $_SESSION["password_reset"] = true;

        header("Location: login.php?reset=success");
        exit;
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>Reset Password - SnapVenture</title>

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

                <h1>Reset Password</h1>

                <?php if ($admin): ?>

                <p class="auth-sub">Choose a new password for your account</p>

                <form method="POST" action="reset-password.php" novalidate>

                    <input
                        type="hidden"
                        name="token"
                        value="<?php echo htmlspecialchars($token); ?>"
                    />

                    <div class="field">
                        <label for="password">New Password</label>
                        <div class="password-wrap">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Create a new password"
                                autocomplete="new-password"
                                minlength="<?php echo AUTH_MIN_PASSWORD_LENGTH; ?>"
                                <?php echo auth_field_attrs($errors, "password", true); ?>
                                required
                            />
                            <!-- One toggle shows/hides both password fields -->
                            <button
                                type="button"
                                class="password-toggle"
                                aria-controls="password confirm_password"
                                aria-label="Show passwords"
                                aria-pressed="false"
                            >
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-2.6 3.6M6.6 6.6A17.4 17.4 0 0 0 2 12s3.6 7 10 7a9.6 9.6 0 0 0 5.4-1.6" />
                                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                                    <path d="M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <?php echo auth_field_message($errors, "password", "At least " . AUTH_MIN_PASSWORD_LENGTH . " characters."); ?>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <input
                            id="confirm_password"
                            name="confirm_password"
                            type="password"
                            placeholder="Re-enter your new password"
                            autocomplete="new-password"
                            <?php echo auth_field_attrs($errors, "confirm_password"); ?>
                            required
                        />
                        <?php echo auth_field_message($errors, "confirm_password"); ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Reset Password
                    </button>

                </form>

                <?php else: ?>

                <p class="auth-sub">&nbsp;</p>

                <?php echo auth_alert("error", "Link invalid or expired", "Reset links work once and expire after 1 hour."); ?>

                <a class="btn btn-primary" href="forgot-password.php">Request a New Link</a>

                <?php endif; ?>

            </div>

            <p class="auth-foot">
                <a href="login.php">Back to sign in</a>
            </p>

        </main>

    </div>

    <script src="../../../js/auth.js"></script>

</body>

</html>
