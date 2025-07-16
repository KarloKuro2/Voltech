<?php
if (!isset($_GET['user_id']) || !isset($_GET['user_level'])) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    echo '<div class="alert alert-danger">Database connection failed.</div>';
    exit();
}
$user_id = intval($_GET['user_id']);
$user_level = intval($_GET['user_level']);
if ($user_level != 4) {
    echo '<div class="alert alert-warning">No activities for this user type.</div>';
    exit();
}

// Fetch admin notifications and show user name and notif_type only
$sql = "SELECT n.user_id, n.notif_type, u.firstname, u.lastname FROM notifications_admin n JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 20";
$res = $con->query($sql);
if ($res && $res->num_rows > 0) {
    echo '<table class="table table-bordered table-striped mb-0">';
    echo '<thead><tr><th>User Name</th><th>Notification Type</th></tr></thead><tbody>';
    while ($row = $res->fetch_assoc()) {
        $fullname = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
        $notif_type = htmlspecialchars($row['notif_type']);
        echo "<tr><td>$fullname</td><td>$notif_type</td></tr>";
    }
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-info">No admin notifications found.</div>';
}
?> 