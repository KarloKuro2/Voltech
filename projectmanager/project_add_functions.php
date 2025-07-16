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
  header("Location: project_details.php?id=$project_id");
  exit();
}
// Add Employee to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_employee'])) {
    $employee_id = intval($_POST['employeeName']);
    $position = mysqli_real_escape_string($con, $_POST['employeePosition']);
    $daily_rate = floatval($_POST['employeeRate']);
    $days = intval($_POST['employeeDays']);
    $schedule = intval($_POST['employeeSchedule']);
    $total = $daily_rate * $schedule;
    $project_id = intval($_GET['id']);
    $sql = "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, days, schedule, total) VALUES ('$project_id', '$employee_id', '$position', '$daily_rate', '$days', '$schedule', '$total')";
    mysqli_query($con, $sql);
    header("Location: project_details.php?id=$project_id&addemp=1");
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