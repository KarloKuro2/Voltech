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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $material_name = mysqli_real_escape_string($con, $_POST['material_name']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $unit = mysqli_real_escape_string($con, $_POST['unit']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $location = mysqli_real_escape_string($con, $_POST['location']);
    $assigned_to = mysqli_real_escape_string($con, $_POST['assigned_to']);
    $purchase_date = mysqli_real_escape_string($con, $_POST['purchase_date']);
    $material_price = (float)$_POST['material_price'];
    $labor_other = (float)$_POST['labor_other'];
    $total_amount = $material_price + $labor_other;
    $insert_query = "INSERT INTO materials (material_name, category, quantity, unit, status, supplier_name, location, assigned_to, purchase_date, material_price, labor_other, total_amount) VALUES ('$material_name', '$category', $quantity, '$unit', '$status', '$supplier_name', '$location', '$assigned_to', '$purchase_date', $material_price, $labor_other, $total_amount)";
    if ($con->query($insert_query)) {
        header('Location: po_warehouse_materials.php?added=1');
        exit();
    } else {
        header('Location: po_warehouse_materials.php?error=1');
        exit();
    }
} else {
    header('Location: po_warehouse_materials.php');
    exit();
} 