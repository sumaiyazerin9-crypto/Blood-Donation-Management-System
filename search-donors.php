<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . "/config/db.php";

function escapeText($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Only authenticated patients may search donors.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"])
) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "patient") {
    http_response_code(403);
    exit("Access denied. This page is for patient accounts only.");
}

$bloodGroups = [
    "A+", "A-", "B+", "B-",
    "AB+", "AB-", "O+", "O-"
];

$districts = [];
$donors = [];
$error = "";

$searchRequested = isset($_GET["search"]);

$bloodGroup = isset($_GET["blood_group"])
    && is_string($_GET["blood_group"])
    ? trim($_GET["blood_group"])
    : "";

$district = isset($_GET["district"])
    && is_string($_GET["district"])
    ? trim($_GET["district"])
    : "";

try {
    $pdo = getDatabaseConnection();

    // Load districts containing available donors.
    $districtStatement = $pdo->query(
        "SELECT DISTINCT district
         FROM donors
         WHERE availability = 1
           AND district IS NOT NULL
           AND district <> ''
         ORDER BY district"
    );

    $districts = $districtStatement->fetchAll(
        PDO::FETCH_COLUMN
    );

    if ($searchRequested) {

        // Validate the selected blood group.
        if (
            $bloodGroup !== "" &&
            !in_array($bloodGroup, $bloodGroups, true)
        ) {
            $error = "Please select a valid blood group.";
        }

        // Validate the selected district.
        if (
            $district !== "" &&
            !in_array($district, $districts, true)
        ) {
            $error = "Please select a valid district.";
        }

        if ($error === "") {

            // Search only available donor accounts.
            $sql =
                "SELECT
                    d.donor_id,
                    d.blood_group,
                    d.district,
                    d.availability
                 FROM donors AS d
                 INNER JOIN users AS u
                    ON d.user_id = u.user_id
                 WHERE u.role = 'donor'
                   AND d.availability = 1";

            $parameters = [];

            // Optional blood group filter.
            if ($bloodGroup !== "") {
                $sql .= " AND d.blood_group = ?";
                $parameters[] = $bloodGroup;
            }

            // Optional district filter.
            if ($district !== "") {
                $sql .= " AND d.district = ?";
                $parameters[] = $district;
            }

            $sql .=
                " ORDER BY d.district ASC,
                           d.donor_id ASC
                  LIMIT 50";

            $statement = $pdo->prepare($sql);
            $statement->execute($parameters);

            $donors = $statement->fetchAll(
                PDO::FETCH_ASSOC
            );
        }
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = "Unable to search donors right now. Please try again.";
}

$patientName = (string) (
    $_SESSION["display_name"] ?? "Patient"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Find Donors | Blood Donation</title>

<style>
:root {
    --red: #8c2634;
    --dark-red: #6f1b29;
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
    background: var(--cream);
    color: var(--ink);
    font-family: Arial, Helvetica, sans-serif;
}

a {
    color: inherit;
    text-decoration: none;
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
}

.back {
    font-size: 13px;
    color: var(--gray);
}

.back:hover {
    color: var(--red);
}

main {
    min-height: 75vh;
    padding: 60px 0 85px;
}

.label {
    color: var(--red);
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 2px;
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
    max-width: 700px;
    margin-bottom: 35px;
}

.card {
    background: white;
    border: 1px solid var(--border);
    padding: 35px;
    margin-bottom: 25px;
}

.card h2 {
    font-family: Georgia, serif;
    font-weight: normal;
    font-size: 25px;
    margin: 0 0 25px;
}

.search-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
    margin-bottom: 25px;
}

.field label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 10px;
}

.field select {
    width: 100%;
    border: 1px solid #d6d3ce;
    background: white;
    padding: 14px;
    color: var(--ink);
    font: 14px Arial, sans-serif;
    border-radius: 3px;
}

.field select:focus {
    outline: 1px solid var(--red);
    border-color: var(--red);
}

button {
    background: var(--red);
    color: white;
    border: 0;
    padding: 14px 27px;
    font: bold 13px Arial, sans-serif;
    cursor: pointer;
}

button:hover {
    background: var(--dark-red);
}

.results-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 22px;
}

.results-heading h2 {
    margin: 0;
}

.count {
    color: var(--gray);
    font-size: 13px;
}

.result {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    align-items: center;
}

.result:last-child {
    border-bottom: 0;
}

.result-label {
    display: block;
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 8px;
}

.result-value {
    font-size: 15px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.status {
    color: #225b34;
    font-size: 13px;
    font-weight: bold;
}

.empty {
    padding: 20px 0;
    color: var(--gray);
    font-size: 14px;
    line-height: 1.7;
}

.alert {
    padding: 17px 20px;
    margin-bottom: 25px;
    background: #fff0f1;
    color: var(--red);
    border: 1px solid #eac5cc;
    font-size: 14px;
}

.note {
    background: white;
    border-left: 3px solid var(--red);
    padding: 20px;
    color: var(--gray);
    font-size: 13px;
    line-height: 1.8;
    margin-top: 25px;
}

footer {
    text-align: center;
    border-top: 1px solid var(--border);
    padding: 25px;
    color: var(--gray);
    font-size: 12px;
}

@media (max-width: 650px) {
    .search-grid,
    .result {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 24px;
    }

    main {
        padding: 42px 0;
    }

    .result {
        gap: 12px;
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

        <a href="dashboard.php" class="back">
            &larr; Back to Dashboard
        </a>

    </div>
</header>

<main>
<div class="container">

    <div class="label">
        Patient Services
    </div>

    <h1>Find Available Donors</h1>

    <p class="intro">
        Welcome, <strong><?= escapeText($patientName) ?></strong>.
        Search registered donors using their
        blood group and district.
        Only donors currently marked as
        available will appear in the results.
    </p>

    <?php if ($error !== ""): ?>
        <div class="alert" role="alert">
            <?= escapeText($error) ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <h2>Search Donors</h2>

        <form method="GET" action="search-donors.php">

            <input type="hidden" name="search" value="1">

            <div class="search-grid">

                <div class="field">
                    <label for="blood_group">
                        Blood Group
                    </label>

                    <select
                        id="blood_group"
                        name="blood_group"
                    >

                        <option value="">
                            All Blood Groups
                        </option>

                        <?php foreach ($bloodGroups as $group): ?>

                            <option
                                value="<?= escapeText($group) ?>"
                                <?= $bloodGroup === $group
                                    ? "selected" : "" ?>
                            >
                                <?= escapeText($group) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="field">
                    <label for="district">
                        District
                    </label>

                    <select id="district" name="district">

                        <option value="">
                            All Districts
                        </option>

                        <?php foreach ($districts as $area): ?>

                            <option
                                value="<?= escapeText($area) ?>"
                                <?= $district === $area
                                    ? "selected" : "" ?>
                            >
                                <?= escapeText($area) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <button type="submit">
                Search Available Donors
            </button>

        </form>

    </section>

    <?php if ($searchRequested && $error === ""): ?>

        <section class="card">

            <div class="results-heading">

                <h2>Search Results</h2>

                <span class="count">
                    <?= count($donors) ?>
                    result(s) shown
                </span>

            </div>

            <?php if (empty($donors)): ?>

                <div class="empty">
                    No available donors matched
                    your selected filters.
                    Try another blood group or district.
                </div>

            <?php else: ?>

                <?php foreach ($donors as $donor): ?>

                    <div class="result">

                        <div>
                            <span class="result-label">
                                Donor Reference
                            </span>

                            <span class="result-value">
                                #<?= escapeText($donor["donor_id"]) ?>
                            </span>
                        </div>

                        <div>
                            <span class="result-label">
                                Blood Group
                            </span>

                            <span class="result-value">
                                <?= escapeText($donor["blood_group"]) ?>
                            </span>
                        </div>

                        <div>
                            <span class="result-label">
                                District
                            </span>

                            <span class="result-value">
                                <?= escapeText($donor["district"]) ?>
                            </span>

                            <div class="status">
                                Available
                            </div>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    <?php endif; ?>

    <div class="note">
        Search results show registered donors
        who have marked themselves available.
        A matching blood group does not confirm
        medical suitability or donation eligibility.
        Donor contact and blood request coordination
        will be handled through the request and
        hospital features.
    </div>

</div>
</main>

<footer>
    Online Blood Donation Management System
</footer>

</body>
</html>
