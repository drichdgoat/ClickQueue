<?php

session_start();

require_once "../../../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Please fill in all fields.</div>';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Please enter a valid email address.</div>';

    } elseif (strlen($password) < 6) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Password must be at least 6 characters.</div>';

    } elseif ($password !== $confirmPassword) {

        $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">Passwords do not match.</div>';

    } else {

        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $message = '<div class="notice error" style="color:#b91c1c;background:#fee2e2;font-weight:600;padding:12px 14px;border-radius:8px;text-align:center;margin-top:16px;">An account with this email already exists.</div>';

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

            $message = '<div class="notice success">
                Account created successfully.
                <a href="login.php">Sign in here</a>.
            </div>';
        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8" />

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1.0"
    />

    <title>Admin Register - SnapVenture</title>

    <link
        rel="stylesheet"
        href="../../../css/register.css"
    />

</head>

<body>

    <div class="login-page">

        <div class="login-box">

            <img
                class="logo"
                src="../../../images/snapventure_logo.jpg"
                alt="SnapVenture Logo"
            />

            <h1>Create Admin Account</h1>

            <form method="POST" action="register.php">

                <div class="field">

                    <label for="name">
                        Name
                    </label>

                    <input
                        id="name"
                        name="name"
                        type="text"
                        placeholder="Enter your name"
                        value="<?php echo htmlspecialchars($name ?? ""); ?>"
                        required
                    />

                </div>

                <div class="field">

                    <label for="email">
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($email ?? ""); ?>"
                        required
                    />

                </div>

                <div class="field">

                    <label for="password">
                        Password
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Enter your password"
                        required
                    />

                </div>

                <div class="field">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        placeholder="Re-enter your password"
                        required
                    />

                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    style="width: 100%"
                >
                    Register
                </button>

            </form>

            <?php echo $message; ?>

            <p style="margin-top: 20px">

                <a href="login.php">
                    ← Back to login
                </a>

            </p>

        </div>

    </div>

</body>

</html>