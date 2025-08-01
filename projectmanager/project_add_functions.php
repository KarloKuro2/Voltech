<?php
// Add Equipment to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_equipment'])) {
  $equipment_id = intval($_POST['equipment_id']);
  $category = mysqli_real_escape_string($con, $_POST['category']);
  $days_used = isset($_POST['days_used']) ? intval($_POST['days_used']) : 0;
  // Fetch price, rental_fee, and depreciation from equipment table
  $eq_res = mysqli_query($con, "SELECT equipment_price, rental_fee, depreciation FROM equipment WHERE id='$equipment_id' LIMIT 1");
  $eq = mysqli_fetch_assoc($eq_res);
  if (strtolower($category) === 'rental' || strtolower($category) === 'rent') {
    $price = $eq['rental_fee'];
    $depreciation = 'None';
  } else {
    $price = $eq['equipment_price'];
    $depreciation = is_numeric($eq['depreciation']) ? intval($eq['depreciation']) : $eq['depreciation'];
  }
  // Straight-line depreciation per day (for total)
  $depreciation_per_day = (is_numeric($depreciation) && $depreciation > 0) ? $price / ($depreciation * 365) : 0;
  $total = $depreciation_per_day * $days_used;
  $project_id = intval($_GET['id']);
  $status = 'Pending';
  $now = date('Y-m-d H:i:s');
  mysqli_query($con, "INSERT INTO project_add_equipment (project_id, equipment_id, category, days_used, total, depreciation, status, price) VALUES ('$project_id', '$equipment_id', '$category', '$days_used', '$total', '$depreciation', '$status', '$price')");
  // Update equipment table status to 'Pending' and set borrow_time
  mysqli_query($con, "UPDATE equipment SET status = 'Pending', borrow_time = '$now' WHERE id = '$equipment_id'");
  // --- NOTIFICATION FOR PROCUREMENT ---
  // Get equipment name
  $eq_name_res = mysqli_query($con, "SELECT equipment_name FROM equipment WHERE id='$equipment_id' LIMIT 1");
  $eq_name_row = mysqli_fetch_assoc($eq_name_res);
  $equipment_name = isset($eq_name_row['equipment_name']) ? $eq_name_row['equipment_name'] : '';
  $notif_type = 'Request';
  $message = mysqli_real_escape_string($con, "I'm requesting for the use of this $equipment_name");
  $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
  $is_read = 0;
  $created_at = date('Y-m-d H:i:s');
  mysqli_query($con, "INSERT INTO notifications_procurement (user_id, notif_type, message, is_read, created_at) VALUES ('$user_id', '$notif_type', '$message', '$is_read', '$created_at')");
  // --- END NOTIFICATION ---
  header("Location: project_details.php?id=$project_id&addequip=1");
  exit();
}
// Add Employee to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_employee'])) {
    $employee_id = intval($_POST['employeeName']);
    $position = mysqli_real_escape_string($con, $_POST['employeePosition']);
    $daily_rate = floatval($_POST['employeeRate']);
    $project_id = intval($_GET['id']);
    // Get project start_date and deadline
    $proj_res = mysqli_query($con, "SELECT start_date, deadline FROM projects WHERE project_id='$project_id' LIMIT 1");
    $proj_row = mysqli_fetch_assoc($proj_res);
    $start_date = $proj_row['start_date'];
    $end_date = $proj_row['deadline'];
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $project_days = $interval->days + 1;
    $total = $daily_rate * $project_days;
    $sql = "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, total) VALUES ('$project_id', '$employee_id', '$position', '$daily_rate', '$total')";
    mysqli_query($con, $sql);
    header("Location: project_details.php?id=$project_id&addemp=1");
    exit();
}
// Remove employee from project (add this block if not present)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_employee'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_employee WHERE id='$row_id'");
    header("Location: project_details.php?id=$project_id&removeemp=1");
    exit();
}
// Add Material to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_material'])) {
    $material_id = intval($_POST['materialName']);
    $project_id = intval($_GET['id']);
    // Check if material already exists for this project
    $exists = mysqli_query($con, "SELECT id FROM project_add_materials WHERE project_id='$project_id' AND material_id='$material_id' LIMIT 1");
    if (mysqli_num_rows($exists) > 0) {
        header("Location: project_details.php?id=$project_id&error=material_exists");
        exit();
    }
    $material_name = mysqli_real_escape_string($con, $_POST['materialNameText']);
    $unit = mysqli_real_escape_string($con, $_POST['materialUnit']);
    $material_price = floatval($_POST['materialPrice']);
    $quantity = intval($_POST['materialQty']);
    $total = $material_price * $quantity;
    // Get current material quantity and warehouse
    $mat_res = mysqli_query($con, "SELECT quantity, location FROM materials WHERE id = '$material_id' LIMIT 1");
    $mat_row = mysqli_fetch_assoc($mat_res);
    $current_qty = intval($mat_row['quantity']);
    $warehouse = mysqli_real_escape_string($con, $mat_row['location']);
    // Get current used_slots
    $slot_res = mysqli_query($con, "SELECT used_slots FROM warehouses WHERE warehouse = '$warehouse' LIMIT 1");
    $slot_row = mysqli_fetch_assoc($slot_res);
    $current_slots = intval($slot_row['used_slots']);
    // Check if enough stock and slots
    if ($quantity > $current_qty) {
        header("Location: project_details.php?id=$project_id&error=insufficient_stock&left=$current_qty");
        exit();
    }
    if ($quantity > $current_slots) {
        header("Location: project_details.php?id=$project_id&error=insufficient_slots&left=$current_slots");
        exit();
    }
    $sql = "INSERT INTO project_add_materials (project_id, material_id, material_name, unit, material_price, quantity, total) VALUES ('$project_id', '$material_id', '$material_name', '$unit', '$material_price', '$quantity', '$total')";
    mysqli_query($con, $sql);
    // Subtract the quantity from the main materials table
    mysqli_query($con, "UPDATE materials SET quantity = quantity - $quantity WHERE id = '$material_id'");
    // Subtract from used_slots in the warehouse
    $loc_res = mysqli_query($con, "SELECT location FROM materials WHERE id = '$material_id' LIMIT 1");
    $loc_row = mysqli_fetch_assoc($loc_res);
    $warehouse = mysqli_real_escape_string($con, $loc_row['location']);
    mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots - $quantity WHERE warehouse = '$warehouse'");
    header("Location: project_details.php?id=$project_id&addmat=1");
    exit();
}
// Remove Material from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    // Get material_id and quantity before deleting
    $res = mysqli_query($con, "SELECT material_id, quantity FROM project_add_materials WHERE id='$row_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $material_id = $row['material_id'];
        $quantity = $row['quantity'];
        // Return quantity to materials table
        mysqli_query($con, "UPDATE materials SET quantity = quantity + $quantity WHERE id = '$material_id'");
        // Add back to used_slots in the warehouse
        $loc_res = mysqli_query($con, "SELECT location FROM materials WHERE id = '$material_id' LIMIT 1");
        $loc_row = mysqli_fetch_assoc($loc_res);
        $warehouse = mysqli_real_escape_string($con, $loc_row['location']);
        mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots + $quantity WHERE warehouse = '$warehouse'");
        // Logging
        error_log("[WAREHOUSE LOG] Returned/Removed material_id=$material_id, qty=$quantity, warehouse='$warehouse' (used_slots +$quantity)");
    }
    mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id'");
    header("Location: project_details.php?id=$project_id&removemat=1");
    exit();
}
// Return Material to Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    // Get material_id and quantity before deleting
    $res = mysqli_query($con, "SELECT material_id, quantity FROM project_add_materials WHERE id='$row_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $material_id = $row['material_id'];
        $quantity = $row['quantity'];
        // Return quantity to materials table
        mysqli_query($con, "UPDATE materials SET quantity = quantity + $quantity WHERE id = '$material_id'");
        // Add back to used_slots in the warehouse
        $loc_res = mysqli_query($con, "SELECT location FROM materials WHERE id = '$material_id' LIMIT 1");
        $loc_row = mysqli_fetch_assoc($loc_res);
        $warehouse = mysqli_real_escape_string($con, $loc_row['location']);
        mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots + $quantity WHERE warehouse = '$warehouse'");
        // Logging
        error_log("[WAREHOUSE LOG] Returned/Removed material_id=$material_id, qty=$quantity, warehouse='$warehouse' (used_slots +$quantity)");
    }
    mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id'");
    header("Location: project_details.php?id=$project_id&returnmat=1");
    exit();
} 