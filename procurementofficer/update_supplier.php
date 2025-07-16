<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    header('Location: po_suppliers.php?error=1');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    $id = (int)$_POST['edit_supplier_id'];
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($con, $_POST['contact_person']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $now = date('Y-m-d H:i:s');
    $update_sql = "UPDATE suppliers SET supplier_name='$supplier_name', contact_person='$contact_person', contact_number='$contact_number', email='$email', address='$address', status='$status', updated_at='$now' WHERE id=$id";
    if ($con->query($update_sql)) {
        header('Location: po_suppliers.php?updated=1');
    } else {
        $err = urlencode('Error updating supplier: ' . $con->error);
        header('Location: po_suppliers.php?error=' . $err);
    }
    exit();
} else {
    header('Location: po_suppliers.php');
    exit();
} 