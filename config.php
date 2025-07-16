<?php
// Only start the session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = new mysqli("localhost", "root", "", "Voltech2");

if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
