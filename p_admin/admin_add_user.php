<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = mysqli_real_escape_string($con, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($con, $_POST['lastname']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_level = mysqli_real_escape_string($con, $_POST['user_level']);

    $query = "INSERT INTO users (firstname, lastname, email, password, user_level, is_verified) 
              VALUES ('$firstname', '$lastname', '$email', '$password', '$user_level', 1)";
    
    if (mysqli_query($con, $query)) {
        header('Location: admin_manage_users.php?success=1');
    } else {
        header('Location: admin_manage_users.php?error=1');
    }
    exit();
}