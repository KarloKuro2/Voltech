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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($con, $_POST['contact_person']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $now = date('Y-m-d H:i:s');
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $insert_sql = "INSERT INTO suppliers (supplier_name, contact_person, contact_number, email, address, status, approval, user_id, created_at, updated_at) VALUES ('$supplier_name', '$contact_person', '$contact_number', '$email', '$address', '$status', 'Pending', $user_id, '$now', '$now')";
    if ($con->query($insert_sql)) {
        // Insert notification for admin
        $user_name = isset($_SESSION['firstname']) && isset($_SESSION['lastname']) ? trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) : '';
        $notif_type = "Add Supplier";
        $notif_message = "$user_name added a new supplier: $supplier_name (Pending approval)";
        $stmt = $con->prepare("INSERT INTO notifications_admin (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->bind_param("iss", $user_id, $notif_type, $notif_message);
        $stmt->execute();
        $stmt->close();
        header('Location: po_suppliers.php?success=1');
    } else {
        $err = urlencode('Error adding supplier: ' . $con->error);
        header('Location: po_suppliers.php?error=' . $err);
    }
    exit();
} else {
    header('Location: po_suppliers.php');
    exit();
} 