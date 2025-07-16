<?php
session_start();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$con = new mysqli("localhost", "root", "", "voltech2");
if ($id > 0) {
    $con->query("UPDATE notifications_admin SET is_read = 1 WHERE id = $id");
}
// Redirect back to the referring page, or fallback to dashboard
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin_dashboard.php';
header("Location: $redirect");
exit(); 