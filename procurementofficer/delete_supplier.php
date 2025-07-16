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
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $delete_sql = "DELETE FROM suppliers WHERE id = $id";
    if ($con->query($delete_sql)) {
        header('Location: po_suppliers.php?deleted=1');
    } else {
        $err = urlencode('Error deleting supplier: ' . $con->error);
        header('Location: po_suppliers.php?error=' . $err);
    }
    exit();
} else {
    header('Location: po_suppliers.php');
    exit();
} 