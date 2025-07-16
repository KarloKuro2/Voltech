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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_name = mysqli_real_escape_string($con, $_POST['equipment_name']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $user_level = isset($_SESSION['user_level']) ? intval($_SESSION['user_level']) : 0;
    $user_name = isset($_SESSION['firstname']) && isset($_SESSION['lastname']) ? trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) : '';
    $equipment_price = isset($_POST['equipment_price']) && $_POST['equipment_price'] !== '' ? floatval($_POST['equipment_price']) : null;
    if ($category === 'Company') {
        $depreciation = isset($_POST['depreciation']) && $_POST['depreciation'] !== '' ? floatval($_POST['depreciation']) : null;
        $rental_fee = null;
    } else {
        $depreciation = null;
        $rental_fee = isset($_POST['rental_fee']) && $_POST['rental_fee'] !== '' ? floatval($_POST['rental_fee']) : null;
    }
    $insert_query = "INSERT INTO equipment (equipment_name, status, approval, category, depreciation, rental_fee, equipment_price, user_id) VALUES ('$equipment_name', '$status', 'Pending', '$category', " . ($depreciation !== null ? "'$depreciation'" : "NULL") . ", " . ($rental_fee !== null ? "'$rental_fee'" : "NULL") . ", " . ($equipment_price !== null ? "'$equipment_price'" : "NULL") . ", $user_id)";
    if ($con->query($insert_query)) {
        // Notification logic (like add_materials.php)
        $notif_type = "Add Equipment";
        $price_display = $category === 'Company' ? $equipment_price : $rental_fee;
        $notif_message = "$user_name added a new equipment: $equipment_name (₱$price_display)";
        // Only notify Admin if Procurement Officer or Admin
        if ($user_level == 4) {
            $stmt2 = $con->prepare("INSERT INTO notifications_admin (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt2->bind_param("iss", $user_id, $notif_type, $notif_message);
            $stmt2->execute();
            $stmt2->close();
        }
        header('Location: po_equipment.php?success=1');
        exit();
    } else {
        $err = urlencode($con->error);
        header("Location: po_equipment.php?error=$err");
        exit();
    }
} 