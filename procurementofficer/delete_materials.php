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
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $delete_query = "DELETE FROM materials WHERE id=$id";
    if ($con->query($delete_query)) {
        header('Location: po_materials.php?deleted=1');
        exit();
    } else {
        header('Location: po_materials.php?error=1');
        exit();
    }
} else {
    header('Location: po_materials.php');
    exit();
} 