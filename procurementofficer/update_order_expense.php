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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_id = intval($_POST['expense_id']);
    $expensecategory = mysqli_real_escape_string($con, $_POST['expensecategory']);
    $expenseamount = floatval($_POST['expenseamount']);
    $expensedate = mysqli_real_escape_string($con, $_POST['expensedate']);
    $description = trim(mysqli_real_escape_string($con, $_POST['description']));
    $user_id = $_SESSION['user_id'];

    $update_query = "UPDATE order_expenses SET expensecategory='$expensecategory', expense='$expenseamount', expensedate='$expensedate', description='$description' WHERE expense_id='$expense_id' AND user_id='$user_id'";
    if (mysqli_query($con, $update_query)) {
        header('Location: po_orders.php?updated=1');
        exit();
    } else {
        header('Location: po_orders.php?error=1');
        exit();
    }
} 