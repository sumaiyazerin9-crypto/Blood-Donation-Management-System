<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/config/db.php";

// Protect the form against unwanted submissions.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escapeText(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}

$bloodGroups = [
    "A+", "A-", "B+", "B-",
    "AB+", "AB-", "O+", "O-"
];

$values = [
    "name" => "",
    "email" => "",
    "age" => "",
    "blood_group" => "",
    "district" => "",
    "phone" => ""
];

$errors = [];

$success = !empty($_SESSION["donor_registered"]);
unset($_SESSION["donor_registered"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($values as $field => $default) {
        $values[$field] = trim(
            (string) ($_POST[$field] ?? "")
        );
    }

    $password = (string) ($_POST["password"] ?? "");
    $confirm = (string) ($_POST["confirm_password"] ?? "");

    if (!hash_equals(
        $_SESSION["csrf_token"],
        (string) ($_POST["csrf_token"] ?? "")
    )) {
        $errors[] = "Invalid form session. Please refresh.";
    }

    if (
        strlen($values["name"]) < 2 ||
        strlen($values["name"]) > 100
    ) {
        $errors[] = "Enter a name of 2 to 100 characters.";
    }

    if (
        !filter_var($values["email"], FILTER_VALIDATE_EMAIL) ||
        strlen($values["email"]) > 150
    ) {
        $errors[] = "Enter a valid email address.";
    }

    $age = filter_var(
        $values["age"],
        FILTER_VALIDATE_INT,
        ["options" => [
            "min_range" => 18,
            "max_range" => 100
        ]]
    );

    if ($age === false) {
        $errors[] = "Enter an age between 18 and 100.";
    }

    if (!in_array(
        $values["blood_group"],
        $bloodGroups,
        true
    )) {
        $errors[] = "Select a valid blood group.";
    }

    if (
        strlen($values["district"]) < 2 ||
        strlen($values["district"]) > 100
    ) {
        $errors[] = "Enter a valid district.";
    }

    if (!preg_match(
        "/^\+?[0-9]{10,15}$/",
        $values["phone"]
    )) {
        $errors[] = "Enter a valid phone number (10-15 digits).";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $pdo = null;

        try {
            $pdo = getDatabaseConnection();
            $pdo->beginTransaction();

            // Create the donor's login account.
            $userStatement = $pdo->prepare(
                "INSERT INTO users
                 (name, email, password_hash, role)
                 VALUES (?, ?, ?, 'donor')"
            );

            $userStatement->execute([
                $values["name"],
                $values["email"],
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $userId = (int) $pdo->lastInsertId();

            // Create the donor profile.
            $donorStatement = $pdo->prepare(
                "INSERT INTO donors
                 (user_id, age, blood_group,
                  district, phone, availability)
                 VALUES (?, ?, ?, ?, ?, 1)"
            );

            $donorStatement->execute([
                $userId,
                $age,
                $values["blood_group"],
                $values["district"],
                $values["phone"]
            ]);

            $pdo->commit();

            $_SESSION["donor_registered"] = true;
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: register-donor.php", true, 303);
            exit;

        } catch (PDOException $e) {

            if (
                $pdo instanceof PDO &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e->getCode() === "23000") {
                $errors[] =
                    "Registration could not be completed. " .
                    "The email may already be registered.";
            } else {
                $errors[] =
                    "Registration failed. Please try again.";
            }
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

<title>Donor Registration | Blood Donation</title>

<style>
:root {
    --red: #8c2634;
    --red-dark: #6f1b29;
    --cream: #f7f5f1;
    --ink: #262626;
    --gray: #686868;
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

header {
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 22px 0;
}

.container {
    width: min(920px, 90%);
    margin: auto;
}

.nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.logo {
    font-family: Georgia, serif;
    font-size: 23px;
    font-weight: bold;
    color: var(--red);
}

.back {
    color: var(--gray);
    font-size: 13px;
}

.back:hover {
    color: var(--red);
}

main {
    padding: 65px 0 85px;
}

.intro {
    max-width: 650px;
    margin-bottom: 35px;
}

.label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--red);
    font-weight: bold;
}

h1 {
    font-family: Georgia, serif;
    font-size: clamp(36px, 5vw, 54px);
    font-weight: normal;
    letter-spacing: -1px;
    margin: 17px 0;
}

.intro p {
    color: var(--gray);
    line-height: 1.8;
    font-size: 15px;
}

.form-box {
    background: white;
    border: 1px solid var(--border);
    padding: 40px;
}

.form-box h2 {
    font-family: Georgia, serif;
    font-size: 25px;
    font-weight: normal;
    margin: 0 0 27px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 23px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    font-size: 13px;
    font-weight: bold;
}

.field input,
.field select {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #d6d3ce;
    border-radius: 3px;
    font-family: inherit;
    font-size: 14px;
    background: white;
    color: var(--ink);
}

.field input:focus,
.field select:focus {
    outline: 1px solid var(--red);
    border-color: var(--red);
}

.note {
    font-size: 12px;
    color: var(--gray);
    line-height: 1.7;
    margin: 25px 0;
}

button {
    padding: 15px 28px;
    background: var(--red);
    color: white;
    border: none;
    font: bold 13px Arial, sans-serif;
    cursor: pointer;
}

button:hover {
    background: var(--red-dark);
}

.alert {
    padding: 16px 20px;
    margin-bottom: 25px;
    line-height: 1.7;
    font-size: 14px;
}

.alert-success {
    background: #eaf7ee;
    color: #225b34;
    border: 1px solid #b5d9bf;
}

/* Sign In link in the successful registration message */
.alert-success a {
    display: inline-block;
    margin-top: 10px;
    color: #225b34;
    font-size: 13px;
    font-weight: bold;
    border-bottom: 1px solid currentColor;
    padding-bottom: 3px;
}

.alert-success a:hover {
    color: #173e24;
}

.alert-error {
    background: #fff0f1;
    color: #8c2634;
    border: 1px solid #eac5cc;
}

.alert-error ul {
    margin: 8px 0 0;
    padding-left: 20px;
}

footer {
    border-top: 1px solid var(--border);
    text-align: center;
    padding: 25px;
    color: var(--gray);
    font-size: 12px;
}

@media (max-width: 650px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-box {
        padding: 25px;
    }

    main {
        padding: 45px 0;
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

        <div class="intro">
            <div class="label">Donor Registration</div>

            <h1>Become a Donor</h1>

            <p>
                Join our donor network by creating
                your profile. Your information will
                help organize potential donor matches
                when blood requests are submitted.
            </p>
        </div>

        <!-- UPDATED SUCCESS MESSAGE -->

        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                <strong>Registration Successful</strong>
                <div>
                    Your donor account has been created
                    successfully. You can now sign in
                    using your registered email address
                    and password.
                </div>

                <a href="login.php">
                    Sign In &rarr;
                </a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <strong>Please correct these errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= escapeText($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-box">
            <h2>Your Information</h2>

            <form method="POST"
                  action="register-donor.php">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapeText($_SESSION['csrf_token']) ?>">

                <div class="form-grid">

                    <div class="field full">
                        <label for="name">Full Name</label>
                        <input type="text" id="name"
                               name="name" required
                               maxlength="100"
                               autocomplete="name"
                               value="<?= escapeText($values['name']) ?>"
                               placeholder="Enter your full name">
                    </div>

                    <div class="field">
                        <label for="email">Email Address</label>
                        <input type="email" id="email"
                               name="email" required
                               maxlength="150"
                               autocomplete="email"
                               value="<?= escapeText($values['email']) ?>"
                               placeholder="name@example.com">
                    </div>

                    <div class="field">
                        <label for="age">Age</label>
                        <input type="number" id="age"
                               name="age" min="18" max="100"
                               required
                               value="<?= escapeText($values['age']) ?>"
                               placeholder="Your age">
                    </div>

                    <div class="field">
                        <label for="blood_group">Blood Group</label>
                        <select id="blood_group"
                                name="blood_group" required>
                            <option value="">
                                Select blood group
                            </option>

                            <?php foreach ($bloodGroups as $group): ?>
                                <option value="<?= escapeText($group) ?>"
                                    <?= $values["blood_group"] === $group
                                        ? "selected" : "" ?>>
                                    <?= escapeText($group) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="district">District</label>
                        <input type="text" id="district"
                               name="district" required
                               maxlength="100"
                               value="<?= escapeText($values['district']) ?>"
                               placeholder="Example: Dhaka">
                    </div>

                    <div class="field full">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone"
                               name="phone" required
                               maxlength="16"
                               autocomplete="tel"
                               value="<?= escapeText($values['phone']) ?>"
                               placeholder="Example: 01712345678">
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password"
                               name="password" required
                               minlength="8"
                               autocomplete="new-password"
                               placeholder="At least 8 characters">
                    </div>

                    <div class="field">
                        <label for="confirm_password">
                            Confirm Password
                        </label>
                        <input type="password"
                               id="confirm_password"
                               name="confirm_password"
                               required minlength="8"
                               autocomplete="new-password"
                               placeholder="Repeat password">
                    </div>

                </div>

                <p class="note">
                    Your registration information will
                    be saved in our database.
                    Registration does not confirm
                    medical eligibility to donate blood.
                </p>

                <button type="submit">
                    Create Donor Account
                </button>

            </form>
        </div>

    </div>
</main>

<footer>
    Online Blood Donation Management System
</footer>

</body>
</html>
