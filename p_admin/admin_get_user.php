<?php
include("session.php");

if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] != 2) {
    die(json_encode(['error' => 'Unauthorized access']));
}

if (isset($_GET['id'])) {
    $user_id = mysqli_real_escape_string($con, $_GET['id']);
    $query = "SELECT id, firstname, lastname, email, user_level, is_verified FROM users WHERE id = '$user_id'";
    $result = mysqli_query($con, $query);
    
    if ($user = mysqli_fetch_assoc($result)) {
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
}