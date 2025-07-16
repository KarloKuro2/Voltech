<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store the timeout status before destroying the session
$timedOut = isset($_GET['timeout']) ? true : false;

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with timeout status
if ($timedOut) {
    header("Location: login.php?timeout=1");
} else {
    header("Location: login.php?logout=1");
}
exit();