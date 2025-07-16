<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: po_dashboard.php');
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
$id = intval($_GET['id']);
$user_id = intval($_SESSION['user_id']);
// Mark as read only if it belongs to this user
$con->query("UPDATE notifications_procurement SET is_read = 1 WHERE id = $id AND user_id = $user_id");
header('Location: po_dashboard.php');
exit(); 