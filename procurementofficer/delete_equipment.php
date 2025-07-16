<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $delete_sql = "DELETE FROM equipment WHERE id = $id";
    if ($con->query($delete_sql) === TRUE) {
        $_SESSION['message'] = "Equipment deleted successfully";
        $_SESSION['message_type'] = "success";
        header("Location: po_equipment.php?success=delete");
        exit();
    } else {
        $_SESSION['message'] = "Error deleting record: " . $con->error;
        $_SESSION['message_type'] = "danger";
        header("Location: po_equipment.php?error=" . urlencode($con->error));
        exit();
    }
} 