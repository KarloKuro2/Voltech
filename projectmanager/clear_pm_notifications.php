<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 3) {
    header("Location: pm_dashboard.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);
$con = new mysqli("localhost", "root", "", "voltech2");
$con->query("DELETE FROM notifications_projectmanager WHERE user_id = $user_id");
header("Location: " . $_SERVER['HTTP_REFERER']);
exit(); 