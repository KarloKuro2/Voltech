<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $id = mysqli_real_escape_string($con, $_POST['user_id']);
    $query = "DELETE FROM users WHERE id='$id'";
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . mysqli_error($con)]);
    }
    exit();
}
echo json_encode(['success' => false, 'message' => 'Invalid request']);