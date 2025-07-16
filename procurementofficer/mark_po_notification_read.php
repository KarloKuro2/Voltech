<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: po_dashboard.php');
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
$id = intval($_GET['id']);
$user_id = intval($_SESSION['user_id']);
// Fetch the notification
$res = $con->query("SELECT user_id, notif_type FROM notifications_procurement WHERE id = $id LIMIT 1");
if ($res && $res->num_rows > 0) {
    $notif = $res->fetch_assoc();
    // Allow if user_id matches, or if notif_type contains 'Request' (except Activation)
    if (
        $notif['user_id'] == $user_id ||
        (stripos($notif['notif_type'], 'Request') !== false && stripos($notif['notif_type'], 'Activation') === false)
    ) {
        $con->query("UPDATE notifications_procurement SET is_read = 1 WHERE id = $id");
    }
}
header('Location: po_dashboard.php');
exit(); 