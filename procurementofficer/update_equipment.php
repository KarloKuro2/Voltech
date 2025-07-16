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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {
    $equipment_id = (int)$_POST['equipment_id'];
    $equipment_name = mysqli_real_escape_string($con, $_POST['equipment_name']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $depreciation = isset($_POST['depreciation']) && $_POST['depreciation'] !== '' ? floatval($_POST['depreciation']) : null;
    $equipment_price = isset($_POST['equipment_price']) && $_POST['equipment_price'] !== '' ? floatval($_POST['equipment_price']) : null;
    $update_query = "UPDATE equipment SET equipment_name='$equipment_name', status='$status', depreciation=" . ($depreciation !== null ? "'$depreciation'" : "NULL") . ", equipment_price=" . ($equipment_price !== null ? "'$equipment_price'" : "NULL") . " WHERE id=$equipment_id";
    if ($con->query($update_query)) {
        header('Location: po_equipment.php?success=edit');
        exit();
    } else {
        $err = urlencode($con->error);
        header("Location: po_equipment.php?error=$err");
        exit();
    }
} 