<?php
session_start();

// Logout requests must come from the logout form.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$expectedToken =
    (string) ($_SESSION["logout_csrf"] ?? "");

$submittedToken =
    (string) ($_POST["csrf_token"] ?? "");

if (
    $expectedToken === "" ||
    !hash_equals($expectedToken, $submittedToken)
) {
    http_response_code(403);
    exit("Invalid logout request.");
}

// Clear session information.
$_SESSION = [];

// Remove the session cookie.
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 3600,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax"
        ]
    );
}

// Destroy the authenticated session.
session_destroy();

// Return to the login page.
header("Location: login.php?logged_out=1");
exit;
