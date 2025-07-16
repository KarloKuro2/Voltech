<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set session timeout to 1 hour (3600 seconds)
$inactive = 3600;

// Check if session timeout is set
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Last request was more than 1 hour ago
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

// Update last activity time stamp
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Change session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

include("config.php");

// Check if user is logged in
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // If not logged in but has remember me cookie
    if(isset($_COOKIE['user_email']) && isset($_COOKIE['remember_token'])) {
        // Handle remember me functionality
        $email = $_COOKIE['user_email'];
        $sql = "SELECT id, firstname, lastname, email FROM users WHERE email = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['email'] = $email;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
        } else {
            header("Location: login.php");
            exit();
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// Get user data if not already set
if(!isset($userid)) {
    $sess_email = $_SESSION["email"];
    $sql = "SELECT id, firstname, lastname, email, user_level FROM users WHERE email = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $sess_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userid = $row["id"];
        $firstname = $row["firstname"];
        $lastname = $row["lastname"];
        $username = $row["firstname"] . " " . $row["lastname"];
        $useremail = $row["email"];
        $user_level = $row["user_level"];
        $userprofile = "uploads/default_profile.png";
        // Ensure user info is also in session for sidebar use
        $_SESSION['userid'] = $userid;
        $_SESSION['username'] = $username;
        $_SESSION['useremail'] = $useremail;
        $_SESSION['userprofile'] = $userprofile;
        
        // Update session with user level if not set
        if(!isset($_SESSION['user_level'])) {
            $_SESSION['user_level'] = $user_level;
        }
    } else {
        // User not found, log them out
        session_unset();
        session_destroy();
        setcookie('user_email', '', time() - 3600, "/");
        setcookie('remember_token', '', time() - 3600, "/");
        header("Location: login.php?error=user_not_found");
        exit();
    }
}