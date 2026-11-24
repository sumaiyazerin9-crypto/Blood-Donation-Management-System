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

// Only authenticated Hospital accounts can access this page.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "hospital"
) {
    header("Location: hospital-login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$profile = false;
$error = "";

$stats = [
    "total_requests" => 0,
    "pending_requests" => 0,
    "approved_requests" => 0,
    "rejected_requests" => 0,
    "fulfilled_requests" => 0,
    "cancelled_requests" => 0
];

// Prepare the logout security token.
if (empty($_SESSION["logout_csrf"])) {
    $_SESSION["logout_csrf"] = bin2hex(random_bytes(32));
}

try {

    $pdo = getDatabaseConnection();

    // Retrieve Hospital information using SQL JOIN.
    $statement = $pdo->prepare(
        "SELECT
            u.user_id,
            u.name,
            u.email,
            h.hospital_id,
            h.hospital_name,
            h.district,
            h.phone
         FROM users AS u
         INNER JOIN hospitals AS h
            ON u.user_id = h.user_id
         WHERE u.user_id = ?
           AND u.role = 'hospital'
         LIMIT 1"
    );

    $statement->execute([$userId]);

    $profile = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {

        $error = "Hospital profile could not be found.";

    } else {

        // Keep the authenticated Hospital ID in the session.
        $_SESSION["hospital_id"] =
            (int) $profile["hospital_id"];

        $_SESSION["hospital_name"] =
            $profile["hospital_name"];

        // Retrieve request statistics for this Hospital only.
        $statsStatement = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_requests,
                SUM(status = 'Pending') AS pending_requests,
                SUM(status = 'Approved') AS approved_requests,
                SUM(status = 'Rejected') AS rejected_requests,
                SUM(status = 'Fulfilled') AS fulfilled_requests,
                SUM(status = 'Cancelled') AS cancelled_requests
             FROM blood_requests
             WHERE hospital_id = ?"
        );

        $statsStatement->execute([
            (int) $profile["hospital_id"]
        ]);

        $result = $statsStatement->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            foreach ($stats as $key => $value) {
                $stats[$key] = (int) ($result[$key] ?? 0);
            }
        }
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = "Unable to load Hospital information. Please try again.";

}

// Prepare formatted identifiers.
$formattedUserId = "";
$formattedHospitalId = "";

if ($profile) {

    $formattedUserId = "USR-" .
        str_pad(
            (string) $profile["user_id"],
            4,
            "0",
            STR_PAD_LEFT
        );

    $formattedHospitalId = "HOS-" .
        str_pad(
            (string) $profile["hospital_id"],
            4,
            "0",
            STR_PAD_LEFT
        );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Hospital Dashboard | Blood Donation</title>

<style>

:root {
    --red: #8c2634;
    --dark-red: #6f1b29;
    --ink: #262626;
    --gray: #686868;
    --cream: #f7f5f1;
    --border: #e4ded6;
    --white: #ffffff;
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

/* REQUEST STATISTICS */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.stat {
    background: white;
    border: 1px solid var(--border);
    padding: 23px;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 13px;
}

.stat-value {
    font-family: Georgia, serif;
    font-size: 32px;
    color: var(--red);
}

/* MESSAGES */

.alert-error {
    background: #fff0f1;
    border: 1px solid #eac5cc;
    color: var(--red);
    padding: 17px 20px;
    margin-bottom: 25px;
    font-size: 14px;
    line-height: 1.7;
}

.note {
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

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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

@media (max-width: 400px) {

    .stats-grid {
        grid-template-columns: 1fr;
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

        <form method="POST" action="logout.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeText($_SESSION["logout_csrf"]) ?>"
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
        Hospital Portal
    </div>

    <h1>Hospital Dashboard</h1>

    <p class="intro">

        Welcome,

        <strong>
            <?= escapeText(
                $profile["hospital_name"] ?? "Hospital"
            ) ?>
        </strong>.

        Manage your hospital profile and monitor blood
        request activity through your dashboard.

    </p>

    <?php if ($error !== ""): ?>

        <div class="alert-error" role="alert">
            <?= escapeText($error) ?>
        </div>

    <?php elseif ($profile): ?>

        <!-- HOSPITAL PROFILE -->

        <section class="card">

            <h2>Hospital Profile</h2>

            <div class="details">

                <div class="detail">
                    <span class="detail-label">
                        User ID
                    </span>

                    <span class="detail-value">
                        <?= escapeText($formattedUserId) ?>
                    </span>
                </div>

                <div class="detail">
                    <span class="detail-label">
                        Hospital ID
                    </span>

                    <span class="detail-value">
                        <?= escapeText($formattedHospitalId) ?>
                    </span>
                </div>

                <div class="detail">
                    <span class="detail-label">
                        Account Holder
                    </span>

                    <span class="detail-value">
                        <?= escapeText($profile["name"]) ?>
                    </span>
                </div>

                <div class="detail">
                    <span class="detail-label">
                        Email Address
                    </span>

                    <span class="detail-value">
                        <?= escapeText($profile["email"]) ?>
                    </span>
                </div>

                <div class="detail">
                    <span class="detail-label">
                        Hospital Name
                    </span>

                    <span class="detail-value">
                        <?= escapeText($profile["hospital_name"]) ?>
                    </span>
                </div>

                <div class="detail">
                    <span class="detail-label">
                        District
                    </span>

                    <span class="detail-value">
                        <?= escapeText($profile["district"]) ?>
                    </span>
                </div>

                <div class="detail">
                    <span class="detail-label">
                        Phone Number
                    </span>

                    <span class="detail-value">
                        <?= escapeText($profile["phone"]) ?>
                    </span>
                </div>

            </div>

        </section>

        <!-- REQUEST STATISTICS -->

        <section class="card">

            <h2>Blood Request Overview</h2>

            <div class="stats-grid">

                <div class="stat">
                    <span class="stat-label">
                        Total Requests
                    </span>

                    <span class="stat-value">
                        <?= $stats["total_requests"] ?>
                    </span>
                </div>

                <div class="stat">
                    <span class="stat-label">
                        Pending
                    </span>

                    <span class="stat-value">
                        <?= $stats["pending_requests"] ?>
                    </span>
                </div>

                <div class="stat">
                    <span class="stat-label">
                        Approved
                    </span>

                    <span class="stat-value">
                        <?= $stats["approved_requests"] ?>
                    </span>
                </div>

                <div class="stat">
                    <span class="stat-label">
                        Rejected
                    </span>

                    <span class="stat-value">
                        <?= $stats["rejected_requests"] ?>
                    </span>
                </div>

                <div class="stat">
                    <span class="stat-label">
                        Fulfilled
                    </span>

                    <span class="stat-value">
                        <?= $stats["fulfilled_requests"] ?>
                    </span>
                </div>

                <div class="stat">
                    <span class="stat-label">
                        Cancelled
                    </span>

                    <span class="stat-value">
                        <?= $stats["cancelled_requests"] ?>
                    </span>
                </div>

            </div>

        </section>

        <!-- REQUEST MANAGEMENT NOTICE -->

        <div class="note">

            <strong>Request Management</strong>

            <p>
                Patient blood request submission and Hospital
                request processing will be connected in the
                next development stage. Once implemented,
                requests assigned to this Hospital will appear
                here.
            </p>

        </div>

    <?php endif; ?>

</div>

</main>

<footer>
    Online Blood Donation Management System
</footer>

</body>

</html>
