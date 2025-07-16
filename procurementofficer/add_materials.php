<?php
session_start();
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Get user info from session
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$user_level = isset($_SESSION['user_level']) ? intval($_SESSION['user_level']) : 0;
$user_name = isset($_SESSION['firstname']) && isset($_SESSION['lastname']) ? trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) : '';

// Get POST data
$material_name = isset($_POST['material_name']) ? trim($_POST['material_name']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Available';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
$purchase_date = isset($_POST['purchase_date']) ? trim($_POST['purchase_date']) : date('Y-m-d');
$material_price = isset($_POST['material_price']) ? floatval($_POST['material_price']) : 0;
$labor_other = isset($_POST['labor_other']) ? floatval($_POST['labor_other']) : 0;
$total_amount = $material_price + $labor_other;

// Insert material
$sql = "INSERT INTO materials (material_name, category, quantity, unit, status, approval, location, supplier_name, purchase_date, material_price, labor_other, total_amount, user_id) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?)";
$stmt = $con->prepare($sql);
$stmt->bind_param("ssissssssddi", $material_name, $category, $quantity, $unit, $status, $location, $supplier_name, $purchase_date, $material_price, $labor_other, $total_amount, $user_id);
$stmt->execute();
$stmt->close();

// Prepare notification
if ($user_level == 4) { // Procurement Officer or Admin
    $notif_type = "Add Material";
    $notif_message = "$user_name added a new material: $material_name (₱$material_price, Total: ₱$total_amount)";
    $stmt2 = $con->prepare("INSERT INTO notifications_admin (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt2->bind_param("iss", $user_id, $notif_type, $notif_message);
    $stmt2->execute();
    $stmt2->close();
}

$con->close();
header("Location: po_materials.php?success=1");
exit(); 