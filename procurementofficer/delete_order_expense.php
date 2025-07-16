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
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $expense_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    $delete_query = "DELETE FROM order_expenses WHERE expense_id='$expense_id' AND user_id='$user_id'";
    if (mysqli_query($con, $delete_query)) {
        header('Location: po_orders.php?deleted=1');
        exit();
    } else {
        header('Location: po_orders.php?error=1');
        exit();
    }
} 