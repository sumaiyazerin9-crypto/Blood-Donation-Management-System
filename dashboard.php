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

// Only logged-in donors and patients can access this page.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    !in_array(
        $_SESSION["role"],
        ["donor", "patient"],
        true
    )
) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];

$pageTitle = $role === "donor"
    ? "Donor Dashboard"
    : "Patient Dashboard";

$profile = false;
$error = "";
$success = "";

// Generate security tokens.
if (empty($_SESSION["logout_csrf"])) {
    $_SESSION["logout_csrf"] =
        bin2hex(random_bytes(32));
}

if (
    $role === "donor" &&
    empty($_SESSION["availability_csrf"])
) {
    $_SESSION["availability_csrf"] =
        bin2hex(random_bytes(32));
}

// Display the message after a successful update.
if (!empty($_SESSION["availability_updated"])) {
    $success = "Your availability has been updated successfully.";
    unset($_SESSION["availability_updated"]);
}

try {
    $pdo = getDatabaseConnection();

    // Load the correct profile for the logged-in user.
    if ($role === "donor") {

        $statement = $pdo->prepare(
            "SELECT
                u.name,
                u.email,
                d.age,
                d.blood_group,
                d.district,
                d.phone,
                d.availability
             FROM users AS u
             INNER JOIN donors AS d
                ON u.user_id = d.user_id
             WHERE u.user_id = ?
               AND u.role = 'donor'
             LIMIT 1"
        );

    } else {

        $statement = $pdo->prepare(
            "SELECT
                u.name,
                u.email,
                p.age,
                p.district,
                p.phone
             FROM users AS u
             INNER JOIN patients AS p
                ON u.user_id = p.user_id
             WHERE u.user_id = ?
               AND u.role = 'patient'
             LIMIT 1"
        );
    }

    $statement->execute([$userId]);

    $profile = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $error = "Your profile could not be found.";
    }

    // Process donor availability updates.
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        // Patients cannot update donor availability.
        if ($role !== "donor") {
            http_response_code(403);
            exit("Access denied.");
        }

        if (!$profile) {
            $error = "Unable to find your donor profile.";

        } else {

            $submittedToken =
                (string) ($_POST["csrf_token"] ?? "");

            $expectedToken =
                (string) $_SESSION["availability_csrf"];

            $newAvailability =
                (string) ($_POST["availability"] ?? "");

            // Validate the security token.
            if (
                !hash_equals(
                    $expectedToken,
                    $submittedToken
                )
            ) {
                $error = "Invalid request. Refresh the page and try again.";

            // Only 1 and 0 are permitted.
            } elseif (
                !in_array(
                    $newAvailability,
                    ["1", "0"],
                    true
                )
            ) {
                $error = "Please select a valid availability status.";

            } else {

                // Update only the logged-in donor's record.
                $updateStatement = $pdo->prepare(
                    "UPDATE donors
                     SET availability = ?
                     WHERE user_id = ?"
                );

                $updateStatement->execute([
                    (int) $newAvailability,
                    $userId
                ]);

                // Generate a fresh form security token.
                $_SESSION["availability_csrf"] =
                    bin2hex(random_bytes(32));

                $_SESSION["availability_updated"] = true;

                // Redirect to prevent duplicate submissions.
                header("Location: dashboard.php", true, 303);
                exit;
            }
        }
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = "Unable to load or update your profile.";
}

// Prepare profile information for display.
$fields = [];

if ($profile) {

    $fields = [
        "Full Name" => $profile["name"],
        "Email Address" => $profile["email"],
        "Age" => $profile["age"],
        "District" => $profile["district"],
        "Phone Number" => $profile["phone"]
    ];

    if ($role === "donor") {

        $fields["Blood Group"] =
            $profile["blood_group"];

        $fields["Availability"] =
            (int) $profile["availability"] === 1
                ? "Available"
                : "Unavailable";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?= escapeText($pageTitle) ?> | Blood Donation
</title>

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
    background: var(--cream);
    color: var(--ink);
    font-family: Arial, Helvetica, sans-serif;
}

.container {
    width: min(1050px, 90%);
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
    text-decoration: none;
}

button {
    background: var(--red);
    color: white;
    border: none;
    padding: 12px 23px;
    font-weight: bold;
    font-size: 13px;
    cursor: pointer;
}

button:hover {
    background: var(--dark-red);
}

main {
    padding: 65px 0 90px;
    min-height: 75vh;
}

.label {
    color: var(--red);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: bold;
}

h1 {
    font-family: Georgia, serif;
    font-size: clamp(35px, 5vw, 52px);
    font-weight: normal;
    margin: 15px 0;
}

.intro {
    color: var(--gray);
    font-size: 15px;
    line-height: 1.8;
    margin-bottom: 38px;
}

.card {
    background: white;
    border: 1px solid var(--border);
    padding: 36px;
    margin-bottom: 25px;
}

.card h2 {
    font-family: Georgia, serif;
    font-weight: normal;
    font-size: 26px;
    margin: 0 0 25px;
}

.details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 26px;
}

.detail {
    border-bottom: 1px solid var(--border);
    padding-bottom: 15px;
}

.detail-label {
    display: block;
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 9px;
}

.detail-value {
    font-size: 15px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

/* DONOR AVAILABILITY SECTION */

.availability-description {
    color: var(--gray);
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 25px;
}

.availability-form {
    max-width: 430px;
}

.availability-form label {
    display: block;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: bold;
}

.availability-form select {
    width: 100%;
    padding: 14px;
    margin-bottom: 20px;
    border: 1px solid #d6d3ce;
    border-radius: 3px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: var(--ink);
}

.availability-form select:focus {
    border-color: var(--red);
    outline: 1px solid var(--red);
}

.availability-form button {
    padding: 15px 28px;
}

/* MESSAGES */

.alert {
    padding: 17px 20px;
    margin-bottom: 25px;
    font-size: 14px;
    line-height: 1.7;
}

.alert-success {
    background: #eaf7ee;
    border: 1px solid #b5d9bf;
    color: #225b34;
}

.alert-error {
    background: #fff0f1;
    border: 1px solid #eac5cc;
    color: var(--red);
}

.note {
    margin-top: 25px;
    padding: 20px;
    border-left: 3px solid var(--red);
    background: white;
    font-size: 13px;
    line-height: 1.8;
    color: var(--gray);
}

footer {
    border-top: 1px solid var(--border);
    text-align: center;
    padding: 25px;
    color: var(--gray);
    font-size: 12px;
}

@media (max-width: 650px) {
    .details {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 25px;
    }

    main {
        padding: 45px 0;
    }

    .nav {
        flex-wrap: wrap;
    }
}
</style>
</head>

<body>

<!-- TOP NAVIGATION -->

<header>
    <div class="container nav">

        <a href="index.php" class="logo">
            Blood Donation.
        </a>

        <form method="POST" action="logout.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeText($_SESSION['logout_csrf']) ?>"
            >

            <button type="submit">
                Logout
            </button>

        </form>

    </div>
</header>

<main>

<div class="container">

    <div class="label">
        My Account
    </div>

    <h1>
        <?= escapeText($pageTitle) ?>
    </h1>

    <p class="intro">
        Welcome,
        <strong>
            <?= escapeText(
                $profile["name"] ??
                $_SESSION["display_name"] ??
                "User"
            ) ?>
        </strong>.
        Your registered information is
        displayed below.
    </p>

    <!-- SUCCESS MESSAGE -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success" role="status">
            <?= escapeText($success) ?>
        </div>

    <?php endif; ?>

    <!-- ERROR MESSAGE -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-error" role="alert">
            <?= escapeText($error) ?>
        </div>

    <?php elseif ($profile): ?>

        <!-- PROFILE INFORMATION -->

        <section class="card">

            <h2>My Profile</h2>

            <div class="details">

                <?php foreach ($fields as $label => $value): ?>

                    <div class="detail">

                        <span class="detail-label">
                            <?= escapeText($label) ?>
                        </span>

                        <span class="detail-value">
                            <?= escapeText($value) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

        <!-- DONOR-ONLY AVAILABILITY UPDATE -->

        <?php if ($role === "donor"): ?>

            <section class="card">

                <h2>Update My Availability</h2>

                <p class="availability-description">
                    Let the system know whether you
                    are currently available to be
                    contacted about potential blood
                    donation requests.
                </p>

                <form
                    method="POST"
                    action="dashboard.php"
                    class="availability-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeText(
                            $_SESSION["availability_csrf"]
                        ) ?>"
                    >

                    <label for="availability">
                        Availability Status
                    </label>

                    <select
                        id="availability"
                        name="availability"
                        required
                    >

                        <option
                            value="1"
                            <?= (int) $profile["availability"] === 1
                                ? "selected" : "" ?>
                        >
                            Available
                        </option>

                        <option
                            value="0"
                            <?= (int) $profile["availability"] === 0
                                ? "selected" : "" ?>
                        >
                            Unavailable
                        </option>

                    </select>

                    <button type="submit">
                        Save Availability
                    </button>

                </form>

                <div class="note">
                    Availability indicates your
                    willingness to be contacted.
                    It does not confirm medical
                    eligibility to donate blood.
                </div>

            </section>

        <?php else: ?>

            <!-- PATIENT INFORMATION -->

            <div class="note">
                Your patient information is loaded
                from MySQL. Blood request management
                will be added in the next development
                stage.
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

</main>

<footer>
    Online Blood Donation Management System
</footer>

</body>
</html>
