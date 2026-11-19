<?php
require_once __DIR__ . "/config/db.php";

// Local development database connection test
$isLocal = in_array(
    $_SERVER["REMOTE_ADDR"] ?? "",
    ["127.0.0.1", "::1"],
    true
);

$connected = false;
$tableCount = 0;

if ($isLocal) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->query("SHOW TABLES");
        $tableCount = count(
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
        $connected = true;
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Online Blood Donation Management System</title>

<style>
:root {
    --red: #8c2634;
    --dark-red: #6f1b29;
    --black: #262626;
    --gray: #686868;
    --cream: #f7f5f1;
    --white: #ffffff;
    --border: #e4ded6;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    background: var(--white);
    color: var(--black);
    font-family: Arial, Helvetica, sans-serif;
}

a {
    text-decoration: none;
    color: inherit;
}

a:focus-visible {
    outline: 2px solid var(--red);
    outline-offset: 4px;
}

.container {
    width: min(1120px, 90%);
    margin: auto;
}

/* NAVIGATION */

header {
    background: var(--white);
    border-bottom: 1px solid var(--border);
}

.navbar {
    min-height: 85px;
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

.logo span {
    color: var(--black);
}

nav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 30px;
}

nav a {
    font-size: 13px;
    font-weight: 600;
}

nav a:hover {
    color: var(--red);
}

/* LOGIN BUTTON - NEW */

nav .login-button {
    display: inline-block;
    background: var(--red);
    color: var(--white);
    padding: 12px 25px;
    font-size: 13px;
    font-weight: bold;
    transition: background .2s;
}

nav .login-button:hover {
    background: var(--dark-red);
    color: var(--white);
}

/* HOMEPAGE INTRODUCTION */

.hero {
    background: var(--cream);
    min-height: min(790px, calc(100svh - 85px));
    display: flex;
    align-items: center;
    padding: 100px 0;
    border-bottom: 1px solid var(--border);
}

.hero-content {
    max-width: 850px;
    margin: auto;
    text-align: center;
}

.hero-label {
    font-size: 11px;
    color: var(--red);
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: bold;
    margin-bottom: 28px;
}

.hero h1 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(42px, 6vw, 76px);
    font-weight: normal;
    line-height: 1.18;
    letter-spacing: -2px;
    margin: 0 auto 27px;
    max-width: 840px;
}

.hero h1 span {
    color: var(--red);
}

.hero p {
    max-width: 570px;
    margin: 0 auto;
    font-size: 16px;
    line-height: 1.9;
    color: var(--gray);
}

.hero-button {
    display: inline-block;
    margin-top: 35px;
    padding: 16px 31px;
    background: var(--red);
    color: white;
    font-size: 13px;
    font-weight: bold;
    transition: background .2s;
}

.hero-button:hover {
    background: var(--dark-red);
}

/* GENERAL SECTIONS */

section.content-section {
    padding: 95px 0;
}

.section-label {
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--red);
    font-size: 11px;
    font-weight: bold;
    margin-bottom: 17px;
}

.section-title {
    font-family: Georgia, serif;
    font-size: clamp(30px, 4vw, 46px);
    font-weight: normal;
    letter-spacing: -1px;
    line-height: 1.25;
    margin: 0 0 20px;
}

.section-description {
    max-width: 650px;
    color: var(--gray);
    font-size: 15px;
    line-height: 1.9;
}

/* ABOUT */

.about {
    background: white;
}

.about-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 65px;
    align-items: center;
}

.about-note {
    border-left: 3px solid var(--red);
    padding: 15px 0 15px 25px;
    font-family: Georgia, serif;
    font-size: 24px;
    line-height: 1.6;
}

/* MODULES */

.modules-section {
    background: var(--cream);
}

.modules-heading {
    margin-bottom: 43px;
}

.modules-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
}

.module-card {
    background: white;
    padding: 36px;
    border: 1px solid var(--border);
    min-height: 230px;
    transition: border-color .2s, transform .2s;
}

.module-card:hover {
    border-color: var(--red);
    transform: translateY(-3px);
}

.module-number {
    color: var(--red);
    font-size: 12px;
    letter-spacing: 1px;
}

.module-card h3 {
    font-family: Georgia, serif;
    font-weight: normal;
    font-size: 29px;
    margin: 23px 0 13px;
}

.module-card p {
    font-size: 14px;
    line-height: 1.9;
    color: var(--gray);
    max-width: 380px;
    margin: 0;
}

.module-card small {
    display: inline-block;
    margin-top: 18px;
    color: var(--red);
    font-size: 11px;
    letter-spacing: .4px;
}

/* REGISTRATION LINKS */

.module-action {
    display: inline-block;
    margin-top: 18px;
    color: var(--red);
    font-size: 13px;
    font-weight: bold;
    text-decoration: none;
    border-bottom: 1px solid var(--red);
    padding-bottom: 5px;
    transition: color .2s, border-color .2s;
}

.module-action:hover {
    color: var(--dark-red);
    border-color: var(--dark-red);
}

/* HOW IT WORKS */

.workflow-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 35px;
    margin-top: 45px;
}

.workflow-item {
    border-top: 2px solid var(--red);
    padding-top: 25px;
}

.workflow-item h3 {
    font-family: Georgia, serif;
    font-size: 23px;
    font-weight: normal;
}

.workflow-item p {
    color: var(--gray);
    font-size: 14px;
    line-height: 1.8;
}

/* LOCAL DEVELOPMENT STATUS */

.dev-status {
    border-top: 1px solid var(--border);
    padding: 25px 0;
    background: #f6f6f6;
}

.dev-status p {
    margin: 0;
    font-size: 13px;
    color: var(--gray);
}

.dev-success {
    color: #267346;
    font-weight: bold;
}

.dev-error {
    color: #ac2434;
    font-weight: bold;
}

/* FOOTER */

footer {
    padding: 38px 0;
    background: var(--black);
    color: white;
}

.footer-layout {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 25px;
}

footer strong {
    font-family: Georgia, serif;
    font-size: 20px;
    font-weight: normal;
}

footer p {
    color: #c5c5c5;
    font-size: 12px;
    line-height: 1.8;
}

/* MOBILE RESPONSIVENESS */

@media (max-width: 750px) {
    .navbar {
        flex-direction: column;
        padding: 23px 0;
    }

    nav {
        justify-content: center;
        gap: 20px;
    }

    .hero {
        min-height: 650px;
        padding: 75px 0;
    }

    .hero h1 {
        letter-spacing: -1px;
    }

    .about-layout,
    .modules-grid,
    .workflow-grid {
        grid-template-columns: 1fr;
    }

    section.content-section {
        padding: 65px 0;
    }

    .module-card {
        min-height: auto;
        padding: 28px;
    }

    .footer-layout {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION -->

<header id="home">
    <div class="container navbar">

        <a href="#home" class="logo">
            Blood <span>Donation.</span>
        </a>

        <nav aria-label="Main navigation">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#modules">Services</a>
            <a href="#how-it-works">How It Works</a>

            <!-- LOGIN BUTTON - NEW -->
            <a href="login.php" class="login-button">
                Login
            </a>
        </nav>

    </div>
</header>

<main>

<!-- HOMEPAGE INTRODUCTION -->

<section class="hero">

    <div class="container">
        <div class="hero-content">

            <div class="hero-label">
                Connecting Donors. Supporting Lives.
            </div>

            <h1>
                Online Blood Donation
                <span>Management System.</span>
            </h1>

            <p>
                Finding blood donors shouldn't be
                complicated. A centralized platform
                designed to connect donors, patients,
                and hospitals, making blood requests
                and donation coordination simpler.
            </p>

            <a href="#modules" class="hero-button">
                Explore Our Services
            </a>

        </div>
    </div>

</section>

<!-- ABOUT US -->

<section class="content-section about" id="about">

    <div class="container about-layout">

        <div>
            <div class="section-label">
                About Us
            </div>

            <h2 class="section-title">
                Making blood donation
                coordination simpler.
            </h2>

            <p class="section-description">
                Finding suitable blood donors
                during an emergency can be challenging.
                Our platform is designed to bring
                donor information, blood requests,
                and hospital coordination together
                in one organized system.
                <br><br>
                Our aim is to make the process
                more accessible, efficient,
                and straightforward for
                everyone involved.
            </p>
        </div>

        <div class="about-note">
            Bringing donors, patients,
            and hospitals closer through
            better communication and
            organized information.
        </div>

    </div>

</section>

<!-- SERVICES -->

<section class="content-section modules-section" id="modules">

    <div class="container">

        <div class="modules-heading">
            <div class="section-label">
                Our Services
            </div>

            <h2 class="section-title">
                How We Can Help
            </h2>

            <p class="section-description">
                Different people have different
                needs when it comes to blood donation.
                Our platform brings essential
                services together, with dedicated
                features for donors, patients,
                hospitals, and administrators.
            </p>
        </div>

        <div class="modules-grid">

            <!-- DONOR -->

            <article class="module-card">

                <span class="module-number">
                    01 / DONORS
                </span>

                <h3>Become a Donor</h3>

                <p>
                    Create and manage your donor
                    profile, update your availability,
                    and keep track of your
                    donation history.
                </p>

                <a href="register-donor.php"
                   class="module-action">
                    Register as a Donor &rarr;
                </a>

            </article>

            <!-- PATIENT -->

            <article class="module-card">

                <span class="module-number">
                    02 / PATIENTS
                </span>

                <h3>Find Blood Donors</h3>

                <p>
                    Submit a blood request,
                    search for potentially
                    compatible donors, and
                    stay informed about
                    your request status.
                </p>

                <a href="register-patient.php"
                   class="module-action">
                    Create a Patient Account &rarr;
                </a>

            </article>

            <!-- HOSPITAL -->

            <article class="module-card">

                <span class="module-number">
                    03 / HOSPITALS
                </span>

                <h3>Hospital Services</h3>

                <p>
                    Coordinate blood requests,
                    access relevant donor
                    information, and maintain
                    records of confirmed donations.
                </p>

                <small>COMING SOON</small>

            </article>

            <!-- ADMIN -->

            <article class="module-card">

                <span class="module-number">
                    04 / ADMINISTRATION
                </span>

                <h3>System Administration</h3>

                <p>
                    Manage user information,
                    monitor blood requests,
                    and maintain organized
                    donation records across
                    the platform.
                </p>

                <small>COMING SOON</small>

            </article>

        </div>
    </div>
</section>

<!-- HOW IT WORKS -->

<section class="content-section" id="how-it-works">

    <div class="container">

        <div class="section-label">
            Simple Steps
        </div>

        <h2 class="section-title">
            How It Works
        </h2>

        <p class="section-description">
            From registering as a donor
            to coordinating a blood request,
            the platform is designed to
            keep every step clear and organized.
        </p>

        <div class="workflow-grid">

            <!-- STEP 1 -->

            <div class="workflow-item">

                <span class="module-number">
                    STEP 01
                </span>

                <h3>Join the Network</h3>

                <p>
                    Create an account and
                    provide the information
                    relevant to your role.
                    Donors and patients can
                    manage their own profiles.
                </p>

            </div>

            <!-- STEP 2 -->

            <div class="workflow-item">

                <span class="module-number">
                    STEP 02
                </span>

                <h3>Find or Request Blood</h3>

                <p>
                    Search for potentially
                    suitable donors or submit
                    a blood request with
                    the required details
                    and hospital information.
                </p>

            </div>

            <!-- STEP 3 -->

            <div class="workflow-item">

                <span class="module-number">
                    STEP 03
                </span>

                <h3>Coordinate and Track</h3>

                <p>
                    Follow request updates
                    while authorized hospitals
                    coordinate the process
                    and maintain records
                    of confirmed donations.
                </p>

            </div>

        </div>

    </div>

</section>

<!-- LOCAL DATABASE CONNECTION TEST -->

<?php if ($isLocal): ?>

<section class="dev-status">
    <div class="container">

        <?php if ($connected): ?>

            <p>
                Local Database:
                <span class="dev-success">Connected</span>
                | Tables Found:
                <?php echo (int) $tableCount; ?>
            </p>

        <?php else: ?>

            <p class="dev-error">
                Database connection failed.
                Check XAMPP and db.php.
            </p>

        <?php endif; ?>

    </div>
</section>

<?php endif; ?>

</main>

<!-- FOOTER -->

<footer>

    <div class="container footer-layout">

        <div>
            <strong>
                Online Blood Donation Management System
            </strong>

            <p>
                Connecting donors, patients,
                and hospitals.
            </p>
        </div>

        <div>
            <p>
                Making blood donation
                coordination simpler.
            </p>
        </div>

    </div>

</footer>

</body>
</html>
