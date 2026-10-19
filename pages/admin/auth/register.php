<?php

session_start();

require_once "../../../config/database.php";
require_once "../../../includes/auth-ui.php";

$errors = [];  // Field errors, keyed by input id
$registered = false;
$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($name === "") {
        $errors["name"] = "Please enter your name.";
    }

    if ($email === "") {
        $errors["email"] = "Please enter your email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Please enter a valid email address.";
    }

    $errors += auth_validate_new_password($password, $confirmPassword);

    if (!$errors) {

        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $errors["email"] = "An account with this email already exists.";

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "INSERT INTO admins (name, email, password)
                 VALUES (?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $email,
                $hashedPassword
            ]);

            // Lets login.php show the "Account created" banner and pre-fill the email
            $_SESSION["registered_email"] = $email;
            $registered = true;
        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>Admin Register - SnapVenture</title>

    <?php if ($registered): ?>
        <!-- Fallback redirect if JavaScript is disabled -->
        <noscript>
            <meta http-equiv="refresh" content="3;url=login.php?registered=1" />
        </noscript>
    <?php endif; ?>

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

                <?php if ($registered): ?>

                <div class="register-success" role="status" aria-live="polite">

                    <svg class="success-check" viewBox="0 0 52 52" aria-hidden="true">
                        <circle class="success-check-circle" cx="26" cy="26" r="24" />
                        <path class="success-check-mark" d="M15 27 l7 7 l15 -15" />
                    </svg>

                    <h1>Account created!</h1>
                    <p class="auth-sub">Redirecting you to sign in&hellip;</p>

                    <div class="redirect-progress">
                        <span></span>
                    </div>

                    <p class="redirect-link">
                        <a class="auth-link" href="login.php?registered=1">Go now &rarr;</a>
                    </p>

                </div>

                <script>
                    setTimeout(function () {
                        window.location.href = "login.php?registered=1";
                    }, 3000);
                </script>

                <?php else: ?>

                <h1>Create Account</h1>
                <p class="auth-sub">Set up your admin access</p>

                <form method="POST" action="register.php" novalidate>

                    <div class="field">
                        <label for="name">Name</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            placeholder="Enter your name"
                            autocomplete="name"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($name); ?>"
                            <?php echo auth_field_attrs($errors, "name"); ?>
                            required
                        />
                        <?php echo auth_field_message($errors, "name"); ?>
                    </div>

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

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Create a password"
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
                            placeholder="Re-enter your password"
                            autocomplete="new-password"
                            <?php echo auth_field_attrs($errors, "confirm_password"); ?>
                            required
                        />
                        <?php echo auth_field_message($errors, "confirm_password"); ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Create Account
                    </button>

                </form>

                <?php endif; ?>

            </div>

            <?php if (!$registered): ?>
            <p class="auth-foot">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
            <?php endif; ?>

        </main>

    </div>

    <script src="../../../js/auth.js"></script>

</body>

</html>
