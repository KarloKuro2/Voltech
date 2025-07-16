<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    header('Location: po_materials.php?error=1');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $material_name = mysqli_real_escape_string($con, $_POST['material_name']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $unit = mysqli_real_escape_string($con, $_POST['unit']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $material_price = (float)$_POST['material_price'];
    $labor_other = (float)$_POST['labor_other'];
    $total_amount = $material_price + $labor_other;
    $update_query = "UPDATE materials SET material_name='$material_name', category='$category', quantity=$quantity, unit='$unit', status='$status', supplier_name='$supplier_name', material_price=$material_price, labor_other=$labor_other, total_amount=$total_amount WHERE id=$id";
    if ($con->query($update_query)) {
        header('Location: po_materials.php?updated=1');
        exit();
    } else {
        header('Location: po_materials.php?error=1');
        exit();
    }
} else {
    header('Location: po_materials.php');
    exit();
} 