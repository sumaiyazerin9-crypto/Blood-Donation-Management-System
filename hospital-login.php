<?php
session_start();

require_once __DIR__ . "/config/db.php";

function escapeText($text)
{
    return htmlspecialchars(
        (string) $text,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Redirect users who already have an active session.
if (isset($_SESSION["user_id"], $_SESSION["role"])) {

    if ($_SESSION["role"] === "hospital") {
        header("Location: hospital-dashboard.php");
        exit;
    }

    if (in_array($_SESSION["role"], ["donor", "patient"], true)) {
        header("Location: dashboard.php");
        exit;
    }
}

// Generate a separate Hospital login security token.
if (empty($_SESSION["hospital_login_csrf"])) {
    $_SESSION["hospital_login_csrf"] =
        bin2hex(random_bytes(32));
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim((string) ($_POST["email"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");

    $submittedToken =
        (string) ($_POST["csrf_token"] ?? "");

    if (
        !hash_equals(
            $_SESSION["hospital_login_csrf"],
            $submittedToken
        )
    ) {
        $error = "Session expired. Refresh and try again.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $password === ""
    ) {
        $error = "Enter a valid email and password.";

    } else {

        try {

            $pdo = getDatabaseConnection();

            // Find the Hospital account and its linked profile.
            $statement = $pdo->prepare(
                "SELECT
                    u.user_id,
                    u.name,
                    u.email,
                    u.password_hash,
                    u.role,
                    h.hospital_id,
                    h.hospital_name
                 FROM users AS u
                 INNER JOIN hospitals AS h
                    ON u.user_id = h.user_id
                 WHERE u.email = ?
                   AND u.role = 'hospital'
                 LIMIT 1"
            );

            $statement->execute([$email]);

            $hospital = $statement->fetch(PDO::FETCH_ASSOC);

            // Verify password and Hospital role.
            if (
                $hospital &&
                password_verify(
                    $password,
                    $hospital["password_hash"]
                )
            ) {

                // Create a fresh authenticated session.
                session_regenerate_id(true);

                $_SESSION["user_id"] =
                    (int) $hospital["user_id"];

                $_SESSION["role"] = "hospital";

                $_SESSION["display_name"] =
                    $hospital["name"];

                $_SESSION["hospital_id"] =
                    (int) $hospital["hospital_id"];

                $_SESSION["hospital_name"] =
                    $hospital["hospital_name"];

                // Prepare the common logout security token.
                $_SESSION["logout_csrf"] =
                    bin2hex(random_bytes(32));

                unset($_SESSION["hospital_login_csrf"]);

                header("Location: hospital-dashboard.php");
                exit;

            } else {

                $error = "Incorrect email or password.";

            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $error =
                "Unable to sign in right now. Please try again.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Hospital Login | Blood Donation</title>

<style>

:root {
    --red: #8c2634;
    --dark-red: #6f1b29;
    --ink: #262626;
    --gray: #686868;
    --cream: #f7f5f1;
    --border: #e4ded6;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--ink);
    background: var(--cream);
}

a {
    color: inherit;
    text-decoration: none;
}

.container {
    width: min(1000px, 90%);
    margin: auto;
}

header {
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 22px 0;
}

.nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.logo {
    font-family: Georgia, serif;
    font-size: 23px;
    font-weight: bold;
    color: var(--red);
}

.back {
    font-size: 13px;
    color: var(--gray);
}

.back:hover {
    color: var(--red);
}

main {
    min-height: calc(100vh - 160px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 75px 0;
}

.login-layout {
    width: 100%;
    max-width: 470px;
    margin: auto;
}

.heading {
    text-align: center;
    margin-bottom: 32px;
}

.label {
    font-size: 11px;
    color: var(--red);
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: bold;
}

h1 {
    font-family: Georgia, serif;
    font-size: 45px;
    font-weight: normal;
    margin: 18px 0;
}

.heading p {
    font-size: 14px;
    color: var(--gray);
    line-height: 1.8;
}

.login-card {
    background: white;
    border: 1px solid var(--border);
    padding: 38px;
}

.field {
    margin-bottom: 23px;
}

.field label {
    display: block;
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: bold;
}

.field input {
    width: 100%;
    padding: 14px;
    border: 1px solid #d6d3ce;
    border-radius: 3px;
    font-size: 14px;
    font-family: inherit;
}

.field input:focus {
    border-color: var(--red);
    outline: 1px solid var(--red);
}

button {
    width: 100%;
    border: none;
    padding: 16px;
    background: var(--red);
    color: white;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: var(--dark-red);
}

.alert {
    padding: 15px;
    margin-bottom: 22px;
    font-size: 13px;
    line-height: 1.7;
}

.alert-error {
    background: #fff0f1;
    border: 1px solid #eac5cc;
    color: var(--red);
}

.alert-success {
    background: #eaf7ee;
    border: 1px solid #b5d9bf;
    color: #225b34;
}

.login-links {
    margin-top: 28px;
    border-top: 1px solid var(--border);
    padding-top: 22px;
    text-align: center;
}

.login-links p {
    color: var(--gray);
    font-size: 13px;
}

.login-links a {
    display: inline-block;
    margin-top: 8px;
    color: var(--red);
    font-size: 13px;
    font-weight: bold;
}

.login-links a:hover {
    color: var(--dark-red);
}

footer {
    padding: 27px 0;
    text-align: center;
    border-top: 1px solid var(--border);
    color: var(--gray);
    font-size: 12px;
}

@media (max-width: 550px) {

    .login-card {
        padding: 25px;
    }

    h1 {
        font-size: 37px;
    }

    main {
        padding: 50px 0;
    }
}

</style>

</head>

<body>

<header>

    <div class="container nav">

        <a href="index.php" class="logo">
            Blood Donation.
        </a>

        <a href="index.php" class="back">
            &larr; Back to Home
        </a>

    </div>

</header>

<main>

    <div class="container">

        <div class="login-layout">

            <div class="heading">

                <div class="label">
                    Hospital Portal
                </div>

                <h1>Hospital Login</h1>

                <p>
                    Access your hospital account using
                    your registered email and password.
                </p>

            </div>

            <div class="login-card">

                <?php if ($error !== ""): ?>

                    <div class="alert alert-error" role="alert">
                        <?= escapeText($error) ?>
                    </div>

                <?php endif; ?>

                <?php if (
                    isset($_GET["logged_out"]) &&
                    $_GET["logged_out"] === "1"
                ): ?>

                    <div class="alert alert-success" role="status">
                        You have successfully logged out.
                    </div>

                <?php endif; ?>

                <form method="POST" action="hospital-login.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeText($_SESSION["hospital_login_csrf"]) ?>"
                    >

                    <div class="field">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="hospital@example.com"
                            value="<?= escapeText($email) ?>"
                        >

                    </div>

                    <div class="field">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >

                    </div>

                    <button type="submit">
                        Sign In as Hospital
                    </button>

                </form>

                <div class="login-links">

                    <p>
                        Donor or Patient?
                    </p>

                    <a href="login.php">
                        Go to Donor / Patient Login
                    </a>

                </div>

            </div>

        </div>

    </div>

</main>

<footer>
    Online Blood Donation Management System
</footer>

</body>

</html>
