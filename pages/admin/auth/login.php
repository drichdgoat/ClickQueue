<?php

session_start();

require_once "../../../config/database.php";
require_once "../../../includes/auth-ui.php";

$alert = "";   // Banner at the top of the card
$errors = [];  // Field errors, keyed by input id
$email = "";

// Success banners only show when the session confirms the action really happened,
// so visiting login.php?registered=1 directly shows nothing
if (isset($_GET["registered"]) && isset($_SESSION["registered_email"])) {
    $alert = auth_alert("success", "Account created successfully", "Please sign in to continue.");
    $email = $_SESSION["registered_email"];
    unset($_SESSION["registered_email"]);
}

if (isset($_GET["reset"]) && !empty($_SESSION["password_reset"])) {
    $alert = auth_alert("success", "Password updated", "Sign in with your new password.");
    unset($_SESSION["password_reset"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "") {
        $errors["email"] = "Please enter your email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Please enter a valid email address.";
    }

    if ($password === "") {
        $errors["password"] = "Please enter your password.";
    }

    if (!$errors) {

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);

        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin["password"])) {

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_name"] = $admin["name"];
            $_SESSION["admin_email"] = $admin["email"];

            header("Location: ../dashboard.php");
            exit;

        }

        // Don't reveal whether the email or the password was wrong
        $alert = auth_alert("error", "Incorrect email or password", "Check your details and try again.");
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>Admin Login - SnapVenture</title>

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

                <h1>Admin Login</h1>
                <p class="auth-sub">Please enter your details</p>

                <?php echo $alert; ?>

                <form method="POST" action="login.php" novalidate>

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
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                <?php echo auth_field_attrs($errors, "password"); ?>
                                required
                            />
                            <button
                                type="button"
                                class="password-toggle"
                                aria-controls="password"
                                aria-label="Show password"
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
                        <?php echo auth_field_message($errors, "password"); ?>
                    </div>

                    <p class="auth-forgot">
                        <a href="forgot-password.php">Forgot password?</a>
                    </p>

                    <button type="submit" class="btn btn-primary">
                        Sign in
                    </button>

                </form>

            </div>

            <p class="auth-foot">
                Are you new? <a href="register.php">Create an Account</a>
            </p>

        </main>

    </div>

    <script src="../../../js/auth.js"></script>

</body>

</html>
