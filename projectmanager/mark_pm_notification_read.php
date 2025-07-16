<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 3) {
    header("Location: pm_dashboard.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);
if (isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $con = new mysqli("localhost", "root", "", "voltech2");
    $con->query("UPDATE notifications_projectmanager SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id");
}
header("Location: " . $_SERVER['HTTP_REFERER']);
exit(); 