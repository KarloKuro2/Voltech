<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    header('Location: po_warehouse_materials.php?error=1');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $warehouse = mysqli_real_escape_string($con, $_POST['warehouse']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $slots = (int)$_POST['slots'];
    $update_query = "UPDATE warehouses SET warehouse='$warehouse', category='$category', slots=$slots WHERE id=$id";
    if ($con->query($update_query)) {
        header('Location: po_warehouse_materials.php?updated=1');
        exit();
    } else {
        header('Location: po_warehouse_materials.php?error=1');
        exit();
    }
} else {
    header('Location: po_warehouse_materials.php');
    exit();
} 