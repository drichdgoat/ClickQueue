<?php

session_start();

require_once "../../../config/database.php";

$message = "";

if (isset($_GET["reset"]) && $_GET["reset"] === "success") {
    $message = '<div class="notice success" style="color:#166534;background:#dcfce7;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Your password has been reset. You can now sign in.</div>';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Please enter your email and password.</div>';

    } else {

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);

        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin["password"])) {

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_name"] = $admin["name"];
            $_SESSION["admin_email"] = $admin["email"];

            header("Location: ../dashboard.php");
            exit;

        } else {

            $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Incorrect email or password.</div>';

        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>Admin Login - SnapVenture</title>

    <link rel="stylesheet" href="../../../css/login.css" />
</head>

<body>

    <div class="login-page">

        <div class="login-box">

            <img
                class="logo"
                src="../../../images/snapventure_logo.jpg"
                alt="SnapVenture Logo"
            />

            <h1>Sign in to your account</h1>

            <p>Admin Login</p>

            <form method="POST" action="login.php">

                <div class="field">

                    <label for="loginEmail">Email</label>

                    <input
                        id="loginEmail"
                        name="email"
                        type="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($email ?? ""); ?>"
                        required
                    />

                </div>

                <div class="field">

                    <label for="loginPassword">Password</label>

                    <input
                        id="loginPassword"
                        name="password"
                        type="password"
                        placeholder="Enter your password"
                        required
                    />

                </div>

                <p style="text-align:right; margin: -8px 0 18px;">
                    <a href="forgot-password.php" style="font-size: 14px; color: var(--brown);">
                        Forgot password?
                    </a>
                </p>

                <button
                    type="submit"
                    class="btn btn-primary"
                    style="width: 100%"
                >
                    Sign In
                </button>

            </form>

            <?php echo $message; ?>

            <p style="margin-top: 20px">

                <a href="../../../index.php">
                    ← Website
                </a>

                ·

                <a href="register.php">
                    Create account
                </a>

            </p>

        </div>

    </div>

</body>

</html>