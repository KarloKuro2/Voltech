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
    $expensecategory = mysqli_real_escape_string($con, $_POST['expensecategory']);
    $expenseamount = floatval($_POST['expenseamount']);
    $expensedate = mysqli_real_escape_string($con, $_POST['expensedate']);
    $description = trim(mysqli_real_escape_string($con, $_POST['description']));
    $user_id = $_SESSION['user_id'];

    // Backend validation
    if (empty($expensecategory) || empty($expensedate) || empty($description) || $expenseamount <= 0) {
        header('Location: po_orders.php?error=validation');
        exit();
    }

    $insert_query = "INSERT INTO order_expenses (user_id, expensecategory, expense, expensedate, description) VALUES ('$user_id', '$expensecategory', '$expenseamount', '$expensedate', '$description')";
    if (mysqli_query($con, $insert_query)) {
        header('Location: po_orders.php?success=1');
        exit();
    } else {
        header('Location: po_orders.php?error=1');
        exit();
    }
} 